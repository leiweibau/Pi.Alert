<?php
// Independent device identities. All mutating callers use the same transaction
// context; persistent triggers also protect scanner and bulk/maintenance writes.
require_once __DIR__ . '/db.php';

function logical_execute(SQLite3 $database, string $sql, array $parameters = array()): SQLite3Result {
    $result = db_execute_prepared($database, $sql, $parameters);
    if ($result === false) throw new RuntimeException('database_error');
    return $result;
}

function logical_transaction(SQLite3 $database, string $trigger, callable $operation) {
    $database->enableExceptions(true);
    $database->exec('BEGIN IMMEDIATE');
    try {
        logical_execute($database, 'UPDATE LogicalDeviceContext SET trigger=:trigger, suppress_journal=0 WHERE id=1', array(':trigger' => $trigger));
        $value = $operation();
        $database->exec("UPDATE LogicalDeviceContext SET trigger='database', suppress_journal=0 WHERE id=1");
        $database->exec('COMMIT');
        return $value;
    } catch (Throwable $error) {
        $database->exec('ROLLBACK');
        throw $error;
    }
}

// Existing MAC mutation messages use direct transactional inserts as well.
// Never replay or write the filesystem journal buffer inside this transaction.
function logical_logging($class, $trigger, $message, $hash, $info): void {
    global $db;
    logical_execute($db, 'INSERT INTO pialert_journal(Journal_DateTime,LogClass,Trigger,LogString,Hash,Additional_Info) VALUES(:date,:class,:trigger,:message,:hash,:info)',
        array(':date'=>date('Y-m-d H:i:s'), ':class'=>(string)$class, ':trigger'=>(string)$trigger,
              ':message'=>(string)$message, ':hash'=>(string)$hash, ':info'=>(string)$info));
}

function logical_id($value, bool $nullable = false): ?int {
    if ($nullable && ($value === null || $value === '')) return null;
    if ((!is_int($value) && !is_string($value)) || preg_match('/^[1-9][0-9]{0,15}$/D', (string) $value) !== 1) {
        throw new InvalidArgumentException('invalid_input');
    }
    return (int) $value;
}

function logical_text($value, bool $required = false): string {
    if (!is_string($value) || strlen($value) > 1000 || preg_match('//u', $value) !== 1
        || preg_match('/[\x00-\x1f\x7f]/u', $value)) throw new InvalidArgumentException('invalid_input');
    $value = preg_replace('/^\s+|\s+$/u', '', $value);
    if (($required && $value === '') || preg_match_all('/./us', $value) > 250) {
        throw new InvalidArgumentException('invalid_input');
    }
    return $value;
}

function logical_name_key(string $name): string {
    // Python is already required by Pi.Alert. This avoids requiring optional PHP
    // intl/mbstring and uses exactly the migration's NFC + Unicode casefold rule.
    $process = proc_open(array('/usr/bin/python3', __DIR__ . '/../../../install/migrate_logical_devices.py', '--name-key'),
        array(0 => array('pipe','r'), 1 => array('pipe','w'), 2 => array('pipe','w')), $pipes);
    if (!is_resource($process)) throw new RuntimeException('database_error');
    fwrite($pipes[0], json_encode($name, JSON_UNESCAPED_UNICODE));
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    stream_get_contents($pipes[2]); fclose($pipes[2]);
    $status = proc_close($process);
    $key = json_decode($output, true);
    if ($status !== 0 || !is_string($key) || $key === '') throw new RuntimeException('database_error');
    return $key;
}

function logical_get(SQLite3 $database, int $id): array {
    $result = logical_execute($database, 'SELECT id,name,device_type,owner,location,vendor,"group",created_at,empty_since FROM LogicalDevices WHERE id=:id', array(':id'=>$id));
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if (!$row) throw new OutOfBoundsException('not_found');
    return $row;
}

function logical_save(SQLite3 $database, array $input, string $trigger): array {
    $id = logical_id($input['id'] ?? null, true);
    $values = array();
    foreach (array('name','device_type','owner','location','vendor','group') as $key) {
        $values[':'.$key] = logical_text($input[$key] ?? '', $key === 'name');
    }
    $values[':name_key'] = logical_name_key($values[':name']);
    return logical_transaction($database, $trigger, static function() use($database,$id,$values) {
        if ($id !== null) logical_get($database, $id);
        $duplicate = logical_execute($database, 'SELECT id FROM LogicalDevices WHERE name_key=:key AND id<>:id', array(':key'=>$values[':name_key'], ':id'=>$id ?? 0))->fetchArray(SQLITE3_ASSOC);
        if ($duplicate) throw new DomainException('duplicate_name');
        if ($id === null) {
            logical_execute($database, 'INSERT INTO LogicalDevices(name,name_key,device_type,owner,location,vendor,"group") VALUES(:name,:name_key,:device_type,:owner,:location,:vendor,:group)', $values);
            $id = $database->lastInsertRowID();
        } else {
            $values[':id'] = $id;
            logical_execute($database, 'UPDATE LogicalDevices SET name=:name,name_key=:name_key,device_type=:device_type,owner=:owner,location=:location,vendor=:vendor,"group"=:group WHERE id=:id', $values);
        }
        return logical_get($database, $id);
    });
}

function logical_assign_in_transaction(SQLite3 $database, string $mac, ?int $id): void {
    if ($mac === '' || strlen($mac) > 128) throw new InvalidArgumentException('invalid_input');
    $row = logical_execute($database, 'SELECT dev_MAC FROM Devices WHERE dev_MAC=:mac', array(':mac'=>$mac))->fetchArray(SQLITE3_ASSOC);
    if (!$row) throw new OutOfBoundsException('not_found');
    if ($id !== null) logical_get($database, $id);
    logical_execute($database, 'UPDATE Devices SET dev_LogicalDeviceID=:id WHERE dev_MAC=:mac', array(':id'=>$id, ':mac'=>$mac));
}

function logical_assign(SQLite3 $database, string $mac, ?int $id, string $trigger): void {
    logical_transaction($database, $trigger, static function() use($database,$mac,$id) {
        logical_assign_in_transaction($database, $mac, $id);
    });
}

function logical_delete(SQLite3 $database, int $id, string $trigger): void {
    logical_transaction($database, $trigger, static function() use($database,$id) {
        logical_get($database, $id);
        // BEFORE DELETE triggers detach and audit every member, including archives.
        logical_execute($database, 'DELETE FROM LogicalDevices WHERE id=:id', array(':id'=>$id));
    });
}

function logical_status_sql(): string {
    return 'CASE WHEN dev_AlertDeviceDown=1 AND dev_PresentLastScan=0 THEN "Down"
        WHEN dev_NewDevice=1 AND dev_PresentLastScan=1 THEN "NewON"
        WHEN dev_NewDevice=1 AND dev_PresentLastScan=0 THEN "NewOFF"
        WHEN dev_Scan_Validation>0 AND dev_Scan_Validation_State>0 AND dev_Scan_Validation_State<=dev_Scan_Validation AND dev_PresentLastScan=1 THEN "OnlineV"
        WHEN dev_PresentLastScan=1 THEN "On-line" ELSE "Off-line" END';
}

function logical_list_row(array $row, bool $queued = false): array {
    $mac = (string) $row['dev_MAC'];
    return array($row['dev_Name'], $row['dev_ConnectionType'], $row['dev_Owner'],
        $row['dev_DeviceType'], $row['dev_Favorite'], $row['dev_Group'], $row['dev_Location'],
        formatDate($row['dev_FirstConnection']), formatDate($row['dev_LastConnection']), $row['dev_LastIP'],
        strlen($mac)>1 && in_array($mac[1], array('2','6','A','E','a','e')) ? 1 : 0, $mac,
        $row['dev_Vendor'], $row['dev_Status'], formatIPlong($row['dev_LastIP']), $row['dev_ScanSource'], $row['rowid'], null, $queued);
}

function logical_list(SQLite3 $database, ?SQLite3 $toolsDatabase = null): array {
    // One read transaction gives names, membership and status a consistent snapshot.
    $database->exec('BEGIN');
    try {
        $devices = array();
        $result = logical_execute($database, 'SELECT id,name,device_type,owner,location,vendor,"group",created_at,empty_since FROM LogicalDevices ORDER BY name_key,id');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['members'] = array();
            $devices[$row['id']] = $row;
        }
        $queued = array();
        if ($toolsDatabase && (int)$toolsDatabase->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='Tools_Nmap_Queue'") > 0) {
            $result = $toolsDatabase->query('SELECT DISTINCT device_mac FROM Tools_Nmap_Queue');
            while ($result && ($row=$result->fetchArray(SQLITE3_ASSOC))) $queued[strtolower(trim($row['device_mac']))] = true;
        }
        $result = logical_execute($database, 'SELECT d.rowid,d.*, '.logical_status_sql().' AS dev_Status,
            CASE WHEN d.dev_ScanSource="local" THEN "local" ELSE COALESCE((SELECT sat_name FROM Satellites WHERE sat_token=d.dev_ScanSource LIMIT 1),d.dev_ScanSource) END AS scan_source_name
            FROM Devices d WHERE dev_LogicalDeviceID IS NOT NULL ORDER BY dev_Name COLLATE NOCASE,dev_MAC');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['list_row'] = logical_list_row($row, isset($queued[strtolower(trim($row['dev_MAC']))]));
            $row['has_valid_ip'] = filter_var($row['dev_LastIP'], FILTER_VALIDATE_IP) !== false;
            $devices[$row['dev_LogicalDeviceID']]['members'][] = $row;
        }
        $database->exec('COMMIT');
        return array_values($devices);
    } catch (Throwable $error) {
        $database->exec('ROLLBACK');
        throw $error;
    }
}

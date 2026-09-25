<?php
// Shared, schema-aware storage for device and ICMP URL actions.

const ENTITY_ACTION_DEFAULT_COLOR = '#6c757d';
const ENTITY_ACTION_DEFAULT_TEXT_COLOR = '#ffffff';

class EntityActionError extends RuntimeException {
    public $errorCode;
    public $rowIndex;
    public $httpStatus;

    public function __construct($code, $status = 400, $row = null) {
        parent::__construct($code);
        $this->errorCode = $code;
        $this->httpStatus = $status;
        $this->rowIndex = $row;
    }
}

function entity_actions_available(SQLite3 $db) {
    $names = array('Entity_Actions', 'idx_entity_actions_device_position',
        'idx_entity_actions_icmp_position', 'trg_entity_actions_device_delete',
        'trg_entity_actions_icmp_delete', 'trg_entity_actions_device_update',
        'trg_entity_actions_icmp_update');
    $found = array();
    $result = $db->query("SELECT name FROM sqlite_master WHERE name LIKE 'Entity_Actions' OR name LIKE 'idx_entity_actions_%' OR name LIKE 'trg_entity_actions_%'");
    while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
        $found[$row['name']] = true;
    }
    return count(array_diff($names, array_keys($found))) === 0;
}

function entity_actions_has_column(SQLite3 $db, $name) {
    $columns = $db->query('PRAGMA table_info(Entity_Actions)');
    while ($columns && ($column = $columns->fetchArray(SQLITE3_ASSOC))) {
        if (($column['name'] ?? '') === $name) {
            return true;
        }
    }
    return false;
}

function entity_actions_has_color(SQLite3 $db) {
    return entity_actions_has_column($db, 'color');
}

function entity_actions_has_text_color(SQLite3 $db) {
    return entity_actions_has_column($db, 'text_color');
}

function entity_actions_contrast_color($color) {
    if (!is_string($color) || !preg_match('/^#[0-9a-fA-F]{6}$/D', $color)) {
        return ENTITY_ACTION_DEFAULT_TEXT_COLOR;
    }
    $channels = array(hexdec(substr($color, 1, 2)) / 255, hexdec(substr($color, 3, 2)) / 255,
        hexdec(substr($color, 5, 2)) / 255);
    foreach ($channels as &$channel) {
        $channel = $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }
    unset($channel);
    $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    return $luminance > 0.179 ? '#111111' : '#ffffff';
}

function entity_actions_target_spec($kind) {
    if ($kind === 'device') {
        return array('Devices', 'dev_MAC', 'device_mac');
    }
    if ($kind === 'icmp') {
        return array('ICMP_Mon', 'icmp_ip', 'icmp_ip');
    }
    throw new EntityActionError('invalid_target');
}

function entity_actions_resolve(SQLite3 $db, $kind, $key) {
    if (!is_string($key) || $key === '' || strlen($key) > 255) {
        throw new EntityActionError('invalid_target');
    }
    list($table, $parentKey) = entity_actions_target_spec($kind);
    $statement = $db->prepare("SELECT $parentKey FROM $table WHERE $parentKey = :key LIMIT 1");
    $statement->bindValue(':key', $key, SQLITE3_TEXT);
    $result = $statement->execute();
    $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
    if (!$row) {
        throw new EntityActionError('target_not_found', 404);
    }
    return (string) $row[$parentKey];
}

function entity_actions_read(SQLite3 $db, $kind, $canonicalKey) {
    list(, , $column) = entity_actions_target_spec($kind);
    $colorColumn = entity_actions_has_color($db) ? 'color' : "'" . ENTITY_ACTION_DEFAULT_COLOR . "' AS color";
    $textColorColumn = entity_actions_has_text_color($db) ? 'text_color' : "'' AS text_color";
    $statement = $db->prepare("SELECT action_id, url, icon_id, label, $colorColumn, $textColorColumn, position FROM Entity_Actions WHERE $column = :key ORDER BY position");
    $statement->bindValue(':key', $canonicalKey, SQLITE3_TEXT);
    $result = $statement->execute();
    if ($result === false) {
        throw new EntityActionError('save_failed', 500);
    }
    $actions = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['action_id'] = (int) $row['action_id'];
        $row['position'] = (int) $row['position'];
        if ($row['text_color'] === '') {
            $row['text_color'] = entity_actions_contrast_color($row['color']);
        }
        $actions[] = $row;
    }
    return $actions;
}

function entity_actions_version($actions) {
    return hash('sha256', json_encode($actions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function entity_actions_icon_ids() {
    static $ids = null;
    if ($ids !== null) {
        return $ids;
    }
    $idFile = __DIR__ . '/../data/action-icon-ids.json';
    $catalogFile = __DIR__ . '/../data/action-icons.json';
    $catalog = json_decode(file_get_contents(is_file($idFile) ? $idFile : $catalogFile), true);
    if (!is_array($catalog)) {
        throw new EntityActionError('save_failed', 500);
    }
    $ids = array();
    foreach ($catalog as $icon) {
        $id = is_string($icon) ? $icon : (is_array($icon) && isset($icon['id']) ? $icon['id'] : null);
        if (is_string($id)) {
            $ids[$id] = true;
        }
    }
    return $ids;
}

function entity_actions_validate($actions) {
    if (!is_array($actions) || array_values($actions) !== $actions) {
        throw new EntityActionError('invalid_actions');
    }
    if (count($actions) > 3) {
        throw new EntityActionError('too_many_actions');
    }
    $icons = entity_actions_icon_ids();
    $validated = array();
    $seenIds = array();
    foreach ($actions as $index => $action) {
        if (!is_array($action)) {
            throw new EntityActionError('invalid_actions', 400, $index);
        }
        $url = $action['url'] ?? null;
        if (!is_string($url) || $url === '' || strlen($url) > 2048 ||
            preg_match('/[\x00-\x1F\x7F]/', $url) ||
            !preg_match('~^https?://~iD', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new EntityActionError('invalid_url', 400, $index);
        }
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) ||
            !in_array(strtolower($parts['scheme'] ?? ''), array('http', 'https'), true)) {
            throw new EntityActionError('invalid_url', 400, $index);
        }
        $icon = $action['icon_id'] ?? null;
        if (!is_string($icon) || !isset($icons[$icon])) {
            throw new EntityActionError('invalid_icon', 400, $index);
        }
        $label = $action['label'] ?? '';
        if (!is_string($label) || strlen($label) > 80 || preg_match('/[\x00-\x1F\x7F]/', $label)) {
            throw new EntityActionError('invalid_label', 400, $index);
        }
        $color = $action['color'] ?? ENTITY_ACTION_DEFAULT_COLOR;
        if (!is_string($color) || !preg_match('/^#[0-9a-fA-F]{6}$/D', $color)) {
            throw new EntityActionError('invalid_color', 400, $index);
        }
        $color = strtolower($color);
        $textColor = $action['text_color'] ?? entity_actions_contrast_color($color);
        if (!is_string($textColor) || !preg_match('/^#[0-9a-fA-F]{6}$/D', $textColor)) {
            throw new EntityActionError('invalid_text_color', 400, $index);
        }
        $textColor = strtolower($textColor);
        $id = $action['action_id'] ?? null;
        if ($id !== null && (!is_int($id) || $id < 1 || isset($seenIds[$id]))) {
            throw new EntityActionError('invalid_actions', 400, $index);
        }
        if ($id !== null) {
            $seenIds[$id] = true;
        }
        $validated[] = array('action_id' => $id, 'url' => $url, 'icon_id' => $icon, 'label' => $label,
            'color' => $color, 'text_color' => $textColor,
            'position' => $index);
    }
    return $validated;
}

function entity_actions_save(SQLite3 $db, $kind, $key, $version, $actions) {
    if (!is_string($version) || !preg_match('/^[a-f0-9]{64}$/D', $version)) {
        throw new EntityActionError('invalid_actions');
    }
    $validated = entity_actions_validate($actions);
    list(, , $column) = entity_actions_target_spec($kind);
    if (!$db->exec('BEGIN IMMEDIATE')) {
        throw new EntityActionError('save_failed', 500);
    }
    try {
        $canonicalKey = entity_actions_resolve($db, $kind, $key);
        $current = entity_actions_read($db, $kind, $canonicalKey);
        if (!hash_equals(entity_actions_version($current), $version)) {
            throw new EntityActionError('conflict', 409);
        }
        $existing = array();
        $createdAt = array();
        $createdStatement = $db->prepare("SELECT action_id, created_at FROM Entity_Actions WHERE $column = :key");
        $createdStatement->bindValue(':key', $canonicalKey, SQLITE3_TEXT);
        $createdResult = $createdStatement->execute();
        while ($createdResult && ($createdRow = $createdResult->fetchArray(SQLITE3_ASSOC))) {
            $createdAt[(int) $createdRow['action_id']] = $createdRow['created_at'];
        }
        foreach ($current as $row) {
            $existing[$row['action_id']] = true;
        }
        foreach ($validated as $index => $row) {
            if ($row['action_id'] !== null && !isset($existing[$row['action_id']])) {
                throw new EntityActionError('foreign_action', 400, $index);
            }
        }
        // Delete/reinsert within the transaction permits arbitrary reorder without
        // temporary collisions in the unique position indexes; IDs remain stable.
        $delete = $db->prepare("DELETE FROM Entity_Actions WHERE $column = :key");
        $delete->bindValue(':key', $canonicalKey, SQLITE3_TEXT);
        if ($delete->execute() === false) {
            throw new EntityActionError('save_failed', 500);
        }
        $now = date('Y-m-d H:i:s');
        $insert = $db->prepare("INSERT INTO Entity_Actions (action_id, $column, url, icon_id, label, color, text_color, position, created_at, updated_at) VALUES (:id, :key, :url, :icon, :label, :color, :text_color, :position, :created, :updated)");
        foreach ($validated as $row) {
            $insert->reset();
            $insert->clear();
            $insert->bindValue(':id', $row['action_id'], $row['action_id'] === null ? SQLITE3_NULL : SQLITE3_INTEGER);
            $insert->bindValue(':key', $canonicalKey, SQLITE3_TEXT);
            $insert->bindValue(':url', $row['url'], SQLITE3_TEXT);
            $insert->bindValue(':icon', $row['icon_id'], SQLITE3_TEXT);
            $insert->bindValue(':label', $row['label'], SQLITE3_TEXT);
            $insert->bindValue(':color', $row['color'], SQLITE3_TEXT);
            $insert->bindValue(':text_color', $row['text_color'], SQLITE3_TEXT);
            $insert->bindValue(':position', $row['position'], SQLITE3_INTEGER);
            $insert->bindValue(':created', $row['action_id'] !== null ? $createdAt[$row['action_id']] : $now, SQLITE3_TEXT);
            $insert->bindValue(':updated', $now, SQLITE3_TEXT);
            if ($insert->execute() === false) {
                throw new EntityActionError('save_failed', 500);
            }
        }
        $saved = entity_actions_read($db, $kind, $canonicalKey);
        if (!$db->exec('COMMIT')) {
            throw new EntityActionError('save_failed', 500);
        }
        return array('actions' => $saved, 'version' => entity_actions_version($saved),
            'schema_available' => true);
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        throw $error;
    }
}

function entity_actions_map(SQLite3 $db, $kind, $keys) {
    if (!entity_actions_available($db) || !$keys) {
        return array();
    }
    list(, , $column) = entity_actions_target_spec($kind);
    $colorColumn = entity_actions_has_color($db) ? 'color' : "'" . ENTITY_ACTION_DEFAULT_COLOR . "' AS color";
    $textColorColumn = entity_actions_has_text_color($db) ? 'text_color' : "'' AS text_color";
    $map = array();
    foreach (array_chunk(array_values(array_unique($keys)), 300) as $chunk) {
        $placeholders = array();
        foreach ($chunk as $index => $key) {
            $placeholders[] = ':key' . $index;
        }
        $statement = $db->prepare("SELECT $column AS target_key, action_id, url, icon_id, label, $colorColumn, $textColorColumn, position FROM Entity_Actions WHERE $column IN (" . implode(',', $placeholders) . ") ORDER BY $column, position");
        foreach ($chunk as $index => $key) {
            $statement->bindValue(':key' . $index, $key, SQLITE3_TEXT);
        }
        $result = $statement->execute();
        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $target = (string) $row['target_key'];
            unset($row['target_key']);
            $row['action_id'] = (int) $row['action_id'];
            $row['position'] = (int) $row['position'];
            if ($row['text_color'] === '') {
                $row['text_color'] = entity_actions_contrast_color($row['color']);
            }
            $map[$target][] = $row;
        }
    }
    return $map;
}

<?php
require_once __DIR__ . '/../front/php/entity-actions.php';

function assert_test($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function expect_error($code, $operation) {
    try {
        $operation();
    } catch (EntityActionError $error) {
        assert_test($error->errorCode === $code, "expected $code, got {$error->errorCode}");
        return;
    }
    throw new RuntimeException("expected $code");
}

$path = tempnam(sys_get_temp_dir(), 'pialert-actions-');
try {
    $db = new SQLite3($path);
    $db->enableExceptions(true);
    $db->exec('CREATE TABLE Devices (dev_MAC TEXT COLLATE NOCASE PRIMARY KEY)');
    $db->exec('CREATE TABLE ICMP_Mon (icmp_ip TEXT PRIMARY KEY)');
    $db->exec("INSERT INTO Devices VALUES ('AA:BB'), ('Internet')");
    $db->exec("INSERT INTO ICMP_Mon VALUES ('192.0.2.1')");
    $db->exec("CREATE TABLE Entity_Actions (
        action_id INTEGER PRIMARY KEY, device_mac TEXT COLLATE NOCASE, icmp_ip TEXT,
        url TEXT NOT NULL, icon_id TEXT NOT NULL, label TEXT NOT NULL DEFAULT '',
        position INTEGER NOT NULL CHECK(position IN (0,1,2)), created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL, color TEXT NOT NULL DEFAULT '#6c757d'
        CHECK(length(color)=7 AND color GLOB '#[0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f]'),
        text_color TEXT NOT NULL DEFAULT '#ffffff'
        CHECK(length(text_color)=7 AND text_color GLOB '#[0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f]'),
        CHECK ((device_mac IS NOT NULL) != (icmp_ip IS NOT NULL)))");
    $db->exec('CREATE UNIQUE INDEX idx_entity_actions_device_position ON Entity_Actions(device_mac,position) WHERE device_mac IS NOT NULL');
    $db->exec('CREATE UNIQUE INDEX idx_entity_actions_icmp_position ON Entity_Actions(icmp_ip,position) WHERE icmp_ip IS NOT NULL');
    foreach (array('device_delete', 'icmp_delete', 'device_update', 'icmp_update') as $suffix) {
        $db->exec("CREATE TRIGGER trg_entity_actions_{$suffix} AFTER INSERT ON Devices BEGIN SELECT 1; END");
    }

    $emptyVersion = entity_actions_version(array());
    $row = array('url' => 'http://localhost:8080/path?x=1#top', 'icon_id' => 'bi:link-45deg',
        'label' => 'Local', 'color' => '#1A73E8', 'text_color' => '#FFEE00');
    assert_test(count(entity_actions_validate(array(
        array('url' => 'https://[::1]:8443/path', 'icon_id' => 'bi:link-45deg', 'label' => ''),
        array('url' => 'http://192.168.1.1/', 'icon_id' => 'bi:link-45deg', 'label' => '')
    ))) === 2, 'local and IPv6 URLs must be accepted');
    assert_test(entity_actions_validate(array(
        array('url' => 'https://example.test', 'icon_id' => 'bi:link-45deg', 'label' => '')
    ))[0]['color'] === ENTITY_ACTION_DEFAULT_COLOR, 'default color missing');
    foreach (array('//example.com', 'data:text/html,evil', 'https://user:pass@example.com/', "https://example.com/\n") as $invalidUrl) {
        expect_error('invalid_url', function () use ($invalidUrl) {
            entity_actions_validate(array(array('url' => $invalidUrl,
                'icon_id' => 'bi:link-45deg', 'label' => '')));
        });
    }
    $saved = entity_actions_save($db, 'device', 'aa:bb', $emptyVersion, array($row));
    assert_test(count($saved['actions']) === 1, 'save failed');
    $id = $saved['actions'][0]['action_id'];
    expect_error('conflict', function () use ($db, $emptyVersion, $row) {
        entity_actions_save($db, 'device', 'AA:BB', $emptyVersion, array($row));
    });
    $bad = $row;
    $bad['url'] = 'javascript:alert(1)';
    expect_error('invalid_url', function () use ($db, $saved, $bad) {
        entity_actions_save($db, 'device', 'AA:BB', $saved['version'], array($bad));
    });
    $badColor = $row;
    $badColor['color'] = 'var(--bs-danger)';
    expect_error('invalid_color', function () use ($badColor) {
        entity_actions_validate(array($badColor));
    });
    $badTextColor = $row;
    $badTextColor['text_color'] = 'white';
    expect_error('invalid_text_color', function () use ($badTextColor) {
        entity_actions_validate(array($badTextColor));
    });
    assert_test(entity_actions_read($db, 'device', 'AA:BB')[0]['action_id'] === $id, 'validation changed data');
    assert_test(entity_actions_read($db, 'device', 'AA:BB')[0]['color'] === '#1a73e8', 'color was not normalized');
    assert_test(entity_actions_read($db, 'device', 'AA:BB')[0]['text_color'] === '#ffee00', 'text color was not normalized');
    $other = entity_actions_save($db, 'icmp', '192.0.2.1', $emptyVersion, array($row));
    $foreign = $row;
    $foreign['action_id'] = $other['actions'][0]['action_id'];
    expect_error('foreign_action', function () use ($db, $saved, $foreign) {
        entity_actions_save($db, 'device', 'AA:BB', $saved['version'], array($foreign));
    });
    $row['action_id'] = $id;
    $newRow = $row;
    unset($newRow['action_id']);
    $thirdRow = $newRow;
    $thirdRow['url'] = 'https://example.test/third';
    $three = entity_actions_save($db, 'device', 'AA:BB', $saved['version'], array($row, $newRow, $thirdRow));
    assert_test(count($three['actions']) === 3, 'three-action limit could not be used');
    // Duplicate IDs are rejected, even for a reorder.
    expect_error('invalid_actions', function () use ($db, $three, $row) {
        entity_actions_save($db, 'device', 'AA:BB', $three['version'], array($row, $row));
    });
    expect_error('too_many_actions', function () use ($db, $three, $row) {
        entity_actions_save($db, 'device', 'AA:BB', $three['version'], array($row, $row, $row, $row));
    });
    $map = entity_actions_map($db, 'device', array('AA:BB', 'Internet'));
    assert_test(count($map['AA:BB']) === 3, 'batch map failed');

    $legacyPath = tempnam(sys_get_temp_dir(), 'pialert-actions-legacy-');
    $legacyDb = new SQLite3($legacyPath);
    $legacyDb->enableExceptions(true);
    $legacyDb->exec('CREATE TABLE Devices (dev_MAC TEXT COLLATE NOCASE PRIMARY KEY)');
    $legacyDb->exec('CREATE TABLE ICMP_Mon (icmp_ip TEXT PRIMARY KEY)');
    $legacyDb->exec("INSERT INTO Devices VALUES ('AA:BB')");
    $legacyDb->exec("CREATE TABLE Entity_Actions (
        action_id INTEGER PRIMARY KEY, device_mac TEXT COLLATE NOCASE, icmp_ip TEXT,
        url TEXT NOT NULL, icon_id TEXT NOT NULL, label TEXT NOT NULL DEFAULT '',
        position INTEGER NOT NULL CHECK(position IN (0,1,2)), created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL, CHECK ((device_mac IS NOT NULL) != (icmp_ip IS NOT NULL)))");
    $legacyDb->exec('CREATE UNIQUE INDEX idx_entity_actions_device_position ON Entity_Actions(device_mac,position) WHERE device_mac IS NOT NULL');
    $legacyDb->exec('CREATE UNIQUE INDEX idx_entity_actions_icmp_position ON Entity_Actions(icmp_ip,position) WHERE icmp_ip IS NOT NULL');
    foreach (array('device_delete', 'icmp_delete', 'device_update', 'icmp_update') as $suffix) {
        $legacyDb->exec("CREATE TRIGGER trg_entity_actions_{$suffix} AFTER INSERT ON Devices BEGIN SELECT 1; END");
    }
    $legacyDb->exec("INSERT INTO Entity_Actions (device_mac,url,icon_id,label,position,created_at,updated_at)
        VALUES ('AA:BB','http://localhost','bi:router','Legacy',0,'now','now')");
    assert_test(entity_actions_available($legacyDb), 'legacy schema should remain readable');
    assert_test(!entity_actions_has_color($legacyDb), 'legacy schema must require the color migration for writes');
    $legacyActions = entity_actions_read($legacyDb, 'device', 'AA:BB');
    assert_test($legacyActions[0]['color'] === ENTITY_ACTION_DEFAULT_COLOR, 'legacy color fallback missing');
    assert_test(entity_actions_map($legacyDb, 'device', array('AA:BB'))['AA:BB'][0]['color'] === ENTITY_ACTION_DEFAULT_COLOR,
        'legacy list color fallback missing');
    assert_test($legacyActions[0]['text_color'] === '#ffffff', 'legacy text color fallback missing');
    $legacyDb->exec("ALTER TABLE Entity_Actions ADD COLUMN color TEXT NOT NULL DEFAULT '#6c757d'");
    $legacyDb->exec("UPDATE Entity_Actions SET color='#ffcc00'");
    assert_test(entity_actions_has_color($legacyDb) && !entity_actions_has_text_color($legacyDb),
        'color-only schema detection failed');
    assert_test(entity_actions_read($legacyDb, 'device', 'AA:BB')[0]['text_color'] === '#111111',
        'color-only contrast fallback missing');
    echo "Entity actions PHP tests passed\n";
} finally {
    if (isset($db)) {
        $db->close();
    }
    unlink($path);
    if (isset($legacyDb)) {
        $legacyDb->close();
    }
    if (isset($legacyPath)) {
        unlink($legacyPath);
    }
}

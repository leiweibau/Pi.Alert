<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . "/php/server/session.php";
pialert_start_session();

if ($_SESSION["login"] != 1) {
	header('Location: ./index.php');
	exit;
}

require 'php/templates/header.php';
require 'php/server/db.php';
require 'php/server/journal.php';

$DBFILE = '../db/pialert.db';
OpenDB();
?>

<div class="content-wrapper">

    <section class="content-header">
    <?php require 'php/templates/notification.php';?>
      <h1 id="pageTitle">
         <?=$pia_lang['Network_Title'];?>
         <a class="btn btn-xs btn-success servicelist_add_serv" href="./networkSettings.php" role="button"><i class="bi bi-plus-lg" style="font-size:1.5rem"></i></a>
      </h1>
    </section>

    <section class="content">

<?php

function network_fetch_rows($result) {
	$rows = array();
	if ($result === false) {
		return $rows;
	}
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		$rows[] = $row;
	}
	return $rows;
}

function network_device_details_url($mac) {
	return './deviceDetails.php?mac=' . rawurlencode((string) $mac);
}

function network_device_link($device) {
	$name = h($device['name'] ?? '');
	if (($device['mac'] ?? '') === 'dumb') {
		return '<a href="./networkSettings.php#hostedit"><b>' . $name . '</b></a>';
	}
	return '<a href="' . h(network_device_details_url($device['mac'] ?? '')) . '"><b>' . $name . '</b></a>';
}

function network_normalize_device($row) {
	return array(
		'name' => (string) ($row['dev_Name'] ?? ''),
		'mac' => (string) ($row['dev_MAC'] ?? ''),
		'ip' => (string) ($row['dev_LastIP'] ?? ''),
		'state' => ($row['dev_PresentLastScan'] ?? 0) === 'dumb' ? 'dumb' : ((int) ($row['dev_PresentLastScan'] ?? 0) === 1 ? 'online' : 'offline'),
		'ports' => (string) ($row['dev_Infrastructure_port'] ?? ''),
	);
}

// A comma is intentionally supported here: one device can be assigned to
// multiple target ports (for example "1,3,7").
function network_parse_target_ports($value, $maximumPort) {
	$ports = array();
	foreach (explode(',', (string) $value) as $part) {
		$part = trim($part);
		if ($part === '' || !ctype_digit($part)) {
			continue;
		}
		$port = (int) $part;
		if ($port >= 1 && $port <= $maximumPort) {
			$ports[$port] = $port;
		}
	}
	return array_values($ports);
}

// Downstream entries retain the existing "MAC,Port;MAC,Port" format. For
// portless infrastructure types, an entry containing only a MAC is valid.
function network_parse_downstream_entries($value) {
	$entries = array();
	foreach (explode(';', (string) $value) as $entry) {
		$entry = trim($entry);
		if ($entry === '') {
			continue;
		}
		$parts = explode(',', $entry, 2);
		$mac = strtolower(trim($parts[0]));
		if ($mac === '') {
			continue;
		}
		$port = null;
		if (isset($parts[1]) && ctype_digit(trim($parts[1]))) {
			$port = (int) trim($parts[1]);
		}
		$entries[] = array('mac' => $mac, 'port' => $port);
	}
	return $entries;
}

function unassigned_devices() {
	global $db;
	$sql = 'SELECT "dev_MAC", "dev_Name" FROM "Devices" WHERE ("dev_Infrastructure" = "" OR "dev_Infrastructure" IS NULL) AND "dev_Archived" = 0';
	foreach (network_fetch_rows($db->query($sql)) as $row) {
		echo '<a href="' . h(network_device_details_url($row['dev_MAC'] ?? '')) . '"><div style="display: inline-block; padding: 5px 15px; font-weight: bold;">' . h($row['dev_Name'] ?? '') . '</div></a>';
	}
}

function get_network_infrastructure($deviceId) {
	global $db;
	$result = db_execute_prepared(
		$db,
		'SELECT * FROM "network_infrastructure" WHERE "device_id" = :id LIMIT 1',
		array(':id' => array((int) $deviceId, SQLITE3_INTEGER))
	);
	return $result === false ? null : ($result->fetchArray(SQLITE3_ASSOC) ?: null);
}

function get_downstream_entries($deviceId) {
	$row = get_network_infrastructure($deviceId);
	return network_parse_downstream_entries($row['net_downstream_devices'] ?? '');
}

function get_downstream_from_mac($mac) {
	global $db;
	$result = db_execute_prepared(
		$db,
		'SELECT * FROM "Devices" WHERE "dev_MAC" = :mac LIMIT 1',
		array(':mac' => (string) $mac)
	);
	return $result === false ? null : ($result->fetchArray(SQLITE3_ASSOC) ?: null);
}

function printNodeOnlineState($state) {
	if ($state === 'online') {
		echo '<i class="fa fa-w fa-circle text-green-light fa-gradient-green"></i>&nbsp;';
	} elseif ($state === 'offline') {
		echo '<i class="fa fa-w fa-circle text-red fa-gradient-red"></i>&nbsp;';
	} elseif ($state === 'inactive') {
		echo '<i class="fa fa-w fa-circle text-gray"></i>&nbsp;';
	}
}

function getNodeOnlineState($name) {
	global $db;
	$result = db_execute_prepared(
		$db,
		'SELECT "dev_PresentLastScan" FROM "Devices" WHERE "dev_Name" = :name LIMIT 1',
		array(':name' => (string) $name)
	);
	$row = $result === false ? false : $result->fetchArray(SQLITE3_ASSOC);
	return $row && (int) $row['dev_PresentLastScan'] === 1 ? 'online' : 'offline';
}

function getNodeOnlineState_by_mac($mac) {
	global $db;
	$result = db_execute_prepared(
		$db,
		'SELECT "dev_PresentLastScan" FROM "Devices" WHERE "dev_MAC" = :mac LIMIT 1',
		array(':mac' => (string) $mac)
	);
	$row = $result === false ? false : $result->fetchArray(SQLITE3_ASSOC);
	return $row && (int) $row['dev_PresentLastScan'] === 1 ? 'online' : 'offline';
}

function getNodeClientsOnlineState($deviceId) {
	global $db;
	$countResult = db_execute_prepared(
		$db,
		'SELECT COUNT(*) AS "count" FROM "Devices" WHERE "dev_PresentLastScan" = 1 AND "dev_Infrastructure" = :id',
		array(':id' => (string) $deviceId)
	);
	$countRow = $countResult === false ? false : $countResult->fetchArray(SQLITE3_ASSOC);
	$count = $countRow ? (int) $countRow['count'] : 0;

	$infrastructure = get_network_infrastructure($deviceId);
	$type = (string) ($infrastructure['net_device_typ'] ?? '');
	if (in_array($type, array('3_WLAN', '4_Powerline', '5_Hypervisor'), true)) {
		foreach (network_parse_downstream_entries($infrastructure['net_downstream_devices'] ?? '') as $entry) {
			if (getNodeOnlineState_by_mac($entry['mac']) === 'online') {
				$count++;
			}
		}
	}

	return array($count > 0 ? 'online' : 'offline', $count);
}

function port_badge($status) {
	if ($status === 'online') {
		echo '<span class="badge bg-green text-white" style="width: 60px;">Online</span>';
	} elseif ($status === 'dumb') {
		echo '<span class="badge bg-yellow text-white" style="width: 60px;">UM</span>';
	} else {
		echo '<span class="badge bg-gray text-white" style="width: 60px;">Offline</span>';
	}
}

function network_type_icon($type) {
	$icons = array(
		'WLAN' => '<i class="bi bi-wifi network_tab_icon text-aqua" style="top: 1px;"></i>',
		'Powerline' => '<i class="bi bi-plug-fill network_tab_icon text-aqua" style="top: 2px;"></i>',
		'Router' => '<i class="bi bi-router-fill network_tab_icon text-aqua" style="top: 2px;"></i>',
		'Switch' => '<i class="bi bi-ethernet network_tab_icon text-aqua" style="top: 2px;"></i>',
		'Internet' => '<i class="bi bi-globe network_tab_icon text-aqua" style="top: 2px;"></i>',
		'Hypervisor' => '<i class="bi bi-hdd-stack-fill network_tab_icon text-aqua" style="top: 2px;"></i>',
	);
	return $icons[$type] ?? h($type);
}

function createnetworktab($deviceId, $deviceName, $deviceType, $active) {
	$deviceId = (int) $deviceId;
	$type = substr((string) $deviceType, 2);
	$nodeState = getNodeOnlineState($deviceName);

	echo '<li class="' . ($active ? 'active' : '') . '">';
	echo '<a href="#network-tab-' . $deviceId . '" data-toggle="tab">';
	if ($nodeState === 'offline') {
		$clientState = getNodeClientsOnlineState($deviceId);
		printNodeOnlineState($clientState[0] === 'offline' && $type === 'WLAN' ? 'inactive' : $clientState[0]);
	} else {
		printNodeOnlineState($nodeState);
	}
	echo h($deviceName) . ' / ' . network_type_icon($type);
	echo '</a></li>';
}

function get_all_devices_from_tables($deviceId) {
	global $db;
	$parameters = array(':id' => (string) $deviceId);
	$detected = db_execute_prepared(
		$db,
		'SELECT * FROM "Devices" WHERE "dev_Infrastructure" = :id AND "dev_Archived" = 0',
		$parameters
	);
	$unmanaged = db_execute_prepared(
		$db,
		'SELECT * FROM "network_dumb_dev" WHERE "dev_Infrastructure" = :id',
		$parameters
	);
	return array_merge(network_fetch_rows($detected), network_fetch_rows($unmanaged));
}

function network_render_device_row($portLabel, $devices, $iconClass = '') {
	echo '<tr>';
	if ($iconClass !== '') {
		echo '<td style="text-align: center;"><i class="fa ' . h($iconClass) . '"></i></td>';
	} else {
		echo '<td style="text-align: right; padding-right:16px;">' . h($portLabel) . '</td>';
	}

	echo '<td>';
	if (count($devices) === 0) {
		port_badge('offline');
	} else {
		foreach ($devices as $index => $device) {
			port_badge($device['state']);
			if ($index < count($devices) - 1) {
				echo '<br>';
			}
		}
	}
	echo '</td>';

	echo '<td style="padding-left: 10px;">';
	foreach ($devices as $index => $device) {
		echo network_device_link($device);
		if ($index < count($devices) - 1) {
			echo '<br>';
		}
	}
	echo '</td>';

	echo '<td style="padding-left: 10px;">';
	foreach ($devices as $index => $device) {
		echo h($device['ip']);
		if ($index < count($devices) - 1) {
			echo '<br>';
		}
	}
	echo '</td></tr>';
}

function createnetworktabcontent($deviceId, $deviceName, $deviceType, $devicePortCount, $active) {
	global $pia_lang;

	$deviceId = (int) $deviceId;
	$portCount = filter_var($devicePortCount, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 1024)));
	$portCount = $portCount === false ? 1 : (int) $portCount;
	$type = substr((string) $deviceType, 2);

	echo '<div class="tab-pane ' . ($active ? 'active' : '') . '" id="network-tab-' . $deviceId . '">';
	echo '<h4>' . h($deviceName) . ' <span class="text-muted">(ID:' . $deviceId . ')</span></h4><br>';
	echo '<div class="box-body no-padding"><table class="table table-striped table-hover"><tbody><tr>';
	echo '<th style="width: 40px">Port</th>';
	echo '<th style="width: 75px">' . h($pia_lang['Network_Table_State']) . '</th>';
	echo '<th>' . h($pia_lang['Network_Table_Hostname']) . '</th>';
	echo '<th>' . h($pia_lang['Network_Table_IP']) . '</th></tr>';

	$devices = array_map('network_normalize_device', get_all_devices_from_tables($deviceId));
	$downstreamEntries = get_downstream_entries($deviceId);

	if ($portCount > 1) {
		$portDevices = array_fill(1, $portCount, array());
		foreach ($devices as $device) {
			foreach (network_parse_target_ports($device['ports'], $portCount) as $port) {
				$portDevices[$port][] = $device;
			}
		}

		// A manual MAC/port mapping intentionally replaces automatic assignments
		// on that port, matching the previous behaviour.
		foreach ($downstreamEntries as $entry) {
			if ($entry['port'] === null || $entry['port'] < 1 || $entry['port'] > $portCount) {
				continue;
			}
			$row = get_downstream_from_mac($entry['mac']);
			if ($row !== null) {
				$portDevices[$entry['port']] = array(network_normalize_device($row));
			}
		}

		for ($port = 1; $port <= $portCount; $port++) {
			network_render_device_row((string) $port, $portDevices[$port]);
		}
	} else {
		foreach ($downstreamEntries as $entry) {
			$row = get_downstream_from_mac($entry['mac']);
			if ($row !== null) {
				$devices[] = network_normalize_device($row);
			}
		}
		usort($devices, function($left, $right) {
			return strnatcasecmp($left['name'], $right['name']);
		});
		$iconMap = array('WLAN' => 'fa-wifi', 'Powerline' => 'fa-flash', 'Hypervisor' => 'fa-computer');
		$iconClass = $iconMap[$type] ?? '';
		foreach ($devices as $device) {
			network_render_device_row('', array($device), $iconClass);
		}
	}

	echo '</tbody></table></div></div>';
}

$networkNameResult = $db->query('SELECT DISTINCT "net_networkname" FROM "network_infrastructure" ORDER BY "net_networkname" COLLATE NOCASE ASC');
foreach (network_fetch_rows($networkNameResult) as $networkNameRow) {
	$networkName = (string) ($networkNameRow['net_networkname'] ?? '');
	echo '<h4 style="font-size: x-large; text-align: center; text-decoration: underline;">' . h($pia_lang['NET_Network_head']) . ': ' . h($networkName) . '</h4>';

	$infrastructureResult = db_execute_prepared(
		$db,
		'SELECT "device_id", "net_device_name", "net_device_typ", "net_device_port" FROM "network_infrastructure" WHERE "net_networkname" = :network_name ORDER BY "net_device_typ" ASC, "net_device_name" ASC',
		array(':network_name' => $networkName)
	);
	$infrastructureRows = network_fetch_rows($infrastructureResult);

	echo '<div class="nav-tabs-custom"><ul class="nav nav-tabs">';
	foreach ($infrastructureRows as $index => $row) {
		if (filter_var($row['device_id'] ?? null, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1))) === false) {
			continue;
		}
		createnetworktab($row['device_id'], $row['net_device_name'] ?? '', $row['net_device_typ'] ?? '', $index === 0);
	}
	echo '</ul><div class="tab-content" style="max-height:400px; overflow:auto;">';
	foreach ($infrastructureRows as $index => $row) {
		if (filter_var($row['device_id'] ?? null, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1))) === false) {
			continue;
		}
		createnetworktabcontent($row['device_id'], $row['net_device_name'] ?? '', $row['net_device_typ'] ?? '', $row['net_device_port'] ?? '', $index === 0);
	}
	echo '</div></div>';
}

?>
<div class="box box-default collapsed-box">
    <div class="box-header with-border" data-widget="collapse">
        <h3 class="box-title"><i class="fa"></i><?=$pia_lang['Network_UnassignedDevices'];?></h3>
          <div class="box-tools pull-right">
            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>
          </div>
    </div>
    <div class="box-body">
<?php
unassigned_devices();
?>
    </div>
</div>

  <div style="width: 100%; height: 20px;"></div>
</section>
  </div>

<?php
require 'php/templates/footer.php';
?>

<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  icmpmonitor.php - Front module. Server side. Manage Devices
//------------------------------------------------------------------------------
//  leiweibau  2023        https://github.com/leiweibau     GNU GPLv3
//------------------------------------------------------------------------------

session_start();

if ($_SESSION["login"] != 1) {
	header('Location: ../../index.php');
	exit;
}
require 'timezone.php';
require 'db.php';
require 'util.php';
require 'journal.php';
require 'language_switch.php';
require '../templates/language/' . $pia_lang_selected . '.php';

// Action selector
// Set maximum execution time to 1 minute
ini_set('max_execution_time', '60');

// Open DB
OpenDB();

// Action functions
if (isset($_REQUEST['action']) && !empty($_REQUEST['action'])) {
	$action = $_REQUEST['action'];
	switch ($action) {
	case 'setICMPHostData':setICMPHostData();
		break;
	case 'deleteICMPHost':deleteICMPHost();
		break;
	case 'insertNewICMPHost':insertNewICMPHost();
		break;
	case 'EnableICMPMon':EnableICMPMon();
		break;
	case 'getDevicesList':getDevicesList();
		break;
	case 'getICMPHostTotals':getICMPHostTotals();
		break;
	case 'getEventsTotalsforICMP':getEventsTotalsforICMP();
		break;
	case 'BulkDeletion':BulkDeletion();
		break;
	}
}

//  Get List Totals
function getICMPHostTotals() {
	global $db;

	$query = "SELECT COUNT(*) AS rowCount FROM ICMP_Mon WHERE icmp_Archived=0 AND icmp_PresentLastScan=0 AND icmp_AlertDown=1";
	$alertDown_Count = $db->querySingle($query);
	$query = "SELECT COUNT(*) AS rowCount FROM ICMP_Mon WHERE icmp_Archived=0 AND icmp_PresentLastScan=1";
	$online_Count = $db->querySingle($query);
	$query = "SELECT COUNT(*) AS rowCount FROM ICMP_Mon WHERE icmp_Archived=0 AND icmp_Favorite=1";
	$favorite_Count = $db->querySingle($query);
	$query = "SELECT COUNT(*) AS rowCount FROM ICMP_Mon WHERE icmp_Archived=0";
	$all_Count = $db->querySingle($query);
	$query = "SELECT COUNT(*) AS rowCount FROM ICMP_Mon WHERE icmp_Archived=1";
	$archived_Count = $db->querySingle($query);

	$totals = array($all_Count, $alertDown_Count, $online_Count, $favorite_Count, $archived_Count);
	echo (json_encode($totals));
}

//  Get List
function getDevicesList() {
	global $db;

	$condition = getDeviceCondition($_REQUEST['status']);
	$sql = 'SELECT rowid, *, CASE
            WHEN icmp_AlertDown=1 AND icmp_PresentLastScan=0 THEN "Down"
            WHEN icmp_Scan_Validation_State=0 AND icmp_PresentLastScan=1 THEN "Online"
            WHEN icmp_Scan_Validation > 0 AND icmp_Scan_Validation_State > 0 AND icmp_Scan_Validation_State <= icmp_Scan_Validation AND icmp_PresentLastScan=1 THEN "OnlineV"
            ELSE "Offline"
          END AS icmp_Status
          FROM ICMP_Mon ' . $condition;
	$result = $db->query($sql);
	// arrays of rows
	$tableData = array();
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		if ($row['icmp_hostname'] == '') {$row['icmp_hostname'] = $row['icmp_ip'];}
		$tableData['data'][] = array(
			$row['icmp_hostname'],
			$row['icmp_ip'],
			$row['icmp_Favorite'],
			$row['icmp_avgrtt'],
			$row['icmp_LastScan'],
			$row['icmp_PresentLastScan'],
			$row['icmp_AlertDown'],
			$row['icmp_Status'],
			$row['rowid'], // Rowid (hidden)
		);
	}
	// Control no rows
	if (empty($tableData['data'])) {
		$tableData['data'] = '';
	}
	// Return json
	echo (json_encode($tableData));
}

//  Status Where conditions
function getDeviceCondition($deviceStatus) {
	switch ($deviceStatus) {
	case 'all':return 'WHERE icmp_Archived=0';
		break;
	case 'connected':return 'WHERE icmp_Archived=0 AND icmp_PresentLastScan=1';
		break;
	case 'favorites':return 'WHERE icmp_Archived=0 AND icmp_Favorite=1';
		break;
	case 'down':return 'WHERE icmp_Archived=0 AND icmp_AlertDown=1 AND icmp_PresentLastScan=0';
		break;
	case 'archived':return 'WHERE icmp_Archived=1';
		break;
	default:return '';
		break;
	}
}

//  Set ICMP Host Data
function setICMPHostData() {
	global $db;
	global $pia_lang;

	$values = array();
	foreach (array('icmp_hostname', 'icmp_type', 'icmp_group', 'icmp_location', 'icmp_owner', 'icmp_notes', 'icmp_vendor', 'icmp_model', 'icmp_serial', 'alertevents', 'alertdown', 'favorit', 'archived') as $key) {
		$value = $_REQUEST[$key] ?? '';
		$values[$key] = is_scalar($value) ? (string)$value : '';
	}
	foreach (array('icmp_group', 'icmp_type', 'icmp_location') as $key) {
		if ($values[$key] === '--') {
			$values[$key] = '';
		}
	}
	$hostip = $_REQUEST['icmp_ip'] ?? '';
	if (!is_scalar($hostip) || (!filter_var($hostip, FILTER_VALIDATE_IP) && !filter_var($hostip, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) {
		echo $pia_lang['BackICMP_mon_UpdICMPError'];
		return;
	}
	$scanvalid = filter_var($_REQUEST['icmp_scanvalid'] ?? 0, FILTER_VALIDATE_INT);
	$mqttdevice = filter_var($_REQUEST['mqttdevice'] ?? 0, FILTER_VALIDATE_INT) ? 1 : 0;
	$cleanup = $mqttdevice ? 0 : 1;
	$sql = 'UPDATE ICMP_Mon SET icmp_hostname = :hostname, icmp_type = :type, icmp_group = :group_name, icmp_location = :location, icmp_owner = :owner, icmp_notes = :notes, icmp_Scan_Validation = :scanvalid, icmp_vendor = :vendor, icmp_model = :model, icmp_serial = :serial, icmp_MQTTDevice = :mqttdevice, icmp_MQTTDevice_cleanup = :cleanup, icmp_AlertEvents = :alertevents, icmp_AlertDown = :alertdown, icmp_Favorite = :favorite, icmp_Archived = :archived WHERE icmp_ip = :ip';
	$result = db_execute_prepared($db, $sql, array(
		':hostname' => $values['icmp_hostname'], ':type' => $values['icmp_type'], ':group_name' => $values['icmp_group'], ':location' => $values['icmp_location'], ':owner' => $values['icmp_owner'], ':notes' => $values['icmp_notes'],
		':scanvalid' => array($scanvalid === false ? 0 : $scanvalid, SQLITE3_INTEGER), ':vendor' => $values['icmp_vendor'], ':model' => $values['icmp_model'], ':serial' => $values['icmp_serial'], ':mqttdevice' => array($mqttdevice, SQLITE3_INTEGER), ':cleanup' => array($cleanup, SQLITE3_INTEGER), ':alertevents' => $values['alertevents'], ':alertdown' => $values['alertdown'], ':favorite' => $values['favorit'], ':archived' => $values['archived'], ':ip' => (string)$hostip
	));
	if ($result == TRUE) {
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0002', '', $hostip);
		echo $pia_lang['BackICMP_mon_UpdICMP'];
		echo ("<meta http-equiv='refresh' content='2; URL=./icmpmonitor.php'>");
	} else {
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0004', '', $hostip);
		logServerConsole('ICMP host update failed: ' . $db->lastErrorMsg());
		echo $pia_lang['BackICMP_mon_UpdICMPError'];
	}
}

//  Delete Host
function deleteICMPHost() {
	global $db;
	global $pia_lang;

	$hostip = $_REQUEST['icmp_ip'] ?? '';
	if (!is_scalar($hostip) || (!filter_var($hostip, FILTER_VALIDATE_IP) && !filter_var($hostip, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) {
		echo $pia_lang['BackICMP_mon_DelICMPError'];
		return false;
	}
	$db->exec('BEGIN');
	$result = db_execute_prepared($db, 'DELETE FROM ICMP_Mon WHERE icmp_ip = :ip', array(':ip' => (string)$hostip));
	$result = $result && db_execute_prepared($db, 'DELETE FROM ICMP_Mon_Events WHERE icmpeve_ip = :ip', array(':ip' => (string)$hostip));
	if ($result) {
		$db->exec('COMMIT');
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0003', '', $hostip);
		echo $pia_lang['BackICMP_mon_DelICMP'];
		echo ("<meta http-equiv='refresh' content='2; URL=./icmpmonitor.php'>");
	} else {
		$db->exec('ROLLBACK');
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0005', '', $hostip);
		logServerConsole('ICMP host delete failed: ' . $db->lastErrorMsg());
		echo $pia_lang['BackICMP_mon_DelICMPError'];
	}
}

//  Insert Service
function insertNewICMPHost() {
	global $db;
	global $pia_lang;
	$hostip = $_REQUEST['icmp_ip'] ?? '';
	$hostname = $_REQUEST['icmp_hostname'] ?? $hostip;
	if (!is_scalar($hostip) || !is_scalar($hostname) || (!filter_var($hostip, FILTER_VALIDATE_IP) && !filter_var($hostip, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) {
		echo $pia_lang['BackICMP_mon_InsICMPError'];
		return false;
	}
	$sql = 'INSERT INTO ICMP_Mon (icmp_ip, icmp_hostname, icmp_LastScan, icmp_PresentLastScan, icmp_avgrtt, icmp_AlertEvents, icmp_AlertDown, icmp_Favorite) VALUES (:ip, :hostname, :timestamp, 0, 99999, :alertevents, :alertdown, :favorite)';
	$result = db_execute_prepared($db, $sql, array(':ip' => (string)$hostip, ':hostname' => (string)$hostname, ':timestamp' => date('Y-m-d H:i:s'), ':alertevents' => (string)($_REQUEST['alertevents'] ?? ''), ':alertdown' => (string)($_REQUEST['alertdown'] ?? ''), ':favorite' => (string)($_REQUEST['icmp_fav'] ?? '')));
	if ($result == TRUE) {
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0001', '', $hostip);
		echo $pia_lang['BackICMP_mon_InsICMP'];
		echo ("<meta http-equiv='refresh' content='2; URL=./icmpmonitor.php'>");
	} else {
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0001', '', $hostip);
		logServerConsole('ICMP host insert failed: ' . $db->lastErrorMsg());
		echo $pia_lang['BackICMP_mon_InsICMPError'];
	}
}

//  Toggle Web Service Monitoring
function EnableICMPMon() {
	global $pia_lang;

	if ($_SESSION['ICMPScan'] == True) {
		exec('../../../back/pialert-cli disable_icmp_mon', $output);
		echo $pia_lang['BackICMP_mon_disabled'];
		// Logging
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0304', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>");
	} else {
		exec('../../../back/pialert-cli enable_icmp_mon', $output);
		echo $pia_lang['BackICMP_mon_enabled'];
		// Logging
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0303', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>");
	}
}

//  Details
function getEventsTotalsforICMP() {
	global $db;

	$hostip = $_REQUEST['hostip'] ?? '';
	if (!is_scalar($hostip) || (!filter_var($hostip, FILTER_VALIDATE_IP) && !filter_var($hostip, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) {
		echo json_encode(array(0, 0));
		return;
	}
	$params = array(':host_ip' => (string) $hostip);
	$result = db_execute_prepared($db, 'SELECT icmpeve_DateTime, icmpeve_EventType
		FROM ICMP_Mon_Connections WHERE icmpeve_ip = :host_ip
		ORDER BY icmpeve_DateTime DESC LIMIT 1', $params);
	$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
	if ($row && $row['icmpeve_EventType'] === 'Connected') {
		$currentTime = new DateTime();
		$recordTime = new DateTime($row['icmpeve_DateTime']);
		$interval = $currentTime->diff($recordTime);
		$eventspresence = $interval->h + ($interval->days * 24);
	} else {
		$eventspresence = 0;
	}

	$result = db_execute_prepared($db, 'SELECT Count(*) FROM ICMP_Mon_Connections
		WHERE icmpeve_ip = :host_ip AND icmpeve_EventType = "Down"', $params);
	$row = $result ? $result->fetchArray(SQLITE3_NUM) : array(0);
	echo json_encode(array($eventspresence, (int) $row[0]));
}

//  Bulk Deletion
function BulkDeletion() {
	global $db;
	global $pia_lang;
	$hosts = $_REQUEST['hosts'] ?? array();
	if (!is_array($hosts)) { $hosts = array(); }
	$hosts = array_values(array_filter(array_map(function ($host) { return is_scalar($host) ? str_replace('_', '.', (string)$host) : ''; }, $hosts), function ($host) { return filter_var($host, FILTER_VALIDATE_IP) || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME); }));
	list($placeholders, $parameters) = db_in_placeholders('host', $hosts);
	if ($placeholders === '') { echo $pia_lang['Device_bulkDel_back_hosts'] . ': 0'; return; }
	$rowCount_before = (int)$db->querySingle('SELECT COUNT(*) FROM ICMP_Mon');
	$db->exec('BEGIN');
	$result = db_execute_prepared($db, 'DELETE FROM ICMP_Mon WHERE icmp_ip IN (' . $placeholders . ')', $parameters);
	if ($result) { $db->exec('COMMIT'); } else { $db->exec('ROLLBACK'); }
	$rowCount_after = (int)$db->querySingle('SELECT COUNT(*) FROM ICMP_Mon');
	echo $pia_lang['Device_bulkDel_back_hosts'] . ': ' . implode(', ', $hosts) . '<br><br>' . $pia_lang['Device_bulkDel_back_before'] . ': ' . $rowCount_before . '<br>' . $pia_lang['Device_bulkDel_back_after'] . ': ' . $rowCount_after;
	echo ("<meta http-equiv='refresh' content='2; URL=./icmpmonitor.php?mod=bulkedit'>");
	pialert_logging('a_021', $_SERVER['REMOTE_ADDR'], 'LogStr_0003', '', implode(',', $hosts));
}

?>

<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  network.php - Back module. Server side. Manage Devices
//------------------------------------------------------------------------------
//  leiweibau  2024+        https://github.com/leiweibau     GNU GPLv3
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
// Set maximum execution time to 15 seconds
ini_set('max_execution_time', '30');

// Open DB
OpenDB();

// Action functions
if (isset($_REQUEST['action']) && !empty($_REQUEST['action'])) {
	$action = $_REQUEST['action'];
	switch ($action) {
	case 'network_device_downlink':network_device_downlink();
		break;
	case 'NetworkInfrastructure_list':NetworkInfrastructure_list();
		break;
	case 'NetworkDeviceTyp_list':NetworkDeviceTyp_list();
		break;
	case 'NetworkGroupName_list':NetworkGroupName_list();
		break;
	case 'addManagedDev':addManagedDev();
		break;
	case 'updManagedDev':updManagedDev();
		break;
	case 'delManagedDev':delManagedDev();
		break;
	case 'addUnManagedDev':addUnManagedDev();
		break;
	case 'updUnManagedDev':updUnManagedDev();
		break;
	case 'delUnManagedDev':delUnManagedDev();
		break;
	default:logServerConsole('Action: ' . $action);
		break;
	}
}

function network_request_text($key) {
	$value = $_REQUEST[$key] ?? '';
	return is_scalar($value) ? trim((string)$value) : '';
}

function network_request_id($key) {
	$value = $_REQUEST[$key] ?? null;
	if (filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1))) === false) {
		return null;
	}
	return (int)$value;
}

function network_device_downlink() {
	global $db;
	$node_typ = substr($_REQUEST['nodetyp'],2);
	$special_dev = array("Router", "Switch", "AP", "Access Point");

	if (in_array($node_typ, $special_dev)) {
		// $func_sql = 'SELECT * FROM "Devices" WHERE "dev_DeviceType" IN ("Router", "Switch", "AP", "Access Point") OR "dev_MAC" = "Internet" ORDER BY "dev_DeviceType" ASC';
		$func_sql = 'SELECT *
				FROM "Devices"
				WHERE "dev_DeviceType" IN ("Router", "Switch", "AP", "Access Point")
				   OR "dev_MAC" LIKE "Internet%"
				ORDER BY "dev_DeviceType" ASC';
		$value_seperator = ',';
	} else {
		// $func_sql = 'SELECT * FROM "Devices" WHERE "dev_DeviceType" NOT IN ("Router", "Switch", "AP", "Access Point") OR "dev_MAC" = "Internet" ORDER BY "dev_Name" ASC';
		$func_sql = 'SELECT *
				FROM "Devices"
				WHERE (
				        "dev_DeviceType" NOT IN ("Router", "Switch", "AP", "Access Point")
				      )
				   OR "dev_MAC" LIKE "Internet%"
				ORDER BY "dev_Name" ASC';
		$value_seperator = ';';
	}
	$func_result = $db->query($func_sql); //->fetchArray(SQLITE3_ASSOC);
	while ($func_res = $func_result->fetchArray(SQLITE3_ASSOC)) {
		if ($value_seperator == "," && $temp_type != "" && $temp_type != $func_res['dev_DeviceType']) {echo '<li class="divider"></li>';}
		echo '<li><a href="#" class="network-value-option" data-action="append" data-target="txtNetworkDeviceDownlinkMac" data-value="' . h($func_res['dev_MAC'] . $value_seperator) . '">' . h($func_res['dev_Name']) . '</a></li>';
		$temp_type = $func_res['dev_DeviceType'];
	}
}

function NetworkInfrastructure_list() {
	global $db;
	$func_sql = 'SELECT * FROM "Devices" WHERE "dev_DeviceType" IN ("Router", "Switch", "AP", "Access Point", "Hypervisor") OR "dev_MAC" LIKE "Internet%" ORDER BY "dev_Name" ASC';

	$func_result = $db->query($func_sql); //->fetchArray(SQLITE3_ASSOC);
	while ($func_res = $func_result->fetchArray(SQLITE3_ASSOC)) {
		echo '<li><a href="#" class="network-value-option" data-action="set" data-target="txtNetworkDeviceName" data-value="' . h($func_res['dev_Name']) . '">' . h($func_res['dev_Name']) . '/' . h($func_res['dev_DeviceType']) . '</a></li>';
	}
}

function NetworkDeviceTyp_list() {
	$mode = network_request_text('mode');
	$targets = array('add' => 'txtNetworkDeviceTyp', 'edit' => 'txtNewNetworkDeviceTyp');
	if (!isset($targets[$mode])) {
		http_response_code(400);
		return;
	}
	$inputfield = $targets[$mode];
	foreach (array('0_Internet' => '0. Internet', '1_Router' => '1. Router', '2_Switch' => '2. Switch', '3_WLAN' => '3. WLAN', '4_Powerline' => '4. Powerline', '5_Hypervisor' => '5. Hypervisor') as $value => $label) {
		echo '<li><a href="#" class="network-value-option" data-action="set" data-target="' . h($inputfield) . '" data-value="' . h($value) . '">' . h($label) . '</a></li>';
	}
}

function NetworkGroupName_list() {
	global $db;
	$mode = network_request_text('mode');
	$targets = array('add' => 'txtNetworkGroupName', 'edit' => 'txtNewNetworkGroupName');
	if (!isset($targets[$mode])) {
		http_response_code(400);
		return;
	}
	$inputfield = $targets[$mode];

	$func_sql = 'SELECT "sat_name" FROM "Satellites"';
	$func_result = $db->query($func_sql); //->fetchArray(SQLITE3_ASSOC);
	while ($func_res = $func_result->fetchArray(SQLITE3_ASSOC)) {
		echo '<li><a href="#" class="network-value-option" data-action="set" data-target="' . h($inputfield) . '" data-value="' . h($func_res['sat_name']) . '">Satellite ' . h($func_res['sat_name']) . '</a></li>';
	}
	echo '<li class="divider"></li>';

	$func_sql = 'SELECT DISTINCT "net_networkname" FROM "network_infrastructure"';
	$func_result = $db->query($func_sql); //->fetchArray(SQLITE3_ASSOC);
	while ($func_res = $func_result->fetchArray(SQLITE3_ASSOC)) {
		echo '<li><a href="#" class="network-value-option" data-action="set" data-target="' . h($inputfield) . '" data-value="' . h($func_res['net_networkname']) . '">Network ' . h($func_res['net_networkname']) . '</a></li>';
	}
}

function addManagedDev() {
	global $db;
	global $pia_lang;

	if (!isset($_REQUEST['NetworkDeviceName']) && !isset($_REQUEST['NetworkDeviceTyp'])) {
		echo "Test";
		return;
	}

	$sql = 'INSERT INTO "network_infrastructure" ("net_device_name", "net_device_typ", "net_device_port", "net_networkname") VALUES (:name, :type, :port, :group_name)';
	$result = db_execute_prepared($db, $sql, array(
		':name' => network_request_text('NetworkDeviceName'),
		':type' => network_request_text('NetworkDeviceTyp'),
		':port' => network_request_text('NetworkDevicePort'),
		':group_name' => network_request_text('NetworkGroupName')
	));

	if ($result == TRUE) {
		echo $pia_lang['BE_NET_Man_Add'];
		// Logging
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0030', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./networkSettings.php'>");
	} else {
		echo $pia_lang['BE_NET_Man_Add_Err'];
		logServerConsole('Network managed-device insert failed: ' . $db->lastErrorMsg());
		// Logging
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0070', '', '');
	}
}

function updManagedDev() {
	global $db;
	global $pia_lang;

	$deviceId = network_request_id('NetworkDeviceID');
	$result = false;
	if ($deviceId !== null && isset($_REQUEST['NewNetworkDeviceTyp'])) {
		$parameters = array(
			':type' => network_request_text('NewNetworkDeviceTyp'),
			':port' => network_request_text('NewNetworkDevicePort'),
			':downstream' => network_request_text('NetworkDeviceDownlink'),
			':group_name' => network_request_text('NewNetworkGroupName'),
			':id' => array($deviceId, SQLITE3_INTEGER)
		);
		if (network_request_text('NewNetworkDeviceName') !== '') {
			$sql = 'UPDATE "network_infrastructure" SET "net_device_name" = :name, "net_device_typ" = :type, "net_device_port" = :port, "net_downstream_devices" = :downstream, "net_networkname" = :group_name WHERE "device_id" = :id';
			$parameters[':name'] = network_request_text('NewNetworkDeviceName');
		} else {
			$sql = 'UPDATE "network_infrastructure" SET "net_device_typ" = :type, "net_device_port" = :port, "net_downstream_devices" = :downstream, "net_networkname" = :group_name WHERE "device_id" = :id';
		}
		$result = db_execute_prepared($db, $sql, $parameters);
	}

	if ($result == TRUE) {
		echo $pia_lang['BE_NET_Man_Upd'];
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0031', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./networkSettings.php'>");
	} else {
		echo $pia_lang['BE_NET_Man_Upd_Err'];
		logServerConsole('Network managed-device update failed: ' . $db->lastErrorMsg());
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0071', '', '');
	}
}

function delManagedDev() {
	global $db;
	global $pia_lang;

	$deviceId = network_request_id('NetworkDeviceID');
	$result = false;
	if ($deviceId !== null) {
		$result = db_execute_prepared($db, 'DELETE FROM "network_infrastructure" WHERE "device_id" = :id', array(':id' => array($deviceId, SQLITE3_INTEGER)));
	}

	if ($result == TRUE) {
		echo $pia_lang['BE_NET_Man_Del'];
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0032', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./networkSettings.php'>");
	} else {
		echo $pia_lang['BE_NET_Man_Del_Err'];
		logServerConsole('Network managed-device delete failed: ' . $db->lastErrorMsg());
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0072', '', '');
	}
}

function addUnManagedDev() {
	global $db;
	global $pia_lang;

	$result = false;
	if (isset($_REQUEST['NetworkUnmanagedDevName']) && isset($_REQUEST['NetworkUnmanagedDevConnect'])) {
		$sql = 'INSERT INTO "network_dumb_dev" ("dev_Name", "dev_MAC", "dev_Infrastructure", "dev_Infrastructure_port", "dev_PresentLastScan", "dev_LastIP") VALUES (:name, :mac, :infrastructure, :port, :present, :ip)';
		$result = db_execute_prepared($db, $sql, array(
			':name' => network_request_text('NetworkUnmanagedDevName'),
			':mac' => 'dumb',
			':infrastructure' => network_request_text('NetworkUnmanagedDevConnect'),
			':port' => network_request_text('NetworkUnmanagedDevPort'),
			':present' => 'dumb',
			':ip' => 'Unmanaged'
		));
	}

	if ($result == TRUE) {
		echo $pia_lang['BE_NET_Man_AddUn'];
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0033', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./networkSettings.php'>");
	} else {
		echo $pia_lang['BE_NET_Man_AddUn_Err'];
		logServerConsole('Network unmanaged-device insert failed: ' . $db->lastErrorMsg());
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0073', '', '');
	}
}

function updUnManagedDev() {
	global $db;
	global $pia_lang;

	$deviceId = network_request_id('NetworkUnmanagedDevID');
	$result = false;
	if ($deviceId !== null && isset($_REQUEST['NewNetworkUnmanagedDevConnect'])) {
		$parameters = array(
			':infrastructure' => network_request_text('NewNetworkUnmanagedDevConnect'),
			':port' => network_request_text('NewNetworkUnmanagedDevPort'),
			':id' => array($deviceId, SQLITE3_INTEGER)
		);
		if (network_request_text('NewNetworkUnmanagedDevName') !== '') {
			$sql = 'UPDATE "network_dumb_dev" SET "dev_Name" = :name, "dev_Infrastructure" = :infrastructure, "dev_Infrastructure_port" = :port WHERE "id" = :id';
			$parameters[':name'] = network_request_text('NewNetworkUnmanagedDevName');
		} else {
			$sql = 'UPDATE "network_dumb_dev" SET "dev_Infrastructure" = :infrastructure, "dev_Infrastructure_port" = :port WHERE "id" = :id';
		}
		$result = db_execute_prepared($db, $sql, $parameters);
	}

	if ($result == TRUE) {
		echo $pia_lang['BE_NET_Man_UpdUn'];
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0034', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./networkSettings.php'>");
	} else {
		echo $pia_lang['BE_NET_Man_UpdUn_Err'];
		logServerConsole('Network unmanaged-device update failed: ' . $db->lastErrorMsg());
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0074', '', '');
	}
}

function delUnManagedDev() {
	global $db;
	global $pia_lang;

	$deviceId = network_request_id('NetworkUnmanagedDevID');
	$result = false;
	if ($deviceId !== null) {
		$result = db_execute_prepared($db, 'DELETE FROM "network_dumb_dev" WHERE "id" = :id', array(':id' => array($deviceId, SQLITE3_INTEGER)));
	}

	if ($result == TRUE) {
		echo $pia_lang['BE_NET_Man_DelUn'];
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0035', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./networkSettings.php'>");
	} else {
		echo $pia_lang['BE_NET_Man_DelUn_Err'];
		logServerConsole('Network unmanaged-device delete failed: ' . $db->lastErrorMsg());
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0075', '', '');
	}
}

//  End
?>

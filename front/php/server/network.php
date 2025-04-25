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

function network_device_downlink() {
	global $db;
	$node_typ = substr($_REQUEST['nodetyp'],2);
	$special_dev = array("Router", "Switch", "AP", "Access Point");

	if (in_array($node_typ, $special_dev)) {
		$func_sql = 'SELECT * FROM "Devices" WHERE "dev_DeviceType" IN ("Router", "Switch", "AP", "Access Point") OR "dev_MAC" = "Internet" ORDER BY "dev_DeviceType" ASC';
		$value_seperator = ',';
	} else {
		$func_sql = 'SELECT * FROM "Devices" WHERE "dev_DeviceType" NOT IN ("Router", "Switch", "AP", "Access Point") OR "dev_MAC" = "Internet" ORDER BY "dev_Name" ASC';
		$value_seperator = ';';
	}
	$func_result = $db->query($func_sql); //->fetchArray(SQLITE3_ASSOC);
	while ($func_res = $func_result->fetchArray(SQLITE3_ASSOC)) {
		if ($value_seperator == "," && $temp_type != "" && $temp_type != $func_res['dev_DeviceType']) {echo '<li class="divider"></li>';}
		echo '<li><a href="javascript:void(0)" onclick="appendTextValue(\'txtNetworkDeviceDownlinkMac\',\'' . $func_res['dev_MAC'] . $value_seperator .'\')">' . $func_res['dev_Name'] . '</a></li>';
		$temp_type = $func_res['dev_DeviceType'];
	}
}

function NetworkInfrastructure_list() {
	global $db;
	$func_sql = 'SELECT * FROM "Devices" WHERE "dev_DeviceType" IN ("Router", "Switch", "AP", "Access Point", "Hypervisor") OR "dev_MAC" = "Internet"';

	$func_result = $db->query($func_sql); //->fetchArray(SQLITE3_ASSOC);
	while ($func_res = $func_result->fetchArray(SQLITE3_ASSOC)) {
		echo '<li><a href="javascript:void(0)" onclick="setTextValue(\'txtNetworkDeviceName\',\'' . $func_res['dev_Name'] . '\')">' . $func_res['dev_Name'] . '/' . $func_res['dev_DeviceType'] . '</a></li>';
	}
}

function NetworkDeviceTyp_list() {
	if ($_REQUEST['mode'] == "add") {$inputfield = "txtNetworkDeviceTyp";}
	if ($_REQUEST['mode'] == "edit") {$inputfield = "txtNewNetworkDeviceTyp";}
	echo '<li><a href="javascript:void(0)" onclick="setTextValue(\''.$inputfield.'\',\'0_Internet\')">0. Internet</a></li>';
	echo '<li><a href="javascript:void(0)" onclick="setTextValue(\''.$inputfield.'\',\'1_Router\')">1. Router</a></li>';
	echo '<li><a href="javascript:void(0)" onclick="setTextValue(\''.$inputfield.'\',\'2_Switch\')">2. Switch</a></li>';
	echo '<li><a href="javascript:void(0)" onclick="setTextValue(\''.$inputfield.'\',\'3_WLAN\')">3. WLAN</a></li>';
	echo '<li><a href="javascript:void(0)" onclick="setTextValue(\''.$inputfield.'\',\'4_Powerline\')">4. Powerline</a></li>';
	echo '<li><a href="javascript:void(0)" onclick="setTextValue(\''.$inputfield.'\',\'5_Hypervisor\')">5. Hypervisor</a></li>';
}

function NetworkGroupName_list() {
	global $db;
	if ($_REQUEST['mode'] == "add") {$inputfield = "txtNetworkGroupName";}
	if ($_REQUEST['mode'] == "edit") {$inputfield = "txtNewNetworkGroupName";}

	$func_sql = 'SELECT "sat_name" FROM "Satellites"';
	$func_result = $db->query($func_sql); //->fetchArray(SQLITE3_ASSOC);
	while ($func_res = $func_result->fetchArray(SQLITE3_ASSOC)) {
		echo '<li><a href="javascript:void(0)" onclick="setTextValue(\'' . $inputfield . '\',\'' . $func_res['sat_name'] . '\')">Satellite ' . $func_res['sat_name'] . '</a></li>';
	}
	echo '<li class="divider"></li>';

	$func_sql = 'SELECT DISTINCT "net_networkname" FROM "network_infrastructure"';
	$func_result = $db->query($func_sql); //->fetchArray(SQLITE3_ASSOC);
	while ($func_res = $func_result->fetchArray(SQLITE3_ASSOC)) {
		echo '<li><a href="javascript:void(0)" onclick="setTextValue(\'' . $inputfield . '\',\'' . $func_res['net_networkname'] . '\')">Network ' . $func_res['net_networkname'] . '</a></li>';
	}
}

function addManagedDev() {
	global $db;
	global $pia_lang;

	if (!isset($_REQUEST['NetworkDeviceName']) && !isset($_REQUEST['NetworkDeviceTyp'])) {
		echo "Test";
		return;
	}

	$sql = 'INSERT INTO "network_infrastructure" ("net_device_name", "net_device_typ", "net_device_port", "net_networkname") VALUES("' . $_REQUEST['NetworkDeviceName'] . '", "' . $_REQUEST['NetworkDeviceTyp'] . '", "' . $_REQUEST['NetworkDevicePort'] . '", "' . $_REQUEST['NetworkGroupName'] . '")';
	$result = $db->query($sql);

	if ($result == TRUE) {
		echo $pia_lang['BE_NET_Man_Add'];
		// Logging
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0030', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./networkSettings.php'>");
	} else {
		echo $pia_lang['BE_NET_Man_Add_Err'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0070', '', '');
	}
}

function updManagedDev() {
	global $db;
	global $pia_lang;

	if (($_REQUEST['NewNetworkDeviceName'] != "") && isset($_REQUEST['NewNetworkDeviceTyp'])) {
		$sql = 'UPDATE "network_infrastructure" SET "net_device_name" = "' . $_REQUEST['NewNetworkDeviceName'] . '", "net_device_typ" = "' . $_REQUEST['NewNetworkDeviceTyp'] . '", "net_device_port" = "' . $_REQUEST['NewNetworkDevicePort'] . '", "net_downstream_devices" = "' . $_REQUEST['NetworkDeviceDownlink'] . '", "net_networkname" = "' . $_REQUEST['NewNetworkGroupName'] . '" WHERE "device_id"="' . $_REQUEST['NetworkDeviceID'] . '"';
		$result = $db->query($sql);
	}
	if (($_REQUEST['NewNetworkDeviceName'] == "") && isset($_REQUEST['NewNetworkDeviceTyp'])) {
		$sql = 'UPDATE "network_infrastructure" SET "net_device_typ" = "' . $_REQUEST['NewNetworkDeviceTyp'] . '", "net_device_port" = "' . $_REQUEST['NewNetworkDevicePort'] . '", "net_downstream_devices" = "' . $_REQUEST['NetworkDeviceDownlink'] . '", "net_networkname" = "' . $_REQUEST['NewNetworkGroupName'] . '" WHERE "device_id "="' . $_REQUEST['NetworkDeviceID'] . '"';
		$result = $db->query($sql);
	}

	if ($result == TRUE) {
		echo $pia_lang['BE_NET_Man_Upd'];
		// Logging
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0031', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./networkSettings.php'>");
	} else {
		echo $pia_lang['BE_NET_Man_Upd_Err'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0071', '', '');
	}
}

function delManagedDev() {
	global $db;
	global $pia_lang;

	if (isset($_REQUEST['NetworkDeviceID'])) {
		$sql = 'DELETE FROM "network_infrastructure" WHERE "device_id"="' . $_REQUEST['NetworkDeviceID'] . '"';
		$result = $db->query($sql);
	}

	if ($result == TRUE) {
		echo $pia_lang['BE_NET_Man_Del'];
		// Logging
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0032', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./networkSettings.php'>");
	} else {
		echo $pia_lang['BE_NET_Man_Del_Err'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0072', '', '');
	}
}

function addUnManagedDev() {
	global $db;
	global $pia_lang;

	if (isset($_REQUEST['NetworkUnmanagedDevName']) && isset($_REQUEST['NetworkUnmanagedDevConnect'])) {
		$ip = 'Unmanaged';
		$dumbvar = 'dumb';
		$sql = 'INSERT INTO "network_dumb_dev" ("dev_Name", "dev_MAC", "dev_Infrastructure", "dev_Infrastructure_port", "dev_PresentLastScan", "dev_LastIP") VALUES("' . $_REQUEST['NetworkUnmanagedDevName'] . '", "' . $dumbvar . '", "' . $_REQUEST['NetworkUnmanagedDevConnect'] . '", "' . $_REQUEST['NetworkUnmanagedDevPort'] . '", "' . $dumbvar . '", "' . $ip . '")';
		$result = $db->query($sql);
	}

	if ($result == TRUE) {
		echo $pia_lang['BE_NET_Man_AddUn'];
		// Logging
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0033', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./networkSettings.php'>");
	} else {
		echo $pia_lang['BE_NET_Man_AddUn_Err'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0073', '', '');
	}
}

function updUnManagedDev() {
	global $db;
	global $pia_lang;

	if (($_REQUEST['NewNetworkUnmanagedDevName'] != "") && isset($_REQUEST['NewNetworkUnmanagedDevConnect']) && isset($_REQUEST['NetworkUnmanagedDevID'])) {
		$sql = 'UPDATE "network_dumb_dev" SET "dev_Name" = "' . $_REQUEST['NewNetworkUnmanagedDevName'] . '", "dev_Infrastructure" = "' . $_REQUEST['NewNetworkUnmanagedDevConnect'] . '", "dev_Infrastructure_port" = "' . $_REQUEST['NewNetworkUnmanagedDevPort'] . '" WHERE "id "="' . $_REQUEST['NetworkUnmanagedDevID'] . '"';
		$result = $db->query($sql);
	}
	if (($_REQUEST['NewNetworkUnmanagedDevName'] == "") && isset($_REQUEST['NewNetworkUnmanagedDevConnect']) && isset($_REQUEST['NetworkUnmanagedDevID'])) {
		$sql = 'UPDATE "network_dumb_dev" SET "dev_Infrastructure" = "' . $_REQUEST['NewNetworkUnmanagedDevConnect'] . '", "dev_Infrastructure_port" = "' . $_REQUEST['NewNetworkUnmanagedDevPort'] . '" WHERE "id"="' . $_REQUEST['NetworkUnmanagedDevID'] . '"';
		$result = $db->query($sql);
	}

	if ($result == TRUE) {
		echo $pia_lang['BE_NET_Man_UpdUn'];
		// Logging
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0034', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./networkSettings.php'>");
	} else {
		echo $pia_lang['BE_NET_Man_UpdUn_Err'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0074', '', '');
	}
}

function delUnManagedDev() {
	global $db;
	global $pia_lang;

	if (isset($_REQUEST['NetworkUnmanagedDevID'])) {
		$sql = 'DELETE FROM "network_dumb_dev" WHERE "id"="' . $_REQUEST['NetworkUnmanagedDevID'] . '"';
		$result = $db->query($sql);
	}

	if ($result == TRUE) {
		echo $pia_lang['BE_NET_Man_DelUn'];
		// Logging
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0035', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./networkSettings.php'>");
	} else {
		echo $pia_lang['BE_NET_Man_DelUn_Err'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_040', $_SERVER['REMOTE_ADDR'], 'LogStr_0075', '', '');
	}
}

//  End
?>

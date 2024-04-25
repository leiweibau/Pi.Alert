<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  network.php - Back module. Server side. Manage Devices
//------------------------------------------------------------------------------
//  leiweibau  2024        https://github.com/leiweibau     GNU GPLv3
//------------------------------------------------------------------------------

session_start();

if ($_SESSION["login"] != 1) {
	header('Location: ../../index.php');
	exit;
}

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
		echo '<li><a href="javascript:void(0)" onclick="setTextValue(\'txtNetworkNodeMac\',\'' . $func_res['dev_Name'] . '\')">' . $func_res['dev_Name'] . '/' . $func_res['dev_DeviceType'] . '</a></li>';
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
//  End
?>

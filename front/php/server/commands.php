<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  commands.php - Back module. Server side. FileSystem Operations
//------------------------------------------------------------------------------
//  leiweibau  2023        https://github.com/leiweibau     GNU GPLv3
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
	case 'PialertReboot':PialertReboot();
		break;
	case 'PialertShutdown':PialertShutdown();
		break;
	default:logServerConsole('Action: ' . $action);
		break;
	}
}

//  PiAlert Reboot
function PialertReboot() {
	global $pia_lang;

	pialert_logging('a_025', $_SERVER['REMOTE_ADDR'], 'LogStr_9993', '', '');
	echo $pia_lang['SysInfo_Gen_execute_command'];
	exec('sleep 5 && sudo /usr/sbin/shutdown -r 0', $output);
}

//  PiAlert Shutdown
function PialertShutdown() {
	global $pia_lang;

	pialert_logging('a_025', $_SERVER['REMOTE_ADDR'], 'LogStr_9994', '', '');
	echo $pia_lang['SysInfo_Gen_execute_command'];
	exec('sleep 5 && sudo /usr/sbin/shutdown -h 0', $output);
}

?>

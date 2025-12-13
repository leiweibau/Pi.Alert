<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  commands.php - Back module. Server side. FileSystem Operations
//------------------------------------------------------------------------------
//  leiweibau  2025+        https://github.com/leiweibau     GNU GPLv3
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
	case 'PialertReboot':PialertReboot();
		break;
	case 'PialertShutdown':PialertShutdown();
		break;
	default:logServerConsole('Action: ' . $action);
		break;
	}
}

function PialertReboot() {
	global $pia_lang;
	global $pia_lang_selected;

	pialert_logging('a_025', $_SERVER['REMOTE_ADDR'], 'LogStr_9993', '', '');

	$token = bin2hex(random_bytes(16));
	file_put_contents('../tmp/reboot_token', $token);

	echo $pia_lang['SysInfo_Gen_execute_command'];
	echo "<meta http-equiv='refresh' content='2; URL=./lib/static/reboot_".$pia_lang_selected.".html'>";

}

//  PiAlert Shutdown
function PialertShutdown() {
	global $pia_lang;
	global $pia_lang_selected;

	pialert_logging('a_025', $_SERVER['REMOTE_ADDR'], 'LogStr_9994', '', '');

	$token = bin2hex(random_bytes(16));
	file_put_contents('../tmp/shutdown_token', $token);

	echo $pia_lang['SysInfo_Gen_execute_command'];
	echo "<meta http-equiv='refresh' content='2; URL=./lib/static/shutdown_".$pia_lang_selected.".html'>";

}

?>

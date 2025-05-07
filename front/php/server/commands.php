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
	$php = '/usr/bin/php';
	$script = __DIR__ . '/run_reboot.php';
	$hash_needed = "e40454515f0b9fe503b5f7ae1af3d221";
	$hash_actual = md5_file($script);
	if ($hash_actual == $hash_needed) {
		echo $pia_lang['SysInfo_Gen_execute_command'];
		echo "<meta http-equiv='refresh' content='2; URL=./lib/static/reboot_".$pia_lang_selected.".html'>";
		flush();
		exec("nohup $php $script > /dev/null 2>&1 &");
	} else {
		echo 'MD5 hash mismatch';
	}
}

//  PiAlert Shutdown
function PialertShutdown() {
	global $pia_lang;
	global $pia_lang_selected;

	pialert_logging('a_025', $_SERVER['REMOTE_ADDR'], 'LogStr_9994', '', '');
	$php = '/usr/bin/php';
	$script = __DIR__ . '/run_shutdown.php';
	$hash_needed = "cfa6548081edf875120acd4b64d38572";
	$hash_actual = md5_file($script);
	if ($hash_actual == $hash_needed) {
		echo $pia_lang['SysInfo_Gen_execute_command'];
		echo "<meta http-equiv='refresh' content='2; URL=./lib/static/shutdown_".$pia_lang_selected.".html'>";
		flush();
		exec("nohup $php $script > /dev/null 2>&1 &");
	} else {
		echo 'MD5 hash mismatch';
	}
}

?>

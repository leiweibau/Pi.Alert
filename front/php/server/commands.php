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
	$hash_needed = "a900a0b42cf922fd0027504cd03711fe";
	$hash_actual = md5_file($script);
	if ($hash_actual == $hash_needed) {
		$token = bin2hex(random_bytes(16)); // Zufälliger Token
		file_put_contents('/tmp/reboot_token', $token); // Token speichern

		echo $pia_lang['SysInfo_Gen_execute_command'];
		echo "<meta http-equiv='refresh' content='2; URL=./lib/static/reboot_".$pia_lang_selected.".html'>";
		flush();
		exec("nohup $php $script $token > /dev/null 2>&1 &");
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
	$hash_needed = "9cac659a4a30b503a96cf652d09d9cb6";
	$hash_actual = md5_file($script);
	if ($hash_actual == $hash_needed) {
		$token = bin2hex(random_bytes(16)); // Zufälliger Token
		file_put_contents('/tmp/shutdown_token', $token); // Token speichern

		echo $pia_lang['SysInfo_Gen_execute_command'];
		echo "<meta http-equiv='refresh' content='2; URL=./lib/static/shutdown_".$pia_lang_selected.".html'>";
		flush();
		exec("nohup $php $script $token > /dev/null 2>&1 &");
	} else {
		echo 'MD5 hash mismatch';
	}
}

?>

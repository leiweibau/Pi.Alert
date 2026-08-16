<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  file.php - Back module. Server side. FileSystem Operations
//------------------------------------------------------------------------------
//  leiweibau  2023+        https://github.com/leiweibau     GNU GPLv3
//------------------------------------------------------------------------------

require_once __DIR__ . "/session.php";
pialert_start_session();
require_once __DIR__ . '/csrf.php';

if ($_SESSION["login"] != 1) {
	header('Location: ../../index.php');
	exit;
}

require 'timezone.php';
require 'db.php';
require 'auth.php';
require 'util.php';
require 'journal.php';
require_once __DIR__ . '/config_file.php';
require 'language_switch.php';
require '../templates/language/' . $pia_lang_selected . '.php';

// Action selector
// Set maximum execution time to 15 seconds
ini_set('max_execution_time', '30');

// Open DB
OpenDB();

pialert_dispatch_action([
    'getReportTotals', 'GetLogfiles', 'GetServerTime', 'GetUpdateStatus',
    'GetARPStatus', 'GetAutoBackupStatus', 'GetConfigFile'
], [
    'RestoreDBfromArchive', 'PurgeDBBackups', 'EnableDarkmode',
    'EnableOnlineHistoryGraph', 'SetAPIKey', 'LoginEnable', 'LoginDisable',
    'deleteAllNotifications', 'deleteAllNotificationsArchive', 'setTheme',
    'setLanguage', 'setArpTimer', 'setDeviceListCol', 'setListHeaderConfig',
    'RestoreConfigFile', 'BackupConfigFile', 'BackupDBtoArchive',
    'BackupDBtoCSV', 'SaveConfigFile', 'setFavIconURL', 'setPiholeURL',
    'ToggleImport', 'ToggleExtLogging', 'ToggleRogueDHCP', 'BlockDeviceMAC',
    'DeleteBlockDeviceMAC', 'BlockDeviceIP', 'DeleteBlockDeviceIP'
]);
// Action functions
if (isset($GLOBALS["pialert_request"]['action']) && !empty($GLOBALS["pialert_request"]['action'])) {
	$action = $GLOBALS["pialert_request"]['action'];
	switch ($action) {
	case 'RestoreDBfromArchive':RestoreDBfromArchive();
		break;
	case 'PurgeDBBackups':PurgeDBBackups();
		break;
	case 'EnableDarkmode':EnableDarkmode();
		break;
	case 'EnableOnlineHistoryGraph':EnableOnlineHistoryGraph();
		break;
	case 'SetAPIKey':SetAPIKey();
		break;
	case 'LoginEnable':LoginEnable();
		break;
	case 'LoginDisable':LoginDisable();
		break;
	case 'deleteAllNotifications':deleteAllNotifications();
		break;
	case 'deleteAllNotificationsArchive':deleteAllNotificationsArchive();
		break;
	case 'setTheme':setTheme();
		break;
	case 'setLanguage':setLanguage();
		break;
	case 'setArpTimer':setArpTimer();
		break;
	case 'setDeviceListCol':setDeviceListCol();
		break;
	case 'setListHeaderConfig':setListHeaderConfig();
		break;
	case 'RestoreConfigFile':RestoreConfigFile();
		break;
	case 'BackupConfigFile':BackupConfigFile();
		break;
	case 'BackupDBtoArchive':BackupDBtoArchive();
		break;
	case 'BackupDBtoCSV':BackupDBtoCSV();
		break;
	case 'SaveConfigFile':SaveConfigFile();
		break;
	case 'getReportTotals':getReportTotals();
		break;
	case 'setFavIconURL':setFavIconURL();
		break;
	case 'setPiholeURL':setPiholeURL();
		break;
	case 'GetLogfiles':GetLogfiles();
		break;
	case 'GetServerTime':GetServerTime();
		break;
	case 'GetUpdateStatus':GetUpdateStatus();
		break;
	case 'GetARPStatus':GetARPStatus();
		break;
	case 'GetAutoBackupStatus':GetAutoBackupStatus();
		break;
	case 'ToggleImport':ToggleImport();
		break;
	case 'ToggleExtLogging':ToggleExtLogging();
		break;
	case 'ToggleRogueDHCP':ToggleRogueDHCP();
		break;
	case 'BlockDeviceMAC':BlockDeviceMAC();
		break;
	case 'DeleteBlockDeviceMAC':DeleteBlockDeviceMAC();
		break;
	case 'BlockDeviceIP':BlockDeviceIP();
		break;
	case 'DeleteBlockDeviceIP':DeleteBlockDeviceIP();
		break;
	case 'GetConfigFile':GetConfigFile();
		break;
	default:logServerConsole('Action: ' . $action);
		break;
	}
}

function GetConfigFile() {
    $configFile = __DIR__ . '/../../../config/pialert.conf';

    if (!file_exists($configFile) || !is_readable($configFile)) {
        http_response_code(500);
        echo 'ERROR: Config file not accessible';
        exit;
    }

    try {
        $content = pialert_mask_config_for_editor($configFile);
    } catch (Throwable $exception) {
        http_response_code(500);
        echo 'ERROR: Unable to read config file';
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo $content;
    exit;
}

function DeleteBlockDeviceIP() {
	global $pia_lang;
    $configfile = '../../../config/pialert.conf';

    if (!isset($GLOBALS["pialert_request"]['ip'])) {
        echo $pia_lang['BE_Dev_Ignore_a'];
        return;
    }

    $removeIP = trim($GLOBALS["pialert_request"]['ip']);

    if ($removeIP === '') {
        echo $pia_lang['BE_Dev_Ignore_b'];
        return;
    }

    if (!file_exists($configfile) || !is_readable($configfile)) {
        echo $pia_lang['BE_Dev_Ignore_c'];
        return;
    }

    $configContent = file_get_contents($configfile);

    if (!preg_match('/^(\s*IP_IGNORE_LIST)(\s*=\s*)\[(.*?)\]\s*$/m', $configContent, $matches)) {
        echo $pia_lang['BE_Dev_Ignore_d'];
        return;
    }

    $ipListRaw = $matches[3];
    $ipArray = array_filter(array_map(function($item) {
        return trim(str_replace("'", '', $item));
    }, explode(',', $ipListRaw)));

    $updatedArray = array_filter($ipArray, function($ip) use ($removeIP) {
        return $ip !== $removeIP;
    });

    $newListLine = $matches[1] . $matches[2] .
        (empty($updatedArray) ? '[]' : "['" . implode("','", $updatedArray) . "']");

    $newConfigContent = preg_replace(
        '/^\s*IP_IGNORE_LIST\s*=\s*\[.*?\]\s*$/m',
        $newListLine,
        $configContent
    );

    if (!is_writable($configfile)) {
        echo $pia_lang['BE_Dev_Ignore_e'];
        return;
    }

    validate_and_replace_pialert_config($configfile, $newConfigContent);
    echo $pia_lang['BE_Dev_Ignore_f'];
	// Logging
	pialert_logging('a_000', $_SERVER['REMOTE_ADDR'], 'LogStr_9999', '1', $removeIP.' '.$pia_lang['BE_Files_Ignore_a']);
	echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php'>";
}

function BlockDeviceIP() {
	global $pia_lang;
    $configfile = '../../../config/pialert.conf';

    if (!isset($GLOBALS["pialert_request"]['ip'])) {
        echo $pia_lang['BE_Dev_Ignore_a'] ;
        return;
    }

    $newIP = trim($GLOBALS["pialert_request"]['ip']);

    if ($newIP === '') {
        echo $pia_lang['BE_Dev_Ignore_b'];
        return;
    }

    if (!file_exists($configfile) || !is_readable($configfile)) {
        echo $pia_lang['BE_Dev_Ignore_c'];
        return;
    }

    $configContent = file_get_contents($configfile);

    if (!preg_match('/^(\s*IP_IGNORE_LIST)(\s*=\s*)\[(.*?)\]\s*$/m', $configContent, $matches)) {
        echo $pia_lang['BE_Dev_Ignore_d'];
        return;
    }

    $ipListRaw = $matches[3];
    $ipArray = array_filter(array_map(function($item) {
        return trim(str_replace("'", '', $item));
    }, explode(',', $ipListRaw)));

    if (!in_array($newIP, $ipArray)) {
        $ipArray[] = $newIP;
    }

    $newListLine = $matches[1] . $matches[2] . "['" . implode("','", $ipArray) . "']";

    $newConfigContent = preg_replace(
        '/^\s*IP_IGNORE_LIST\s*=\s*\[.*?\]\s*$/m',
        $newListLine,
        $configContent
    );

    if (!is_writable($configfile)) {
        echo $pia_lang['BE_Dev_Ignore_e'];
        return;
    }

    validate_and_replace_pialert_config($configfile, $newConfigContent);
    echo $pia_lang['BE_Dev_Ignore_g'];
	// Logging
	pialert_logging('a_000', $_SERVER['REMOTE_ADDR'], 'LogStr_9999', '1', $newIP.' '.$pia_lang['BE_Files_Ignore_b']);
}

function DeleteBlockDeviceMAC() {
	global $pia_lang;
    $configfile = '../../../config/pialert.conf';

    if (!isset($GLOBALS["pialert_request"]['mac'])) {
        echo $pia_lang['BE_Dev_Ignore_h'];
        return;
    }

    $removeMac = strtolower(trim($GLOBALS["pialert_request"]['mac']));

    if (!file_exists($configfile) || !is_readable($configfile)) {
        echo $pia_lang['BE_Dev_Ignore_c'];
        return;
    }

    $configContent = file_get_contents($configfile);

    // Zeile mit MAC_IGNORE_LIST extrahieren
    if (!preg_match('/^(\s*MAC_IGNORE_LIST)(\s*=\s*)\[(.*?)\]\s*$/m', $configContent, $matches)) {
        echo $pia_lang['BE_Dev_Ignore_i'];
        return;
    }

    $macListRaw = $matches[3]; // Inhalt zwischen den Klammern

    // Liste bereinigen und als Array aufbereiten
    $macArray = array_filter(array_map(function($item) {
        return strtolower(trim(str_replace("'", '', $item)));
    }, explode(',', $macListRaw)));

    // MAC entfernen, wenn vorhanden
    $updatedArray = array_filter($macArray, function($mac) use ($removeMac) {
        return $mac !== $removeMac;
    });

    // Neue Zeile erzeugen
    $newListLine = $matches[1] . $matches[2] .
        (empty($updatedArray) ? '[]' : "['" . implode("','", $updatedArray) . "']");

    // Alte Zeile ersetzen
    $newConfigContent = preg_replace(
        '/^\s*MAC_IGNORE_LIST\s*=\s*\[.*?\]\s*$/m',
        $newListLine,
        $configContent
    );

    // Datei schreiben
    if (!is_writable($configfile)) {
        echo $pia_lang['BE_Dev_Ignore_e'];
        return;
    }

    validate_and_replace_pialert_config($configfile, $newConfigContent);
    echo $pia_lang['BE_Dev_Ignore_j'];

	// Logging
	pialert_logging('a_000', $_SERVER['REMOTE_ADDR'], 'LogStr_9999', '1', $removeMac.' '.$pia_lang['BE_Files_Ignore_a']);
	echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php'>";
}

function BlockDeviceMAC() {
	global $pia_lang;
	$configfile = '../../../config/pialert.conf';

    if (!isset($GLOBALS["pialert_request"]['mac'])) {
        echo $pia_lang['BE_Dev_Ignore_h'];
        return;
    }

    $newMac = strtolower(trim($GLOBALS["pialert_request"]['mac']));

    if (!file_exists($configfile) || !is_readable($configfile)) {
        echo $pia_lang['BE_Dev_Ignore_c'];
        return;
    }

    $configContent = file_get_contents($configfile);

    // Original-Zeile parsen: Key, Gleichheitszeichen mit Leerzeichen, Inhalt
    if (!preg_match('/^(\s*MAC_IGNORE_LIST)(\s*=\s*)\[(.*?)\]\s*$/m', $configContent, $matches)) {
        echo $pia_lang['BE_Dev_Ignore_i'];
        return;
    }

    $macListRaw = $matches[3]; // Inhalt zwischen den Klammern

    // In Array umwandeln
    $macArray = array_filter(array_map(function($item) {
        return strtolower(trim(str_replace("'", '', $item)));
    }, explode(',', $macListRaw)));

    // Neuen MAC hinzufügen, falls nicht vorhanden
    if (!in_array($newMac, $macArray)) {
        $macArray[] = $newMac;
    }

    // Neue MAC_IGNORE_LIST-Zeile im ursprünglichen Format bauen
    $newListLine = $matches[1] . $matches[2] . "['" . implode("','", $macArray) . "']";

    // Ersetzen der alten Zeile im Dateitext
    $newConfigContent = preg_replace(
        '/^\s*MAC_IGNORE_LIST\s*=\s*\[.*?\]\s*$/m',
        $newListLine,
        $configContent
    );

    // Schreiben in Datei
    if (!is_writable($configfile)) {
        echo $pia_lang['BE_Dev_Ignore_e'];
        return;
    }

    validate_and_replace_pialert_config($configfile, $newConfigContent);
    echo $pia_lang['BE_Dev_Ignore_k'];

	// Logging
	pialert_logging('a_000', $_SERVER['REMOTE_ADDR'], 'LogStr_9999', '1', $newMac.' '.$pia_lang['BE_Files_Ignore_b']);
}

function GetAutoBackupStatus() {
	global $pia_lang;
	if (file_exists("../../../back/.backup")) {$result = array($pia_lang['BE_Files_autobkp_pending']);} else {$result = array($pia_lang['BE_Files_autobkp_pause']);}
	//Count db backups
	$backupdir = "../../../db";
	$backupfiles = glob($backupdir . "/pialertdb_20*.zip");
	$result[] = count($backupfiles);
	//Calc Backup size
	$backupfile_size = 0;
	foreach ($backupfiles as $file) {
		$backupfile_size = $backupfile_size + filesize($file);
	}
	//Count config backups
	$backupdir = "../../../config";
	$backupfiles = glob($backupdir . "/pialert-20*.bak");
	$result[] = count($backupfiles);

	$result[] = number_format(($backupfile_size / 1000000), 2, ",", ".") . ' MB';
	echo json_encode($result);
}

function GetARPStatus() {
	global $pia_lang;
	if (file_exists("../../../back/.scanning")) {$result = array('');} else {$result = array($pia_lang['MT_arpscancout_norun']);}
	echo json_encode($result);
}

function GetUpdateStatus() {
	$updatenotification = '../../auto_Update.info';
	if (file_exists($updatenotification)) {
		$answer[0] = "i";
		echo (json_encode($answer));
	} else {
		$answer[0] = "";
		echo (json_encode($answer));
	}
}

function GetServerTime() {
	echo date("Y,n,j,G,i,s");
}

// Read logfiles --------------------------------------------------------------
function GetLogfiles() {
	global $pia_lang;

	$logDirectory = dirname(__DIR__, 3) . '/log';
	$logfiles = array(
		$logDirectory . '/pialert.1.log',
		$logDirectory . '/pialert.IP.log',
		$logDirectory . '/pialert.vendors.log',
		$logDirectory . '/pialert.cleanup.log',
		$logDirectory . '/pialert.webservices.log',
		$logDirectory . '/pialert.speedtest.log',
		$logDirectory . '/pialert.nmap.log'
	);
	$logmessages = array(
		$pia_lang['MT_Tools_Logviewer_Scan_empty'],
		$pia_lang['MT_Tools_Logviewer_IPLog_empty'],
		'',
		$pia_lang['MT_Tools_Logviewer_Cleanup_empty'],
		$pia_lang['MT_Tools_Logviewer_WebServices_empty'],
		'',
		$pia_lang['MT_Tools_Logviewer_Nmap_empty']
	);

	$logs = array();
	foreach ($logfiles as $index => $logfile) {
		$file = file_get_contents($logfile, true);
		$logs[] = $file === false || $file === '' ? $logmessages[$index] : $file;
	}

	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode($logs);
}



//  Save Config
function SaveConfigFile() {
    global $pia_lang;
    global $db;

    $configfile = __DIR__ . '/../../../config/pialert.conf';
    $laststate = __DIR__ . '/../../../config/pialert-prev.bak';
    $content = isset($GLOBALS['pialert_request']['configfile'])
        ? $GLOBALS['pialert_request']['configfile'] : null;

    if (!is_string($content) || strlen($content) > PIALERT_CONFIG_MAX_BYTES) {
        http_response_code(400);
        pialert_logging('a_000', $_SERVER['REMOTE_ADDR'], 'LogStr_9998', '1', '');
        echo isset($pia_lang['BE_Dev_ConfEditor_Invalid'])
            ? $pia_lang['BE_Dev_ConfEditor_Invalid'] : 'Invalid configuration';
        return;
    }

    $lockHandle = null;
    try {
        $lockHandle = pialert_acquire_config_lock($configfile);
        pialert_create_verified_config_backup($configfile, $laststate);
    } catch (Throwable $exception) {
        pialert_release_config_lock($lockHandle);
        http_response_code(500);
        pialert_logging('a_000', $_SERVER['REMOTE_ADDR'], 'LogStr_9998', '1', '');
        echo $pia_lang['BE_Dev_ConfEditor_CopError'];
        return;
    }

    try {
        $prepared = pialert_prepare_editor_candidate(
            $content, $laststate, $configfile);
        validate_and_replace_pialert_config(
            $configfile, $prepared['content'], false, $lockHandle);

        if (!empty($prepared['metadata']['password_changed'])) {
            pialert_revoke_all_remember_tokens($db);
        }
    } catch (InvalidArgumentException $exception) {
        http_response_code(400);
        pialert_logging('a_000', $_SERVER['REMOTE_ADDR'], 'LogStr_9998', '1', '');
        echo isset($pia_lang['BE_Dev_ConfEditor_Invalid'])
            ? $pia_lang['BE_Dev_ConfEditor_Invalid'] : 'Invalid configuration';
        return;
    } catch (Throwable $exception) {
        http_response_code(500);
        pialert_logging('a_000', $_SERVER['REMOTE_ADDR'], 'LogStr_9998', '1', '');
        echo isset($pia_lang['BE_Dev_ConfEditor_SaveError'])
            ? $pia_lang['BE_Dev_ConfEditor_SaveError'] : 'Unable to save configuration';
        return;
    } finally {
        pialert_release_config_lock($lockHandle);
    }

    echo $pia_lang['BE_Dev_ConfEditor_CopOkay'];
    pialert_logging('a_000', $_SERVER['REMOTE_ADDR'], 'LogStr_9999', '1', '');
    echo "<meta http-equiv='refresh' content='2; URL=./index.php'>";
}

//  Backup DB to Archiv
function BackupDBtoArchive() {

	$db_file_path = '../../../db';
	$db_file_name_org = 'pialert.db';
	$dbtools_file_name_org = 'pialert_tools.db';
	$db_file_path_temp = '../../../db/temp';

	$db_file_org_full = $db_file_path . '/' . $db_file_name_org; # ../../../db/pialert.db
	$db_file_new_full = $db_file_path_temp . '/' . $db_file_name_org; # ../../../db/temp/pialert.db
	$dbtools_file_org_full = $db_file_path . '/' . $dbtools_file_name_org; # ../../../db/pialert.db
	$dbtools_file_new_full = $db_file_path_temp . '/' . $dbtools_file_name_org; # ../../../db/temp/pialert.db

	$Pia_Archive_Name = 'pialertdb_' . date("Ymd_His") . '.zip';
	$Pia_Archive_Path = '../../../db/';

	global $pia_lang;

	// Check if DB has open transactions
	if ((filesize($db_file_org_full . '-wal') != "0") && (filesize($dbtools_file_org_full . '-wal') != "0")) {
		//DEBUG
		//echo filesize($db_file_org_full.'-shm').'-'.filesize($db_file_org_full.'-wal').' - ';
		echo $pia_lang['BE_Dev_Backup_WALError'];exit;
	}

	// copy database
	exec('sqlite3 "' . $db_file_org_full . '" ".backup ' . $db_file_new_full . '"', $output);
	exec('sqlite3 "' . $dbtools_file_org_full . '" ".backup ' . $dbtools_file_new_full . '"', $output);

	if (file_exists($db_file_new_full) && file_exists($dbtools_file_new_full)) {
		// Integrity Check if file copy exists
		// $sql1 = "PRAGMA integrity_check";
		// $sql2 = "PRAGMA foreign_key_check";
		// exec('sqlite3 ' . $db_file_temp_full . ' "' . $sql1 . '"', $output_a);
		// exec('sqlite3 ' . $db_file_temp_full . ' "' . $sql2 . '"', $output_b);

		// Create archive with actual date
		exec('zip -j ' . $Pia_Archive_Path . $Pia_Archive_Name . ' ' . $db_file_new_full . ' ' . $dbtools_file_org_full, $output);
		// check if archive exists
		if (file_exists($Pia_Archive_Path . $Pia_Archive_Name) && filesize($Pia_Archive_Path . $Pia_Archive_Name) > 0) {
			// if archive exists
			echo $pia_lang['BE_Dev_Backup_okay'] . ': (' . $Pia_Archive_Name . ')';
			unlink($db_file_new_full);
			echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=3'>");
		} else {
			// if archive not exists
			echo $pia_lang['BE_Dev_Backup_Failed'] . ' / Integrity Checked (pialert-latestbackup.db)';
		}
	} else {
		// File does not exists
		echo $pia_lang['BE_Dev_Backup_CopError'];exit;
	}
	// Logging
	pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0011', '', '');
}

//  Backup DB to CSV
function BackupDBtoCSV() {
	global $pia_lang;

	$Pia_Archive_Name = 'pialertcsv.zip';
	$Pia_Archive_Path = '../../../db/';

	$db_file_path = '../../../db';
	$db_file_name_org = 'pialert.db';
	$db_file_path_temp = '../../../db/temp';

	$db_file_org_full = $db_file_path . '/' . $db_file_name_org; # ../../../db/pialert.db
	$csv_file_devices = $db_file_path_temp . '/devices.csv'; # ../../../db/temp/devices.csv
	$csv_file_services = $db_file_path_temp . '/services.csv'; # ../../../db/temp/services.csv
	$csv_file_icmphosts = $db_file_path_temp . '/icmphosts.csv'; # ../../../db/temp/icmphosts.csv

	// delete old archive
	unlink($Pia_Archive_Path . $Pia_Archive_Name);

	exec('sqlite3 -header -csv "' . $db_file_org_full . '" "select * from devices;" > ' . $csv_file_devices, $output);
	exec('sqlite3 -header -csv "' . $db_file_org_full . '" "select * from services;" > ' . $csv_file_services, $output);
	exec('sqlite3 -header -csv "' . $db_file_org_full . '" "select * from ICMP_Mon;" > ' . $csv_file_icmphosts, $output);

	if (!file_exists($csv_file_devices) || !file_exists($csv_file_services) || !file_exists($csv_file_icmphosts)) {
		echo $pia_lang['BE_Dev_BackupCSV_FailedExport'];
		// delete csv files
		unlink($csv_file_devices);
		unlink($csv_file_services);
		unlink($csv_file_icmphosts);
		exit;
	}

	// create new archive
	exec('zip -j ' . $Pia_Archive_Path . $Pia_Archive_Name . ' ' . $db_file_path_temp . '/*.csv', $output);
	// delete csv files
	unlink($csv_file_devices);
	unlink($csv_file_services);
	unlink($csv_file_icmphosts);

	if (!file_exists($Pia_Archive_Path . $Pia_Archive_Name)) {
		echo $pia_lang['BE_Dev_BackupCSV_FailedZip'];
		exit;
	}

	echo $pia_lang['BE_Dev_BackupCSV_okay'];
	// Logging
	pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0036', '', '');
}

//  Restore DB from Archiv
function RestoreDBfromArchive() {
	// prepare fast Backup
	$file = '../../../db/pialert.db';
	
	global $pia_lang;

	$Pia_Archive_Path = '../../../db/';
	exec('/bin/ls -Art ' . $Pia_Archive_Path . '*.zip | /bin/tail -n 1 | /usr/bin/xargs -n1 /bin/unzip -o -d ../../../db/', $output);
	// check if the pialert.db exists
	if (file_exists($file)) {
		echo $pia_lang['BE_Dev_Restore_okay'];
		// unlink($oldfile);
		echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=3'>";
	} else {
		echo $pia_lang['BE_Dev_Restore_Failed'];
	}
	// }
}

//  Enable Login
function LoginEnable() {
	global $pia_lang;
	global $db;

	pialert_revoke_all_remember_tokens($db);
	$sessionCookieName = session_name();
	$_SESSION = array();
	session_destroy();
	pialert_delete_auth_cookie($sessionCookieName);
	exec("../../../back/pialert-cli set_login", $output);
	pialert_logging("a_005", $_SERVER["REMOTE_ADDR"], "LogStr_0050", "", "");
	echo $pia_lang["BE_Dev_Login_enabled"];
	echo "<meta http-equiv='refresh' content='1; ./index.php'>";
}

//  Disable Login
function LoginDisable() {
	global $pia_lang;
	global $db;

	pialert_logging("a_005", $_SERVER["REMOTE_ADDR"], "LogStr_0051", "", "");
	pialert_revoke_all_remember_tokens($db);
	$sessionCookieName = session_name();
	$_SESSION = array();
	session_destroy();
	pialert_delete_auth_cookie($sessionCookieName);
	exec("../../../back/pialert-cli unset_login", $output);
	echo $pia_lang["BE_Dev_Login_disabled"];
	echo "<meta http-equiv='refresh' content='1; ./index.php'>";
}

//  Set Device List Columns
function setDeviceListCol() {
	global $pia_lang;

	$param_map = [
	    'connectiontype' => 'Set_ConnectionType',
	    'favorite'       => 'Set_Favorites',
	    'group'          => 'Set_Group',
	    'owner'          => 'Set_Owner',
	    'type'           => 'Set_Type',
	    'firstsess'      => 'Set_First_Session',
	    'lastsess'       => 'Set_Last_Session',
	    'lastip'         => 'Set_LastIP',
	    'mactype'        => 'Set_MACType',
	    'macaddress'     => 'Set_MACAddress',
	    'macvendor'      => 'Set_MACVendor',
	    'location'       => 'Set_Location',
	    'wakeonlan'      => 'Set_WakeOnLAN'
	];

	foreach ($param_map as $request_key => $var_name) {
	    if (!isset($GLOBALS["pialert_request"][$request_key]) || !in_array($GLOBALS["pialert_request"][$request_key], ['0', '1'], true)) {
	        exit("Error. Wrong variable value for $request_key!");
	    }
	    $$var_name = $GLOBALS["pialert_request"][$request_key]; // dynamische Variable
	}

	echo $pia_lang['BE_Dev_DevListCol_noti_text'];
	$config_array = array('ConnectionType' => $Set_ConnectionType, 'Favorites' => $Set_Favorites, 'Group' => $Set_Group, 'Owner' => $Set_Owner, 'Type' => $Set_Type, 'FirstSession' => $Set_First_Session, 'LastSession' => $Set_Last_Session, 'LastIP' => $Set_LastIP, 'MACType' => $Set_MACType, 'MACAddress' => $Set_MACAddress, 'MACVendor' => $Set_MACVendor, 'Location' => $Set_Location, 'WakeOnLAN' => $Set_WakeOnLAN);
	$DevListCol_file = '../../../config/setting_devicelist';
	$DevListCol_new = fopen($DevListCol_file, 'w');
	fwrite($DevListCol_new, json_encode($config_array));
	fclose($DevListCol_new);
	echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>";
	// Logging
	pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0052', '', '');
}

//  Set Device List Columns
function setListHeaderConfig() {
	global $pia_lang;

	$valid_keys = [
	    'devices' => ['hc_devall' => 'all', 'hc_devcon' => 'con', 'hc_devfav' => 'fav', 'hc_devdnw' => 'dnw', 'hc_devarc' => 'arc', 'hc_devnew' => 'new'],
	    'icmp' =>    ['hc_icmpall' => 'all', 'hc_icmpcon' => 'con', 'hc_icmpfav' => 'fav', 'hc_icmpdnw' => 'dnw', 'hc_icmparc' => 'arc'],
	    'presence' => ['hc_presall' => 'all', 'hc_prescon' => 'con', 'hc_presfav' => 'fav', 'hc_presdnw' => 'dnw', 'hc_presarc' => 'arc', 'hc_presnew' => 'new']
	];

	$list = [];
	foreach ($valid_keys as $category => $keys) {
	    foreach ($keys as $request_key => $subkey) {
	        if (!isset($GLOBALS["pialert_request"][$request_key]) || !in_array($GLOBALS["pialert_request"][$request_key], ['0', '1'], true)) {
	            exit("Error. Wrong variable value for $request_key!");
	        }
	        $list[$category][$subkey] = (int) $GLOBALS["pialert_request"][$request_key];
	    }
	}

	echo $pia_lang['BE_Files_HeaderConfig_noti_text'];
	$ListHeaderConfig_file = '../../../config/setting_listheaders';
	$ListHeaderConfig_new = fopen($ListHeaderConfig_file, 'w');
	fwrite($ListHeaderConfig_new, json_encode($list));
	fclose($ListHeaderConfig_new);
	echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>";
	// Logging
	pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0076', '', '');
}

//  Purge Backups
function PurgeDBBackups() {
	global $pia_lang;

	// Clean DB Backups
	$Pia_Archive_Path = '../../../db';
	$Pia_Backupfiles = array();
	$files = array_diff(scandir($Pia_Archive_Path, SCANDIR_SORT_DESCENDING), array('.', '..', 'pialert.db', 'temp', 'GeoLite2-Country.mmdb', 'pialert.db-shm', 'pialert.db-wal', 'pialertcsv.zip', 'user_vendors.txt', 'pialert_tools.db', 'pialert_tools.db-shm', 'pialert_tools.db-wal'));
    foreach ($files as &$item) {
        $item = $Pia_Archive_Path . '/' . $item;
        array_push($Pia_Backupfiles, $item);
    }
	if (sizeof($Pia_Backupfiles) > 3) {
		rsort($Pia_Backupfiles);
		unset($Pia_Backupfiles[0], $Pia_Backupfiles[1], $Pia_Backupfiles[2]);
		$Pia_Backupfiles_Purge = array_values($Pia_Backupfiles);
		for ($i = 0; $i < sizeof($Pia_Backupfiles_Purge); $i++) {
			unlink($Pia_Backupfiles_Purge[$i]);
		}
	}
	// Clean Config Backups
	unset($Pia_Backupfiles);
	$Pia_Archive_Path = '../../../config';
	// Only timestamped backups are purge candidates. Lock files, temporary
	// atomic-write files, examples, settings and active configurations are not.
	$Pia_Backupfiles = glob($Pia_Archive_Path . '/pialert-20*.bak') ?: array();
	if (sizeof($Pia_Backupfiles) > 3) {
		rsort($Pia_Backupfiles);
		unset($Pia_Backupfiles[0], $Pia_Backupfiles[1], $Pia_Backupfiles[2]);
		$Pia_Backupfiles_Purge = array_values($Pia_Backupfiles);
		for ($i = 0; $i < sizeof($Pia_Backupfiles_Purge); $i++) {
			unlink($Pia_Backupfiles_Purge[$i]);
		}
	}
	// Logging
	pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0013', '', '');

	echo $pia_lang['BE_Dev_DBTools_Purge'];
	echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=3'>";
}

//  Toggle Dark/Light Themes
function EnableDarkmode() {
	$file = '../../../config/setting_darkmode';
	global $pia_lang;

	if (file_exists($file)) {
		echo $pia_lang['BE_Dev_darkmode_disabled'];
		unlink($file);
		// Logging
		pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0055', '', '');

		echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>";
	} else {
		echo $pia_lang['BE_Dev_darkmode_enabled'];
		$darkmode = fopen($file, 'w');
		// Logging
		pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0056', '', '');
		echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>";
	}
}

//  Toggle History Graph Themes
function EnableOnlineHistoryGraph() {
	$file = '../../../config/setting_noonlinehistorygraph';
	global $pia_lang;

	if (file_exists($file)) {
		echo $pia_lang['BE_Dev_onlinehistorygraph_enabled'];
		unlink($file);
		// Logging
		pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0058', '', '');
		echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>";
	} else {
		echo $pia_lang['BE_Dev_onlinehistorygraph_disabled'];
		$history = fopen($file, 'w');
		fclose($history);
		// Logging
		pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0057', '', '');
		echo "<meta http-equiv='refresh'content='2; URL=./maintenance.php?tab=4'>";
	}
}

//  Set API-Key
function SetAPIKey() {
	//$file = '../../../db/setting_noonlinehistorygraph';
	global $pia_lang;

	exec('../../../back/pialert-cli set_apikey', $output);
	// Logging
	pialert_logging('a_070', $_SERVER['REMOTE_ADDR'], 'LogStr_0700', '', '');
	echo $pia_lang['BE_Dev_setapikey'];
	echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>";
}

//  Set Theme
function setTheme() {
	global $pia_lang;

	$installed_skins = array('skin-black-light',
		'skin-black',
		'skin-blue-light',
		'skin-blue',
		'skin-green-light',
		'skin-green',
		'skin-purple-light',
		'skin-purple',
		'skin-red-light',
		'skin-red',
		'skin-yellow-light',
		'skin-yellow');

	$installed_themes = array('leiweibau_dark',
		'leiweibau_light');

	if (isset($GLOBALS["pialert_request"]['SkinSelection'])) {
		$skin_set_dir = '../../../config/';
		// echo "Enter Level 1";
		$skin_selector = htmlspecialchars($GLOBALS["pialert_request"]['SkinSelection']);
		if (in_array($skin_selector, $installed_skins)) {
			// lösche alle vorherigen skins
			foreach ($installed_skins as $file) {
				unlink($skin_set_dir . 'setting_' . $file);
			}
			// lösche alle vorherigen themes
			foreach ($installed_themes as $file) {
				unlink($skin_set_dir . 'setting_theme_' . $file);
			}
			foreach ($installed_skins as $file) {
				if (file_exists($skin_set_dir . 'setting_' . $file)) {
					$skin_error = True;
					break;
				} else {
					$skin_error = False;
				}
			}
			if ($skin_error == False) {
				$testskin = fopen($skin_set_dir . 'setting_' . $skin_selector, 'w');
				echo $pia_lang['BE_Dev_Theme_set'] . ': ' . $GLOBALS["pialert_request"]['SkinSelection'];
				echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>";
			} else {
				echo $pia_lang['BE_Dev_Theme_notset'];
				echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>";
			}
		} elseif (in_array($skin_selector, $installed_themes)) {
			// lösche alle vorherigen skins
			foreach ($installed_skins as $file) {
				unlink($skin_set_dir . 'setting_' . $file);
			}
			// lösche alle vorherigen themes
			foreach ($installed_themes as $file) {
				unlink($skin_set_dir . 'setting_theme_' . $file);
			}
			foreach ($installed_skins as $file) {
				if (file_exists($skin_set_dir . 'setting_theme_' . $file)) {
					$skin_error = True;
					break;
				} else {
					$skin_error = False;
				}
			}
			if ($skin_error == False) {
				$testskin = fopen($skin_set_dir . 'setting_theme_' . $skin_selector, 'w');
				echo $pia_lang['BE_Dev_Theme_set'] . ': ' . $GLOBALS["pialert_request"]['SkinSelection'];
				echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>";
			} else {
				echo $pia_lang['BE_Dev_Theme_notset'];
				echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>";
			}
		} else {echo $pia_lang['BE_Dev_Theme_invalid'];}
	}
	// Logging
	pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0053', '', $skin_selector);
}

//  Set Language
function setLanguage() {
	global $pia_lang;

	$pia_installed_langs = array('en_us',
		'de_de',
		'es_es',
		'fr_fr',
		'it_it',
		'pl_pl',
		'cz_cs',
		'dk_da',
		'nl_nl',
	    'fi_fi',
	    'lt_lt',
	    'no_no',
	    'ru_ru',
	    'se_sv',
	    'ua_uk');

	if (isset($GLOBALS["pialert_request"]['LangSelection'])) {
		$pia_lang_set_dir = '../../../config/';
		$pia_lang_selector = htmlspecialchars($GLOBALS["pialert_request"]['LangSelection']);
		if (in_array($pia_lang_selector, $pia_installed_langs)) {
			foreach ($pia_installed_langs as $file) {
				unlink($pia_lang_set_dir . 'setting_language_' . $file);
			}
			foreach ($pia_installed_langs as $file) {
				if (file_exists($pia_lang_set_dir . 'setting_language_' . $file)) {
					$pia_lang_error = True;
					break;
				} else {
					$pia_lang_error = False;
				}
			}
			if ($pia_lang_error == False) {
				$testlang = fopen($pia_lang_set_dir . 'setting_language_' . $pia_lang_selector, 'w');
				echo $pia_lang['BE_Dev_Language_set'] . ': ' . $GLOBALS["pialert_request"]['LangSelection'];
				echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>";
			} else {
				echo $pia_lang['BE_Dev_Language_notset'];
				echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>";
			}
		} else {echo $pia_lang['BE_Dev_Language_invalid'];}
	}
	// Logging
	pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0054', '', $pia_lang_selector);
}

//  Set Timer
function setArpTimer() {
	global $pia_lang;

	if (isset($GLOBALS["pialert_request"]['ArpTimer'])) {
		#$pia_lang_set_dir = '../../../config/';
		$file = '../../../config/setting_stoppialert';
		if (file_exists($file)) {
			echo $pia_lang['BE_Dev_Arpscan_enabled'];
			// Logging
			pialert_logging('a_002', $_SERVER['REMOTE_ADDR'], 'LogStr_0510', '', '');
			exec('../../../back/pialert-cli enable_scan', $output);
			echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php'>";
		} else {
			if (is_numeric($GLOBALS["pialert_request"]['ArpTimer'])) {
				// Logging
				pialert_logging('a_002', $_SERVER['REMOTE_ADDR'], 'LogStr_0511', '', $GLOBALS["pialert_request"]['ArpTimer'] . ' min');
				exec('../../../back/pialert-cli disable_scan ' . $GLOBALS["pialert_request"]['ArpTimer'], $output);
			} else {
				// Logging
				pialert_logging('a_002', $_SERVER['REMOTE_ADDR'], 'LogStr_0512', '', '');
				exec('../../../back/pialert-cli disable_scan', $output);
			}
			echo $pia_lang['BE_Dev_Arpscan_disabled'];
			echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php'>";
		}
	}
}

//  Restore Config File
function RestoreConfigFile() {
    global $pia_lang;

    $file = __DIR__ . '/../../../config/pialert.conf';
    $laststate = __DIR__ . '/../../../config/pialert-prev.bak';
    $lockHandle = null;

    try {
        $lockHandle = pialert_acquire_config_lock($file);
        $content = file_get_contents($laststate);
        if ($content === false) {
            throw new RuntimeException('Unable to read configuration backup');
        }
        validate_and_replace_pialert_config($file, $content, true, $lockHandle);
        echo $pia_lang['BE_Dev_ConfEditor_RestoreOkay'];
    } catch (Throwable $exception) {
        http_response_code(500);
        echo $pia_lang['BE_Dev_ConfEditor_RestoreError'];
    } finally {
        pialert_release_config_lock($lockHandle);
    }

    pialert_logging('a_000', $_SERVER['REMOTE_ADDR'], 'LogStr_0006', '1', '');
    echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php'>";
}

//  Backup Config File
function BackupConfigFile() {
    global $pia_lang;

    $file = __DIR__ . '/../../../config/pialert.conf';
    $newfile = __DIR__ . '/../../../config/pialert-' . date('Ymd_His') . '.bak';
    $laststate = __DIR__ . '/../../../config/pialert-prev.bak';
    $lockHandle = null;

    try {
        $lockHandle = pialert_acquire_config_lock($file);
        pialert_create_verified_config_backup($file, $newfile);
        pialert_create_verified_config_backup($file, $laststate);
        echo $pia_lang['BE_Dev_ConfEditor_CopOkay'];
    } catch (Throwable $exception) {
        http_response_code(500);
        echo $pia_lang['BE_Dev_ConfEditor_CopError'];
    } finally {
        pialert_release_config_lock($lockHandle);
    }

    pialert_logging('a_000', $_SERVER['REMOTE_ADDR'], 'LogStr_0007', '1', '');
    if ($GLOBALS['pialert_request']['reload'] == 'yes') {
        echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=3'>";
    }
}

//  Delete All Notification in WebGUI
function deleteAllNotifications() {
	global $pia_lang;

	$regex = '/[0-9]+-[0-9]+_.*\\.txt/i';
	$reports_path = '../../reports/';
	$files = array_diff(scandir($reports_path, SCANDIR_SORT_DESCENDING), array('.', '..', 'archived'));
	$count_all_reports = sizeof($files);
	foreach ($files as &$item) {
		if (preg_match($regex, $item) == True) {
			unlink($reports_path . $item);
		}
	}
	echo $count_all_reports . ' ' . $pia_lang['BE_Dev_Report_Delete'];
	echo "<meta http-equiv='refresh' content='2; URL=./reports.php'>";
	// Logging
	pialert_logging('a_050', $_SERVER['REMOTE_ADDR'], 'LogStr_0504', '', '');
}

//  Delete All Notification in WebGUI
function deleteAllNotificationsArchive() {
	global $pia_lang;

	$regex = '/[0-9]+-[0-9]+_.*\\.txt/i';
	$reports_path = '../../reports/archived/';
	$files = array_diff(scandir($reports_path, SCANDIR_SORT_DESCENDING), array('.', '..', 'archived'));
	$count_all_reports = sizeof($files);
	foreach ($files as &$item) {
		if (preg_match($regex, $item) == True) {
			unlink($reports_path . $item);
		}
	}
	echo $count_all_reports . ' ' . $pia_lang['BE_Dev_Report_Delete'];
	echo "<meta http-equiv='refresh' content='2; URL=./reports.php?report_source=archive'>";
	// Logging
	pialert_logging('a_050', $_SERVER['REMOTE_ADDR'], 'LogStr_0506', '', '');
}

// Get Report Counter
function getReportTotals() {
	$files = array_diff(scandir('../../reports'), array('..', '.', 'archived'));
	$report_counter = count($files);
	$totals = array($report_counter);
	echo (json_encode($totals));
}

//  Set FavIcon
function setFavIconURL() {
	global $pia_lang;

	if (isset($GLOBALS["pialert_request"]['FavIconURL'])) {
		$iconlist = array();
		$iconlist['redglass_w_local'] = 'img/favicons/glass_red_white.png';
		$iconlist['redflat_w_local'] = 'img/favicons/flat_red_white.png';
		$iconlist['redglass_b_local'] = 'img/favicons/glass_red_black.png';
		$iconlist['redflat_b_local'] = 'img/favicons/flat_red_black.png';
		$iconlist['blueglass_w_local'] = 'img/favicons/glass_blue_white.png';
		$iconlist['blueflat_w_local'] = 'img/favicons/flat_blue_white.png';
		$iconlist['blueglass_b_local'] = 'img/favicons/glass_blue_black.png';
		$iconlist['blueflat_b_local'] = 'img/favicons/flat_blue_black.png';
		$iconlist['greenglass_w_local'] = 'img/favicons/glass_green_white.png';
		$iconlist['greenflat_w_local'] = 'img/favicons/flat_green_white.png';
		$iconlist['greenglass_b_local'] = 'img/favicons/glass_green_black.png';
		$iconlist['greenflat_b_local'] = 'img/favicons/flat_green_black.png';
		$iconlist['yellowglass_w_local'] = 'img/favicons/glass_yellow_white.png';
		$iconlist['yellowflat_w_local'] = 'img/favicons/flat_yellow_white.png';
		$iconlist['yellowglass_b_local'] = 'img/favicons/glass_yellow_black.png';
		$iconlist['yellowflat_b_local'] = 'img/favicons/flat_yellow_black.png';
		$iconlist['purpleglass_w_local'] = 'img/favicons/glass_purple_white.png';
		$iconlist['purpleflat_w_local'] = 'img/favicons/flat_purple_white.png';
		$iconlist['purpleglass_b_local'] = 'img/favicons/glass_purple_black.png';
		$iconlist['purpleflat_b_local'] = 'img/favicons/flat_purple_black.png';
		$iconlist['blackglass_w_local'] = 'img/favicons/glass_black_white.png';
		$iconlist['blackflat_w_local'] = 'img/favicons/flat_black_white.png';
		$iconlist['whiteglass_b_local'] = 'img/favicons/glass_white_black.png';
		$iconlist['whiteflat_b_local'] = 'img/favicons/flat_white_black.png';
		$iconlist['redglass_w_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/glass_red_white.png';
		$iconlist['redflat_w_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/flat_red_white.png';
		$iconlist['redglass_b_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/glass_red_black.png';
		$iconlist['redflat_b_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/flat_red_black.png';
		$iconlist['blueglass_w_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/glass_blue_white.png';
		$iconlist['blueflat_w_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/flat_blue_white.png';
		$iconlist['blueglass_b_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/glass_blue_black.png';
		$iconlist['blueflat_b_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/flat_blue_black.png';
		$iconlist['greenglass_w_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/glass_green_white.png';
		$iconlist['greenflat_w_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/flat_green_white.png';
		$iconlist['greenglass_b_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/glass_green_black.png';
		$iconlist['greenflat_b_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/flat_green_black.png';
		$iconlist['yellowglass_w_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/glass_yellow_white.png';
		$iconlist['yellowflat_w_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/flat_yellow_white.png';
		$iconlist['yellowglass_b_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/glass_yellow_black.png';
		$iconlist['yellowflat_b_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/flat_yellow_black.png';
		$iconlist['purpleglass_w_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/glass_purple_white.png';
		$iconlist['purpleflat_w_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/flat_purple_white.png';
		$iconlist['purpleglass_b_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/glass_purple_black.png';
		$iconlist['purpleflat_b_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/flat_purple_black.png';
		$iconlist['blackglass_w_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/glass_black_white.png';
		$iconlist['blackflat_w_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/flat_black_white.png';
		$iconlist['whiteglass_b_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/glass_white_black.png';
		$iconlist['whiteflat_b_remote'] = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/flat_white_black.png';

		$url = $GLOBALS["pialert_request"]['FavIconURL'];

		if ($iconlist[$url] != "") {
			$newfavicon_url = $iconlist[$url];
			$file_path = '../../../config/setting_favicon';
			file_put_contents($file_path, $newfavicon_url);
			echo $pia_lang['BE_Files_FavIcon_okay'];
			echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>";
		} else {
			$temp_favicon_url = filter_var($url, FILTER_SANITIZE_URL);
			if (filter_var($temp_favicon_url, FILTER_VALIDATE_URL) && strtolower(substr($temp_favicon_url, 0, 4)) == "http") {
				$newfavicon_url = $temp_favicon_url;
				$file_path = '../../../config/setting_favicon';
				file_put_contents($file_path, $newfavicon_url);
				echo $pia_lang['BE_Files_FavIcon_okay'];
				echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>";
			} else {
				echo $pia_lang['BE_Files_FavIcon_error'];
			}
		}
	}
	// Logging
	pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0059', '', $GLOBALS["pialert_request"]['FavIconURL']);
}


//  Set Pihole URL
function setPiholeURL() {
	global $pia_lang;

	if (isset($GLOBALS["pialert_request"]['PiholeURL'])) {
				$url = $GLOBALS["pialert_request"]['PiholeURL'];
		$temp_favicon_url = filter_var($url, FILTER_SANITIZE_URL);
		if (filter_var($temp_favicon_url, FILTER_VALIDATE_URL) && strtolower(substr($temp_favicon_url, 0, 4)) == "http") {
			$newfavicon_url = $temp_favicon_url;
			$file_path = '../../../config/setting_piholebutton';
			file_put_contents($file_path, $newfavicon_url);
			echo $pia_lang['BE_Files_PiholeURL_okay'] ;
			echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>";
		} elseif ($url == "") {
			$newfavicon_url = $temp_favicon_url;
			$file_path = '../../../config/setting_piholebutton';
			file_put_contents($file_path, $newfavicon_url);
			echo $pia_lang['BE_Files_PiholeURL_remove'];
			echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>";
		} else {
			echo $pia_lang['BE_Files_PiholeURL_error'];
		}
	}
	// Logging
	pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0060', '', $GLOBALS["pialert_request"]['PiholeURL']);
}


function ToggleImport() {

    $file_path = '../../../config/pialert.conf';

    if (!isset($GLOBALS["pialert_request"]['deviceType']) || !isset($GLOBALS["pialert_request"]['toggleState'])) {
        echo "Missing Parameter";
        exit;
    }

    $deviceMap = [
        'FB' => 'FRITZBOX_ACTIVE',
        'MT' => 'MIKROTIK_ACTIVE',
        'UF' => 'UNIFI_ACTIVE',
        'OW' => 'OPENWRT_ACTIVE',
        'AW' => 'ASUSWRT_ACTIVE',
        'PF' => 'PFSENSE_ACTIVE',
        'OPN' => 'OPNSENSE_ACTIVE',
        'PiN' => 'PIHOLE_ACTIVE',
        'PiD' => 'DHCP_ACTIVE',
        'AG' => 'ADGUARD_ACTIVE',
    ];

    $deviceType = $GLOBALS["pialert_request"]['deviceType'];
    $toggleState = filter_var($GLOBALS["pialert_request"]['toggleState'], FILTER_VALIDATE_BOOLEAN);

    if (!array_key_exists($deviceType, $deviceMap)) {
        echo 'Invalid device type';
        exit;
    }

    $configKey = $deviceMap[$deviceType];
    $newValue = $toggleState ? 'False' : 'True';

    if (!file_exists($file_path)) {
        echo 'Configuration file not found';
        exit;
    }

    $fileContents = file_get_contents($file_path);
    if (strpos($fileContents, $configKey) === false) {
        echo "Key '{$configKey}' not found in configuration";
        exit;
    }

    $pattern = '/^(' . preg_quote($configKey, '/') . '\s*=\s*)(True|False)$/m';
    $replacement = '${1}' . $newValue;

    $newContents = preg_replace($pattern, $replacement, $fileContents);

    if ($newContents !== null) {
        validate_and_replace_pialert_config($file_path, $newContents);
        echo "{$configKey} set to {$newValue}";
    } else {
        echo 'Failed to update configuration';
    }
	// Logging
	pialert_logging('a_000', $_SERVER['REMOTE_ADDR'], 'LogStr_9999', '1', $configKey.' set to '.$newValue);
	echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>";
}

function ToggleExtLogging() {

    $file_path = '../../../config/pialert.conf';
	$toggleState = filter_var($GLOBALS["pialert_request"]['toggleState'], FILTER_VALIDATE_BOOLEAN);


    $configKey = 'PRINT_LOG';
    $newValue = $toggleState ? 'False' : 'True';

    if (!file_exists($file_path)) {
        echo 'Configuration file not found';
        exit;
    }

    $fileContents = file_get_contents($file_path);
    if (strpos($fileContents, $configKey) === false) {
        echo "Key '{$configKey}' not found in configuration";
        exit;
    }

    $pattern = '/^(' . preg_quote($configKey, '/') . '\s*=\s*)(True|False)$/m';
    $replacement = '${1}' . $newValue;

    $newContents = preg_replace($pattern, $replacement, $fileContents);

    if ($newContents !== null) {
        validate_and_replace_pialert_config($file_path, $newContents);
        echo "{$configKey} set to {$newValue}";
    } else {
        echo 'Failed to update configuration';
    }
	// Logging
	pialert_logging('a_000', $_SERVER['REMOTE_ADDR'], 'LogStr_9999', '1', $configKey.' set to '.$newValue);
	echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>";
}

function ToggleRogueDHCP() {

    $file_path = '../../../config/pialert.conf';
	$toggleState = filter_var($GLOBALS["pialert_request"]['toggleState'], FILTER_VALIDATE_BOOLEAN);


    $configKey = 'SCAN_ROGUE_DHCP';
    $newValue = $toggleState ? 'False' : 'True';

    if (!file_exists($file_path)) {
        echo 'Configuration file not found';
        exit;
    }

    $fileContents = file_get_contents($file_path);
    if (strpos($fileContents, $configKey) === false) {
        echo "Key '{$configKey}' not found in configuration";
        exit;
    }

    $pattern = '/^(' . preg_quote($configKey, '/') . '\s*=\s*)(True|False)$/m';
    $replacement = '${1}' . $newValue;

    $newContents = preg_replace($pattern, $replacement, $fileContents);

    if ($newContents !== null) {
        validate_and_replace_pialert_config($file_path, $newContents);
        echo "{$configKey} set to {$newValue}";
    } else {
        echo 'Failed to update configuration';
    }
	// Logging
	pialert_logging('a_000', $_SERVER['REMOTE_ADDR'], 'LogStr_9999', '1', $configKey.' set to '.$newValue);
	echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>";
}
?>

<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  services.php - Front module. Server side. Manage Devices
//------------------------------------------------------------------------------
//  leiweibau  2024        https://github.com/leiweibau     GNU GPLv3
//------------------------------------------------------------------------------

session_start();

if ($_SESSION["login"] != 1) {
	header('Location: ../../index.php');
	exit;
}

// External files
require 'timezone.php';
require 'db.php';
require 'util.php';
require 'journal.php';
require 'language_switch.php';
require '../templates/language/' . $pia_lang_selected . '.php';

//  Action selector
// Set maximum execution time to 1 minute
ini_set('max_execution_time', '60');

// Open DB
OpenDB();

// Action functions
if (isset($_REQUEST['action']) && !empty($_REQUEST['action'])) {
	$action = $_REQUEST['action'];
	switch ($action) {
	case 'getEventsTotals':getEventsTotals();
		break;
	case 'getEvents':getEvents();
		break;
	case 'getEventsTotalsforService':getEventsTotalsforService();
		break;
	case 'setServiceData':setServiceData();
		break;
	case 'deleteService':deleteService();
		break;
	case 'insertNewService':insertNewService();
		break;
	case 'downloadGeoDB':downloadGeoDB();
		break;
	case 'deleteGeoDB':deleteGeoDB();
		break;
	case 'updateGeoDB':updateGeoDB();
		break;
	case 'EnableWebServiceMon':EnableWebServiceMon();
		break;
	case 'getServiceMonTotals':getServiceMonTotals();
		break;
	case 'DeleteAllWebServices':DeleteAllWebServices();
		break;
	case 'getServicesJournal':getServicesJournal();
        break;
	default:logServerConsole('Action: ' . $action);
		break;
	}
}

function getServicesJournal() {
    global $db;

    header('Content-Type: application/json');

    $data = [];

    $sql = "
        SELECT 
            monevj_URL,
            monevj_DateTime,
            monevj_Additional_Info
        FROM Services_Events_Journal
        ORDER BY datetime(monevj_DateTime) DESC
        LIMIT 50
    ";

    $result = $db->query($sql);

    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
        }
    }

    echo json_encode($data);
    exit;
}

//  Delete all devices
function DeleteAllWebServices() {
	global $db;
	global $pia_lang;

	$sql = 'DELETE FROM Services';
	$result = $db->query($sql);

	if ($result == TRUE) {
		echo $pia_lang['BE_Dev_DBTools_DelServ'];
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0039', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./services.php'>");
	} else {
		echo $pia_lang['BE_Dev_DBTools_DelServError'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0040', '', '');
	}
}

function getServiceMonTotals() {
	global $db;

	$query = "SELECT COUNT(*) AS rowCount FROM Services WHERE mon_LastStatus=0 AND mon_LastLatency=99999999 AND mon_AlertDown=1";
	$alertDown_Count = $db->querySingle($query);
	$query = "SELECT COUNT(*) AS rowCount FROM Services WHERE mon_LastStatus=200";
	$online_Count = $db->querySingle($query);
	$query = "SELECT COUNT(*) AS rowCount FROM Services WHERE mon_LastStatus!=200 AND mon_LastStatus!=0";
	$warning_Count = $db->querySingle($query);
	$query = "SELECT COUNT(*) AS rowCount FROM Services";
	$all_Count = $db->querySingle($query);

	$totals = array($all_Count, $alertDown_Count, $online_Count, $warning_Count);
	echo (json_encode($totals));
}

function updateGeoDB() {
	global $pia_lang;

	$deletePath = '../../../db/GeoLite2-Country.mmdb';
	if (file_exists($deletePath)) {
		unlink($deletePath);
	}

	$fileUrl = 'https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-Country.mmdb';
	$savePath = '../../../db/GeoLite2-Country.mmdb';

	// Disable caching
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	header('Cache-Control: post-check=0, pre-check=0', false);
	header('Pragma: no-cache');

	file_put_contents($savePath, fopen($fileUrl, 'r'));
	echo json_encode(['filePath' => $savePath]);
	// Logging
	pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0008', '', '');
}

//  Toggle Web Service Monitoring
function EnableWebServiceMon() {
	global $pia_lang;

	if ($_SESSION['Scan_WebServices'] == True) {
		exec('../../../back/pialert-cli disable_service_mon', $output);
		echo $pia_lang['BE_Dev_webservicemon_disabled'];
		// Logging
		pialert_logging('a_030', $_SERVER['REMOTE_ADDR'], 'LogStr_0302', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>");
	} else {
		exec('../../../back/pialert-cli enable_service_mon', $output);
		echo $pia_lang['BE_Dev_webservicemon_enabled'];
		// Logging
		pialert_logging('a_030', $_SERVER['REMOTE_ADDR'], 'LogStr_0301', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>");
	}
}

//  Query total numbers of Events from Device
function getEventsTotalsforService() {
	global $db;

	$serviceURL = isset($_REQUEST['url']) && is_scalar($_REQUEST['url']) ? (string) $_REQUEST['url'] : '';
	$queries = array(
		'SELECT Count(*) FROM Services_Events WHERE moneve_URL = :url',
		'SELECT Count(*) FROM Services_Events WHERE moneve_URL = :url AND moneve_StatusCode LIKE "2%"',
		'SELECT Count(*) FROM Services_Events WHERE moneve_URL = :url AND moneve_StatusCode LIKE "3%"',
		'SELECT Count(*) FROM Services_Events WHERE moneve_URL = :url AND moneve_StatusCode LIKE "4%"',
		'SELECT Count(*) FROM Services_Events WHERE moneve_URL = :url AND moneve_StatusCode LIKE "5%"',
		'SELECT Count(*) FROM Services_Events WHERE moneve_URL = :url AND moneve_Latency LIKE "99999%"',
	);
	$totals = array();
	foreach ($queries as $query) {
		$result = db_execute_prepared($db, $query, array(':url' => $serviceURL));
		$row = $result ? $result->fetchArray(SQLITE3_NUM) : array(0);
		$totals[] = (int) $row[0];
	}
	echo json_encode($totals);
}

//  Query total numbers of Events
function getEventsTotals() {
	global $db;

	$queries = array(
		'SELECT Count(*) FROM Services_Events WHERE moneve_DateTime >= :period',
		'SELECT Count(*) FROM Services_Events WHERE moneve_DateTime >= :period AND moneve_StatusCode LIKE "2%"',
		'SELECT Count(*) FROM Services_Events WHERE moneve_DateTime >= :period AND moneve_StatusCode LIKE "3%"',
		'SELECT Count(*) FROM Services_Events WHERE moneve_DateTime >= :period AND moneve_StatusCode LIKE "4%"',
		'SELECT Count(*) FROM Services_Events WHERE moneve_DateTime >= :period AND moneve_StatusCode LIKE "5%"',
		'SELECT Count(*) FROM Services_Events WHERE moneve_DateTime >= :period AND moneve_Latency LIKE "99999%"',
	);
	$totals = array();
	foreach ($queries as $query) {
		$result = db_execute_prepared($db, $query, array(':period' => getDateFromPeriodValue()));
		$row = $result ? $result->fetchArray(SQLITE3_NUM) : array(0);
		$totals[] = (int) $row[0];
	}
	echo json_encode($totals);
}

//  Query the List of events
function getEvents() {
	global $db;

	$type = isset($_REQUEST['type']) && is_scalar($_REQUEST['type']) ? (string) $_REQUEST['type'] : '';
	$sql = 'SELECT * FROM Services_Events WHERE moneve_DateTime >= :period';
	switch ($type) {
	case 'all':
		break;
	case '2':
		$sql .= ' AND moneve_StatusCode LIKE "2%"';
		break;
	case '3':
		$sql .= ' AND moneve_StatusCode LIKE "3%"';
		break;
	case '4':
		$sql .= ' AND moneve_StatusCode LIKE "4%"';
		break;
	case '5':
		$sql .= ' AND moneve_StatusCode LIKE "5%"';
		break;
	case '99999999':
		$sql .= ' AND moneve_Latency LIKE "999999%"';
		break;
	default:
		$sql .= ' AND 1 = 0';
		break;
	}
	$result = db_execute_prepared($db, $sql, array(':period' => getDateFromPeriodValue()));
	$tableData = array();
	while ($result && ($row = $result->fetchArray(SQLITE3_NUM))) {
		$row[1] = formatDate($row[1]);
		if ($row[3] == '99999999') {
			$row[3] = 'No Response';
		}
		$tableData['data'][] = $row;
	}
	if (empty($tableData['data'])) {
		$tableData['data'] = '';
	}
	echo json_encode($tableData);
}

//  Set Services Data
function setServiceData() {
	global $db; global $pia_lang;
	$url = $_REQUEST['url'] ?? '';
	if (!is_scalar($url)) { echo $pia_lang['BE_Webs_UpdServError']; return; }
	$sql = 'UPDATE Services SET mon_Tags = :tags, mon_MAC = :mac, mon_AlertDown = :alertdown, mon_AlertUp = :alertup, mon_AlertEvents = :alertevents WHERE mon_URL = :url';
	$result = db_execute_prepared($db, $sql, array(':tags' => (string)($_REQUEST['tags'] ?? ''), ':mac' => (string)($_REQUEST['mac'] ?? ''), ':alertdown' => (string)($_REQUEST['alertdown'] ?? ''), ':alertup' => (string)($_REQUEST['alertup'] ?? ''), ':alertevents' => (string)($_REQUEST['alertevents'] ?? ''), ':url' => (string)$url));
	if ($result) { pialert_logging('a_030', $_SERVER['REMOTE_ADDR'], 'LogStr_0002', '', $url); echo $pia_lang['BE_Webs_UpdServ']; }
	else { pialert_logging('a_030', $_SERVER['REMOTE_ADDR'], 'LogStr_0004', '', $url); logServerConsole('Service update failed: ' . $db->lastErrorMsg()); echo $pia_lang['BE_Webs_UpdServError']; }
}

//  Delete Service
function deleteService() {
	global $db; global $pia_lang;
	$url = $_REQUEST['url'] ?? '';
	if (!$url || !is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)) { return false; }
	$db->exec('BEGIN');
	$result = db_execute_prepared($db, 'DELETE FROM Services WHERE mon_URL = :url', array(':url' => $url));
	$result = $result && db_execute_prepared($db, 'DELETE FROM Services_Events WHERE moneve_URL = :url', array(':url' => $url));
	if ($result) { $db->exec('COMMIT'); pialert_logging('a_030', $_SERVER['REMOTE_ADDR'], 'LogStr_0003', '', $url); echo $pia_lang['BE_Webs_DelServ']; echo ("<meta http-equiv='refresh' content='2; URL=./services.php'>"); }
	else { $db->exec('ROLLBACK'); pialert_logging('a_030', $_SERVER['REMOTE_ADDR'], 'LogStr_0005', '', $url); logServerConsole('Service delete failed: ' . $db->lastErrorMsg()); echo $pia_lang['BE_Webs_DelServError']; }
}

//  Insert Service
function insertNewService() {
	global $db;
	global $pia_lang;

	$url = $_REQUEST['url'];

	if (!$url || !is_string($url) || !preg_match('/^http(s)?:\/\/[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(\/.*)?$/i', $url)) {
		echo $pia_lang['BE_Webs_InsServError'].$pia_lang['BE_Webs_InsServError_a'];
		return false;
	}

	$check_timestamp = date("Y-m-d H:i:s");

	$checkURL = curl_init($url);
	curl_setopt($checkURL, CURLOPT_HEADER, 1);
	curl_setopt($checkURL, CURLOPT_NOBODY, 1);
	curl_setopt($checkURL, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($checkURL, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($checkURL, CURLOPT_TIMEOUT, 10);
	$output = curl_exec($checkURL);
	$httpstats = curl_getinfo($checkURL);
	$http_code = curl_getinfo($checkURL, CURLINFO_HTTP_CODE);
	curl_close($checkURL);

	$sql = 'INSERT INTO Services ("mon_URL", "mon_MAC", "mon_LastStatus", "mon_LastLatency", "mon_LastScan", "mon_Tags", "mon_AlertEvents", "mon_AlertDown", "mon_AlertUp", "mon_TargetIP") VALUES (:url, :mac, :status, :latency, :scan, :tags, :events, :down, :up, :target)';
	$result = db_execute_prepared($db, $sql, array(':url' => $url, ':mac' => (string)($_REQUEST['mac'] ?? ''), ':status' => array((int)$http_code, SQLITE3_INTEGER), ':latency' => (string)$httpstats['total_time'], ':scan' => $check_timestamp, ':tags' => (string)($_REQUEST['tags'] ?? ''), ':events' => (string)($_REQUEST['alertevents'] ?? ''), ':down' => (string)($_REQUEST['alertdown'] ?? ''), ':up' => (string)($_REQUEST['alertup'] ?? ''), ':target' => (string)$httpstats['primary_ip']));
	// check result
	if ($result == TRUE) {
		// Logging
		pialert_logging('a_030', $_SERVER['REMOTE_ADDR'], 'LogStr_0001', '', $url);
		echo $pia_lang['BE_Webs_InsServ'];
		echo ("<meta http-equiv='refresh' content='2; URL=./services.php'>");
	} else {
		// Logging
		pialert_logging('a_030', $_SERVER['REMOTE_ADDR'], 'LogStr_0001', '', $url);
		logServerConsole('Service insert failed: ' . $db->lastErrorMsg());
		echo $pia_lang['BE_Webs_InsServError'];
	}

}

//  Download GeoDB
function downloadGeoDB() {
	$fileUrl = 'https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-Country.mmdb';
	$savePath = '../../../db/GeoLite2-Country.mmdb';

// Disable caching
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	header('Cache-Control: post-check=0, pre-check=0', false);
	header('Pragma: no-cache');

	file_put_contents($savePath, fopen($fileUrl, 'r'));
	echo json_encode(['filePath' => $savePath]);
	// Logging
	pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0008', '', '');
}

//  Delete GeoDB
function deleteGeoDB() {
// $fileUrl = 'https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-Country.mmdb';

	$deletePath = '../../../db/GeoLite2-Country.mmdb';
	if (file_exists($deletePath)) {
		unlink($deletePath);
	}
	// Logging
	pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0009', '', '');

}
?>

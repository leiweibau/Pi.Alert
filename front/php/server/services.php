<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  services.php - Front module. Server side. Manage Devices
//------------------------------------------------------------------------------
//  leiweibau  2024        https://github.com/leiweibau     GNU GPLv3
//------------------------------------------------------------------------------

require_once __DIR__ . "/session.php";
pialert_start_session();
require_once __DIR__ . '/csrf.php';

if ($_SESSION["login"] != 1) {
	header('Location: ../../index.php');
	exit;
}

// External files
require 'timezone.php';
require 'db.php';
require 'util.php';
require 'service_url.php';
require 'journal.php';
require 'language_switch.php';
require '../templates/language/' . $pia_lang_selected . '.php';

//  Action selector
// Set maximum execution time to 1 minute
ini_set('max_execution_time', '60');

// Open DB
OpenDB();

pialert_dispatch_action([
    'getEventsTotals', 'getEvents', 'getEventsTotalsforService',
    'getServiceMonTotals', 'getServicesJournal'
], [
    'setServiceData', 'deleteService', 'insertNewService', 'downloadGeoDB',
    'deleteGeoDB', 'updateGeoDB', 'EnableWebServiceMon', 'DeleteAllWebServices'
]);
// Action functions
if (isset($GLOBALS["pialert_request"]['action']) && !empty($GLOBALS["pialert_request"]['action'])) {
	$action = $GLOBALS["pialert_request"]['action'];
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

	$serviceURL = isset($GLOBALS["pialert_request"]['url']) && is_scalar($GLOBALS["pialert_request"]['url']) ? (string) $GLOBALS["pialert_request"]['url'] : '';
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

	$type = isset($GLOBALS["pialert_request"]['type']) && is_scalar($GLOBALS["pialert_request"]['type']) ? (string) $GLOBALS["pialert_request"]['type'] : '';
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
	$url = $GLOBALS["pialert_request"]['url'] ?? '';
	if (!is_scalar($url) || !pialert_validate_service_key((string) $url)) { echo $pia_lang['BE_Webs_UpdServError']; return; }
	$sql = 'UPDATE Services SET mon_Tags = :tags, mon_MAC = :mac, mon_AlertDown = :alertdown, mon_AlertUp = :alertup, mon_AlertEvents = :alertevents WHERE mon_URL = :url';
	$result = db_execute_prepared($db, $sql, array(':tags' => (string)($GLOBALS["pialert_request"]['tags'] ?? ''), ':mac' => (string)($GLOBALS["pialert_request"]['mac'] ?? ''), ':alertdown' => (string)($GLOBALS["pialert_request"]['alertdown'] ?? ''), ':alertup' => (string)($GLOBALS["pialert_request"]['alertup'] ?? ''), ':alertevents' => (string)($GLOBALS["pialert_request"]['alertevents'] ?? ''), ':url' => (string)$url));
	if ($result) { pialert_logging('a_030', $_SERVER['REMOTE_ADDR'], 'LogStr_0002', '', $url); echo $pia_lang['BE_Webs_UpdServ']; }
	else { pialert_logging('a_030', $_SERVER['REMOTE_ADDR'], 'LogStr_0004', '', $url); logServerConsole('Service update failed: ' . $db->lastErrorMsg()); echo $pia_lang['BE_Webs_UpdServError']; }
}

//  Delete Service
function deleteService() {
	global $db; global $pia_lang;
	$url = $GLOBALS["pialert_request"]['url'] ?? '';
	if (!pialert_validate_service_key($url)) { return false; }
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

	$url = $GLOBALS["pialert_request"]['url'] ?? '';

	if (!pialert_validate_service_url($url)) {
		echo $pia_lang['BE_Webs_InsServError'].$pia_lang['BE_Webs_InsServError_a'];
		return false;
	}

    $existing = db_execute_prepared($db, 'SELECT 1 FROM Services WHERE mon_URL = :url LIMIT 1', array(':url' => $url));
    if ($existing && $existing->fetchArray(SQLITE3_NUM)) {
        echo $pia_lang['BE_Webs_InsServError'];
        return false;
    }

	$check_timestamp = date("Y-m-d H:i:s");

    $checkResult = pialert_check_service_url($url);

    $sql = 'INSERT OR IGNORE INTO Services '
        . '("mon_URL", "mon_MAC", "mon_LastStatus", "mon_LastLatency", "mon_LastScan", "mon_Tags", "mon_AlertEvents", "mon_AlertDown", "mon_AlertUp", "mon_TargetIP", "mon_Notes", "mon_ssl_subject", "mon_ssl_issuer", "mon_ssl_valid_from", "mon_ssl_valid_to", "mon_ssl_fc") '
        . 'VALUES (:url, :mac, :status, :latency, :scan, :tags, :events, :down, :up, :target, :notes, :ssl_subject, :ssl_issuer, :ssl_valid_from, :ssl_valid_to, :ssl_fc)';
    $params = array(
        ':url' => $url,
        ':mac' => (string)($GLOBALS["pialert_request"]['mac'] ?? ''),
        ':status' => array($checkResult['status'], SQLITE3_INTEGER),
        ':latency' => $checkResult['latency'],
        ':scan' => $check_timestamp,
        ':tags' => (string)($GLOBALS["pialert_request"]['tags'] ?? ''),
        ':events' => (string)($GLOBALS["pialert_request"]['alertevents'] ?? ''),
        ':down' => (string)($GLOBALS["pialert_request"]['alertdown'] ?? ''),
        ':up' => (string)($GLOBALS["pialert_request"]['alertup'] ?? ''),
        ':target' => $checkResult['target_ip'],
        ':notes' => $checkResult['note'],
        ':ssl_subject' => $checkResult['ssl_subject'],
        ':ssl_issuer' => $checkResult['ssl_issuer'],
        ':ssl_valid_from' => $checkResult['ssl_valid_from'],
        ':ssl_valid_to' => $checkResult['ssl_valid_to'],
        ':ssl_fc' => array(0, SQLITE3_INTEGER),
    );
    $result = db_execute_prepared($db, $sql, $params);
	// check result
	if ($result !== false && $db->changes() === 1) {
		echo $pia_lang['BE_Webs_InsServ'];
        pialert_logging('a_030', $_SERVER['REMOTE_ADDR'], 'LogStr_0001', '', $url);
		echo ("<meta http-equiv='refresh' content='2; URL=./services.php'>");
	} else {
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

<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  events.php - Front module. Server side. Manage Events
//------------------------------------------------------------------------------
//  Puche 2021        pi.alert.application@gmail.com        GNU GPLv3
//------------------------------------------------------------------------------
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '60');

require_once __DIR__ . "/session.php";
pialert_start_session();
require_once __DIR__ . '/csrf.php';

if ($_SESSION["login"] != 1) {
	header('Location: ../../index.php');
	exit;
}
require 'timezone.php';
require 'db.php';
require 'util.php';

// Action selector

// Open DB
OpenDB();

pialert_dispatch_action([
    'getEventsTotals', 'getEvents', 'getDeviceSessions', 'getDevicePresence',
    'getDeviceEvents', 'getEventsCalendar'
], []);
// Action functions
if (isset($GLOBALS["pialert_request"]['action']) && !empty($GLOBALS["pialert_request"]['action'])) {
	$action = $GLOBALS["pialert_request"]['action'];
	switch ($action) {
	case 'getEventsTotals':getEventsTotals();
		break;
	case 'getEvents':getEvents();
		break;
	case 'getDeviceSessions':getDeviceSessions();
		break;
	case 'getDevicePresence':getDevicePresence();
		break;
	case 'getDeviceEvents':getDeviceEvents();
		break;
	case 'getEventsCalendar':getEventsCalendar();
		break;
	default:logServerConsole('Action: ' . $action);
		break;
	}
}

//  Query total numbers of Events
function getEventsTotals() {
	global $db;

	$parameters = array(':period' => getDateFromPeriodValue());
	$queries = array(
		'SELECT Count(*) FROM Events WHERE eve_DateTime >= :period',
		'SELECT Count(*) FROM Sessions WHERE (ses_DateTimeConnection >= :period OR ses_DateTimeDisconnection >= :period OR ses_StillConnected = 1)',
		'SELECT Count(*) FROM Sessions WHERE (ses_DateTimeConnection IS NULL AND ses_DateTimeDisconnection >= :period) OR (ses_DateTimeDisconnection IS NULL AND ses_StillConnected = 0 AND ses_DateTimeConnection >= :period)',
		'SELECT Count(*) FROM Events WHERE eve_DateTime >= :period AND eve_EventType LIKE "VOIDED%"',
		'SELECT Count(*) FROM Events WHERE eve_DateTime >= :period AND eve_EventType = "New Device"',
		'SELECT Count(*) FROM Events WHERE eve_DateTime >= :period AND eve_EventType = "Device Down"',
	);

	$totals = array();
	foreach ($queries as $query) {
		$result = db_execute_prepared($db, $query, $parameters);
		$row = $result ? $result->fetchArray(SQLITE3_NUM) : array(0);
		$totals[] = (int) $row[0];
	}

	echo json_encode($totals);
}

//  Query the List of events
function getEvents() {
	global $db;

	$type = isset($GLOBALS["pialert_request"]['type']) && is_scalar($GLOBALS["pialert_request"]['type']) ? (string) $GLOBALS["pialert_request"]['type'] : '';
	$parameters = array(':period' => getDateFromPeriodValue());
	$eventsSql = 'SELECT eve_DateTime AS eve_DateTimeOrder, dev_name, dev_owner, eve_DateTime, eve_EventType, NULL, NULL, NULL, NULL, eve_IP, NULL, eve_AdditionalInfo, NULL, Dev_MAC
		FROM Events_Devices WHERE eve_DateTime >= :period';
	$sessionsSql = 'SELECT IFNULL(ses_DateTimeConnection, ses_DateTimeDisconnection) ses_DateTimeOrder,
		dev_name, dev_owner, NULL, NULL, ses_DateTimeConnection, ses_DateTimeDisconnection, NULL, NULL, ses_IP, NULL, ses_AdditionalInfo, ses_StillConnected, Dev_MAC
		FROM Sessions_Devices';
	$allSql = $eventsSql . '
		UNION ALL
		SELECT icmpeve_DateTime AS eve_DateTimeOrder, icmp_hostname || " **" AS dev_name, icmp_owner AS dev_owner,
		icmpeve_DateTime AS eve_DateTime, icmpeve_EventType AS eve_EventType, NULL, NULL, NULL, NULL, icmpeve_ip,
		NULL, icmpeve_AdditionalInfo, NULL, NULL
		FROM ICMP_Mon_Connections
		LEFT JOIN ICMP_Mon ON ICMP_Mon_Connections.icmpeve_ip = ICMP_Mon.icmp_ip
		WHERE icmpeve_DateTime >= :period';

	switch ($type) {
	case 'all':
		$sql = $allSql;
		break;
	case 'sessions':
		$sql = $sessionsSql . ' WHERE (ses_DateTimeConnection >= :period OR ses_DateTimeDisconnection >= :period OR ses_StillConnected = 1)';
		break;
	case 'missing':
		$sql = $sessionsSql . ' WHERE (ses_DateTimeConnection IS NULL AND ses_DateTimeDisconnection >= :period) OR (ses_DateTimeDisconnection IS NULL AND ses_StillConnected = 0 AND ses_DateTimeConnection >= :period)';
		break;
	case 'voided':
		$sql = $eventsSql . ' AND eve_EventType LIKE "VOIDED%"';
		break;
	case 'new':
		$sql = $eventsSql . ' AND eve_EventType = "New Device"';
		break;
	case 'down':
		$sql = $eventsSql . ' AND eve_EventType = "Device Down"';
		break;
	default:
		$sql = 'SELECT NULL WHERE 1 = 0';
		$parameters = array();
		break;
	}

	$result = db_execute_prepared($db, $sql, $parameters);
	$tableData = array();
	while ($result && ($row = $result->fetchArray(SQLITE3_NUM))) {
		if ($type == 'sessions' || $type == 'missing') {
			if (!empty($row[5]) && !empty($row[6])) {
				$row[7] = formatDateDiff($row[5], $row[6]);
				$row[8] = abs(strtotime($row[6]) - strtotime($row[5]));
			} elseif ($row[12] == 1) {
				$row[7] = formatDateDiff($row[5], '');
				$row[8] = abs(strtotime('now') - strtotime($row[5]));
			} else {
				$row[7] = '...';
				$row[8] = 0;
			}

			$row[5] = !empty($row[5]) ? formatDate($row[5]) : '<missing event>';
			if (!empty($row[6])) {
				$row[6] = formatDate($row[6]);
			} elseif ($row[12] == 0) {
				$row[6] = '<missing event>';
			} else {
				$row[6] = '...';
			}
		} else {
			$row[3] = formatDate($row[3]);
		}

		$row[10] = formatIPlong($row[9]);
		$tableData['data'][] = $row;
	}

	if (empty($tableData['data'])) {
		$tableData['data'] = '';
	}

	echo json_encode($tableData);
}

//  Query Device Sessions
function getDeviceSessions() {
	global $db;

	$mac = isset($GLOBALS["pialert_request"]['mac']) && is_scalar($GLOBALS["pialert_request"]['mac']) ? (string) $GLOBALS["pialert_request"]['mac'] : '';
	$sql = 'SELECT IFNULL(ses_DateTimeConnection, ses_DateTimeDisconnection) ses_DateTimeOrder,
		ses_EventTypeConnection, ses_DateTimeConnection, ses_EventTypeDisconnection, ses_DateTimeDisconnection,
		ses_StillConnected, ses_IP, ses_AdditionalInfo
		FROM Sessions
		WHERE ses_MAC = :mac
		AND (ses_DateTimeConnection >= :period OR ses_DateTimeDisconnection >= :period OR ses_StillConnected = 1)';
	$result = db_execute_prepared($db, $sql, array(':mac' => $mac, ':period' => getDateFromPeriodValue()));

	$tableData = array();
	while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
		if ($row['ses_EventTypeConnection'] == '<missing event>') {
			$ini = $row['ses_EventTypeConnection'];
		} else {
			$ini = formatDate($row['ses_DateTimeConnection']);
		}

		if ($row['ses_StillConnected'] == true) {
			$end = '...';
		} elseif ($row['ses_EventTypeDisconnection'] == '<missing event>') {
			$end = $row['ses_EventTypeDisconnection'];
		} else {
			$end = formatDate($row['ses_DateTimeDisconnection']);
		}

		if ($row['ses_EventTypeConnection'] == '<missing event>' || $row['ses_EventTypeDisconnection'] == '<missing event>') {
			$dur = '...';
		} elseif ($row['ses_StillConnected'] == true) {
			$dur = formatDateDiff($row['ses_DateTimeConnection'], '');
		} else {
			$dur = formatDateDiff($row['ses_DateTimeConnection'], $row['ses_DateTimeDisconnection']);
		}

		$info = $row['ses_AdditionalInfo'];
		if ($row['ses_EventTypeConnection'] == 'New Device') {
			$info = $row['ses_EventTypeConnection'] . ':   ' . $info;
		}
		$tableData['data'][] = array($row['ses_DateTimeOrder'], $ini, $end, $dur, $row['ses_IP'], $info);
	}

	if (empty($tableData['data'])) {
		$tableData['data'] = '';
	}
	echo json_encode($tableData);
}

//  Query Device Presence Calendar
function getDevicePresence() {
	global $db;

	$mac = isset($GLOBALS["pialert_request"]['mac']) && is_scalar($GLOBALS["pialert_request"]['mac']) ? (string) $GLOBALS["pialert_request"]['mac'] : '';
	$start = isset($GLOBALS["pialert_request"]['start']) && is_scalar($GLOBALS["pialert_request"]['start']) ? (string) $GLOBALS["pialert_request"]['start'] : '';
	$end = isset($GLOBALS["pialert_request"]['end']) && is_scalar($GLOBALS["pialert_request"]['end']) ? (string) $GLOBALS["pialert_request"]['end'] : '';
	try {
		$startDate = formatDateISO($start);
		$endDate = formatDateISO($end);
	} catch (Exception $exception) {
		echo json_encode('');
		return;
	}

	$sql = 'SELECT ses_EventTypeConnection, ses_DateTimeConnection, ses_EventTypeDisconnection,
		ses_DateTimeDisconnection, ses_IP, ses_AdditionalInfo, ses_StillConnected,
		CASE WHEN ses_EventTypeConnection = "<missing event>" THEN
			IFNULL((SELECT MAX(ses_DateTimeDisconnection) FROM Sessions AS SES2
				WHERE SES2.ses_MAC = SES1.ses_MAC AND SES2.ses_DateTimeDisconnection < SES1.ses_DateTimeDisconnection),
				DATETIME(ses_DateTimeDisconnection, "-1 hour"))
			ELSE ses_DateTimeConnection END AS ses_DateTimeConnectionCorrected,
		CASE WHEN ses_EventTypeDisconnection = "<missing event>" THEN
			(SELECT MIN(ses_DateTimeConnection) FROM Sessions AS SES2
				WHERE SES2.ses_MAC = SES1.ses_MAC AND SES2.ses_DateTimeConnection > SES1.ses_DateTimeConnection)
			ELSE ses_DateTimeDisconnection END AS ses_DateTimeDisconnectionCorrected
		FROM Sessions AS SES1
		WHERE ses_MAC = :mac
		AND (ses_DateTimeConnectionCorrected <= date(:end)
		AND (ses_DateTimeDisconnectionCorrected >= date(:start) OR ses_StillConnected = 1))';
	$result = db_execute_prepared($db, $sql, array(':mac' => $mac, ':start' => $startDate, ':end' => $endDate));

	$tableData = array();
	while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
		if ($row['ses_EventTypeConnection'] == '<missing event>' || $row['ses_EventTypeDisconnection'] == '<missing event>') {
			$color = '#f39c12';
		} elseif ($row['ses_StillConnected'] == 1) {
			$color = '#00a659';
		} else {
			$color = '#0073b7';
		}
		$tooltip = 'Connection: ' . formatEventDate($row['ses_DateTimeConnection'], $row['ses_EventTypeConnection']) . chr(13) .
			'Disconnection: ' . formatEventDate($row['ses_DateTimeDisconnection'], $row['ses_EventTypeDisconnection']) . chr(13) .
			'IP: ' . $row['ses_IP'];
		$tableData[] = array(
			'title' => '',
			'start' => formatDateISO($row['ses_DateTimeConnectionCorrected']),
			'end' => formatDateISO($row['ses_DateTimeDisconnectionCorrected']),
			'color' => $color,
			'tooltip' => $tooltip,
		);
	}

	if (empty($tableData)) {
		$tableData = '';
	}
	echo json_encode($tableData);
}

//  Query Presence Calendar for all Devices
function getEventsCalendar() {
	global $db;

	$scanSource = isset($GLOBALS["pialert_request"]['scansource']) && is_scalar($GLOBALS["pialert_request"]['scansource']) && $GLOBALS["pialert_request"]['scansource'] !== '' ? (string) $GLOBALS["pialert_request"]['scansource'] : 'local';
	$start = isset($GLOBALS["pialert_request"]['start']) && is_scalar($GLOBALS["pialert_request"]['start']) ? (string) $GLOBALS["pialert_request"]['start'] : '';
	$end = isset($GLOBALS["pialert_request"]['end']) && is_scalar($GLOBALS["pialert_request"]['end']) ? (string) $GLOBALS["pialert_request"]['end'] : '';
	try {
		$startDate = formatDateISO($start);
		$endDate = formatDateISO($end);
	} catch (Exception $exception) {
		echo json_encode('');
		return;
	}

	$sql = 'SELECT ses_MAC, ses_EventTypeConnection, ses_DateTimeConnection, ses_EventTypeDisconnection,
		ses_DateTimeDisconnection, ses_IP, ses_AdditionalInfo, ses_StillConnected,
		CASE WHEN ses_EventTypeConnection = "<missing event>" THEN
			IFNULL((SELECT MAX(ses_DateTimeDisconnection) FROM Sessions AS SES2
				WHERE LOWER(SES2.ses_MAC) = LOWER(SES1.ses_MAC) AND SES2.ses_DateTimeDisconnection < SES1.ses_DateTimeDisconnection),
				DATETIME(ses_DateTimeDisconnection, "-1 hour"))
			ELSE ses_DateTimeConnection END AS ses_DateTimeConnectionCorrected,
		CASE WHEN ses_EventTypeDisconnection = "<missing event>" THEN
			(SELECT MIN(ses_DateTimeConnection) FROM Sessions AS SES2
				WHERE LOWER(SES2.ses_MAC) = LOWER(SES1.ses_MAC) AND SES2.ses_DateTimeConnection > SES1.ses_DateTimeConnection)
			ELSE ses_DateTimeDisconnection END AS ses_DateTimeDisconnectionCorrected
		FROM Sessions AS SES1
		JOIN Devices AS DEV ON LOWER(SES1.ses_MAC) = LOWER(DEV.dev_MAC)
		WHERE DEV.dev_PresencePage = 1 AND DEV.dev_ScanSource = :scan_source
		AND (ses_DateTimeConnectionCorrected <= date(:end)
		AND (ses_DateTimeDisconnectionCorrected >= date(:start) OR ses_StillConnected = 1))';
	$result = db_execute_prepared($db, $sql, array(':scan_source' => $scanSource, ':start' => $startDate, ':end' => $endDate));

	$tableData = array();
	while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
		if ($row['ses_EventTypeConnection'] == '<missing event>' || $row['ses_EventTypeDisconnection'] == '<missing event>') {
			$color = '#f39c12';
		} elseif ($row['ses_StillConnected'] == 1) {
			$color = '#00a659';
		} else {
			$color = '#0073b7';
		}
		$tooltip = 'Connection: ' . formatEventDate($row['ses_DateTimeConnection'], $row['ses_EventTypeConnection']) . chr(13) .
			'Disconnection: ' . formatEventDate($row['ses_DateTimeDisconnection'], $row['ses_EventTypeDisconnection']) . chr(13) .
			'IP: ' . $row['ses_IP'];
		$tableData[] = array(
			'resourceId' => strtolower($row['ses_MAC']),
			'title' => '',
			'start' => formatDateISO($row['ses_DateTimeConnectionCorrected']),
			'end' => formatDateISO($row['ses_DateTimeDisconnectionCorrected']),
			'color' => $color,
			'tooltip' => $tooltip,
			'className' => 'no-border',
		);
	}

	if (empty($tableData)) {
		$tableData = '';
	}
	echo json_encode($tableData);
}

//  Query Device events
function getDeviceEvents() {
	global $db;

	$mac = isset($GLOBALS["pialert_request"]['mac']) && is_scalar($GLOBALS["pialert_request"]['mac']) ? (string) $GLOBALS["pialert_request"]['mac'] : '';
	$showConnections = isset($GLOBALS["pialert_request"]['hideConnections']) && is_scalar($GLOBALS["pialert_request"]['hideConnections']) && $GLOBALS["pialert_request"]['hideConnections'] === 'false';
	$sql = 'SELECT eve_DateTime, eve_EventType, eve_IP, eve_AdditionalInfo
		FROM Events
		WHERE eve_MAC = :mac AND eve_DateTime >= :period
		AND ((eve_EventType <> "Connected" AND eve_EventType <> "Disconnected"
			AND eve_EventType <> "VOIDED - Connected" AND eve_EventType <> "VOIDED - Disconnected")
		OR :show_connections = 1)';
	$result = db_execute_prepared($db, $sql, array(
		':mac' => $mac,
		':period' => getDateFromPeriodValue(),
		':show_connections' => array($showConnections ? 1 : 0, SQLITE3_INTEGER),
	));

	$tableData = array();
	while ($result && ($row = $result->fetchArray(SQLITE3_NUM))) {
		$row[0] = formatDate($row[0]);
		$tableData['data'][] = $row;
	}

	if (empty($tableData['data'])) {
		$tableData['data'] = '';
	}
	echo json_encode($tableData);
}

?>

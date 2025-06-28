<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  icmpmonitor.php - Front module. Server side. Manage Devices
//------------------------------------------------------------------------------
//  leiweibau  2023        https://github.com/leiweibau     GNU GPLv3
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
// Set maximum execution time to 1 minute
ini_set('max_execution_time', '60');

// Open DB
OpenDB();

// Action functions
if (isset($_REQUEST['action']) && !empty($_REQUEST['action'])) {
	$action = $_REQUEST['action'];
	switch ($action) {
	case 'setICMPHostData':setICMPHostData();
		break;
	case 'deleteICMPHost':deleteICMPHost();
		break;
	case 'insertNewICMPHost':insertNewICMPHost();
		break;
	case 'EnableICMPMon':EnableICMPMon();
		break;
	case 'getDevicesList':getDevicesList();
		break;
	case 'getICMPHostTotals':getICMPHostTotals();
		break;
	case 'getEventsTotalsforICMP':getEventsTotalsforICMP();
		break;
	case 'BulkDeletion':BulkDeletion();
		break;
	}
}

//  Get List Totals
function getICMPHostTotals() {
	global $db;

	$query = "SELECT COUNT(*) AS rowCount FROM ICMP_Mon WHERE icmp_Archived=0 AND icmp_PresentLastScan=0 AND icmp_AlertDown=1";
	$alertDown_Count = $db->querySingle($query);
	$query = "SELECT COUNT(*) AS rowCount FROM ICMP_Mon WHERE icmp_Archived=0 AND icmp_PresentLastScan=1";
	$online_Count = $db->querySingle($query);
	$query = "SELECT COUNT(*) AS rowCount FROM ICMP_Mon WHERE icmp_Archived=0 AND icmp_Favorite=1";
	$favorite_Count = $db->querySingle($query);
	$query = "SELECT COUNT(*) AS rowCount FROM ICMP_Mon WHERE icmp_Archived=0";
	$all_Count = $db->querySingle($query);
	$query = "SELECT COUNT(*) AS rowCount FROM ICMP_Mon WHERE icmp_Archived=1";
	$archived_Count = $db->querySingle($query);

	$totals = array($all_Count, $alertDown_Count, $online_Count, $favorite_Count, $archived_Count);
	echo (json_encode($totals));
}

//  Get List
function getDevicesList() {
	global $db;

	$condition = getDeviceCondition($_REQUEST['status']);
	$sql = 'SELECT rowid, *, CASE
            WHEN icmp_AlertDown=1 AND icmp_PresentLastScan=0 THEN "Down"
            WHEN icmp_Scan_Validation_State=0 AND icmp_PresentLastScan=1 THEN "Online"
            WHEN icmp_Scan_Validation > 0 AND icmp_Scan_Validation_State > 0 AND icmp_Scan_Validation_State <= icmp_Scan_Validation AND icmp_PresentLastScan=1 THEN "OnlineV"
            ELSE "Offline"
          END AS icmp_Status
          FROM ICMP_Mon ' . $condition;
	$result = $db->query($sql);
	// arrays of rows
	$tableData = array();
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		if ($row['icmp_hostname'] == '') {$row['icmp_hostname'] = $row['icmp_ip'];}
		$tableData['data'][] = array(
			$row['icmp_hostname'],
			$row['icmp_ip'],
			$row['icmp_Favorite'],
			$row['icmp_avgrtt'],
			$row['icmp_LastScan'],
			$row['icmp_PresentLastScan'],
			$row['icmp_AlertDown'],
			$row['icmp_Status'],
			$row['rowid'], // Rowid (hidden)
		);
	}
	// Control no rows
	if (empty($tableData['data'])) {
		$tableData['data'] = '';
	}
	// Return json
	echo (json_encode($tableData));
}

//  Status Where conditions
function getDeviceCondition($deviceStatus) {
	switch ($deviceStatus) {
	case 'all':return 'WHERE icmp_Archived=0';
		break;
	case 'connected':return 'WHERE icmp_Archived=0 AND icmp_PresentLastScan=1';
		break;
	case 'favorites':return 'WHERE icmp_Archived=0 AND icmp_Favorite=1';
		break;
	case 'down':return 'WHERE icmp_Archived=0 AND icmp_AlertDown=1 AND icmp_PresentLastScan=0';
		break;
	case 'archived':return 'WHERE icmp_Archived=1';
		break;
	default:return '';
		break;
	}
}

//  Set ICMP Host Data
function setICMPHostData() {
	global $db;
	global $pia_lang;

	if ($_REQUEST['icmp_group'] == '--') {unset($_REQUEST['icmp_group']);}
	if ($_REQUEST['icmp_type'] == '--') {unset($_REQUEST['icmp_type']);}
	if ($_REQUEST['icmp_location'] == '--') {unset($_REQUEST['icmp_location']);}
	if (!is_numeric($_REQUEST['icmp_scanvalid'])) {$_REQUEST['icmp_scanvalid'] = 0;}

	$sql = 'UPDATE ICMP_Mon SET
				icmp_hostname        = "' . quotes($_REQUEST['icmp_hostname']) . '",
                icmp_type            = "' . quotes($_REQUEST['icmp_type']) . '",
                icmp_group           = "' . quotes($_REQUEST['icmp_group']) . '",
                icmp_location        = "' . quotes($_REQUEST['icmp_location']) . '",
                icmp_owner           = "' . quotes($_REQUEST['icmp_owner']) . '",
                icmp_notes           = "' . quotes($_REQUEST['icmp_notes']) . '",
                icmp_Scan_Validation = "' . quotes($_REQUEST['icmp_scanvalid']) . '",
                icmp_vendor          = "' . quotes($_REQUEST['icmp_vendor']) . '",
                icmp_model           = "' . quotes($_REQUEST['icmp_model']) . '",
                icmp_serial          = "' . quotes($_REQUEST['icmp_serial']) . '",
                icmp_AlertEvents     = "' . quotes($_REQUEST['alertevents']) . '",
                icmp_AlertDown       = "' . quotes($_REQUEST['alertdown']) . '",
                icmp_Favorite        = "' . quotes($_REQUEST['favorit']) . '",
                icmp_Archived        = "' . quotes($_REQUEST['archived']) . '"
          WHERE icmp_ip="' . $_REQUEST['icmp_ip'] . '"';

	$result = $db->query($sql);

	if ($result == TRUE) {
		// Logging
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0002', '', $_REQUEST['icmp_ip']);
		echo $pia_lang['BackICMP_mon_UpdICMP'];
		echo ("<meta http-equiv='refresh' content='2; URL=./icmpmonitor.php'>");
	} else {
		// Logging
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0004', '', $_REQUEST['icmp_ip']);
		echo $pia_lang['BackICMP_mon_UpdICMPError'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
	}
}

//  Delete Host
function deleteICMPHost() {
	global $db;
	global $pia_lang;

	$hostip = $_REQUEST['icmp_ip'];
	if (!filter_var($hostip, FILTER_FLAG_IPV4) && !filter_var($hostip, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
		echo $pia_lang['BackICMP_mon_DelICMPError'];
		return false;
	}
	$sql = 'DELETE FROM ICMP_Mon WHERE icmp_ip="' . $hostip . '"';
	$result = $db->query($sql);
	$sql = 'DELETE FROM ICMP_Mon_Events WHERE icmpeve_ip="' . $hostip . '"';
	$result = $db->query($sql);

	if ($result == TRUE) {
		// Logging
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0003', '', $url);
		echo $pia_lang['BackICMP_mon_DelICMP'];
		echo ("<meta http-equiv='refresh' content='2; URL=./icmpmonitor.php'>");
	} else {
		// Logging
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0005', '', $url);
		echo $pia_lang['BackICMP_mon_DelICMPError'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
	}
}

//  Insert Service
function insertNewICMPHost() {
	global $db;
	global $pia_lang;

	$hostip = $_REQUEST['icmp_ip'];
	if ($_REQUEST['icmp_hostname'] == "") {$_REQUEST['icmp_hostname'] = $_REQUEST['icmp_ip'];}
	$check_timestamp = date("Y-m-d H:i:s");

	if (!filter_var($hostip, FILTER_FLAG_IPV4) && !filter_var($hostip, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
		echo $pia_lang['BackICMP_mon_InsICMPError'];
		return false;
	}

	$sql = 'INSERT INTO ICMP_Mon ("icmp_ip", "icmp_hostname", "icmp_LastScan", "icmp_PresentLastScan", "icmp_avgrtt", "icmp_AlertEvents", "icmp_AlertDown", "icmp_Favorite")
                         VALUES("' . $hostip . '", "' . $_REQUEST['icmp_hostname'] . '", "' . $check_timestamp . '", "0", "99999", "' . $_REQUEST['alertevents'] . '", "' . $_REQUEST['alertdown'] . '", "' . $_REQUEST['icmp_fav'] . '")';
	$result = $db->query($sql);

	if ($result == TRUE) {
		// Logging
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0001', '', $hostip);
		echo $pia_lang['BackICMP_mon_InsICMP'];
		echo ("<meta http-equiv='refresh' content='2; URL=./icmpmonitor.php'>");
	} else {
		// Logging
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0001', '', $hostip);
		echo $pia_lang['BackICMP_mon_InsICMPError'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
	}
}

//  Toggle Web Service Monitoring
function EnableICMPMon() {
	global $pia_lang;

	if ($_SESSION['ICMPScan'] == True) {
		exec('../../../back/pialert-cli disable_icmp_mon', $output);
		echo $pia_lang['BackICMP_mon_disabled'];
		// Logging
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0304', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>");
	} else {
		exec('../../../back/pialert-cli enable_icmp_mon', $output);
		echo $pia_lang['BackICMP_mon_enabled'];
		// Logging
		pialert_logging('a_031', $_SERVER['REMOTE_ADDR'], 'LogStr_0303', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>");
	}
}

//  Details
function getEventsTotalsforICMP() {
	global $db;

	// Request Parameters
	$hostip = $_REQUEST['hostip'];

	$SQL = 'SELECT icmpeve_DateTime, icmpeve_EventType
	        FROM ICMP_Mon_Connections
	        WHERE icmpeve_ip = "' . $hostip . '"
	        ORDER BY icmpeve_DateTime DESC
	        LIMIT 1';

    $result = $db->query($SQL);
	if ($result && $result->num_rows > 0) {
	    $row = $result->fetch_assoc();

	    if ($row['icmpeve_EventType'] === 'Connected') {
	        $currentTime = new DateTime();
	        $recordTime = new DateTime($row['icmpeve_DateTime']);

	        $interval = $currentTime->diff($recordTime);
	        $hoursDifference = $interval->h + ($interval->days * 24);

	        $eventspresence = $hoursDifference;
	    } else {
	        $eventspresence = 0;
	    }
	} else {
	    $eventspresence = 0;
	}

	// Down
	$SQL1 = 'SELECT Count(*)
           FROM ICMP_Mon_Connections
           WHERE icmpeve_ip = "' . $hostip . '" AND icmpeve_EventType = "Down"';
	$result = $db->query($SQL1);
	$row = $result->fetchArray(SQLITE3_NUM);
	$eventsdown = $row[0];
	// Return json

	echo (json_encode(array($eventspresence, $eventsdown)));
}

//  Bulk Deletion
function BulkDeletion() {
	global $db;
	global $pia_lang;

	$hosts = str_replace("_", ".", '"' . implode('","', $_REQUEST['hosts']) . '"');
	$journal_hosts = str_replace("_", ".", implode(',', $_REQUEST['hosts']));
	echo $pia_lang['Device_bulkDel_back_hosts'] . ': ' . str_replace(",", ", ", $hosts) . '<br><br>';

	$sql = "SELECT COUNT(*) AS row_count FROM ICMP_Mon";
	$result = $db->query($sql);

	$row = $result->fetchArray();
	$rowCount_before = $row['row_count'];

	$sql = "DELETE FROM ICMP_Mon WHERE icmp_ip IN ($hosts)";
	$result = $db->query($sql);

	$sql = "SELECT COUNT(*) AS row_count FROM ICMP_Mon";
	$result = $db->query($sql);

	$row = $result->fetchArray();
	$rowCount_after = $row['row_count'];

	echo $pia_lang['Device_bulkDel_back_before'] . ': ' . $rowCount_before . '<br>' . $pia_lang['Device_bulkDel_back_after'] . ': ' . $rowCount_after;
	echo ("<meta http-equiv='refresh' content='2; URL=./icmpmonitor.php?mod=bulkedit'>");

	// Logging
	pialert_logging('a_021', $_SERVER['REMOTE_ADDR'], 'LogStr_0003', '', $journal_hosts);

}

?>

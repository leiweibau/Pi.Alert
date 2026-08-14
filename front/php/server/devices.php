<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  devices.php - Front module. Server side. Manage Devices
//------------------------------------------------------------------------------
//  Puche      2021        pi.alert.application@gmail.com   GNU GPLv3
//  jokob-sk   2022        jokob.sk@gmail.com               GNU GPLv3
//  leiweibau  2025+       https://github.com/leiweibau     GNU GPLv3
//------------------------------------------------------------------------------

require_once __DIR__ . "/session.php";
pialert_start_session();
require_once __DIR__ . '/csrf.php';

if ($_SESSION["login"] != 1) {
	header('Location: ../../index.php');
	exit;
}

// if ($_SESSION["login"] != 1) {
// 	if ($GLOBALS["pialert_request"]['action'] != "getDevicesTotals") {
// 	header('Location: ../../index.php');
// 	exit;
// 	}
// }
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
OpenDB_Tools();

pialert_dispatch_action([
    'getDeviceData', 'getNetworkNodes', 'ListInactiveHosts',
    'getDevicesTotals', 'getDevicesList', 'getDevicesListCalendar',
    'getOwners', 'getDeviceTypes', 'getGroups', 'getLocations',
    'getLinkSpeed', 'getConnectionType', 'getSpeedtestResults'
], [
    'setDeviceData', 'deleteDevice', 'deleteAllWithEmptyMACs',
    'deleteAllDevices', 'deleteUnknownDevices', 'TestNotificationSystem',
    'deleteEvents', 'deleteActHistory', 'deleteDeviceEvents',
    'DeleteInactiveHosts', 'wakeonlan', 'BulkDeletion', 'EnableMainScan',
    'EnableSatelliteScan', 'DeleteDeviceFilter', 'SetDeviceFilter',
    'DeleteSpeedtestResults', 'DeleteNmapScansResults', 'SaveFilterID',
    'CreateNewSatellite', 'SaveSatellite', 'DeleteSatellite',
    'resetVoidedEvents', 'MTUpdateColumnContent', 'MTDeletColumnContent'
]);
// Action functions
if (isset($GLOBALS["pialert_request"]['action']) && !empty($GLOBALS["pialert_request"]['action'])) {
	$action = $GLOBALS["pialert_request"]['action'];
	switch ($action) {
	case 'getDeviceData':getDeviceData();
		break;
	case 'setDeviceData':setDeviceData();
		break;
	case 'deleteDevice':deleteDevice();
		break;
	case 'getNetworkNodes':getNetworkNodes();
		break;
	case 'deleteAllWithEmptyMACs':deleteAllWithEmptyMACs();
		break;
	case 'deleteAllDevices':deleteAllDevices();
		break;
	case 'deleteUnknownDevices':deleteUnknownDevices();
		break;
	case 'TestNotificationSystem':TestNotificationSystem();
		break;
	case 'deleteEvents':deleteEvents();
		break;
	case 'deleteActHistory':deleteActHistory();
		break;
	case 'deleteDeviceEvents':deleteDeviceEvents();
		break;
	case 'DeleteInactiveHosts':DeleteInactiveHosts();
		break;
	case 'ListInactiveHosts':ListInactiveHosts();
		break;
	case 'wakeonlan':wakeonlan();
		break;
	case 'BulkDeletion':BulkDeletion();
		break;
	case 'getDevicesTotals':getDevicesTotals();
		break;
	case 'getDevicesList':getDevicesList();
		break;
	case 'getDevicesListCalendar':getDevicesListCalendar();
		break;
	case 'getOwners':getOwners();
		break;
	case 'getDeviceTypes':getDeviceTypes();
		break;
	case 'getGroups':getGroups();
		break;
	case 'getLocations':getLocations();
		break;
	case 'getLinkSpeed':getLinkSpeed();
		break;
	case 'getConnectionType':getConnectionType();
		break;
	case 'getSpeedtestResults':getSpeedtestResults();
		break;
	case 'EnableMainScan':EnableMainScan();
		break;
	case 'EnableSatelliteScan':EnableSatelliteScan();
		break;
	case 'DeleteDeviceFilter':DeleteDeviceFilter();
		break;
	case 'SetDeviceFilter':SetDeviceFilter();
		break;
	case 'DeleteSpeedtestResults':DeleteSpeedtestResults();
		break;
	case 'DeleteNmapScansResults':DeleteNmapScansResults();
		break;
	case 'SaveFilterID':SaveFilterID();
		break;
	case 'CreateNewSatellite':CreateNewSatellite();
		break;
	case 'SaveSatellite':SaveSatellite();
		break;
	case 'DeleteSatellite':DeleteSatellite();
		break;
	case 'resetVoidedEvents':resetVoidedEvents();
		break;
	case 'MTUpdateColumnContent':MTUpdateColumnContent();
		break;
	case 'MTDeletColumnContent':MTDeletColumnContent();
		break;
     default:logServerConsole('Action: ' . $action);
		break;
	}
}

function MTDeletColumnContent() {
	global $db;
	global $pia_lang;

	$column = htmlspecialchars($GLOBALS["pialert_request"]['column']) ?? '';
	$column_content = htmlspecialchars($GLOBALS["pialert_request"]['ccontent']) ?? '';
	$new_column_content = htmlspecialchars($GLOBALS["pialert_request"]['nccontent']) ?? '';

	if ($new_column_content !== '') {
	    die($pia_lang['BE_Dev_ColumnErr_a']);
	}

	$columnMap = [
	    'Group'       => ['Devices' => 'dev_Group',        'ICMP_Mon' => 'icmp_group'],
	    'Owner'       => ['Devices' => 'dev_Owner',        'ICMP_Mon' => 'icmp_owner'],
	    'Type'        => ['Devices' => 'dev_DeviceType',   'ICMP_Mon' => 'icmp_type'],
	    'Location'    => ['Devices' => 'dev_Location',     'ICMP_Mon' => 'icmp_location'],
	    'LinkSpeed'   => ['Devices' => 'dev_LinkSpeed'],
	    'ConnectType' => ['Devices' => 'dev_ConnectionType']
	];

	if (!isset($columnMap[$column])) {
		die($pia_lang['BE_Dev_ColumnErr_b'].'&quot;'.$column.'&quot;');
	}

	$ok_message = "";
	$er_message = "";

	// Devices-Spalte löschen
	if (isset($columnMap[$column]['Devices'])) {
	    $field = $columnMap[$column]['Devices'];
	    $sql = "UPDATE Devices SET $field = '' WHERE $field = :old_value";
	    $stmt = $db->prepare($sql);
	    if ($stmt) {
	        $stmt->bindValue(':old_value', $column_content, SQLITE3_TEXT);
	        $stmt->execute();
	        $changed = $db->changes();
	        $ok_message .= $pia_lang['NAV_Devices'].": ".$changed." ".$pia_lang['BE_Dev_ColumnOk_a']."<br>";
	    } else {
	        $er_message .= $pia_lang['BE_Dev_ColumnErr_c']." ".$pia_lang['NAV_Devices'].": " . $db->lastErrorMsg() . "<br>";
	    }
	}

	// ICMP_Mon-Spalte löschen
	if (isset($columnMap[$column]['ICMP_Mon'])) {
	    $field = $columnMap[$column]['ICMP_Mon'];
	    $sql = "UPDATE ICMP_Mon SET $field = '' WHERE $field = :old_value";
	    $stmt = $db->prepare($sql);
	    if ($stmt) {
	        $stmt->bindValue(':old_value', $column_content, SQLITE3_TEXT);
	        $stmt->execute();
	        $changed = $db->changes();
	        $ok_message .= $pia_lang['NAV_ICMPScan'].": ".$changed." ".$pia_lang['BE_Dev_ColumnOk_a']."<br>";
	    } else {
	        $er_message .= $pia_lang['BE_Dev_ColumnErr_c']." ".$pia_lang['NAV_ICMPScan'].": " . $db->lastErrorMsg() . "<br>";
	    }
	}

	// Ausgabe
	if ($er_message == "") {
		echo $column . $pia_lang['BE_Dev_ColumnOk_c'].'<br>' . $ok_message;
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0003', '', $column.'/'.$column_content);
	} else {
		echo $pia_lang['BE_Dev_ColumnErr_d'] . $er_message;
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0005', '', $column.'/'.$column_content);
	}

	echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=2'>";
}

function MTUpdateColumnContent() {
	global $db;
	global $pia_lang;

	$column = htmlspecialchars($GLOBALS["pialert_request"]['column']) ?? '';
	$column_content = htmlspecialchars($GLOBALS["pialert_request"]['ccontent']) ?? '';
	$new_column_content = htmlspecialchars($GLOBALS["pialert_request"]['nccontent']) ?? '';

	$columnMap = [
	    'Group'       => ['Devices' => 'dev_Group',        'ICMP_Mon' => 'icmp_group'],
	    'Owner'       => ['Devices' => 'dev_Owner',        'ICMP_Mon' => 'icmp_owner'],
	    'Type'        => ['Devices' => 'dev_DeviceType',   'ICMP_Mon' => 'icmp_type'],
	    'Location'    => ['Devices' => 'dev_Location',     'ICMP_Mon' => 'icmp_location'],
	    'LinkSpeed'   => ['Devices' => 'dev_LinkSpeed'],
	    'ConnectType' => ['Devices' => 'dev_ConnectionType']
	];

	if (!isset($columnMap[$column])) {
	    die($pia_lang['BE_Dev_ColumnErr_b'].'&quot;'.$column.'&quot;');
	}

	$ok_message = "";
	$er_message = "";

	// Devices-Update vorbereiten
	if (isset($columnMap[$column]['Devices'])) {
	    $field = $columnMap[$column]['Devices'];
	    $sql = "UPDATE Devices SET $field = :new_value WHERE $field = :old_value";
	    $stmt = $db->prepare($sql);
	    if ($stmt) {
	        $stmt->bindValue(':new_value', $new_column_content, SQLITE3_TEXT);
	        $stmt->bindValue(':old_value', $column_content, SQLITE3_TEXT);
	        $stmt->execute();
	        $changed = $db->changes();
	        $ok_message .= $pia_lang['NAV_Devices'].": ".$changed." ".$pia_lang['BE_Dev_ColumnOk_b']."<br>";
	    } else {
	        $er_message .= $pia_lang['BE_Dev_ColumnErr_c']." ".$pia_lang['NAV_Devices'].": " . $db->lastErrorMsg() . "<br>";
	    }
	}

	// ICMP_Mon-Update (wenn vorhanden)
	if (isset($columnMap[$column]['ICMP_Mon'])) {
	    $field = $columnMap[$column]['ICMP_Mon'];
	    $sql = "UPDATE ICMP_Mon SET $field = :new_value WHERE $field = :old_value";
	    $stmt = $db->prepare($sql);
	    if ($stmt) {
	        $stmt->bindValue(':new_value', $new_column_content, SQLITE3_TEXT);
	        $stmt->bindValue(':old_value', $column_content, SQLITE3_TEXT);
	        $stmt->execute();
	        $changed = $db->changes();
	        $ok_message .= $pia_lang['NAV_ICMPScan'].": ".$changed." ".$pia_lang['BE_Dev_ColumnOk_b']."<br>";
	    } else {
	        $er_message .= $pia_lang['BE_Dev_ColumnErr_c']." ".$pia_lang['NAV_ICMPScan'].": " . $db->lastErrorMsg() . "<br>";
	    }
	}

	if ($er_message == "") {
		echo $column . $pia_lang['BE_Dev_ColumnOk_d'].'<br>'.$ok_message;
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0002', '', $column.'/'.$column_content.' -- '.$new_column_content);
	} else {
		echo $pia_lang['BE_Dev_ColumnErr_e'] . $er_message;
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0004', '', $column.'/'.$column_content.' -- '.$new_column_content);
	}
	echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=2'>";
}

function SaveSatellite() {
	global $db;
	global $pia_lang;

	$currentDateTime = date('Y-m-d H:i');

	$satellite_name        = $GLOBALS["pialert_request"]['satellite_name'] ?? '';
	$new_satellite_name    = $GLOBALS["pialert_request"]['changed_satellite_name'] ?? '';
	$satellite_id          = $GLOBALS["pialert_request"]['sat_id'] ?? '';

	if ($satellite_name === '' || $new_satellite_name === '' || $satellite_id === '') {
	    echo $pia_lang['BE_Dev_SatUpdateError'] . '<br>Ungültige Eingabedaten.';
	    return;
	}

	$sql = 'UPDATE Satellites SET
		sat_name = :new_name,
		sat_lastupdate = :last_update
		WHERE sat_id = :sat_id AND sat_name = :old_name';

	$stmt = $db->prepare($sql);
	if (!$stmt) {
		echo $pia_lang['BE_Dev_SatUpdateError'] . '<br>Prepare fehlgeschlagen: ' . $db->lastErrorMsg();
		return;
	}

	$stmt->bindValue(':new_name', $new_satellite_name, SQLITE3_TEXT);
	$stmt->bindValue(':last_update', $currentDateTime, SQLITE3_TEXT);
	$stmt->bindValue(':sat_id', $satellite_id, SQLITE3_TEXT);
	$stmt->bindValue(':old_name', $satellite_name, SQLITE3_TEXT);

	$result = $stmt->execute();

	if ($result) {
		echo $pia_lang['BE_Dev_SatUpdate'] . '<br>' .
		     htmlspecialchars($satellite_name) . ' &#8594; ' .
		     htmlspecialchars($new_satellite_name);
		pialert_logging('a_033', $_SERVER['REMOTE_ADDR'], 'LogStr_0002', '', 'ID: '.$satellite_id.' ('.$satellite_name.'/'.$new_satellite_name.')');
	} else {
		echo $pia_lang['BE_Dev_SatUpdateError'] . '<br>' . $db->lastErrorMsg();
		pialert_logging('a_033', $_SERVER['REMOTE_ADDR'], 'LogStr_0004', '', 'ID: '.$satellite_id.' ('.$satellite_name.'/'.$new_satellite_name.')');
	}

	echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=5'>");
}

function DeleteSatellite() {
	global $db;
	global $pia_lang;

	$satellite_name = $GLOBALS["pialert_request"]['satellite_name'] ?? '';
	$satellite_id   = $GLOBALS["pialert_request"]['sat_id'] ?? '';

	// 1. Geräte löschen, deren ScanSource dem sat_token des Satelliten entspricht
	$sql1 = 'DELETE FROM Devices
	         WHERE dev_ScanSource IN (
	             SELECT sat_token FROM Satellites WHERE sat_id = :sat_id
	         )';

	$stmt1 = $db->prepare($sql1);
	if ($stmt1) {
		$stmt1->bindValue(':sat_id', $satellite_id, SQLITE3_TEXT);
		$stmt1->execute();
	} else {
		echo $pia_lang['BE_Dev_SatDeleteError'] . '<br>Fehler beim Löschen aus Devices: ' . $db->lastErrorMsg();
		return;
	}

	// 2. Satellit selbst löschen
	$sql2 = 'DELETE FROM Satellites WHERE sat_id = :sat_id AND sat_name = :sat_name';

	$stmt2 = $db->prepare($sql2);
	if ($stmt2) {
		$stmt2->bindValue(':sat_id', $satellite_id, SQLITE3_TEXT);
		$stmt2->bindValue(':sat_name', $satellite_name, SQLITE3_TEXT);
		$result = $stmt2->execute();
	} else {
		echo $pia_lang['BE_Dev_SatDeleteError'] . '<br>Fehler beim Löschen aus Satellites: ' . $db->lastErrorMsg();
		return;
	}

	if ($result) {
		echo $pia_lang['BE_Dev_SatDelete'];
		pialert_logging('a_033', $_SERVER['REMOTE_ADDR'], 'LogStr_0003', '', 'ID: '.$satellite_id.' ('.$satellite_name.')');
	} else {
		echo $pia_lang['BE_Dev_SatDeleteError'] . '<br>' . $db->lastErrorMsg();
		pialert_logging('a_033', $_SERVER['REMOTE_ADDR'], 'LogStr_0005', '', 'ID: '.$satellite_id.' ('.$satellite_name.')');
	}

	echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=5'>");
}

function generateRandomString($length) {
	$keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$pieces = [];
	$max = strlen($keyspace)-1;
	for ($i = 0; $i < $length; ++$i) {
	   $pieces []= $keyspace[random_int(0, $max)];
	}
	return implode('', $pieces);
}

function CreateNewSatellite() {
	global $db;
	global $pia_lang;

	$currentDateTime = date('Y-m-d H:i');

	$satellite_name = ($GLOBALS["pialert_request"]['new_satellite_name'] === '') ? 'Satellite' : $GLOBALS["pialert_request"]['new_satellite_name'];

	// Token und Passwort generieren
	$satellite_token = generateRandomString(48);
	$satellite_password = generateRandomString(96);

	// INSERT als Prepared Statement
	$sql = 'INSERT INTO Satellites (sat_name, sat_token, sat_password, sat_lastupdate)
	        VALUES (:name, :token, :password, :lastupdate)';

	$stmt = $db->prepare($sql);
	if (!$stmt) {
		echo $pia_lang['BE_Dev_SatCreateError'] . '<br>Prepare fehlgeschlagen: ' . $db->lastErrorMsg();
		return;
	}

	// Parameter binden
	$stmt->bindValue(':name', $satellite_name, SQLITE3_TEXT);
	$stmt->bindValue(':token', $satellite_token, SQLITE3_TEXT);
	$stmt->bindValue(':password', $satellite_password, SQLITE3_TEXT);
	$stmt->bindValue(':lastupdate', $currentDateTime, SQLITE3_TEXT);

	// Ausführen
	$result = $stmt->execute();

	if ($result) {
		echo $pia_lang['BE_Dev_SatCreate'];
		pialert_logging('a_033', $_SERVER['REMOTE_ADDR'], 'LogStr_0001', '', 'Name: '.$satellite_name);
	} else {
		echo $pia_lang['BE_Dev_SatCreateError'] . '<br>' . $db->lastErrorMsg();
		pialert_logging('a_033', $_SERVER['REMOTE_ADDR'], 'LogStr_0000', '', 'Name: '.$satellite_name);
	}

	echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=5'>");
}


function SaveFilterID() {
	global $db; global $pia_lang;
	$filterid = filter_var($GLOBALS["pialert_request"]['filterid'] ?? null, FILTER_VALIDATE_INT);
	if ($filterid === false || $filterid === null) { echo $pia_lang['BE_Dev_Upd_FilterError']; return; }
	$params = array(':name' => (string)($GLOBALS["pialert_request"]['filtername'] ?? ''), ':string' => (string)($GLOBALS["pialert_request"]['filterstring'] ?? ''), ':index' => (string)($GLOBALS["pialert_request"]['filterindex'] ?? ''), ':column' => (string)($GLOBALS["pialert_request"]['filtercolumn'] ?? ''), ':group' => (string)($GLOBALS["pialert_request"]['filtergroup'] ?? ''), ':id' => array((int)$filterid, SQLITE3_INTEGER));
	$result = db_execute_prepared($db, 'UPDATE Devices_table_filter SET filtername=:name, filterstring=:string, reserve_a=:index, reserve_b=:column, reserve_c=:group WHERE id=:id', $params);
	if ($result) { echo $pia_lang['BE_Dev_Upd_Filter']; pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0046', '', 'ID: '.$filterid); }
	else { logServerConsole('Device filter update failed: ' . $db->lastErrorMsg()); echo $pia_lang['BE_Dev_Upd_FilterError']; pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0047', '', 'ID: '.$filterid); }
	echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>");
}

function SetDeviceFilter() {
	global $db;
	global $pia_lang;

	$colfilterarray = array();
	if ($GLOBALS["pialert_request"]['fname'] == 0) {array_push($colfilterarray, "0");}
	if ($GLOBALS["pialert_request"]['fowner'] == 0) {array_push($colfilterarray, "2");}
	if ($GLOBALS["pialert_request"]['fgroup'] == 0) {array_push($colfilterarray, "5");}
	if ($GLOBALS["pialert_request"]['flocation'] == 0) {array_push($colfilterarray, "6");}
	if ($GLOBALS["pialert_request"]['ftype'] == 0) {array_push($colfilterarray, "3");}
	if ($GLOBALS["pialert_request"]['fip'] == 0) {array_push($colfilterarray, "9");}
	if ($GLOBALS["pialert_request"]['fmac'] == 0) {array_push($colfilterarray, "11");}
	if ($GLOBALS["pialert_request"]['fvendor'] == 0) {array_push($colfilterarray, "12");}
	if ($GLOBALS["pialert_request"]['fconnectiont'] == 0) {array_push($colfilterarray, "1");}

	$newcolfilter = implode(",", $colfilterarray);

	$filtername = isset($GLOBALS["pialert_request"]['filtername']) && is_scalar($GLOBALS["pialert_request"]['filtername']) ? (string) $GLOBALS["pialert_request"]['filtername'] : '';
	$filterstring = isset($GLOBALS["pialert_request"]['filterstring']) && is_scalar($GLOBALS["pialert_request"]['filterstring']) ? (string) $GLOBALS["pialert_request"]['filterstring'] : '';
	$filtergroup = isset($GLOBALS["pialert_request"]['filtergroup']) && is_scalar($GLOBALS["pialert_request"]['filtergroup']) ? (string) $GLOBALS["pialert_request"]['filtergroup'] : '';
	// Create table if not exist
	$sql = "CREATE TABLE IF NOT EXISTS Devices_table_filter (
	            id INTEGER PRIMARY KEY,
	            filtername TEXT NOT NULL,
	            filterstring TEXT NOT NULL,
	            reserve_a INTEGER,
	            reserve_b TEXT,
	            reserve_c TEXT
	        )";
	// Write filter in db
	// reserve_b is for select column for search
	try {
		$result = $db->query($sql);
		
		if ($filtername != "" && $filterstring != "") {
			try {
				$sql_insert_data = 'INSERT INTO Devices_table_filter ("filtername", "filterstring", "reserve_b", "reserve_c") VALUES (:name, :string, :columns, :group_name)';
				$result = db_execute_prepared($db, $sql_insert_data, array(':name' => $filtername, ':string' => $filterstring, ':columns' => $newcolfilter, ':group_name' => $filtergroup));
				echo $pia_lang['BE_Dev_table_filter_ok_a'] . '"' . h($filtername) . '"' . $pia_lang['BE_Dev_table_filter_ok_b'] . '"' . h($filterstring) . '"' . $pia_lang['BE_Dev_table_filter_ok_c'];
				pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0042', '', $filtername.'/'.$filterstring);
			} catch (Exception $e) {
				die($pia_lang['BE_Dev_table_filter_error_a'] . '"' . h($filtername) . '"' . $pia_lang['BE_Dev_table_filter_error_b'] . '"' . h($filterstring) . '"' . $pia_lang['BE_Dev_table_filter_error_c']);
				pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0041', '', '');
			}
		} else {
			echo $pia_lang['BE_Dev_table_filter_error_d'];
			pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0043', '', '');
		}
	} catch (Exception $e) {
	    die($pia_lang['BE_Dev_table_filter_error_e']);
	    pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0044', '', '');
	}
	echo ("<meta http-equiv='refresh' content='2; URL=./devices.php'>");
}

function DeleteDeviceFilter() {
	global $db; global $pia_lang;
	$filterstring = isset($GLOBALS["pialert_request"]['filterstring']) && is_scalar($GLOBALS["pialert_request"]['filterstring']) ? (string) $GLOBALS["pialert_request"]['filterstring'] : '';
	$result = db_execute_prepared($db, 'DELETE FROM Devices_table_filter WHERE filterstring = :filterstring', array(':filterstring' => $filterstring));
	if (!$result) { logServerConsole('Device filter delete failed: ' . $db->lastErrorMsg()); }
	echo $pia_lang['BE_Dev_table_delfilter_ok'] . h($filterstring);
	pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0045', '', $filterstring);
	echo ("<meta http-equiv='refresh' content='2; URL=./devices.php'>");
}

//  Query Device Data
function getDeviceData() {
	global $db;

	$identifier = isset($GLOBALS["pialert_request"]['mac']) && is_scalar($GLOBALS["pialert_request"]['mac']) ? (string) $GLOBALS["pialert_request"]['mac'] : '';
	$result = db_execute_prepared($db, 'SELECT rowid, *,
		CASE WHEN dev_AlertDeviceDown=1 AND dev_PresentLastScan=0 THEN "Down"
		WHEN dev_PresentLastScan=1 THEN "On-line" ELSE "Off-line" END AS dev_Status
		FROM Devices WHERE dev_MAC = :identifier OR CAST(rowid AS TEXT) = :identifier', array(':identifier' => $identifier));
	$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
	if (!$row) {
		echo json_encode(null);
		return;
	}

	$deviceData = $row;
	$mac = $deviceData['dev_MAC'];
	$periodDate = getDateFromPeriodValue();
	$deviceData['dev_Name'] = strval($deviceData['dev_Name']);
	$deviceData['dev_Owner'] = strval($deviceData['dev_Owner']);
	$deviceData['dev_Model'] = strval($deviceData['dev_Model']);
	$deviceData['dev_Vendor'] = strval($deviceData['dev_Vendor']);
	$deviceData['dev_Serialnumber'] = strval($deviceData['dev_Serialnumber']);
	$deviceData['dev_Network_Node_MAC'] = $row['dev_Infrastructure'];
	$deviceData['dev_Network_Node_port'] = $row['dev_Infrastructure_port'];
	$deviceData['dev_FirstConnection'] = formatDate($row['dev_FirstConnection']);
	$deviceData['dev_LastConnection'] = formatDate($row['dev_LastConnection']);
	$deviceData['dev_RandomMAC'] = in_array($mac[1], array('2', '6', 'A', 'E', 'a', 'e')) ? 1 : 0;
	$params = array(':mac' => $mac, ':period' => $periodDate);

	$result = db_execute_prepared($db, 'SELECT COUNT(*) FROM Sessions WHERE ses_MAC = :mac
		AND (ses_DateTimeConnection >= :period OR ses_DateTimeDisconnection >= :period OR ses_StillConnected = 1)', $params);
	$row = $result ? $result->fetchArray(SQLITE3_NUM) : array(0);
	$deviceData['dev_Sessions'] = (int) $row[0];

	$result = db_execute_prepared($db, 'SELECT COUNT(*) FROM Events WHERE eve_MAC = :mac AND eve_DateTime >= :period
		AND eve_EventType <> "Connected" AND eve_EventType <> "Disconnected"', $params);
	$row = $result ? $result->fetchArray(SQLITE3_NUM) : array(0);
	$deviceData['dev_Events'] = (int) $row[0];

	$result = db_execute_prepared($db, 'SELECT COUNT(*) FROM Events WHERE eve_MAC = :mac AND eve_DateTime >= :period
		AND eve_EventType = "Device Down"', $params);
	$row = $result ? $result->fetchArray(SQLITE3_NUM) : array(0);
	$deviceData['dev_DownAlerts'] = (int) $row[0];

	$result = db_execute_prepared($db, 'SELECT CAST((MAX(0, SUM(julianday(IFNULL(ses_DateTimeDisconnection, DATETIME("now", "localtime")))
		- julianday(CASE WHEN ses_DateTimeConnection < :period THEN :period ELSE ses_DateTimeConnection END)) * 24)) AS INT)
		FROM Sessions WHERE ses_MAC = :mac AND ses_DateTimeConnection IS NOT NULL
		AND (ses_DateTimeDisconnection IS NOT NULL OR ses_StillConnected = 1)
		AND (ses_DateTimeConnection >= :period OR ses_DateTimeDisconnection >= :period OR ses_StillConnected = 1)', $params);
	$row = $result ? $result->fetchArray(SQLITE3_NUM) : array(0);
	$deviceData['dev_PresenceHours'] = round($row[0]);
	echo json_encode($deviceData);
}

//  Update Device Data
function setDeviceData() {
	global $db; global $pia_lang;
	$mac = $GLOBALS["pialert_request"]['mac'] ?? ''; if (!is_scalar($mac) || !filter_var(str_replace('-', ':', $mac), FILTER_VALIDATE_MAC)) { echo $pia_lang['BE_Dev_DBTools_UpdDevError']; return; }
	$keys = array('name','owner','type','vendor','model','serialnumber','favorite','showpresence','group','location','comments','networknode','networknodeport','connectiontype','linkspeed','staticIP','mqttdevice','scancycle','alertevents','alertdown','skiprepeated','scanvalid','newdevice','archived');
	$params = array(':mac' => (string)$mac); foreach ($keys as $key) { $value = $GLOBALS["pialert_request"][$key] ?? ''; $params[':'.$key] = is_scalar($value) ? (string)$value : ''; }
	$params[':cleanup'] = array($params[':mqttdevice'] === '1' ? 0 : 1, SQLITE3_INTEGER);
	$sql = 'UPDATE Devices SET dev_Name=:name, dev_Owner=:owner, dev_DeviceType=:type, dev_Vendor=:vendor, dev_Model=:model, dev_Serialnumber=:serialnumber, dev_Favorite=:favorite, dev_PresencePage=:showpresence, dev_Group=:group, dev_Location=:location, dev_Comments=:comments, dev_Infrastructure=:networknode, dev_Infrastructure_port=:networknodeport, dev_ConnectionType=:connectiontype, dev_LinkSpeed=:linkspeed, dev_StaticIP=:staticIP, dev_MQTTDevice=:mqttdevice, dev_MQTTDevice_cleanup=:cleanup, dev_ScanCycle=:scancycle, dev_AlertEvents=:alertevents, dev_AlertDeviceDown=:alertdown, dev_SkipRepeated=:skiprepeated, dev_Scan_Validation=:scanvalid, dev_NewDevice=:newdevice, dev_Archived=:archived WHERE dev_MAC=:mac';
	$result = db_execute_prepared($db, $sql, $params);
	if ($result) { echo $pia_lang['BE_Dev_DBTools_UpdDev']; pialert_logging('a_020', $_SERVER['REMOTE_ADDR'], 'LogStr_0002', '', $mac); }
	else { logServerConsole('Device update failed: ' . $db->lastErrorMsg()); echo $pia_lang['BE_Dev_DBTools_UpdDevError']; pialert_logging('a_020', $_SERVER['REMOTE_ADDR'], 'LogStr_0004', '', $mac); }
}

//  Delete Device
function deleteDevice() {
	global $db; global $pia_lang;
	$mac = $GLOBALS["pialert_request"]['mac'] ?? ''; $result = is_scalar($mac) ? db_execute_prepared($db, 'DELETE FROM Devices WHERE dev_MAC=:mac', array(':mac' => (string)$mac)) : false;
	if ($result) { echo $pia_lang['BE_Dev_DBTools_DelDev_a']; pialert_logging('a_020', $_SERVER['REMOTE_ADDR'], 'LogStr_0003', '', $mac); }
	else { logServerConsole('Device delete failed: ' . $db->lastErrorMsg()); echo $pia_lang['BE_Dev_DBTools_DelDevError_a']; pialert_logging('a_020', $_SERVER['REMOTE_ADDR'], 'LogStr_0005', '', $mac); }
}

//  Delete all devices with empty MAC addresses
function deleteAllWithEmptyMACs() {
	global $db;
	global $pia_lang;

	// sql
	$sql = 'DELETE FROM Devices WHERE dev_MAC=""';
	$result = $db->query($sql);

	if ($result == TRUE) {
		echo $pia_lang['BE_Dev_DBTools_DelDev_b'];
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0016', '', '');
	} else {
		echo $pia_lang['BE_Dev_DBTools_DelDevError_b'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0017', '', '');
	}
}

//  Delete all devices with empty MAC addresses
function deleteUnknownDevices() {
	global $db;
	global $pia_lang;

	$sql = 'DELETE FROM Devices WHERE dev_Name="(unknown)"';
	$result = $db->query($sql);

	if ($result == TRUE) {
		echo $pia_lang['BE_Dev_DBTools_DelDev_b'];
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0018', '', '');
	} else {
		echo $pia_lang['BE_Dev_DBTools_DelDevError_b'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0019', '', '');
	}
}

//  Delete Device Events
function deleteDeviceEvents() {
	global $db; global $pia_lang;
	$mac = $GLOBALS["pialert_request"]['mac'] ?? ''; $result = is_scalar($mac) ? db_execute_prepared($db, 'DELETE FROM Events WHERE eve_MAC=:mac', array(':mac' => (string)$mac)) : false;
	if ($result) { echo $pia_lang['BE_Dev_DBTools_DelEvents']; pialert_logging('a_020', $_SERVER['REMOTE_ADDR'], 'LogStr_0020', '', $mac); }
	else { logServerConsole('Device event delete failed: ' . $db->lastErrorMsg()); echo $pia_lang['BE_Dev_DBTools_DelEventsError']; pialert_logging('a_020', $_SERVER['REMOTE_ADDR'], 'LogStr_0021', '', $mac); }
}

//  Delete all devices
function deleteAllDevices() {
	global $db;
	global $pia_lang;

	$sql = 'DELETE FROM Devices';
	$result = $db->query($sql);

	if ($result == TRUE) {
		echo $pia_lang['BE_Dev_DBTools_DelDev_b'];
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0022', '', '');
	} else {
		echo $pia_lang['BE_Dev_DBTools_DelDevError_b'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0023', '', '');
	}
}

//  Delete all Events
function deleteEvents() {
	global $db;
	global $pia_lang;

	$sql = 'DELETE FROM Events';
	$result = $db->query($sql);

	$sql = 'DELETE FROM Sessions';
	$result = $db->query($sql);

	if ($result == TRUE) {
		echo $pia_lang['BE_Dev_DBTools_DelEvents'];
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0024', '', '');
	} else {
		echo $pia_lang['BE_Dev_DBTools_DelEventsError'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0025', '', '');
	}
}

//  Delete History
function deleteActHistory() {
	global $db;
	global $pia_lang;

	$sql = 'DELETE FROM Online_History';
	$result = $db->query($sql);

	if ($result == TRUE) {
		echo $pia_lang['BE_Dev_DBTools_DelActHistory'];
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0026', '', '');
	} else {
		echo $pia_lang['BE_Dev_DBTools_DelActHistoryError'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0027', '', '');
	}
}

//  Test Notification
function TestNotificationSystem() {
	global $pia_lang;

	exec('../../../back/pialert-cli reporting_test', $output);
	// Logging
	pialert_logging('a_050', $_SERVER['REMOTE_ADDR'], 'LogStr_0500', '', '');
	echo $pia_lang['BE_Dev_test_notification'];
	echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>");
}

//  Query total numbers of Devices by status
function getDevicesTotals() {
	global $db;

	$scanSource = isset($GLOBALS["pialert_request"]['scansource']) && is_scalar($GLOBALS["pialert_request"]['scansource']) && $GLOBALS["pialert_request"]['scansource'] !== '' ? (string) $GLOBALS["pialert_request"]['scansource'] : 'local';
	$totals = array();
	foreach (array('all', 'connected', 'favorites', 'new', 'down', 'archived', 'presence') as $status) {
		list($condition, $parameters) = getDeviceCondition($status, $scanSource);
		$result = db_execute_prepared($db, 'SELECT COUNT(*) FROM Devices ' . $condition, $parameters);
		$row = $result ? $result->fetchArray(SQLITE3_NUM) : array(0);
		$totals[] = (int) $row[0];
	}
	echo json_encode($totals);
}


//  Query the List of devices in a determined Status
function getDevicesList() {
	global $db, $db_tools;

	$status = isset($GLOBALS["pialert_request"]['status']) && is_scalar($GLOBALS["pialert_request"]['status']) ? (string) $GLOBALS["pialert_request"]['status'] : 'all';
	$scanSource = isset($GLOBALS["pialert_request"]['scansource']) && is_scalar($GLOBALS["pialert_request"]['scansource']) && $GLOBALS["pialert_request"]['scansource'] !== '' ? (string) $GLOBALS["pialert_request"]['scansource'] : 'local';
	list($condition, $parameters) = getDeviceCondition($status, $scanSource);
	$sql = 'SELECT rowid, *, CASE
		WHEN dev_AlertDeviceDown=1 AND dev_PresentLastScan=0 THEN "Down"
		WHEN dev_NewDevice=1 AND dev_PresentLastScan=1 THEN "NewON"
		WHEN dev_NewDevice=1 AND dev_PresentLastScan=0 THEN "NewOFF"
		WHEN dev_Scan_Validation > 0 AND dev_Scan_Validation_State > 0 AND dev_Scan_Validation_State <= dev_Scan_Validation AND dev_PresentLastScan=1 THEN "OnlineV"
		WHEN dev_PresentLastScan=1 THEN "On-line" ELSE "Off-line" END AS dev_Status
		FROM Devices ' . $condition;
	$queuedDeviceMacs = array();
	$queueTableExists = $db_tools->querySingle(
		"SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'Tools_Nmap_Queue'"
	);
	if ((int) $queueTableExists > 0) {
		$queueResult = $db_tools->query('SELECT DISTINCT device_mac FROM Tools_Nmap_Queue');
		while ($queueResult && ($queueRow = $queueResult->fetchArray(SQLITE3_ASSOC))) {
			$queuedDeviceMacs[strtolower(trim((string) $queueRow['device_mac']))] = true;
		}
	}
	$result = db_execute_prepared($db, $sql, $parameters);
	$tableData = array();
	while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
		$isNmapQueued = isset($queuedDeviceMacs[strtolower(trim((string) $row['dev_MAC']))]);
		$tableData['data'][] = array($row['dev_Name'], $row['dev_ConnectionType'], $row['dev_Owner'],
			$row['dev_DeviceType'], $row['dev_Favorite'], $row['dev_Group'], $row['dev_Location'],
			formatDate($row['dev_FirstConnection']), formatDate($row['dev_LastConnection']), $row['dev_LastIP'],
			(in_array($row['dev_MAC'][1], array('2', '6', 'A', 'E', 'a', 'e')) ? 1 : 0), $row['dev_MAC'],
			$row['dev_Vendor'], $row['dev_Status'], formatIPlong($row['dev_LastIP']), $row['dev_ScanSource'], $row['rowid'],
			null, $isNmapQueued);
	}
	if (empty($tableData['data'])) {
		$tableData['data'] = '';
	}
	echo json_encode($tableData);
}

//  Query the List of devices for calendar
function getDevicesListCalendar() {
	global $db;

	$status = isset($GLOBALS["pialert_request"]['status']) && is_scalar($GLOBALS["pialert_request"]['status']) ? (string) $GLOBALS["pialert_request"]['status'] : 'all';
	$scanSource = isset($GLOBALS["pialert_request"]['scansource']) && is_scalar($GLOBALS["pialert_request"]['scansource']) && $GLOBALS["pialert_request"]['scansource'] !== '' ? (string) $GLOBALS["pialert_request"]['scansource'] : 'local';
	list($condition, $parameters) = getDeviceCondition($status, $scanSource);
	$result = db_execute_prepared($db, 'SELECT * FROM Devices ' . $condition . ' AND dev_PresencePage = 1', $parameters);

	$tableData = array();
	while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
		if ($row['dev_Favorite'] == 1) {
			$row['dev_Name'] = "★ " . $row['dev_Name'];
		}
		$tableData[] = array('id' => $row['dev_MAC'], 'title' => $row['dev_Name'], 'favorite' => $row['dev_Favorite']);
	}
	echo json_encode($tableData);
}

//  Query the List of Owners
function getOwners() {
	global $db;

	$sql = 'SELECT DISTINCT 1 as dev_Order, dev_Owner AS DeviceOwner
          FROM Devices
          WHERE dev_Owner <> "(unknown)" AND dev_Owner <> ""
            AND dev_Favorite = 1
          
          UNION
          
          SELECT DISTINCT 2 as dev_Order, dev_Owner AS DeviceOwner
          FROM Devices
          WHERE dev_Owner <> "(unknown)" AND dev_Owner <> ""
            AND dev_Favorite = 0
            AND dev_Owner NOT IN
               (SELECT dev_Owner FROM Devices WHERE dev_Favorite = 1)

		UNION

		SELECT DISTINCT 2 as dev_Order, icmp_owner AS DeviceOwner
		FROM ICMP_Mon
		WHERE icmp_owner NOT IN ("(unknown)") AND icmp_owner <> ""

        ORDER BY 1,2 ';
	$result = $db->query($sql);

	// arrays of rows
	$tableData = array();
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		$tableData[] = array('order' => $row['dev_Order'],
			'name' => $row['DeviceOwner']);
	}
	// Return json
	echo json_encode($tableData);
}

//  Query the List of types
function getDeviceTypes() {
	global $db;

	$sql = 'SELECT DISTINCT 9 as dev_Order, dev_DeviceType AS DeviceType
          FROM Devices
          WHERE dev_DeviceType <> "" AND dev_DeviceType NOT IN ("",
                 "Smartphone", "Tablet",
                 "Laptop", "PC", "Printer", "Server", "Singleboard Computer (SBC)",
                 "Game Console", "SmartTV", "Virtual Assistance",
                 "House Appliance", "Phone", "Radio",
                 "AP", "NAS", "Router", "Hypervisor", "USB WIFI Adapter", "USB LAN Adapter")

		UNION

		SELECT DISTINCT 9 as dev_Order, icmp_type AS DeviceType
		FROM ICMP_Mon
		WHERE icmp_type <> "" AND icmp_type NOT IN ("",
                 "Smartphone", "Tablet",
                 "Laptop", "PC", "Printer", "Server", "Singleboard Computer (SBC)",
                 "Game Console", "SmartTV", "Virtual Assistance",
                 "House Appliance", "Phone", "Radio",
                 "AP", "NAS", "Router", "Hypervisor", "USB WIFI Adapter", "USB LAN Adapter")

          UNION SELECT 1 as dev_Order, "Smartphone"
          UNION SELECT 1 as dev_Order, "Tablet"

          UNION SELECT 2 as dev_Order, "Laptop"
          UNION SELECT 2 as dev_Order, "PC"
          UNION SELECT 2 as dev_Order, "Printer"
          UNION SELECT 2 as dev_Order, "Singleboard Computer (SBC)"
          UNION SELECT 2 as dev_Order, "USB LAN Adapter"
          UNION SELECT 2 as dev_Order, "USB WIFI Adapter"

          UNION SELECT 3 as dev_Order, "Game Console"
          UNION SELECT 3 as dev_Order, "SmartTV"
          UNION SELECT 3 as dev_Order, "Virtual Assistance"

          UNION SELECT 4 as dev_Order, "House Appliance"
          UNION SELECT 4 as dev_Order, "Phone"
          UNION SELECT 4 as dev_Order, "Radio"

          UNION SELECT 5 as dev_Order, "AP"
          UNION SELECT 5 as dev_Order, "NAS"
          UNION SELECT 5 as dev_Order, "Router"
          UNION SELECT 5 as dev_Order, "Server"
          UNION SELECT 5 as dev_Order, "Hypervisor"

          UNION SELECT 10 as dev_Order, "Other"

          ORDER BY 1,2';
	$result = $db->query($sql);

	// arrays of rows
	$tableData = array();
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		$tableData[] = array('order' => $row['dev_Order'],
			'name' => $row['DeviceType']);
	}
	// Return json
	echo json_encode($tableData);
}

//  Query the List of groups
function getGroups() {
	global $db;

	$sql = 'SELECT DISTINCT 8 as dev_Order, dev_Group  AS GroupName
          FROM Devices
          WHERE dev_Group NOT IN ("(unknown)", "Others", "Friends", "Personal", "Always on") AND dev_Group <> ""

		UNION

		SELECT DISTINCT 8 as dev_Order, icmp_group AS GroupName
		FROM ICMP_Mon
		WHERE icmp_group NOT IN ("(unknown)", "Others", "Friends", "Personal", "Always on") AND icmp_group <> ""

          UNION SELECT 1 as dev_Order, "Always on"
          UNION SELECT 1 as dev_Order, "Friends"
          UNION SELECT 1 as dev_Order, "Personal"
          UNION SELECT 9 as dev_Order, "Others"
          ORDER BY dev_Order, GroupName';
	$result = $db->query($sql);

	// arrays of rows
	$tableData = array();
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		$tableData[] = array('order' => $row['dev_Order'],
			'name' => $row['GroupName']);
	}

	// Return json
	echo json_encode($tableData);
}

//  Query the List of LinkSpeed
function getLinkSpeed() {
	global $db;

	$sql = 'SELECT DISTINCT 9 as dev_Order, dev_LinkSpeed
          FROM Devices
          WHERE dev_LinkSpeed NOT IN ("", "10 Mbps", "100 Mbps", "1.0 Gbps",
          	"2.5 Gbps", "5 Gbps", "10 Gbps", "20 Gbps", "25 Gbps", "40 Gbps") AND dev_LinkSpeed <> ""
          UNION SELECT 1 as dev_Order, "10 Mbps"
          UNION SELECT 1 as dev_Order, "100 Mbps"
          UNION SELECT 2 as dev_Order, "1.0 Gbps"
          UNION SELECT 2 as dev_Order, "2.5 Gbps"
          UNION SELECT 2 as dev_Order, "5 Gbps"
          UNION SELECT 3 as dev_Order, "10 Gbps"
          UNION SELECT 3 as dev_Order, "20 Gbps"
          UNION SELECT 3 as dev_Order, "25 Gbps"
          UNION SELECT 3 as dev_Order, "40 Gbps"
          ORDER BY 1,2 ';
	$result = $db->query($sql);

	// arrays of rows
	$tableData = array();
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		$tableData[] = array('order' => $row['dev_Order'],
			'name' => $row['dev_LinkSpeed']);
	}

	// Return json
	echo json_encode($tableData);
}

//  Query the List of ConnectionType
function getConnectionType() {
	global $db;

	$sql = 'SELECT DISTINCT 9 as dev_Order, dev_ConnectionType
          FROM Devices
          WHERE dev_ConnectionType NOT IN ("", "Ethernet", "Fibre", "WiFi", "Bluetooth",
          	"Virtual Machine", "Container") AND dev_ConnectionType <> ""
          UNION SELECT 1 as dev_Order, "Ethernet"
          UNION SELECT 1 as dev_Order, "Fibre"
          UNION SELECT 2 as dev_Order, "WiFi"
          UNION SELECT 2 as dev_Order, "Bluetooth"
          UNION SELECT 3 as dev_Order, "Virtual Machine"
          UNION SELECT 3 as dev_Order, "Container"
          ORDER BY 1,2 ';
	$result = $db->query($sql);

	// arrays of rows
	$tableData = array();
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		$tableData[] = array('order' => $row['dev_Order'],
			'name' => $row['dev_ConnectionType']);
	}

	// Return json
	echo json_encode($tableData);
}

//  Query the List of locations
function getLocations() {
	global $db;

	$sql = 'SELECT DISTINCT 9 as dev_Order, dev_Location AS Location
          FROM Devices
          WHERE dev_Location <> ""
            AND dev_Location NOT IN (
                "Bathroom", "Bedroom", "Dining room", "Hallway",
                "Kitchen", "Laundry", "Living room", "Study",
                "Other")

		UNION

		SELECT DISTINCT 9 as dev_Order, icmp_location AS Location
		FROM ICMP_Mon
		WHERE icmp_location <> ""
            AND icmp_location NOT IN (
                "Bathroom", "Bedroom", "Dining room", "Hallway",
                "Kitchen", "Laundry", "Living room", "Study",
                "Other")


          UNION SELECT 1 as dev_Order, "Bathroom"
          UNION SELECT 1 as dev_Order, "Bedroom"
          UNION SELECT 1 as dev_Order, "Dining room"
          UNION SELECT 1 as dev_Order, "Hall"
          UNION SELECT 1 as dev_Order, "Kitchen"
          UNION SELECT 1 as dev_Order, "Laundry"
          UNION SELECT 1 as dev_Order, "Living room"
          UNION SELECT 1 as dev_Order, "Study"

          UNION SELECT 10 as dev_Order, "Other"
          ORDER BY 1,2 ';


	$result = $db->query($sql);
	// arrays of rows
	$tableData = array();
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		$tableData[] = array('order' => $row['dev_Order'],
			'name' => $row['Location']);
	}
	// Return json
	echo json_encode($tableData);
}

//  Query Device Data
function getNetworkNodes() {
	global $db;

	// Device Data
	$sql = 'SELECT * FROM network_infrastructure';
	$result = $db->query($sql);
	// arrays of rows
	$tableData = array();
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		// Push row data
		$tableData[] = array('id' => $row['device_id'],
			'name' => $row['net_device_name'] . '/' . substr($row['net_device_typ'], 2));
	}
	// Control no rows
	if (empty($tableData)) {
		$tableData = [];
	}
	// Return json
	echo json_encode($tableData);
}

//  Status Where conditions
function getDeviceCondition($deviceStatus, $scanSource) {
	global $db;

	if (!is_scalar($scanSource) || $scanSource === '') {
		$scanSource = 'local';
	}
	$scanSource = (string) $scanSource;
	if (!preg_match('/^[a-zA-Z0-9_-]{1,250}$/', $scanSource)) {
		return array('WHERE 1 = 0', array());
	}

	$sourceResult = db_execute_prepared($db, 'SELECT 1 FROM Devices WHERE dev_ScanSource = :scan_source LIMIT 1', array(':scan_source' => $scanSource));
	if (!$sourceResult || !$sourceResult->fetchArray()) {
		return array('WHERE 1 = 0', array());
	}

	$statusConditions = array(
		'all' => 'dev_Archived = 0',
		'connected' => 'dev_Archived = 0 AND dev_PresentLastScan = 1',
		'favorites' => 'dev_Archived = 0 AND dev_Favorite = 1',
		'new' => 'dev_Archived = 0 AND dev_NewDevice = 1',
		'down' => 'dev_Archived = 0 AND dev_AlertDeviceDown = 1 AND dev_PresentLastScan = 0',
		'archived' => 'dev_Archived = 1',
		'presence' => 'dev_Archived = 0 AND dev_PresencePage = 1',
	);
	if (!isset($statusConditions[$deviceStatus])) {
		return array('WHERE 1 = 0', array());
	}

	return array('WHERE dev_ScanSource = :scan_source AND ' . $statusConditions[$deviceStatus], array(':scan_source' => $scanSource));
}

//  Delete Inactive Hosts
function DeleteInactiveHosts() {
	global $pia_lang;
	global $db;

	$result = db_execute_prepared($db, 'SELECT dev_MAC FROM Devices WHERE dev_PresentLastScan = 0 AND dev_LastConnection <= date("now", "-30 day")');
	$success = $result !== false;
	if ($success) {
		$db->exec('BEGIN');
		while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
			$params = array(':mac' => $res['dev_MAC']);
			if (!db_execute_prepared($db, 'DELETE FROM Devices WHERE dev_MAC = :mac', $params)
				|| !db_execute_prepared($db, 'DELETE FROM Events WHERE eve_MAC = :mac', $params)) {
				$success = false;
				break;
			}
		}
		$db->exec($success ? 'COMMIT' : 'ROLLBACK');
	}
	if ($success) {
		echo $pia_lang['BE_Dev_DBTools_DelInactHosts'];
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0015', '', '');
	} else {
		logServerConsole('Inactive device deletion failed: ' . $db->lastErrorMsg());
		echo $pia_lang['BE_Dev_DBTools_DelInactHostsError'];
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0014', '', '');
	}
}

//  List Inactive Hosts
function ListInactiveHosts() {
	global $pia_lang;
	global $db;

	$inactive_hosts[0] = '';

	$i = 1;
	$sql = 'SELECT * FROM Devices WHERE dev_PresentLastScan = 0 AND dev_LastConnection <= date("now", "-30 day") ORDER BY dev_LastConnection DESC';
	$result = $db->query($sql);
	while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
		$inactive_hosts[0] .= $i . '.   ' . $res['dev_Name'] . ' / ' . $res['dev_MAC'] . ' / ' . $res['dev_LastConnection'] . "\n";
		$i++;
	}
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode($inactive_hosts);
}

//  Delete All Notification in WebGUI
function deleteAllNotifications() {
	global $pia_lang;

	$regex = '/[0-9]+-[0-9]+_.*\\.txt/i';
	$reports_path = '../../reports/';
	$files = array_diff(scandir($reports_path, SCANDIR_SORT_DESCENDING), array('.', '..', 'download_report.php'));
	$count_all_reports = sizeof($files);
	foreach ($files as &$item) {
		if (preg_match($regex, $item) == True) {
			unlink($reports_path . $item);
		}
	}
	echo $count_all_reports . ' ' . $pia_lang['BE_Dev_Report_Delete'];
	echo ("<meta http-equiv='refresh' content='2; URL=./reports.php'>");
	// Logging
	pialert_logging('a_050', $_SERVER['REMOTE_ADDR'], 'LogStr_0504', '', '');
}

//  Wake-on-LAN 1/2
function crosscheckMAC($query_mac) {
	global $db;
	$result = db_execute_prepared($db, 'SELECT dev_MAC FROM Devices WHERE dev_MAC = :mac', array(':mac' => (string) $query_mac));
	$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
	return $row ? $row['dev_MAC'] : '';
}

//  Wake-on-LAN 2/2
function wakeonlan() {
	global $pia_lang;
	global $db;

	$WOL_HOST_IP = $GLOBALS["pialert_request"]['ip'];
	$WOL_HOST_MAC = $GLOBALS["pialert_request"]['mac'];

	if (!filter_var($WOL_HOST_IP, FILTER_VALIDATE_IP)) {
		echo "Invalid IP! " . $pia_lang['BackDevDetail_Tools_WOL_error'];exit;
	} elseif (!filter_var($WOL_HOST_MAC, FILTER_VALIDATE_MAC)) {
		echo "Invalid MAC! " . $pia_lang['BackDevDetail_Tools_WOL_error'];exit;
	} elseif (crosscheckMAC($WOL_HOST_MAC) == "") {
		echo "Unknown MAC! " . $pia_lang['BackDevDetail_Tools_WOL_error'];exit;
	}
	exec('wakeonlan ' . $WOL_HOST_MAC, $output_a);
	exec('wakeonlan ' . $WOL_HOST_MAC, $output_b);
	exec('wakeonlan ' . $WOL_HOST_MAC, $output_c);
	echo $pia_lang['BackDevDetail_Tools_WOL_okay'];
	$wol_output = implode('<br>', $output_a) . '<br>' . implode('<br>', $output_b) . '<br>' . implode('<br>', $output_c);
	$wol_output = $wol_output . '<br>IP: ' . $WOL_HOST_IP;
	// Logging
	pialert_logging('a_025', $_SERVER['REMOTE_ADDR'], 'LogStr_0251', '', $wol_output);
}

//  Bulk Deletion
function BulkDeletion() {
	global $db; global $pia_lang;
	$hosts = $GLOBALS["pialert_request"]['hosts'] ?? array(); if (!is_array($hosts)) { $hosts = array(); }
	$hosts = array_values(array_filter($hosts, 'is_scalar'));
	list($placeholders, $params) = db_in_placeholders('mac', $hosts);
	if ($placeholders === '') { echo $pia_lang['Device_bulkDel_back_hosts'] . ': 0'; return; }
	$before = (int)$db->querySingle('SELECT COUNT(*) FROM Devices');
	$db->exec('BEGIN'); $result = db_execute_prepared($db, 'DELETE FROM Devices WHERE dev_MAC IN (' . $placeholders . ')', $params);
	if ($result) { $db->exec('COMMIT'); } else { $db->exec('ROLLBACK'); logServerConsole('Device bulk delete failed: ' . $db->lastErrorMsg()); }
	$after = (int)$db->querySingle('SELECT COUNT(*) FROM Devices');
	echo $pia_lang['Device_bulkDel_back_hosts'] . ': ' . h(implode(', ', $hosts)) . '<br><br>' . $pia_lang['Device_bulkDel_back_before'] . ': ' . $before . '<br>' . $pia_lang['Device_bulkDel_back_after'] . ': ' . $after;
	echo ("<meta http-equiv='refresh' content='2; URL=./devices.php?mod=bulkedit'>");
	pialert_logging('a_021', $_SERVER['REMOTE_ADDR'], 'LogStr_0003', '', implode(',', $hosts));
}

//  Toggle Satellites
function EnableSatelliteScan() {
	global $pia_lang;

	if ($_SESSION['Scan_Satellite'] == True) {
		exec('../../../back/pialert-cli disable_satellites', $output);
		echo $pia_lang['BE_Dev_satellites_disabled'];
		// Logging
		pialert_logging('a_033', $_SERVER['REMOTE_ADDR'], 'LogStr_0306', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>");
	} else {
		exec('../../../back/pialert-cli enable_satellites', $output);
		echo $pia_lang['BE_Dev_satellites_enabled'];
		// Logging
		pialert_logging('a_033', $_SERVER['REMOTE_ADDR'], 'LogStr_0305', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>");
	}
}

//  Toggle Arp Scan
function EnableMainScan() {
	global $pia_lang;

	if ($_SESSION['Scan_MainScan'] == True) {
		exec('../../../back/pialert-cli disable_mainscan', $output);
		echo $pia_lang['BE_Dev_MainScan_disabled'];
		// Logging
		pialert_logging('a_032', $_SERVER['REMOTE_ADDR'], 'LogStr_9992', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>");
	} else {
		exec('../../../back/pialert-cli enable_mainscan', $output);
		echo $pia_lang['BE_Dev_MainScan_enabled'];
		// Logging
		pialert_logging('a_032', $_SERVER['REMOTE_ADDR'], 'LogStr_9991', '', '');
		echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=1'>");
	}
}

//  Return the current Speedtest table and chart data without reloading the page
function getSpeedtestResults() {
	global $db_tools;

	header('Content-Type: application/json');
	$response = array(
		'rows' => array(),
		'chart' => array('labels' => array(), 'ping' => array(), 'down' => array(), 'up' => array())
	);
	$result = $db_tools->query(
		'SELECT speed_date, speed_isp, speed_server, speed_ping, speed_down, speed_up '
		. 'FROM Tools_Speedtest_History ORDER BY speed_date DESC'
	);
	if ($result === false) {
		http_response_code(500);
		echo json_encode($response);
		return;
	}

	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		$response['rows'][] = array(
			(string) ($row['speed_date'] ?? ''),
			(string) ($row['speed_isp'] ?? ''),
			(string) ($row['speed_server'] ?? ''),
			(string) ($row['speed_ping'] ?? ''),
			(string) ($row['speed_down'] ?? ''),
			(string) ($row['speed_up'] ?? '')
		);

		if (count($response['chart']['labels']) < 20) {
			$dateParts = preg_split('/[- :]/', (string) ($row['speed_date'] ?? ''));
			$label = count($dateParts) >= 5
				? $dateParts[2] . '.' . $dateParts[1] . '. ' . $dateParts[3] . ':' . $dateParts[4]
				: (string) ($row['speed_date'] ?? '');
			$response['chart']['labels'][] = $label;
			$response['chart']['ping'][] = (float) ($row['speed_ping'] ?? 0);
			$response['chart']['down'][] = (float) ($row['speed_down'] ?? 0);
			$response['chart']['up'][] = (float) ($row['speed_up'] ?? 0);
		}
	}

	echo json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE);
}

//  Delete all Speedtests
function DeleteSpeedtestResults() {
	global $db;
	global $db_tools;
	global $pia_lang;

	$sql = 'DELETE FROM Tools_Speedtest_History';
	$result = $db_tools->query($sql);

	if ($result == TRUE) {
		echo $pia_lang['BE_Dev_DBTools_DelSpeedtest'];
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0028', '', '');
	} else {
		echo $pia_lang['BE_Dev_DBTools_DelSpeedtestError'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0029', '', '');
	}
}

//  Delete all Nmap Scans
function DeleteNmapScansResults() {
	global $db;
	global $db_tools;
	global $pia_lang;

	$sql = 'DELETE FROM Tools_Nmap_ManScan';
	$result = $db_tools->query($sql);

	if ($result == TRUE) {
		echo $pia_lang['BE_Dev_DBTools_DelNmapScans'];
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0037', '', '');
	} else {
		echo $pia_lang['BE_Dev_DBTools_DelNmapScansError'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0038', '', '');
	}
}

function resetVoidedEvents() {
	global $db;
	global $pia_lang;

	$sql = 'UPDATE "Events"
			SET "eve_EventType" = "Disconnected"
			WHERE "eve_EventType" = "VOIDED - Disconnected"';
	$result = $db->query($sql);

	$sql = 'UPDATE "Events"
			SET "eve_EventType" = "Connected"
			WHERE "eve_EventType" = "VOIDED - Connected"';
	$result = $db->query($sql);

	if ($result == TRUE) {
		echo $pia_lang['BE_Dev_DBTools_resetVoided'];
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0102', '', '');
	} else {
		echo $pia_lang['BE_Dev_DBTools_resetVoidedError'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0103', '', '');
	}
}
//  End
?>

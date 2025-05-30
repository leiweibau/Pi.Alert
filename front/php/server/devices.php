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

session_start();

if ($_SESSION["login"] != 1) {
	if ($_REQUEST['action'] != "getDevicesTotals") {
	header('Location: ../../index.php');
	exit;
	}
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
	case 'MTXDeletColumnContent':MTXDeletColumnContent();
		break;
     default:logServerConsole('Action: ' . $action);
		break;
	}
}

function MTXDeletColumnContent() {
	global $db;
	global $pia_lang;

	$column = htmlspecialchars($_REQUEST['column']) ?? '';
	$column_content = htmlspecialchars($_REQUEST['ccontent']) ?? '';
	$new_column_content = htmlspecialchars($_REQUEST['nccontent']) ?? '';

	if ($new_column_content !== '') {
	    die("Fehler: Der neue Spalteninhalt muss leer sein, damit gelöscht werden kann.");
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
	    die("Ungültiger Spaltenname: [$column]<br>");
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
	        $ok_message .= "Devices: $changed Eintrag/Einträge geleert<br>";
	    } else {
	        $er_message .= "Fehler beim Prepare für Devices: " . $db->lastErrorMsg() . "<br>";
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
	        $ok_message .= "ICMP_Mon: $changed Eintrag/Einträge geleert<br>";
	    } else {
	        $er_message .= "Fehler beim Prepare für ICMP_Mon: " . $db->lastErrorMsg() . "<br>";
	    }
	}

	// Ausgabe
	if ($er_message == "") {
		echo $column . '-Spalteninhalt erfolgreich gelöscht<br>' . $ok_message;
		// Optionales Logging hier möglich
		//pialert_logging('a_033', $_SERVER['REMOTE_ADDR'], 'LogStr_0004', '', 'ID: '.$satellite_id.' ('.$satellite_name.'/'.$new_satellite_name.')');
	} else {
		echo $pia_lang['BE_Dev_SatUpdateError'] . $er_message;
		// Optionales Logging hier möglich
		//pialert_logging('a_033', $_SERVER['REMOTE_ADDR'], 'LogStr_0004', '', 'ID: '.$satellite_id.' ('.$satellite_name.'/'.$new_satellite_name.')');
	}

	echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=2'>";
}

function MTUpdateColumnContent() {
	global $db;
	global $pia_lang;

	$column = htmlspecialchars($_REQUEST['column']) ?? '';
	$column_content = htmlspecialchars($_REQUEST['ccontent']) ?? '';
	$new_column_content = htmlspecialchars($_REQUEST['nccontent']) ?? '';

	//echo $column." - ".$column_content." - ".$new_column_content;

	$columnMap = [
	    'Group'       => ['Devices' => 'dev_Group',        'ICMP_Mon' => 'icmp_group'],
	    'Owner'       => ['Devices' => 'dev_Owner',        'ICMP_Mon' => 'icmp_owner'],
	    'Type'        => ['Devices' => 'dev_DeviceType',   'ICMP_Mon' => 'icmp_type'],
	    'Location'    => ['Devices' => 'dev_Location',     'ICMP_Mon' => 'icmp_location'],
	    'LinkSpeed'   => ['Devices' => 'dev_LinkSpeed'],
	    'ConnectType' => ['Devices' => 'dev_ConnectionType']
	];

	if (!isset($columnMap[$column])) {
	    die("Ungültiger Spaltenname: [$column]<br>");
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
	        $ok_message .= "Devices aktualisiert: $changed Zeile(n)<br>";
	    } else {
	        $er_message .= "Fehler beim Prepare für Devices: " . $db->lastErrorMsg() . "<br>";
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
	        $ok_message .= "ICMP_Mon aktualisiert: $changed Zeile(n)<br>";
	    } else {
	        $er_message .= "Fehler beim Prepare für ICMP_Mon: " . $db->lastErrorMsg() . "<br>";
	    }
	}

	if ($er_message == "") {
		echo $column . '-Spalte aktualisiert<br>'.$ok_message;
		// Logging
		//pialert_logging('a_033', $_SERVER['REMOTE_ADDR'], 'LogStr_0002', '', 'ID: '.$satellite_id.' ('.$satellite_name.'/'.$new_satellite_name.')');
	} else {
		echo $pia_lang['BE_Dev_SatUpdateError'] . $er_message;
		// Logging
		//pialert_logging('a_033', $_SERVER['REMOTE_ADDR'], 'LogStr_0004', '', 'ID: '.$satellite_id.' ('.$satellite_name.'/'.$new_satellite_name.')');
	}
	echo "<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=2'>";
}

function SaveSatellite() {
	global $db;
	global $pia_lang;

	$currentDateTime = date('Y-m-d H:i');

	$satellite_name        = $_REQUEST['satellite_name'] ?? '';
	$new_satellite_name    = $_REQUEST['changed_satellite_name'] ?? '';
	$satellite_id          = $_REQUEST['sat_id'] ?? '';

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

	$satellite_name = $_REQUEST['satellite_name'] ?? '';
	$satellite_id   = $_REQUEST['sat_id'] ?? '';

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

	$satellite_name = ($_REQUEST['new_satellite_name'] === '') ? 'Satellite' : $_REQUEST['new_satellite_name'];

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
	global $db;
	global $pia_lang;

	$filterid = htmlspecialchars($_REQUEST['filterid']);
	$filtername = htmlspecialchars($_REQUEST['filtername']);
	$filterstring = htmlspecialchars($_REQUEST['filterstring']);
	$filterindex = htmlspecialchars($_REQUEST['filterindex']);
	$filtercolumn = htmlspecialchars($_REQUEST['filtercolumn']);
	$filtergroup = htmlspecialchars($_REQUEST['filtergroup']);

	// sql
	$sql = 'UPDATE Devices_table_filter SET
                 filtername      = "' . quotes($filtername) . '",
                 filterstring    = "' . quotes($filterstring) . '",
                 reserve_a       = "' . quotes($filterindex) . '",
                 reserve_b       = "' . quotes($filtercolumn) . '",
                 reserve_c       = "' . quotes($filtergroup) . '"
          WHERE id="' . $filterid . '"';
	$result = $db->query($sql);

	if ($result == TRUE) {
		echo $pia_lang['BE_Dev_Upd_Filter'];
		// Logging
		pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0046', '', 'ID: '.$filterid);
	} else {
		echo $pia_lang['BE_Dev_Upd_FilterError'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0047', '', 'ID: '.$filterid);
	}
	echo ("<meta http-equiv='refresh' content='2; URL=./maintenance.php?tab=4'>");
}

function SetDeviceFilter() {
	global $db;
	global $pia_lang;

	$colfilterarray = array();
	if ($_REQUEST['fname'] == 0) {array_push($colfilterarray, "0");}
	if ($_REQUEST['fowner'] == 0) {array_push($colfilterarray, "2");}
	if ($_REQUEST['fgroup'] == 0) {array_push($colfilterarray, "5");}
	if ($_REQUEST['flocation'] == 0) {array_push($colfilterarray, "6");}
	if ($_REQUEST['ftype'] == 0) {array_push($colfilterarray, "3");}
	if ($_REQUEST['fip'] == 0) {array_push($colfilterarray, "9");}
	if ($_REQUEST['fmac'] == 0) {array_push($colfilterarray, "11");}
	if ($_REQUEST['fvendor'] == 0) {array_push($colfilterarray, "12");}
	if ($_REQUEST['fconnectiont'] == 0) {array_push($colfilterarray, "1");}

	$newcolfilter = implode(",", $colfilterarray);

	$filtername = filter_var($_REQUEST['filtername'], FILTER_SANITIZE_STRING);
	$filterstring = filter_var($_REQUEST['filterstring'], FILTER_SANITIZE_STRING);
	$filtergroup = filter_var($_REQUEST['filtergroup'], FILTER_SANITIZE_STRING);
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
				$sql_insert_data = 'INSERT INTO Devices_table_filter ("filtername", "filterstring", "reserve_b", "reserve_c") 
		                               VALUES ("' . $filtername . '", "' . $filterstring . '", "' . $newcolfilter . '", "' . $filtergroup . '")';

				$result = $db->query($sql_insert_data);
				echo $pia_lang['BE_Dev_table_filter_ok_a'] . '"' .$filtername . '"' . $pia_lang['BE_Dev_table_filter_ok_b'] . '"' .$filterstring . '"' . $pia_lang['BE_Dev_table_filter_ok_c'];
				pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0042', '', $filtername.'/'.$filterstring);
			} catch (Exception $e) {
				die($pia_lang['BE_Dev_table_filter_error_a'] . '"' .$filtername . '"' . $pia_lang['BE_Dev_table_filter_error_b'] . '"' .$filterstring . '"' . $pia_lang['BE_Dev_table_filter_error_c']);
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
	global $db;
	global $pia_lang;

	$filterstring = filter_var($_REQUEST['filterstring'], FILTER_SANITIZE_STRING);
	$sql = 'DELETE FROM Devices_table_filter WHERE filterstring="' . $filterstring . '"';
	// execute sql
	$result = $db->query($sql);

	echo $pia_lang['BE_Dev_table_delfilter_ok'] . $filterstring;
	pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0045', '', $filterstring);
	echo ("<meta http-equiv='refresh' content='2; URL=./devices.php'>");
}

//  Query Device Data
function getDeviceData() {
	global $db;

	// Request Parameters
	$periodDate = getDateFromPeriod();
	$mac = $_REQUEST['mac'];
	// Device Data
	$sql = 'SELECT rowid, *,
            CASE WHEN dev_AlertDeviceDown=1 AND dev_PresentLastScan=0 THEN "Down"
                 WHEN dev_PresentLastScan=1 THEN "On-line"
                 ELSE "Off-line" END as dev_Status
          FROM Devices
          WHERE dev_MAC="' . $mac . '" or cast(rowid as text)="' . $mac . '"';
	$result = $db->query($sql);
	$row = $result->fetchArray(SQLITE3_ASSOC);
	$deviceData = $row;
	$mac = $deviceData['dev_MAC'];
	$deviceData['dev_Name'] = strval($deviceData['dev_Name']);
	$deviceData['dev_Owner'] = strval($deviceData['dev_Owner']);
	$deviceData['dev_Model'] = strval($deviceData['dev_Model']);
	$deviceData['dev_Vendor'] = strval($deviceData['dev_Vendor']);
	$deviceData['dev_Serialnumber'] = strval($deviceData['dev_Serialnumber']);
	$deviceData['dev_Network_Node_MAC'] = $row['dev_Infrastructure'];
	$deviceData['dev_Network_Node_port'] = $row['dev_Infrastructure_port'];
	$deviceData['dev_FirstConnection'] = formatDate($row['dev_FirstConnection']); // Date formated
	$deviceData['dev_LastConnection'] = formatDate($row['dev_LastConnection']); // Date formated
	$deviceData['dev_RandomMAC'] = (in_array($mac[1], array("2", "6", "A", "E", "a", "e")) ? 1 : 0);
	// Count Totals
	$condition = ' WHERE eve_MAC="' . $mac . '" AND eve_DateTime >= ' . $periodDate;
	// Connections
	$sql = 'SELECT COUNT(*) FROM Sessions
          WHERE ses_MAC="' . $mac . '"
          AND (   ses_DateTimeConnection    >= ' . $periodDate . '
               OR ses_DateTimeDisconnection >= ' . $periodDate . '
               OR ses_StillConnected = 1 )';
	$result = $db->query($sql);
	$row = $result->fetchArray(SQLITE3_NUM);
	$deviceData['dev_Sessions'] = $row[0];
	// Events
	$sql = 'SELECT COUNT(*) FROM Events ' . $condition . ' AND eve_EventType <> "Connected" AND eve_EventType <> "Disconnected" ';
	$result = $db->query($sql);
	$row = $result->fetchArray(SQLITE3_NUM);
	$deviceData['dev_Events'] = $row[0];
	// Down Alerts
	$sql = 'SELECT COUNT(*) FROM Events ' . $condition . ' AND eve_EventType = "Device Down"';
	$result = $db->query($sql);
	$row = $result->fetchArray(SQLITE3_NUM);
	$deviceData['dev_DownAlerts'] = $row[0];
	// Presence hours
	$sql = 'SELECT CAST(( MAX (0, SUM (julianday (IFNULL (ses_DateTimeDisconnection, DATETIME("now","localtime")))
                                     - julianday (CASE WHEN ses_DateTimeConnection < ' . $periodDate . ' THEN ' . $periodDate . '
                                                       ELSE ses_DateTimeConnection END)) *24 )) AS INT)
          FROM Sessions
          WHERE ses_MAC="' . $mac . '"
            AND ses_DateTimeConnection IS NOT NULL
            AND (ses_DateTimeDisconnection IS NOT NULL OR ses_StillConnected = 1 )
            AND (   ses_DateTimeConnection    >= ' . $periodDate . '
                 OR ses_DateTimeDisconnection >= ' . $periodDate . '
                 OR ses_StillConnected = 1 )';
	$result = $db->query($sql);
	$row = $result->fetchArray(SQLITE3_NUM);
	$deviceData['dev_PresenceHours'] = round($row[0]);
	// Return json
	echo json_encode($deviceData);
}

//  Update Device Data
function setDeviceData() {
	global $db;
	global $pia_lang;

	// sql
	$sql = 'UPDATE Devices SET
                 dev_Name                 = "' . quotes($_REQUEST['name']) . '",
                 dev_Owner                = "' . quotes($_REQUEST['owner']) . '",
                 dev_DeviceType           = "' . quotes($_REQUEST['type']) . '",
                 dev_Vendor               = "' . quotes($_REQUEST['vendor']) . '",
                 dev_Model                = "' . quotes($_REQUEST['model']) . '",
                 dev_Serialnumber         = "' . quotes($_REQUEST['serialnumber']) . '",
                 dev_Favorite             = "' . quotes($_REQUEST['favorite']) . '",
                 dev_PresencePage         = "' . quotes($_REQUEST['showpresence']) . '",
                 dev_Group                = "' . quotes($_REQUEST['group']) . '",
                 dev_Location             = "' . quotes($_REQUEST['location']) . '",
                 dev_Comments             = "' . quotes($_REQUEST['comments']) . '",
                 dev_Infrastructure       = "' . quotes($_REQUEST['networknode']) . '",
                 dev_Infrastructure_port  = "' . quotes($_REQUEST['networknodeport']) . '",
                 dev_ConnectionType       = "' . quotes($_REQUEST['connectiontype']) . '",
                 dev_LinkSpeed            = "' . quotes($_REQUEST['linkspeed']) . '",
                 dev_StaticIP             = "' . quotes($_REQUEST['staticIP']) . '",
                 dev_ScanCycle            = "' . quotes($_REQUEST['scancycle']) . '",
                 dev_AlertEvents          = "' . quotes($_REQUEST['alertevents']) . '",
                 dev_AlertDeviceDown      = "' . quotes($_REQUEST['alertdown']) . '",
                 dev_SkipRepeated         = "' . quotes($_REQUEST['skiprepeated']) . '",
                 dev_Scan_Validation      = "' . quotes($_REQUEST['scanvalid']) . '",
                 dev_NewDevice            = "' . quotes($_REQUEST['newdevice']) . '",
                 dev_Archived             = "' . quotes($_REQUEST['archived']) . '"
          WHERE dev_MAC="' . $_REQUEST['mac'] . '"';
	$result = $db->query($sql);

	if ($result == TRUE) {
		echo $pia_lang['BE_Dev_DBTools_UpdDev'];
		// Logging
		pialert_logging('a_020', $_SERVER['REMOTE_ADDR'], 'LogStr_0002', '', $_REQUEST['mac']);
	} else {
		echo $pia_lang['BE_Dev_DBTools_UpdDevError'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_020', $_SERVER['REMOTE_ADDR'], 'LogStr_0004', '', $_REQUEST['mac']);
	}
}

//  Delete Device
function deleteDevice() {
	global $db;
	global $pia_lang;

	// sql
	$sql = 'DELETE FROM Devices WHERE dev_MAC="' . $_REQUEST['mac'] . '"';
	// execute sql
	$result = $db->query($sql);
	// check result
	if ($result == TRUE) {
		echo $pia_lang['BE_Dev_DBTools_DelDev_a'];
		// Logging
		pialert_logging('a_020', $_SERVER['REMOTE_ADDR'], 'LogStr_0003', '', $_REQUEST['mac']);
	} else {
		echo $pia_lang['BE_Dev_DBTools_DelDevError_a'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_020', $_SERVER['REMOTE_ADDR'], 'LogStr_0005', '', $_REQUEST['mac']);
	}
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
	global $db;
	global $pia_lang;

	$sql = 'DELETE FROM Events WHERE eve_MAC="' . $_REQUEST['mac'] . '"';
	$result = $db->query($sql);

	if ($result == TRUE) {
		echo $pia_lang['BE_Dev_DBTools_DelEvents'];
		// Logging
		pialert_logging('a_020', $_SERVER['REMOTE_ADDR'], 'LogStr_0020', '', $_REQUEST['mac']);
	} else {
		echo $pia_lang['BE_Dev_DBTools_DelEventsError'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_020', $_SERVER['REMOTE_ADDR'], 'LogStr_0021', '', $_REQUEST['mac']);
	}
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

	if (!$_REQUEST['scansource']) {$scansource = 'local';} else {$scansource = $_REQUEST['scansource'];}
	// combined query
	$result = $db->query(
		'SELECT
        (SELECT COUNT(*) FROM Devices ' . getDeviceCondition('all',$scansource) . ') as devices,
        (SELECT COUNT(*) FROM Devices ' . getDeviceCondition('connected',$scansource) . ') as connected,
        (SELECT COUNT(*) FROM Devices ' . getDeviceCondition('favorites',$scansource) . ') as favorites,
        (SELECT COUNT(*) FROM Devices ' . getDeviceCondition('new',$scansource) . ') as new,
        (SELECT COUNT(*) FROM Devices ' . getDeviceCondition('down',$scansource) . ') as down,
        (SELECT COUNT(*) FROM Devices ' . getDeviceCondition('archived',$scansource) . ') as archived,
        (SELECT COUNT(*) FROM Devices ' . getDeviceCondition('presence',$scansource) . ') as presence
   ');
	$row = $result->fetchArray(SQLITE3_NUM);
	echo json_encode(array($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6]));
}

//  Query the List of devices in a determined Status
function getDevicesList() {
	global $db;

	$condition = getDeviceCondition($_REQUEST['status'],$_REQUEST['scansource']);
	$sql = 'SELECT rowid, *, CASE
            WHEN dev_AlertDeviceDown=1 AND dev_PresentLastScan=0 THEN "Down"
            WHEN dev_NewDevice=1 AND dev_PresentLastScan=1 THEN "NewON"
            WHEN dev_NewDevice=1 AND dev_PresentLastScan=0 THEN "NewOFF"
            WHEN dev_Scan_Validation > 0 AND dev_Scan_Validation_State > 0 AND dev_Scan_Validation_State <= dev_Scan_Validation AND dev_PresentLastScan=1 THEN "OnlineV"
            WHEN dev_PresentLastScan=1 THEN "On-line"
            ELSE "Off-line"
          END AS dev_Status
          FROM Devices ' . $condition;
	$result = $db->query($sql);
	// arrays of rows
	$tableData = array();
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		$tableData['data'][] = array($row['dev_Name'],
			$row['dev_ConnectionType'],
			$row['dev_Owner'],
			$row['dev_DeviceType'],
			$row['dev_Favorite'],
			$row['dev_Group'],
			$row['dev_Location'],
			formatDate($row['dev_FirstConnection']),
			formatDate($row['dev_LastConnection']),
			$row['dev_LastIP'],
			(in_array($row['dev_MAC'][1], array("2", "6", "A", "E", "a", "e")) ? 1 : 0),
			$row['dev_MAC'], // MAC (hidden)
			$row['dev_Vendor'],
			$row['dev_Status'],
			formatIPlong($row['dev_LastIP']), // IP orderable
			$row['dev_ScanSource'],
			$row['rowid'], // Rowid (hidden)
		);
	}
	// Control no rows
	if (empty($tableData['data'])) {
		$tableData['data'] = '';
	}
	// Return json
	echo json_encode($tableData);
}

//  Query the List of devices for calendar
function getDevicesListCalendar() {
	global $db;

	$condition = getDeviceCondition($_REQUEST['status'],$_REQUEST['scansource']);
	$result = $db->query('SELECT * FROM Devices ' . $condition . ' AND dev_PresencePage=1');

	// arrays of rows
	$tableData = array();
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		if ($row['dev_Favorite'] == 1) {
			$row['dev_Name'] = '<span class="text-yellow">&#9733</span>&nbsp' . $row['dev_Name'];
		}

		$tableData[] = array('id' => $row['dev_MAC'],
			'title' => $row['dev_Name'],
			'favorite' => $row['dev_Favorite']);
	}
	// Return json
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

	$sql = 'SELECT DISTINCT 1 as dev_Order, dev_LinkSpeed
          FROM Devices
          WHERE dev_LinkSpeed NOT IN ("(unknown)", "Others") AND dev_LinkSpeed <> ""
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

	$sql = 'SELECT DISTINCT 1 as dev_Order, dev_ConnectionType
          FROM Devices
          WHERE dev_ConnectionType NOT IN ("(unknown)", "Others") AND dev_ConnectionType <> ""
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
function getDeviceCondition($deviceStatus, $scansource) {
	if ($scansource == "all") {$scansource_query = "";} else {$scansource_query = 'dev_ScanSource="'.$scansource.'" AND ';}
	switch ($deviceStatus) {
	case 'all':return 'WHERE '.$scansource_query.'dev_Archived=0';
		break;
	case 'connected':return 'WHERE '.$scansource_query.'dev_Archived=0 AND dev_PresentLastScan=1';
		break;
	case 'favorites':return 'WHERE '.$scansource_query.'dev_Archived=0 AND dev_Favorite=1';
		break;
	case 'new':return 'WHERE '.$scansource_query.'dev_Archived=0 AND dev_NewDevice=1';
		break;
	case 'down':return 'WHERE '.$scansource_query.'dev_Archived=0 AND dev_AlertDeviceDown=1 AND dev_PresentLastScan=0';
		break;
	case 'archived':return 'WHERE '.$scansource_query.'dev_Archived=1';
		break;
	case 'presence':return 'WHERE '.$scansource_query.'dev_Archived=0 AND dev_PresencePage=1';
		break;
	default:return 'WHERE '.$scansource_query.'AND 1=0';
		break;
	}
}

//  Delete Inactive Hosts
function DeleteInactiveHosts() {
	global $pia_lang;
	global $db;

	$sql = 'SELECT * FROM Devices WHERE dev_PresentLastScan = 0 AND dev_LastConnection <= date("now", "-30 day")';
	$result = $db->query($sql);
	while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
		$sql_dev = 'DELETE FROM Devices WHERE dev_MAC="' . $res['dev_MAC'] . '"';
		$result_dev = $db->query($sql_dev);
		$sql_evt = 'DELETE FROM Events WHERE eve_MAC="' . $res['dev_MAC'] . '"';
		$result_evt = $db->query($sql_evt);
	}
	//check result
	if ($result_dev == TRUE && $result_evt == TRUE) {
		echo $pia_lang['BE_Dev_DBTools_DelInactHosts'];
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0015', '', '');
	} else {
		echo $pia_lang['BE_Dev_DBTools_DelInactHostsError'] . '<br>' . "\n\n$sql_loop \n\n" . $db->lastErrorMsg();
		// Logging
		pialert_logging('a_010', $_SERVER['REMOTE_ADDR'], 'LogStr_0014', '', '');
	}
}

//  List Inactive Hosts
function ListInactiveHosts() {
	global $pia_lang;
	global $db;

	$inactive_hosts = array();

	$i=1;
	$sql = 'SELECT * FROM Devices WHERE dev_PresentLastScan = 0 AND dev_LastConnection <= date("now", "-30 day") ORDER BY dev_LastConnection DESC';
	$result = $db->query($sql);
	while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
		$inactive_hosts[0] .= $i .'.&nbsp;&nbsp;&nbsp;'.$res['dev_Name'] . '&nbsp;&nbsp;/&nbsp;&nbsp;' . $res['dev_MAC'] . '&nbsp;&nbsp;/&nbsp;&nbsp;' . $res['dev_LastConnection'] ."<br>";
		$i++;
	}
	echo (json_encode($inactive_hosts));
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
	$sql = 'SELECT * FROM Devices WHERE dev_MAC="' . $query_mac . '"';
	$result = $db->query($sql);
	$row = $result->fetchArray(SQLITE3_ASSOC);
	return $row['dev_MAC'];
}

//  Wake-on-LAN 2/2
function wakeonlan() {
	global $pia_lang;
	global $db;

	$WOL_HOST_IP = $_REQUEST['ip'];
	$WOL_HOST_MAC = $_REQUEST['mac'];

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
	global $db;
	global $pia_lang;

	$hosts = '"' . implode('","', $_REQUEST['hosts']) . '"';
	$journal_hosts = implode(',', $_REQUEST['hosts']);
	echo $pia_lang['Device_bulkDel_back_hosts'] . ': ' . str_replace(",", ", ", $hosts) . '<br><br>';

	$sql = "SELECT COUNT(*) AS row_count FROM Devices";
	$result = $db->query($sql);

	$row = $result->fetchArray();
	$rowCount_before = $row['row_count'];

	$sql = "DELETE FROM Devices WHERE dev_MAC IN ($hosts)";
	$result = $db->query($sql);

	$sql = "SELECT COUNT(*) AS row_count FROM Devices";
	$result = $db->query($sql);

	$row = $result->fetchArray();
	$rowCount_after = $row['row_count'];

	echo $pia_lang['Device_bulkDel_back_before'] . ': ' . $rowCount_before . '<br>' . $pia_lang['Device_bulkDel_back_after'] . ': ' . $rowCount_after;
	echo ("<meta http-equiv='refresh' content='2; URL=./devices.php?mod=bulkedit'>");

	// Logging
	pialert_logging('a_021', $_SERVER['REMOTE_ADDR'], 'LogStr_0003', '', $journal_hosts);

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

//  Delete all Speedtests
function DeleteSpeedtestResults() {
	global $db;
	global $pia_lang;

	$sql = 'DELETE FROM Tools_Speedtest_History';
	$result = $db->query($sql);

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
	global $pia_lang;

	$sql = 'DELETE FROM Tools_Nmap_ManScan';
	$result = $db->query($sql);

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

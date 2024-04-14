<?php
session_start();

if ($_SESSION["login"] != 1) {
	header('Location: ../index.php');
	exit;
}

foreach (glob("../config/setting_language*") as $filename) {
	$pia_lang_selected = str_replace('setting_language_', '', basename($filename));
}
if (strlen($pia_lang_selected) == 0) {$pia_lang_selected = 'en_us';}

require '../php/server/db.php';
require '../php/server/journal.php';
require '../php/templates/language/' . $pia_lang_selected . '.php';

$DBFILE = '../../db/pialert.db';
$PIA_HOST_IP = $_REQUEST['host'];
$PIA_SCAN_TIME = date('Y-m-d H:i:s');

OpenDB();

function crosscheckIP($query_ip) {
	global $db;

	$sql = 'SELECT dev_LastIP FROM Devices WHERE dev_LastIP="' . $query_ip . '" UNION
        SELECT icmp_ip AS dev_LastIP FROM ICMP_Mon WHERE icmp_ip="' . $query_ip . '"';
	$result = $db->query($sql);
	$row = $result->fetchArray(SQLITE3_ASSOC);
	$neededIP = $row['dev_LastIP'];
	return $neededIP;
}

if (filter_var($PIA_HOST_IP, FILTER_VALIDATE_IP)) {

	// Check if IP is already known and in DB
	$db_crosscheck = crosscheckIP($PIA_HOST_IP);
	if (isset($db_crosscheck)) {

		$CSVFILE = '';
		$results = $db->query('SELECT * FROM Tools_Nmap_ManScan WHERE scan_target="' . $PIA_HOST_IP . '" ORDER BY scan_date DESC');
		while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
		    $CSVFILE .= '"' . $row['ID'] . '",';
		    $CSVFILE .= '"' . $row['scan_date'] . '",';
		    $CSVFILE .= '"' . $row['scan_target'] . '",';
		    $CSVFILE .= '"' . $row['scan_type'] . '",';
		    $CSVFILE .= '"' . str_replace("\n", " / ", str_replace("###", "-", $row['scan_result'])) . '",';
		    $CSVFILE .= '"' . $row['reserve_a'] . '",';
		    $CSVFILE .= '"' . $row['reserve_b'] . '",';
		    $CSVFILE .= '"' . $row['reserve_c'] . '",';
		    $CSVFILE .= '"' . $row['reserve_d'] . '",';
		    $CSVFILE .= "\n";
		}

		header('Content-Description: File Transfer');
		header("Content-Type: text/csv");
		header('Content-Disposition: attachment; filename=Host_'.str_replace(".", "-", $PIA_HOST_IP).'.csv');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . strlen($CSVFILE));

		echo $CSVFILE;

		// Logging
		pialert_logging('a_002', $_SERVER['REMOTE_ADDR'], 'LogStr_0221', '', '');
	} else {
		echo "Unknown IP";
		// Logging
		pialert_logging('a_002', $_SERVER['REMOTE_ADDR'], 'LogStr_0222', '', '');
		exit;}
} else {
	echo "Wrong parameter";
	// Logging
	pialert_logging('a_002', $_SERVER['REMOTE_ADDR'], 'LogStr_0223', '', '');
	exit;
}

?>

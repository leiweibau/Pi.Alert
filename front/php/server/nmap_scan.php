<?php
ini_set('max_execution_time', '60');
set_time_limit(60);
session_start();

if ($_SESSION["login"] != 1) {
	header('Location: ../../index.php');
	exit;
}

foreach (glob("../../../db/setting_language*") as $filename) {
	$pia_lang_selected = str_replace('setting_language_', '', basename($filename));
}
if (strlen($pia_lang_selected) == 0) {$pia_lang_selected = 'en_us';}

require 'db.php';
require 'journal.php';
require '../templates/language/' . $pia_lang_selected . '.php';

$DBFILE = '../../../db/pialert.db';
$PIA_HOST_IP = $_REQUEST['scan'];
$PIA_SCAN_MODE = $_REQUEST['mode'];

// Open DB
OpenDB();

// Check given host/mac
function crosscheckIP($query_ip) {
	global $db;
	$sql = 'SELECT * FROM Devices WHERE dev_LastIP="' . $query_ip . '"';
	$result = $db->query($sql);
	$row = $result->fetchArray(SQLITE3_ASSOC);
	$neededIP = $row['dev_LastIP'];
	if ($neededIP == "") {
		$sql = 'SELECT * FROM ICMP_Mon WHERE icmp_ip="' . $query_ip . '"';
		$result = $db->query($sql);
		$row = $result->fetchArray(SQLITE3_ASSOC);
		$neededIP = $row['icmp_ip'];
	}
	return $neededIP;
}
// Find start and end of the nmap port list
function nmap_search_portlist($arr) {
	$array_pointer = array();
    foreach($arr as $index => $string) {
        if (substr($string, 0, 4) == "PORT") {$array_pointer['start'] = $index+1;}
        if (($string == "" || substr($string, 0, 11) == "MAC Address") && $array_pointer['start'] != "") {
        	$array_pointer['end'] = $index;
        	return $array_pointer;
        }
    }
    return $array_pointer;
}
// Convert portlist
function nmap_trim_portlist($P_start, $P_end, $array) {
	$length = $P_end - $P_start;
	$raw_portlist = array_splice($array, $P_start, $length);
	$final_portlist = array();
	for ($i=0;$i<sizeof($raw_portlist);++$i) {
		$rawline = array_values(array_filter(explode(" ", $raw_portlist[$i])));
		$final_portlist[$i]['service'] = trim($rawline[2]);
		$final_portlist[$i]['status'] = trim($rawline[1]);
		$raw_subline = explode("/", $rawline[0]);
		$final_portlist[$i]['port'] = trim($raw_subline[0]);
		$final_portlist[$i]['protocol'] = trim($raw_subline[1]);
	}
	return $final_portlist;
}

// Check if IP is valid
if (filter_var($PIA_HOST_IP, FILTER_VALIDATE_IP)) {

	// Check if IP is already known and in DB
	$db_crosscheck = crosscheckIP($PIA_HOST_IP);
	if (isset($db_crosscheck)) {
		if ($PIA_SCAN_MODE == 'fast') {
			exec('nmap -F ' . $PIA_HOST_IP, $nmap_scan_results);
		} elseif ($PIA_SCAN_MODE == 'normal') {
			exec('nmap ' . $PIA_HOST_IP, $nmap_scan_results);
		} elseif ($PIA_SCAN_MODE == 'detail') {
			exec('sudo nmap -sU -sT -p U:53,67-69,111,137,512,514,525,1701,1719,T:1-65535 --max-retries 0 ' . $PIA_HOST_IP, $nmap_scan_results);
		}
		// Logging
		pialert_logging('a_002', $_SERVER['REMOTE_ADDR'], 'LogStr_0210', '', $PIA_SCAN_MODE . ' Scan: ' . $PIA_HOST_IP);
	} else {echo "Unknown IP";exit;}
} else {echo "Wrong parameter";exit;}
echo '<h4>Scan (' . $PIA_SCAN_MODE . ') Results of: ' . $PIA_HOST_IP . '</h4>';
echo '<pre style="border: none;">';

// Prepare short term memory
$PIA_SCAN_TIME = date('Y-m-d H:i:s');

unset($_SESSION['ScanShortMem_NMAP']);
$_SESSION['ScanShortMem_NMAP'] = 'Last Nmap Scan<br><br><span style="display:inline-block; width: 100px;">Scan Target:</span> ' . $PIA_HOST_IP . '<br><span style="display:inline-block; width: 100px;">Scan Mode:</span> ' . $PIA_SCAN_MODE . '<br><span style="display:inline-block; width: 100px;">Scan Time:</span> ' . $PIA_SCAN_TIME . '<br><br>Result:<br>';

// Get start and end of the nmap portlist
$array_pointer = nmap_search_portlist($nmap_scan_results);
if (sizeof($array_pointer) == 2) {
	// if start and end pointer
 	$nmap_scan_portlist = nmap_trim_portlist($array_pointer['start'], $array_pointer['end'], $nmap_scan_results);	
} else {
	// empty array if no start and end pointer
    $nmap_scan_portlist = array();
}

// Output formated nmap report
if (sizeof($nmap_scan_portlist) > 0) {
	echo "Port\tProtocol\tStatus\tService\n";
	foreach ($nmap_scan_portlist as $line) {
		if ($line['status'] != "open|filtered") {
			echo $line['port'] . "\t" . $line['protocol'] . "\t\t" . $line['status'] . "\t". $line['service'] . "\n";
			// Safe last Scan result in Session (Short term memory)
			$_SESSION['ScanShortMem_NMAP'] = $_SESSION['ScanShortMem_NMAP'] . $line['port'] . " - " . $line['protocol'] . " - " . $line['status'] . " - ". $line['service'] . '<br>';
		}
	}
} else {
	echo $pia_lang['nmap_no_scan_results'];
}

echo '</pre>';

?>
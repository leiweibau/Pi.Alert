<?php
ini_set('max_execution_time', '80');
set_time_limit(80);
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
$PIA_SCAN_TIME = date('Y-m-d H:i:s');

// Open DB
OpenDB();

// functions -------------------------------------------------------
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
// Format portlist output
function create_portlist_table($portliststring) {
	$temp_array = explode("\n", $portliststring);
	for ($i=0;$i<sizeof($temp_array);$i++) {
		$temp_ports = explode("###", $temp_array[$i]);
		echo '<div class="row">
		          <div class="col-xs-2">'.$temp_ports[0] .'</div>
		          <div class="col-xs-2">'. $temp_ports[1] .'</div>
		          <div class="col-xs-2">'. $temp_ports[2] . '</div>
		          <div class="col-xs-6">'. $temp_ports[3] . '</div>
		      </div>';
	}
}

// Main action (Scan Mode)-------------------------------------------------------
// Check if IP is valid
if ($_REQUEST['mode'] != "view") {
	if (filter_var($PIA_HOST_IP, FILTER_VALIDATE_IP)) {

		// Check if IP is already known and in DB
		$db_crosscheck = crosscheckIP($PIA_HOST_IP);
		if (isset($db_crosscheck)) {
			if ($PIA_SCAN_MODE == 'fast') {
				exec('timeout 60 nmap -F ' . $PIA_HOST_IP, $nmap_scan_results);
			} elseif ($PIA_SCAN_MODE == 'normal') {
				exec('timeout 60 nmap ' . $PIA_HOST_IP, $nmap_scan_results);
			} elseif ($PIA_SCAN_MODE == 'detail') {
				exec('timeout 60 sudo nmap -sU -sT -p U:53,67-69,111,137,512,514,525,1701,1719,T:1-65535 --max-retries 0 ' . $PIA_HOST_IP, $nmap_scan_results);
			}
			// Logging
			pialert_logging('a_002', $_SERVER['REMOTE_ADDR'], 'LogStr_0210', '', $PIA_SCAN_MODE . ' Scan: ' . $PIA_HOST_IP);
		} else {echo "Unknown IP";exit;}
	} else {echo "Wrong parameter";exit;}

	// Get start and end of the nmap portlist
	$array_pointer = nmap_search_portlist($nmap_scan_results);
	if (sizeof($array_pointer) == 2) {
		// if start and end pointer
	 	$nmap_scan_portlist = nmap_trim_portlist($array_pointer['start'], $array_pointer['end'], $nmap_scan_results);	
	} else {
		// empty array if no start and end pointer
	    $nmap_scan_portlist = array();
	}

	// Show prev. results
	$res = $db->query('SELECT * FROM Tools_Nmap_ManScan WHERE scan_target="' . $PIA_HOST_IP . '" ORDER BY scan_date DESC LIMIT 1');
	$row = $res->fetchArray();
	if ($row != "") {
		echo '<div class="row">
  				<div class="col-md-6">
  					<div class="row" style="padding-bottom:5px;">
  					   <div class="col-xs-12"><h4>Previous scan results of ' . $row['scan_target'] . '</h4></div>
  					</div>
  					<div class="row" style="padding-bottom:5px;">
  					   <div class="col-xs-4"><b>Scan Date:</b></div>
  					   <div class="col-xs-6"> '.$row['scan_date'].'</div>
  					</div>
  					<div class="row" style="padding-bottom:5px;">
  					   <div class="col-xs-4"><b>Scan Mode:</b></div>
  					   <div class="col-xs-6"> '.$row['scan_type'].'</div>
  					</div>
  					<div class="row" style="">
			           <div class="col-xs-2 text-uppercase"><strong>Port</strong></div>
			           <div class="col-xs-2 text-uppercase"><strong>Protocol</strong></div>
			           <div class="col-xs-2 text-uppercase"><strong>Status</strong></div>
			           <div class="col-xs-6 text-uppercase"><strong>Service</strong></div>
			    	</div>';
		create_portlist_table($row['scan_result']);
		echo '  </div>';
	} else {
		//Open row if no prev scan
		echo '<div class="row">';
	}

	// Process formated nmap report
	if (sizeof($nmap_scan_portlist) > 0) {
		foreach ($nmap_scan_portlist as $line) {
			if ($line['status'] != "open|filtered") {
				// Add line break
				if (isset($PIA_SCAN_RESULT)) {$PIA_SCAN_RESULT = $PIA_SCAN_RESULT."\n";}
				$PIA_SCAN_RESULT = $PIA_SCAN_RESULT . $line['port'] . "###" . $line['protocol'] . "###" . $line['status'] . "###". $line['service'];
			}
		}
		// Output
		if (strlen($PIA_SCAN_RESULT) > 2) {
			echo '<div class="col-md-6">
					<div class="row" style="padding-bottom:5px;">
	  				   <div class="col-xs-12"><h4 class="text-danger">Current scan results of ' . $PIA_HOST_IP . '</h4></div>
	  				</div>
	  				<div class="row" style="padding-bottom:5px;">
	  				   <div class="col-xs-4"><b>Scan Date:</b></div>
					   <div class="col-xs-6 text-danger"> '.$PIA_SCAN_TIME.'</div>
	  				</div>
	  				<div class="row" style="padding-bottom:5px;">
	  				   <div class="col-xs-4"><b>Scan Mode:</b></div>
	  				   <div class="col-xs-6"> '.$PIA_SCAN_MODE.'</div>
					</div>
	  				<div class="row" style="">
				       <div class="col-xs-2 text-uppercase"><strong>Port</strong></div>
			           <div class="col-xs-2 text-uppercase"><strong>Protocol</strong></div>
			           <div class="col-xs-2 text-uppercase"><strong>Status</strong></div>
			           <div class="col-xs-6 text-uppercase"><strong>Service</strong></div>
			    	</div>';
			create_portlist_table($PIA_SCAN_RESULT);
			echo '</div>';

			// Save to db, only if results available
			$sql = 'INSERT INTO "Tools_Nmap_ManScan" ("scan_date", "scan_target", "scan_type", "scan_result", "reserve_a", "reserve_b", "reserve_c", "reserve_d") VALUES("' . $PIA_SCAN_TIME . '", "' . $PIA_HOST_IP . '", "' . $PIA_SCAN_MODE . '", "' . $PIA_SCAN_RESULT . '", "", "", "", "")';
			$result = $db->exec($sql);			
		} else {
			echo '<div class="col-md-6">'.$pia_lang['nmap_no_scan_results'].'</div>';
		}
		// Close row if noch act results
		echo '</div>';

	} else {
		echo '<div class="col-md-6">'.$pia_lang['nmap_no_scan_results'].'</div></div>';
	}

} elseif ($_REQUEST['mode'] == "view") {
// Main action (View Mode)-------------------------------------------------------
	if (filter_var($PIA_HOST_IP, FILTER_VALIDATE_IP)) {
		$res = $db->query('SELECT * FROM Tools_Nmap_ManScan WHERE scan_target="' . $PIA_HOST_IP . '" ORDER BY scan_date DESC LIMIT 1');
		$row = $res->fetchArray();

		if ($row != "") {
			echo '<h4>Last scan Results of ' . $row['scan_target'] . '</h4>';
			echo '<div class="row">
      				<div class="col-md-6">
      					<div class="row" style="padding-bottom:5px;">
      					   <div class="col-xs-4"><b>Scan Date:</b></div>
      					   <div class="col-xs-6"> '.$row['scan_date'].'</div>
      					</div>
      					<div class="row" style="padding-bottom:5px;">
      					   <div class="col-xs-4"><b>Scan Mode:</b></div>
      					   <div class="col-xs-6"> '.$row['scan_type'].'</div>
      					</div>
      					<div class="row" style="">
				           <div class="col-xs-2 text-uppercase"><strong>Port</strong></div>
				           <div class="col-xs-2 text-uppercase"><strong>Protocol</strong></div>
				           <div class="col-xs-2 text-uppercase"><strong>Status</strong></div>
				           <div class="col-xs-6 text-uppercase"><strong>Service</strong></div>
				    	</div>';
			create_portlist_table($row['scan_result']);
			echo '  </div>
			      </div>';
		}

	}


}

?>
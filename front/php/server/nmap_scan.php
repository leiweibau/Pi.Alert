<?php
ini_set('max_execution_time', '65');
set_time_limit(65);
require_once __DIR__ . "/session.php";
pialert_start_session();
require_once __DIR__ . '/csrf.php';

if ($_SESSION["login"] != 1) {
	header('Location: ../../index.php');
	exit;
}
require 'timezone.php';
require 'db.php';
require 'journal.php';
require_once 'util.php';
require 'language_switch.php';
require '../templates/language/' . $pia_lang_selected . '.php';

pialert_require_method('POST');
pialert_validate_csrf();
// $DBFILE = '../../../db/pialert.db';
// $DBFILE_TOOLS = '../../../db/pialert_tools.db';
$PIA_HOST_IP = isset($_POST['scan']) && is_scalar($_POST['scan']) ? (string) $_POST['scan'] : '';
$PIA_SCAN_MODE = isset($_POST['mode']) && is_scalar($_POST['mode']) ? (string) $_POST['mode'] : '';
$PIA_DEVICE_MAC = isset($_POST['mac']) && is_scalar($_POST['mac']) ? trim((string) $_POST['mac']) : '';
$PIA_SCAN_TIME = date('Y-m-d H:i:s');

if (!in_array($PIA_SCAN_MODE, array('fast', 'normal', 'view', 'detail', 'detail_status'), true)) {
	http_response_code(400);
	exit('Unsupported scan mode');
}

// Open DB
OpenDB();
OpenDB_Tools();

// functions -------------------------------------------------------
// Check given host/mac
function crosscheckIP($query_ip) {
	global $db;
	$result = db_execute_prepared($db, 'SELECT dev_LastIP FROM Devices WHERE dev_LastIP = :ip UNION SELECT icmp_ip AS dev_LastIP FROM ICMP_Mon WHERE icmp_ip = :ip', array(':ip' => $query_ip));
	$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
	return $row ? $row['dev_LastIP'] : null;
}

function ensure_nmap_queue_schema() {
	global $db_tools;
	$queueSql = 'CREATE TABLE IF NOT EXISTS Tools_Nmap_Queue (
		queue_id INTEGER PRIMARY KEY AUTOINCREMENT,
		device_mac TEXT NOT NULL COLLATE NOCASE,
		target_ip TEXT NOT NULL,
		scan_type TEXT NOT NULL DEFAULT "detail",
		source TEXT NOT NULL DEFAULT "manual",
		status TEXT NOT NULL DEFAULT "queued",
		requested_at TEXT NOT NULL,
		started_at TEXT,
		completed_at TEXT,
		attempts INTEGER NOT NULL DEFAULT 0,
		last_error TEXT,
		result_id INTEGER,
		UNIQUE (device_mac, scan_type)
	)';
	$scheduleSql = 'CREATE TABLE IF NOT EXISTS Tools_Nmap_Schedule (
		schedule_id INTEGER PRIMARY KEY AUTOINCREMENT,
		device_mac TEXT NOT NULL COLLATE NOCASE UNIQUE,
		scan_type TEXT NOT NULL DEFAULT "detail",
		interval_minutes INTEGER NOT NULL,
		next_run_at TEXT,
		enabled INTEGER NOT NULL DEFAULT 0,
		last_queued_at TEXT
	)';
	return $db_tools->exec($queueSql) && $db_tools->exec($scheduleSql);
}

function nmap_json_response($statusCode, $payload) {
	http_response_code($statusCode);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	exit;
}

function get_device_for_nmap_queue($mac, $ip = null) {
	global $db;
	if ($mac === '' || strlen($mac) > 64) {
		return false;
	}
	$sql = 'SELECT dev_MAC, dev_LastIP FROM Devices WHERE dev_MAC = :mac COLLATE NOCASE';
	$params = array(':mac' => $mac);
	if ($ip !== null) {
		$sql .= ' AND dev_LastIP = :ip';
		$params[':ip'] = $ip;
	}
	$sql .= ' LIMIT 1';
	$result = db_execute_prepared($db, $sql, $params);
	return $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
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
	global $pia_lang;
	if (trim((string) $portliststring) === '') {
		echo '<div class="col-xs-12">' . h($pia_lang['nmap_no_scan_results']) . '</div>';
		return;
	}
	$temp_array = explode("\n", $portliststring);
	for ($i=0;$i<sizeof($temp_array);$i++) {
		$temp_ports = explode("###", $temp_array[$i]);
		echo '<div class="row">
		          <div class="col-xs-2">'.h($temp_ports[0] ?? '') .'</div>
		          <div class="col-xs-2">'.h($temp_ports[1] ?? '') .'</div>
		          <div class="col-xs-3">'.h($temp_ports[2] ?? '') . '</div>
		          <div class="col-xs-5">'.h($temp_ports[3] ?? '') . '</div>
		      </div>';
	}
}

function create_scanoutput_box($date, $type, $target, $box_type) {
	global $pia_lang;

	if ($box_type == 'previous') {
		$headline = $pia_lang['DevDetail_Tools_nmap_head_prev'];
		$text_color = '';
		$reloadlink = '<a class="nmappagerelaod nmap-reload" href="#" data-target="' . h($target) . '"><i class="text-aqua fa-solid fa-rotate-left" style="font-size:18px; margin-left: 5px;"></i></a>';}
	elseif ($box_type == 'latest') {
		$headline = $pia_lang['DevDetail_Tools_nmap_head_latest'];
		$text_color = '';
		$reloadlink = '';}
	elseif ($box_type == 'current') {
		$headline = $pia_lang['DevDetail_Tools_nmap_head_cur'];
		$text_color = "text-red";
		$reloadlink = '<a class="nmappagerelaod nmap-reload" href="#" data-target="' . h($target) . '"><i class="text-aqua fa-solid fa-rotate-left" style="font-size:18px; margin-left: 5px;"></i></a>';}

	if ($type == 'fast') {
		$type_lang = $pia_lang['DevDetail_Tools_nmap_buttonFast'];}
	elseif ($type == 'normal') {
		$type_lang = $pia_lang['DevDetail_Tools_nmap_buttonDefault'];}
	elseif ($type == 'detail') {
		$type_lang = $pia_lang['DevDetail_Tools_nmap_buttonDetail'];}

	echo '<div class="col-md-6" style="margin-bottom:20px">
			<div class="row" style="padding-bottom:5px;">
			   <div class="col-xs-12"><span class="'.$text_color.'" style="font-size:18px">'.$headline.'</span> '.$reloadlink.'</div>
			</div>
			<div class="row" style="padding-bottom:5px;">
			   <div class="col-xs-4"><b>'.$pia_lang['ookla_devdetails_table_time'].':</b></div>
			   <div class="col-xs-6 '.$text_color.'">'.h($date).'</div>
			</div>
			<div class="row" style="padding-bottom:5px;">
			   <div class="col-xs-4"><b>'.$pia_lang['nmap_devdetails_scanmode'].':</b></div>
			   <div class="col-xs-6">'.h($type_lang).'</div>
			</div>
			<div class="row" style="padding-bottom:5px;">
			   <div class="col-xs-4"><b>'.$pia_lang['WEBS_tablehead_TargetIP'].':</b></div>
			   <div class="col-xs-6">' . h($target) . '</div>
			</div>
			<div class="row" style="">
           	   <div class="col-xs-2 text-uppercase"><strong>Port</strong></div>
               <div class="col-xs-2 text-uppercase"><strong>Prot.</strong></div>
               <div class="col-xs-3 text-uppercase"><strong>Status</strong></div>
               <div class="col-xs-5 text-uppercase"><strong>Service</strong></div>
    	    </div>';
}

// Detailed scans are queued and processed by back/pialert_tools.py. They must
// never run inside the FastCGI request.
if ($PIA_SCAN_MODE === 'detail' || $PIA_SCAN_MODE === 'detail_status') {
	if (!ensure_nmap_queue_schema()) {
		nmap_json_response(500, array('pending' => false, 'error' => 'queue_unavailable'));
	}
	if ($PIA_SCAN_MODE === 'detail' && !filter_var($PIA_HOST_IP, FILTER_VALIDATE_IP)) {
		nmap_json_response(400, array('pending' => false, 'error' => 'invalid_ip'));
	}

	$device = get_device_for_nmap_queue($PIA_DEVICE_MAC, $PIA_SCAN_MODE === 'detail' ? $PIA_HOST_IP : null);
	if ($device === false) {
		nmap_json_response(400, array('pending' => false, 'error' => 'unknown_device'));
	}

	if ($PIA_SCAN_MODE === 'detail_status') {
		$result = db_execute_prepared(
			$db_tools,
			'SELECT queue_id, status FROM Tools_Nmap_Queue WHERE device_mac = :mac COLLATE NOCASE AND scan_type = :type LIMIT 1',
			array(':mac' => $device['dev_MAC'], ':type' => 'detail')
		);
		$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
		nmap_json_response(200, array(
			'pending' => $row !== false,
			'queue_id' => $row !== false ? (int) $row['queue_id'] : null,
			'status' => $row !== false ? (string) $row['status'] : null,
		));
	}

	if (!$db_tools->exec('BEGIN IMMEDIATE')) {
		nmap_json_response(503, array('pending' => false, 'error' => 'queue_busy'));
	}
	$insert = db_execute_prepared(
		$db_tools,
		'INSERT OR IGNORE INTO Tools_Nmap_Queue (device_mac, target_ip, scan_type, source, status, requested_at, attempts) VALUES (:mac, :ip, :type, :source, :status, :requested, 0)',
		array(
			':mac' => $device['dev_MAC'],
			':ip' => $PIA_HOST_IP,
			':type' => 'detail',
			':source' => 'manual',
			':status' => 'queued',
			':requested' => $PIA_SCAN_TIME,
		)
	);
	if ($insert === false) {
		$db_tools->exec('ROLLBACK');
		nmap_json_response(500, array('pending' => false, 'error' => 'queue_write_failed'));
	}
	$wasQueued = $db_tools->changes() > 0;
	$result = db_execute_prepared(
		$db_tools,
		'SELECT queue_id, status FROM Tools_Nmap_Queue WHERE device_mac = :mac COLLATE NOCASE AND scan_type = :type LIMIT 1',
		array(':mac' => $device['dev_MAC'], ':type' => 'detail')
	);
	$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
	if ($result) {
		$result->finalize();
	}
	if ($row === false || !$db_tools->exec('COMMIT')) {
		$db_tools->exec('ROLLBACK');
		nmap_json_response(500, array('pending' => false, 'error' => 'queue_write_failed'));
	}

	if ($wasQueued) {
		pialert_logging('a_002', $_SERVER['REMOTE_ADDR'] ?? 'webui', 'LogStr_0260', '', 'Detail scan queued: ' . $device['dev_MAC'] . ' / ' . $PIA_HOST_IP);
	}
	nmap_json_response(200, array(
		'pending' => true,
		'queued' => $wasQueued,
		'already_queued' => !$wasQueued,
		'queue_id' => (int) $row['queue_id'],
		'status' => (string) $row['status'],
	));
}

// Main action (Scan Mode)-------------------------------------------------------
// Check if IP is valid
if ($PIA_SCAN_MODE != "view") {
	if (filter_var($PIA_HOST_IP, FILTER_VALIDATE_IP)) {

		// Check if IP is already known and in DB
		$db_crosscheck = crosscheckIP($PIA_HOST_IP);
		if (isset($db_crosscheck)) {
			if ($PIA_SCAN_MODE == 'fast') {
				exec('timeout 60 nmap -F ' . $PIA_HOST_IP, $nmap_scan_results);
			} elseif ($PIA_SCAN_MODE == 'normal') {
				exec('timeout 60 nmap ' . $PIA_HOST_IP, $nmap_scan_results);
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

	echo '<div class="row">';
	// Show prev. results
	$res = db_execute_prepared($db_tools, 'SELECT * FROM Tools_Nmap_ManScan WHERE scan_target = :target ORDER BY scan_date DESC LIMIT 1', array(':target' => $PIA_HOST_IP));
	$row = $res->fetchArray();
	if ($row != "") {
		create_scanoutput_box($row['scan_date'], $row['scan_type'], $row['scan_target'], 'previous');
		create_portlist_table($row['scan_result']);
		echo '  </div>';
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
			create_scanoutput_box($PIA_SCAN_TIME, $PIA_SCAN_MODE, $PIA_HOST_IP, 'current');
			create_portlist_table($PIA_SCAN_RESULT);
			echo '</div>';

			// Save to db, only if results available
			$sql = 'INSERT INTO "Tools_Nmap_ManScan" ("scan_date", "scan_target", "scan_type", "scan_result", "reserve_a", "reserve_b", "reserve_c", "reserve_d") VALUES (:date, :target, :type, :result, :reserve_a, :reserve_b, :reserve_c, :reserve_d)';
				$result = db_execute_prepared($db_tools, $sql, array(':date' => $PIA_SCAN_TIME, ':target' => $PIA_HOST_IP, ':type' => $PIA_SCAN_MODE, ':result' => $PIA_SCAN_RESULT, ':reserve_a' => '', ':reserve_b' => '', ':reserve_c' => '', ':reserve_d' => ''));
		} else {
			echo '<div class="col-md-6">'.$pia_lang['nmap_no_scan_results'].'</div>';
		}
		// Close row if noch act results
		echo '</div>';

	} else {
		echo '<div class="col-md-6">'.$pia_lang['nmap_no_scan_results'].'</div></div>';
	}

    $countResult = db_execute_prepared($db_tools, 'SELECT COUNT(*) AS count_entries FROM Tools_Nmap_ManScan WHERE scan_target = :target', array(':target' => $PIA_HOST_IP));
	$scancounter = $countResult ? (int)$countResult->fetchArray(SQLITE3_ASSOC)['count_entries'] : 0;
	echo $pia_lang['nmap_devdetails_countmsg_a'] . $scancounter . $pia_lang['nmap_devdetails_countmsg_b'];

} elseif ($PIA_SCAN_MODE == "view") {
// Main action (View Mode)-------------------------------------------------------
	if (filter_var($PIA_HOST_IP, FILTER_VALIDATE_IP)) {
		$res = db_execute_prepared($db_tools, 'SELECT * FROM Tools_Nmap_ManScan WHERE scan_target = :target ORDER BY scan_date DESC LIMIT 1', array(':target' => $PIA_HOST_IP));
		$row = $res ? $res->fetchArray() : false;

		if ($row != "") {
	    	$countResult = db_execute_prepared($db_tools, 'SELECT COUNT(*) AS count_entries FROM Tools_Nmap_ManScan WHERE scan_target = :target', array(':target' => $PIA_HOST_IP));
	    $countRow = $countResult ? $countResult->fetchArray(SQLITE3_ASSOC) : array('count_entries' => 0);
	    $scancounter = (int) $countRow['count_entries'];

			echo '<div class="row">';
			create_scanoutput_box($row['scan_date'], $row['scan_type'], $row['scan_target'], 'latest');
			create_portlist_table($row['scan_result']);
			echo '</div>';

			echo '<div class="col-md-6">
					<div class="row">
						<div class="col-xs-12 text-center" style="margin-top:30px">' . $pia_lang['nmap_devdetails_countmsg_a'] . $scancounter . $pia_lang['nmap_devdetails_countmsg_b'] . '</div>
				  	</div>';
			echo '	<div class="row">
						<div class="col-xs-12 text-center" style="margin-top:20px;margin-bottom:20px">
							<a role="button" class="btn btn-primary pa-btn" href="./download/hostnmapresultscvs.php?host='.rawurlencode($PIA_HOST_IP).'">'.$pia_lang['nmap_devdetails_download'].'</a>
						</div>
				  	</div>
				  </div>';
			// Close row
			echo '</div>';
		}
	}
}

?>

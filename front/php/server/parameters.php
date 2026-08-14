<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  parameters.php - Front module. Server side. Manage Parameters
//------------------------------------------------------------------------------
//  Puche 2021              pi.alert.application@gmail.com     GNU GPLv3
//  leiweibau  2024+        https://github.com/leiweibau       GNU GPLv3
//------------------------------------------------------------------------------

require_once __DIR__ . "/session.php";
pialert_start_session();
require_once __DIR__ . '/csrf.php';
error_reporting(0);

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

//  Action selector
// Set maximum execution time to 15 seconds
ini_set('max_execution_time', '15');

// Open DB
OpenDB();

pialert_dispatch_action(
    ['get', 'getJournalParameter', 'getReportParameter'],
    ['set', 'setJournalParameter', 'setReportParameter']
);
// Action functions
if (isset($GLOBALS["pialert_request"]['action']) && !empty($GLOBALS["pialert_request"]['action'])) {
	$action = $GLOBALS["pialert_request"]['action'];
	switch ($action) {
	case 'get':getParameter();
		break;
	case 'set':setParameter();
		break;
	case 'getJournalParameter':getJournalParameter();
		break;
	case 'setJournalParameter':setJournalParameter();
		break;
	case 'setReportParameter':setReportParameter();
		break;
	case 'getReportParameter':getReportParameter();
		break;
	default:logServerConsole('Action: ' . $action);
		break;
	}
}
function saveParameters($par_ID, $par_Long_Value) {
	global $db;

	$result = db_execute_prepared($db, 'SELECT COUNT(*) AS count FROM Parameters WHERE par_ID = :id', array(':id' => $par_ID));
	$row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
	if (!$row) {
		logServerConsole('Parameter lookup failed: ' . $db->lastErrorMsg());
		return false;
	}

	if ((int)$row['count'] > 0) {
		return db_execute_prepared($db, 'UPDATE Parameters SET par_Long_Value = :value WHERE par_ID = :id', array(':value' => $par_Long_Value, ':id' => $par_ID));
	}
	return db_execute_prepared($db, 'INSERT INTO Parameters (par_ID, par_Long_Value) VALUES (:id, :value)', array(':id' => $par_ID, ':value' => $par_Long_Value));
}

//  Get Parameter Value
function getParameter() {
	global $db;

	$parameter = $GLOBALS["pialert_request"]['parameter'] ?? '';
	$result = db_execute_prepared($db, 'SELECT par_Value FROM Parameters WHERE par_ID = :id', array(':id' => is_scalar($parameter) ? (string)$parameter : ''));
	$row = $result ? $result->fetchArray(SQLITE3_NUM) : false;
	echo json_encode($row ? $row[0] : null);
}

//  Set Parameter Value
function setParameter() {
	global $db;
	global $pia_lang;

	$parameter = $GLOBALS["pialert_request"]['parameter'] ?? '';
	$value = $GLOBALS["pialert_request"]['value'] ?? '';
	if (!is_scalar($parameter) || !is_scalar($value)) {
		echo $pia_lang['BE_Param_error_update'];
		return;
	}

	$result = db_execute_prepared($db, 'UPDATE Parameters SET par_Value = :value WHERE par_ID = :id', array(':value' => (string)$value, ':id' => (string)$parameter));
	if (!$result) {
		echo $pia_lang['BE_Param_error_update'];
		logServerConsole('Parameter update failed: ' . $db->lastErrorMsg());
		return;
	}

	if ($db->changes() === 0) {
		$result = db_execute_prepared($db, 'INSERT INTO Parameters (par_ID, par_Value) VALUES (:id, :value)', array(':id' => (string)$parameter, ':value' => (string)$value));
		if (!$result) {
			echo $pia_lang['BE_Param_error_create'];
			logServerConsole('Parameter insert failed: ' . $db->lastErrorMsg());
			return;
		}
	}
	echo 'OK';
}

function setJournalParameter() {
	global $db;
	global $pia_lang;
    
    if ($_POST['column'] == "trigger") {
    	// Get old value
	    $result = $db->query("SELECT par_Long_Value FROM Parameters WHERE par_ID = 'journal_trigger_filter'");
	    $row = $result->fetchArray(SQLITE3_ASSOC);
	    if ($row) {
	        $old_data_a = $row['par_Long_Value'];
	    } else {$old_data_a = "";}
	    $result = $db->query("SELECT par_Long_Value FROM Parameters WHERE par_ID = 'journal_trigger_filter_color'");
	    $row = $result->fetchArray(SQLITE3_ASSOC);
	    if ($row) {
	        $old_data_b = $row['par_Long_Value'];
	    } else {$old_data_b = "";}

    	$triggerNames = "";
    	$triggerColors = "";
    	if ($_POST['triggerNames'] != "") {
    		$triggerNames = implode(",", $_POST['triggerNames']);
    	}        
        if ($_POST['triggerColors'] != "") {
        	$triggerColors = implode(",", $_POST['triggerColors']);
    	}

        saveParameters('journal_trigger_filter', $triggerNames);
        saveParameters('journal_trigger_filter_color', $triggerColors);

        // Get new value
	    $result = $db->query("SELECT par_Long_Value FROM Parameters WHERE par_ID = 'journal_trigger_filter'");
	    $row = $result->fetchArray(SQLITE3_ASSOC);
	    if ($row) {
	        $new_data_a = $row['par_Long_Value'];
	    } else {$new_data_a = "";}
	    $result = $db->query("SELECT par_Long_Value FROM Parameters WHERE par_ID = 'journal_trigger_filter_color'");
	    $row = $result->fetchArray(SQLITE3_ASSOC);
	    if ($row) {
	        $new_data_b = $row['par_Long_Value'];
	    } else {$new_data_b = "";}

	    // Compare old and new
	    if ($old_data_a != $new_data_a || $old_data_b != $new_data_b) {
	    	echo $pia_journ_lang['Journal_TableHead_Trigger'] . " " . $pia_lang['BE_Param_Colors'];
	        // Logging
		    pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0048', '', 'trigger');
	    } else {echo $pia_journ_lang['Journal_TableHead_Trigger'] . " " . $pia_lang['BE_Param_Colors_error'];}
    }

    if ($_POST['column'] == "method") {
    	// Get old value
	    $result = $db->query("SELECT par_Long_Value FROM Parameters WHERE par_ID = 'journal_method_filter'");
	    $row = $result->fetchArray(SQLITE3_ASSOC);
	    if ($row) {
	        $old_data_a = $row['par_Long_Value'];
	    } else {$old_data_a = "";}
	    $result = $db->query("SELECT par_Long_Value FROM Parameters WHERE par_ID = 'journal_method_filter_color'");
	    $row = $result->fetchArray(SQLITE3_ASSOC);
	    if ($row) {
	        $old_data_b = $row['par_Long_Value'];
	    } else {$old_data_b = "";}

    	$methodNames = "";
    	$methodColors = "";
    	if ($_POST['methodNames'] != "") {
    		$methodNames = implode(",", $_POST['methodNames']);
    	}        
        if ($_POST['methodColors'] != "") {
        	$methodColors = implode(",", $_POST['methodColors']);
    	}
        
        saveParameters('journal_method_filter', $methodNames);
        saveParameters('journal_method_filter_color', $methodColors);

        // Get new value
	    $result = $db->query("SELECT par_Long_Value FROM Parameters WHERE par_ID = 'journal_method_filter'");
	    $row = $result->fetchArray(SQLITE3_ASSOC);
	    if ($row) {
	        $new_data_a = $row['par_Long_Value'];
	    } else {$new_data_a = "";}
	    $result = $db->query("SELECT par_Long_Value FROM Parameters WHERE par_ID = 'journal_method_filter_color'");
	    $row = $result->fetchArray(SQLITE3_ASSOC);
	    if ($row) {
	        $new_data_b = $row['par_Long_Value'];
	    } else {$new_data_b = "";}

	    // Compare old and new
	    if ($old_data_a != $new_data_a || $old_data_b != $new_data_b) {
	    	echo $pia_journ_lang['Journal_TableHead_Class'] . " " . $pia_lang['BE_Param_Colors'];
	        // Logging
		    pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0048', '', 'method');
	    } else {echo $pia_journ_lang['Journal_TableHead_Class'] . " " . $pia_lang['BE_Param_Colors_error'];}
    }
}

function getJournalParameter() {
    global $db;
	$responseData = [];

	$ids = [
	    'journal_trigger_filter',
	    'journal_trigger_filter_color',
	    'journal_method_filter',
	    'journal_method_filter_color'
	];

	foreach ($ids as $id) {
	    $result = $db->query("SELECT par_Long_Value FROM Parameters WHERE par_ID = '$id'");
	    $row = $result->fetchArray(SQLITE3_ASSOC);
	    if ($row) {
	        $responseData[$id] = $row['par_Long_Value'];
	    } else {
	        $responseData[$id] = null; // Falls kein Wert gefunden wird
	    }
	}
	echo json_encode($responseData);
}

function setReportParameter() {
	global $db;
	global $pia_lang;
    
    // Get old value
    $result = $db->query("SELECT par_Long_Value FROM Parameters WHERE par_ID = 'report_headline_colors'");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        $old_data = $row['par_Long_Value'];
    } else {$old_data = "";}

	$defaults = array("#30bbbb", "#D81B60", "#00c0ef", "#831CFF", "#00a65a", "#cc6600");
	$submittedColors = $_POST['HeadLineColors'] ?? array();
	if (!is_array($submittedColors)) {
		$submittedColors = array();
	}

	$validatedColors = array();
	foreach ($defaults as $index => $fallback) {
		$candidate = $submittedColors[$index] ?? '';
		if (!is_scalar($candidate)) {
			$candidate = '';
		}
		$candidate = trim((string) $candidate);
		$validatedColors[] = preg_match('/^#[0-9a-f]{6}$/i', $candidate) ? $candidate : $fallback;
	}
	$HeadLineColors = implode(",", $validatedColors);

    saveParameters('report_headline_colors', $HeadLineColors);

    // Get new value
    $result = $db->query("SELECT par_Long_Value FROM Parameters WHERE par_ID = 'report_headline_colors'");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        $new_data = $row['par_Long_Value'];
    } else {$new_data = "";}

    if ($old_data != $new_data) {
    	echo "Report " . $pia_lang['BE_Param_Colors'];
	    // Logging
	    pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0049', '', '');
    } else {echo "Report " . $pia_lang['BE_Param_Colors_error'];}

}

function getReportParameter() {
    global $db;
	$responseData = "";

    $result = $db->query("SELECT par_Long_Value FROM Parameters WHERE par_ID = 'report_headline_colors'");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        $responseData = $row['par_Long_Value'];
    }
	echo json_encode($responseData);
}

?>

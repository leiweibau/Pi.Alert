<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  parameters.php - Front module. Server side. Manage Parameters
//------------------------------------------------------------------------------
//  Puche 2021              pi.alert.application@gmail.com     GNU GPLv3
//  leiweibau  2023+        https://github.com/leiweibau       GNU GPLv3
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

//  Action selector
// Set maximum execution time to 15 seconds
ini_set('max_execution_time', '15');

// Open DB
OpenDB();

// Action functions
if (isset($_REQUEST['action']) && !empty($_REQUEST['action'])) {
	$action = $_REQUEST['action'];
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
    
    $result = $db->query("SELECT COUNT(*) as count FROM Parameters WHERE par_ID = '$par_ID'");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($row['count'] > 0) {
        $db->query("UPDATE Parameters SET par_Long_Value = '$par_Long_Value' WHERE par_ID = '$par_ID'");
    } else {
        $db->query("INSERT INTO Parameters (par_ID, par_Long_Value) VALUES ('$par_ID', '$par_Long_Value')");
    }
}

//  Get Parameter Value
function getParameter() {
	global $db;

	$parameter = $_REQUEST['parameter'];
	$sql = 'SELECT par_Value FROM Parameters
          WHERE par_ID="' . quotes($_REQUEST['parameter']) . '"';
	$result = $db->query($sql);
	$row = $result->fetchArray(SQLITE3_NUM);
	$value = $row[0];

	echo (json_encode($value));
}

//  Set Parameter Value
function setParameter() {
	global $db;
	global $pia_lang;

	// Update value
	$sql = 'UPDATE Parameters SET par_Value="' . quotes($_REQUEST['value']) . '"
          WHERE par_ID="' . quotes($_REQUEST['parameter']) . '"';
	$result = $db->query($sql);

	if (!$result == TRUE) {
		echo $pia_lang['BE_Param_error_update'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
		return;
	}

	$changes = $db->changes();
	if ($changes == 0) {
		// Insert new value
		$sql = 'INSERT INTO Parameters (par_ID, par_Value)
            VALUES ("' . quotes($_REQUEST['parameter']) . '",
                    "' . quotes($_REQUEST['value']) . '")';
		$result = $db->query($sql);

		if (!$result == TRUE) {
			echo $pia_lang['BE_Param_error_create'] . "\n\n$sql \n\n" . $db->lastErrorMsg();
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

	$HeadLineColors = "";
	// Set Default Colors if unset
	if ($_POST['HeadLineColors'] != "") {
		$temp_array = $_POST['HeadLineColors'];
		if ($temp_array[0] == "") {$temp_array[0] = "#30bbbb";} // Internet
		if ($temp_array[1] == "") {$temp_array[1] = "#D81B60";} // Devices
		if ($temp_array[2] == "") {$temp_array[2] = "#00c0ef";} // Services
		if ($temp_array[3] == "") {$temp_array[3] = "#831CFF";} // ICMP
		if ($temp_array[4] == "") {$temp_array[4] = "#00a65a";} // Test/System
		$HeadLineColors = implode(",", $temp_array);
	}

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

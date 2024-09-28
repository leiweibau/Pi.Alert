<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  parameters.php - Front module. Server side. Manage Parameters
//------------------------------------------------------------------------------
//  Puche 2021        pi.alert.application@gmail.com        GNU GPLv3
//------------------------------------------------------------------------------


require 'db.php';
require 'util.php';
require 'journal.php';

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
	default:logServerConsole('Action: ' . $action);
		break;
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

function setJournalParameter() {
	global $db;
    
    if ($_POST['column'] == "trigger") {
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
        echo "Trigger saved";

        // Logging
	    pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0048', '', 'trigger');
    }

    if ($_POST['column'] == "method") {
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
        echo "Methode saved";

        // Logging
	    pialert_logging('a_005', $_SERVER['REMOTE_ADDR'], 'LogStr_0048', '', 'method');
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

	// Update value
	$sql = 'UPDATE Parameters SET par_Value="' . quotes($_REQUEST['value']) . '"
          WHERE par_ID="' . quotes($_REQUEST['parameter']) . '"';
	$result = $db->query($sql);

	if (!$result == TRUE) {
		echo "Error updating parameter\n\n$sql \n\n" . $db->lastErrorMsg();
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
			echo "Error creating parameter\n\n$sql \n\n" . $db->lastErrorMsg();
			return;
		}
	}

	echo 'OK';
}

?>

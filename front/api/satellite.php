<?php

// 0 = ok
// 1 = config error
// 2 = file error
// 3 = permission error

# require '../php/server/timezone.php';
// Get all configured Sat Tokens in direct mode
function get_all_satellites() {
    $database = '../../db/pialert.db';
    // Check if the database is available, else Error via JSON
    if (!file_exists($database)) {
		json_response(1, "Pi.Alert database not found");
    	die();
    }

    $db = new SQLite3($database);
    $sql_select = 'SELECT * FROM Satellites ORDER BY sat_name ASC';
    $result = $db->query($sql_select);
    $i = 0;
    // Create a nested array
    if ($result) {
        if ($result->numColumns() > 0) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $func_satellite_list[$i]['sat_name'] = $row['sat_name'];
                $func_satellite_list[$i]['sat_token'] = $row['sat_token'];
                $func_satellite_list[$i]['sat_password'] = $row['sat_password'];
                $i++;
            }
        }
    }
    $db->close();
    return $func_satellite_list;
}
// JSON Response
function json_response($status_code, $api_message) {
	header('Content-Type: application/json');
	$response = array("status" => "$status_code", "message" => "$api_message");
	echo json_encode($response);
}
function purge_old_results() {
	$directory = '../satellites';
	$files = scandir($directory);
	$currentTime = time();
	$ageLimit = 590; // 9 minutes 50 seconds

	foreach ($files as $file) {
	    $filePath = $directory . '/' . $file;

	    if ($file === 'readme' || $file === 'readme.txt') {
	        continue;
	    }

	    if (is_file($filePath)) {
	        $fileModificationTime = filemtime($filePath);
	        if ($currentTime - $fileModificationTime > $ageLimit) {
	            unlink($filePath);
	        }
	    }
	}
}

$http_response = '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>404 Not Found</title>
</head><body>
<h1>Not Found</h1>
<p>The requested URL was not found on this server.</p>
</body></html>';

// Check whether mode or token is set, otherwise HTTP 404
if ($_REQUEST['mode'] == "" || $_REQUEST['token'] == "") {
	header('HTTP/1.0 404 Not Found', true, 404);
	echo $http_response;
	die();
}
// Check if payload is set when usingg direct or mropy mode, otherwise HTTP 404
if (($_REQUEST['mode'] == "direct" || $_REQUEST['mode'] == "proxy") && !isset($_FILES['encrypted_data'])) {
	header('HTTP/1.0 404 Not Found', true, 404);
	echo $http_response;
	die();
}

$incomming_token = $_REQUEST['token'];

// Procedure for direct API call (Pi.Alert)
if ($_REQUEST['mode'] == "direct") {
	// Query from the database
	$satellite_list = get_all_satellites();
	$satellite_tokens = array();
	$satellite_passwords = array();

	for($x=0;$x<sizeof($satellite_list);$x++) {
		array_push($satellite_tokens, $satellite_list[$x]['sat_token']);
		array_push($satellite_passwords, $satellite_list[$x]['sat_password']);
	}

	// If the token is valid
	if (in_array($incomming_token, $satellite_tokens)) {

		$file = $_FILES['encrypted_data'];
		$filename = 'encrypted_'.$incomming_token;
		$tempPath = $file['tmp_name'];
		$destinationPath = __DIR__ . '/../satellites/'. $filename;
		move_uploaded_file($tempPath, $destinationPath);

		// Check whether the payload has been moved to the target directory, else Error via JSON
		if (!file_exists($destinationPath)) {
			json_response(2,"File was not received");
			die();
		}

		$key = array_search($incomming_token, $satellite_tokens);
		$password = $satellite_passwords[$key];

		$openssl_command = sprintf(
		    'openssl enc -d -aes-256-cbc -in '.$destinationPath.' -pbkdf2 -pass pass:%s',
		    escapeshellarg($password)
		);

		$decrypted_data = shell_exec($openssl_command);
		if ($decrypted_data === null) {
			json_response(1,"Decryption Error");
		    die();
		}

		$decrypted_array = json_decode($decrypted_data, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			json_response(2,"JSON Error");
			die();
		}

		file_put_contents('../satellites/'.$incomming_token.'.json', json_encode($decrypted_array, JSON_PRETTY_PRINT));
		unlink($destinationPath);

		// Run through the process without errors
		json_response(0,"File was received");
	} else {
		// If the token is invalid
		json_response(3,"Invalid Satellite ID");		
	}

} elseif ($_REQUEST['mode'] == "proxy") {
    // Procedure for Proxy Mode API call
    // Check if the config file is available, else Error via JSON
    if (!file_exists('config.php')) {
		json_response(1,"Config file not found");
    	die();
    }
	if (!is_dir( '../satellites' )) {
		json_response(1,"satellites folder not found");
    	die();    
	}
    // import config file
    require 'config.php';

    // If the token is valid
	if (in_array($incomming_token, $valid_tokens)) {

		$file = $_FILES['encrypted_data'];
		$filename = 'encrypted_'.$incomming_token;
		$tempPath = $file['tmp_name'];
		$destinationPath = __DIR__ . '/../satellites/'. $filename;
		move_uploaded_file($tempPath, $destinationPath);

		// Check whether the payload has been moved to the target directory, else Error via JSON
		if (!file_exists($destinationPath)) {
			json_response(2,"File was not received by proxy");	
			die();
		}

		// Run through the process without errors
		json_response(0,"File was received by proxy");	
	} else {
		// If the token is invalid
		json_response(3,"Invalid Satellite ID");	
	}
} elseif ($_REQUEST['mode'] == "get") {
	purge_old_results();

	$directory = '../satellites';
	$sat_enc_result = $directory . '/encrypted_'. $incomming_token;

	if (file_exists($sat_enc_result)) {
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="encrypted_' . $incomming_token . '"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($sat_enc_result));
		readfile($sat_enc_result);
	} else {
		header('HTTP/1.0 404 Not Found', true, 404);
	}
}
?>

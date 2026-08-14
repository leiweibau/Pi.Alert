<?php

// 0 = ok
// 1 = config error
// 2 = file error
// 3 = permission error

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

function purge_old_results($directory = null, $currentTime = null, $ageLimit = 590) {
	$directory = $directory ?? (__DIR__ . '/../satellites');
	$currentTime = $currentTime ?? time();
	$files = is_dir($directory) ? scandir($directory) : false;
	if ($files === false) {
		return 0;
	}

	$deleted = 0;
	foreach ($files as $file) {
		if (strncmp($file, 'encrypted_', 10) !== 0) {
			continue;
		}

		$filePath = $directory . DIRECTORY_SEPARATOR . $file;
		if (!is_file($filePath) || is_link($filePath)) {
			continue;
		}

		$fileModificationTime = filemtime($filePath);
		if ($fileModificationTime !== false
			&& $currentTime - $fileModificationTime > $ageLimit
			&& unlink($filePath)) {
			$deleted++;
		}
	}

	return $deleted;
}

// Allows the cleanup regression test to load the production function without
// executing the HTTP endpoint.
if (defined('PIALERT_SATELLITE_FUNCTIONS_ONLY') && PIALERT_SATELLITE_FUNCTIONS_ONLY === true) {
	return;
}

$http_response = '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>404 Not Found</title>
</head><body>
<h1>Not Found</h1>
<p>The requested URL was not found on this server.</p>
</body></html>';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$request = $method === 'POST' ? $_POST : $_GET;
$mode = isset($request['mode']) && is_scalar($request['mode']) ? (string) $request['mode'] : '';
if (($mode === 'direct' || $mode === 'proxy') && $method !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method Not Allowed');
}
if ($mode === 'get' && $method !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Method Not Allowed');
}
if (!in_array($mode, ['direct', 'proxy', 'get'], true)) {
    http_response_code(400);
    exit('Unknown mode');
}
// Check whether mode or token is set, otherwise HTTP 404
if ($mode === '' || !isset($request['token']) || !is_scalar($request['token']) || (string) $request['token'] === '') {
	header('HTTP/1.0 404 Not Found', true, 404);
	echo $http_response;
	die();
}
// Check if payload is set when usingg direct or proxy mode, otherwise HTTP 404
if (($request['mode'] == "direct" || $request['mode'] == "proxy") && !isset($_FILES['encrypted_data'])) {
	header('HTTP/1.0 404 Not Found', true, 404);
	echo $http_response;
	die();
}

$incomming_token = (string) $request['token'];

// Procedure for direct API call (Pi.Alert)
if ($request['mode'] == "direct") {
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

} elseif ($request['mode'] == "proxy") {
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
} elseif ($request['mode'] == "get") {
	// Keep the original proxy behaviour: remove expired transport results
	// immediately before attempting to serve the requested result.
	$directory = __DIR__ . '/../satellites';
	purge_old_results($directory);
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

<?php
function get_all_satellites() {
    $database = '../../db/pialert.db';

    if (!file_exists($database)) {
		header('Content-Type: application/json');
		$response = array("message" => "Pi.Alert database not found");
		echo json_encode($response);
    	die();
    }

    $db = new SQLite3($database);
    $sql_select = 'SELECT * FROM Satellites ORDER BY sat_name ASC';
    $result = $db->query($sql_select);
    $i = 0;
    if ($result) {
        if ($result->numColumns() > 0) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                //array_push($_SESSION['Filter_Table'], $row);
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

$incomming_token = $_REQUEST['token'];

if ($_REQUEST['token'] == "") {
	header('HTTP/1.0 404 Not Found', true, 404);
	echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>404 Not Found</title>
</head><body>
<h1>Not Found</h1>
<p>The requested URL was not found on this server.</p>
</body></html>';
	die();
}

//---------------------------------------------------------------------------------------

if ($_REQUEST['mode'] != "proxy") {

	$satellite_list = get_all_satellites();

	$satellite_tokens = array();
	$satellite_passwords = array();

	for($x=0;$x<sizeof($satellite_list);$x++) {
		array_push($satellite_tokens, $satellite_list[$x]['sat_token']);
		array_push($satellite_passwords, $satellite_list[$x]['sat_password']);
	}

	if (in_array($incomming_token, $satellite_tokens) && isset($_FILES['encrypted_data'])) {

		# decrypting in non proxy mode
		# API runs on Pi.Alert
		$file = $_FILES['encrypted_data'];

		$filename = 'encrypted_'.$incomming_token;
		$tempPath = $file['tmp_name'];
		$destinationPath = __DIR__ . '/../satellites/'. $filename;

		move_uploaded_file($tempPath, $destinationPath);

		if (!file_exists($destinationPath)) {
			header('Content-Type: application/json');
			$response = array("message" => "File was not received");
			echo json_encode($response);
			die();
		}

		$key = array_search ($incomming_token, $satellite_tokens);
		$password = $satellite_passwords[$key];  // Get password from token id

		$openssl_command = sprintf(
		    'openssl enc -d -aes-256-cbc -in '.$destinationPath.' -pbkdf2 -pass pass:%s',
		    escapeshellarg($password)
		);

		$decrypted_data = shell_exec($openssl_command);
		if ($decrypted_data === null) {
			header('Content-Type: application/json');
			$response = array("message" => "Decryption Error");
			echo json_encode($response);
		    die();
		}

		$decrypted_array = json_decode($decrypted_data, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			header('Content-Type: application/json');
			$response = array("message" => "JSON Error");
			echo json_encode($response);
			die();
		}

		file_put_contents('../satellites/'.$incomming_token.'.json', json_encode($decrypted_array, JSON_PRETTY_PRINT));
		unlink($destinationPath);

		header('Content-Type: application/json');
		$response = array("message" => "Okay");
		echo json_encode($response);

	} else {
		header('Content-Type: application/json');
		$response = array("message" => "Invalid Satellite ID");
		echo json_encode($response);		
	}

} else {

    if (!file_exists('config.php')) {
		header('Content-Type: application/json');
		$response = array("message" => "Configfile not found");
		echo json_encode($response);
    	die();
    }

    require 'config.php';

	if (in_array($incomming_token, $valid_tokens) && isset($_FILES['encrypted_data'])) {

		# No decrypting in proxy mode
		# API runs on third party webserver 
		$file = $_FILES['encrypted_data'];

		$filename = 'encrypted_'.$incomming_token;
		$tempPath = $file['tmp_name'];
		$destinationPath = __DIR__ . '/../satellites/'. $filename;

		move_uploaded_file($tempPath, $destinationPath);

		if (!file_exists($destinationPath)) {
			header('Content-Type: application/json');
			$response = array("message" => "File was not received");
			echo json_encode($response);
			die();
		}

		header('Content-Type: application/json');
		$response = array("message" => "File was received by proxy");
		echo json_encode($response);
	} else {
		header('Content-Type: application/json');
		$response = array("message" => "Invalid Satellite ID");
		echo json_encode($response);
	}

} 


?>

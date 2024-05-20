<?php

$statellite_list[0]['sat_name'] = 'Ophelia';
$statellite_list[0]['sat_token'] = 'qwertzuiop';
$statellite_list[0]['sat_password'] = 'password1';

$statellite_list[1]['sat_name'] = 'Juliet';
$statellite_list[1]['sat_token'] = 'asdfghjklk';
$statellite_list[1]['sat_password'] = 'password2';

$incomming_token = $_REQUEST['TOKEN'];
$satellites_tokes = array($statellite_list[0]['sat_token'], $statellite_list[1]['sat_token']);
$satellites_passwords = array($statellite_list[0]['sat_password'], $statellite_list[1]['sat_password']);

if (in_array($incomming_token, $satellites_tokes) && isset($_FILES['encrypted_data'])) {

    $file = $_FILES['encrypted_data'];

    $filename = 'encrypted_'.$incomming_token;
    $tempPath = $file['tmp_name'];
    $destinationPath = __DIR__ . '/../satellites/'. $filename;

    move_uploaded_file($tempPath, $destinationPath);

	$key = array_search ($incomming_token, $satellites_tokes);
	$password = $satellites_passwords[$key];  // Hier das tatsächliche Passwort einfügen

	$openssl_command = sprintf(
	    'openssl enc -d -aes-256-cbc -in '.$destinationPath.' -pbkdf2 -pass pass:%s',
	    escapeshellarg($password)
	);

	$decrypted_data = shell_exec($openssl_command);
	if ($decrypted_data === null) {
		header('Content-Type: application/json');
		$response = array("message" => "Decryption Error");
		echo json_encode($response);
	    exit();
	}

	$decrypted_array = json_decode($decrypted_data, true);
	if (json_last_error() !== JSON_ERROR_NONE) {
		header('Content-Type: application/json');
		$response = array("message" => "JSON Error");
		echo json_encode($response);
		exit();
	}

	file_put_contents('../satellites/'.$incomming_token.'.json', json_encode($decrypted_array, JSON_PRETTY_PRINT));
	unlink($destinationPath);

	header('Content-Type: application/json');
	$response = array("message" => "Okay");
	echo json_encode($response);

} else {
	header('Content-Type: application/json');
	$response = array("message" => "Error");
	echo json_encode($response);
}

?>

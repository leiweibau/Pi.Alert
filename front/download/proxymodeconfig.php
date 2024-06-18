<?php
session_start();

if ($_SESSION["login"] != 1) {
	header('Location: ../index.php');
	exit;
}

require '../php/server/db.php';
require '../php/server/journal.php';

$CURRENT_TIME = date('Y-m-d H:i:s');

$config_string = '<?php
$valid_tokens = array(';

$database = '../../db/pialert.db';
$db = new SQLite3($database);
$sql_select = 'SELECT * FROM Satellites';
$result = $db->query($sql_select);
if ($result) {
    if ($result->numColumns() > 0) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $config_string .= "\n";
            $config_string .= '    "'.$row['sat_token'].'",';
        }
    }
}
$db->close();

$config_string .= "\n);\n?>\n";

header('Content-Description: File Transfer');
header("Content-Type: text/php");
header('Content-Disposition: attachment; filename=config.php');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($config_string));

echo $config_string;

?>

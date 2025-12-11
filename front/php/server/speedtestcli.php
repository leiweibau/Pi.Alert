<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  speedtestcli.php - Front module. Server side. Manage Devices
//------------------------------------------------------------------------------
//  leiweibau  2024        https://github.com/leiweibau     GNU GPLv3
//------------------------------------------------------------------------------
session_start();
require 'timezone.php';
require 'db.php';
require 'journal.php';
$DBFILE = '../../../db/pialert.db';
$DBFILE_TOOLS = '../../../db/pialert_tools.db';

// Open DB
OpenDB();
OpenDB_Tools();

exec('../../../back/speedtest-cli --secure --simple', $output);

$cli_output = implode('<br>', $output);
// Logging
pialert_logging('a_002', $_SERVER['REMOTE_ADDR'], 'LogStr_0255', '', $cli_output);

echo '<h4>Speedtest Results</h4>';
echo '<pre style="border: none;">';

$ping = null;
$download = null;
$upload = null;

foreach ($output as $line) {
    $line = trim($line);

    // Ping
    if (preg_match('/^Ping:\s*([\d\.]+)\s*ms/i', $line, $m)) {
        $ping = $m[1];
    }

    // Download in Mbit/s -> Mbps
    if (preg_match('/^Download:\s*([\d\.]+)\s*Mbit\/s/i', $line, $m)) {
        $download = $m[1];
    }

    // Upload in Mbit/s -> Mbps
    if (preg_match('/^Upload:\s*([\d\.]+)\s*Mbit\/s/i', $line, $m)) {
        $upload = $m[1];
    }
}

echo "Ping: " . $ping . " ms\n";
echo "Download: " . $download . " Mbps\n";
echo "Upload: " . $upload . " Mbps\n";

$test_time = date('Y-m-d H:i:s');

$sql = 'INSERT INTO "Tools_Speedtest_History" ("speed_date", "speed_isp", "speed_server", "speed_ping", "speed_down", "speed_up") VALUES("' . $test_time . '", "Manual Speedtest", "Manual Speedtest", "' . $ping . '", "' . $download . '", "' . $upload . '")';
$result = $db_tools->query($sql);

echo '</pre>';
?>
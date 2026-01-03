<?php
session_start();
if ($_SESSION["login"] != 1) {
	header('Location: ../../index.php');
	exit;
}
require 'timezone.php';
require 'db.php';
$DBFILE = '../../../db/pialert.db';
$DBFILE_TOOLS = '../../../db/pialert_tools.db';

// Open DB
OpenDB();
OpenDB_Tools();

// Action functions
if (isset($_REQUEST['action']) && !empty($_REQUEST['action'])) {
	$action = $_REQUEST['action'];
    switch ($action) {
    case 'getLogfileDatesAsJson':getLogfileDatesAsJson();
        break;
    case 'getLogfileContent':getLogfileContent();
        break;
    case 'getSpeedtestHistory':getSpeedtestHistory();
        break;
    case 'getLocalDeviceStatus':getLocalDeviceStatus();
        break;
    case 'getIcmpDeviceStatus':getIcmpDeviceStatus();
        break;
    case 'getReportsCount':getReportsCount();
        break;
    case 'getReportContent':getReportContent();
        break;
    case 'getLatestReports':getLatestReports();
        break;
    case 'getDeviceHistoryChart':getDeviceHistoryChart();
        break;
    case 'getServiceStatusSummary':getServiceStatusSummary();
        break;
	default:logServerConsole('Action: ' . $action);
		break;
	}
}
// --------------------------------------------------------------------
function getLogfileTableMap(): array {
    return [
        'pialert.1.log'           => 'Log_History_Scan',
        'pialert.webservices.log' => 'Log_History_WebServices',
        'pialert.cleanup.log'     => 'Log_History_Cleanup',
        'pialert.IP.log'          => 'Log_History_InternetIP',
        'pialert.vendors.log'     => 'Log_History_Vendors',
        'pialert.speedtest.log'   => 'Log_History_Speedtest',
    ];
}
// --------------------------------------------------------------------
function getLogfileDatesAsJson()
{
    global $db_tools;

    header('Content-Type: application/json');

    if (!isset($_REQUEST['logfile'])) {
        echo json_encode([]);
        exit;
    }

    $logfile = $_REQUEST['logfile'];
    $map = getLogfileTableMap();

    if (!isset($map[$logfile])) {
        echo json_encode([]);
        exit;
    }

    $table = $map[$logfile];

    $sql = "SELECT ScanDate FROM {$table} ORDER BY ScanDate DESC";

    $result = $db_tools->query($sql);
    $dates  = [];

    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if (!empty($row['ScanDate'])) {
                $dates[] = $row['ScanDate'];
            }
        }
    }

    echo json_encode($dates);
    exit;
}
// --------------------------------------------------------------------
function getLogfileContent() {
	global $db_tools;

    $logfile = $_REQUEST['logfile'] ?? '';
    $date    = $_REQUEST['date'] ?? '';

    header('Content-Type: plain/text');

    $map = getLogfileTableMap();
    if (!isset($map[$logfile])) {
        echo 'Invalid logfile';
        exit;
    }

    $table = $map[$logfile];

	// rudimentäre Absicherung des Datums
    $date = SQLite3::escapeString($date);

    $sql = "SELECT Logfile FROM {$table} WHERE ScanDate = '{$date}' LIMIT 1";

    $result = $db_tools->query($sql);
    if (!$result) {
        echo 'Query failed';
        exit;
    }

    $row = $result->fetchArray(SQLITE3_ASSOC);
    if (!$row) {
        echo 'No log found';
        exit;
    }

    echo $row['Logfile'];
    exit;
}
// --------------------------------------------------------------------
function getSpeedtestHistory() {
    global $db_tools;

    header('Content-Type: application/json');

    global $db_tools;

    // erlaubte Zeiträume
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
    if (!in_array($days, [7, 14, 21], true)) {
        $days = 7;
    }

    $sql = "
        SELECT
            speed_date,
            speed_ping,
            speed_down,
            speed_up
        FROM Tools_Speedtest_History
        WHERE speed_date >= datetime('now', '-{$days} days')
        ORDER BY speed_date ASC
    ";

    $result = $db_tools->query($sql);

    $data = [
        'labels' => [],
        'ping'   => [],
        'down'   => [],
        'up'     => []
    ];

    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data['labels'][] = $row['speed_date'];
            $data['ping'][]   = (float)$row['speed_ping'];
            $data['down'][]   = (float)$row['speed_down'];
            $data['up'][]     = (float)$row['speed_up'];
        }
    }

    echo json_encode($data);
    exit;
}
// --------------------------------------------------------------------
function getLocalDeviceStatus() {

    header('Content-Type: application/json');
    global $db;

    $sqlLatest = "
        SELECT Scan_Date
        FROM Online_History
        WHERE data_source LIKE 'main_scan%'
        ORDER BY Scan_Date DESC
        LIMIT 1
    ";

    $resLatest = $db->query($sqlLatest);
    if (!$resLatest || !($rowLatest = $resLatest->fetchArray(SQLITE3_ASSOC))) {
        echo json_encode([]);
        exit;
    }

    $latestScanDate = SQLite3::escapeString($rowLatest['Scan_Date']);

    $sqlSum = "
        SELECT
            SUM(Online_Devices)   AS online,
            SUM(Down_Devices)     AS offline,
            SUM(All_Devices)      AS total,
            SUM(Archived_Devices) AS archived
        FROM Online_History
        WHERE Scan_Date   = '{$latestScanDate}'
          AND data_source LIKE 'main_scan%'
    ";

    $result = $db->query($sqlSum);
    if ($result) {
        $row = $result->fetchArray(SQLITE3_ASSOC);
    }

    echo json_encode([
        'online'   => (int)($row['online'] ?? 0),
        'offline'  => (int)($row['offline'] ?? 0),
        'archived' => (int)($row['archived'] ?? 0),
        'total'    => (int)($row['total'] ?? 0),
        'scanDate' => $latestScanDate
    ]);

    exit;
}
// --------------------------------------------------------------------
function getIcmpDeviceStatus() {
    global $db;

    header('Content-Type: application/json');

    $sql = "
        SELECT
            Online_Devices,
            Down_Devices,
            Archived_Devices,
            All_Devices
        FROM Online_History
        WHERE data_source = 'icmp_scan'
        ORDER BY Scan_Date DESC
        LIMIT 1
    ";

    $result = $db->query($sql);
    if ($result) {
        $row = $result->fetchArray(SQLITE3_ASSOC);
    }

    echo json_encode([
        'online'   => (int)($row['Online_Devices'] ?? 0),
        'offline'  => (int)($row['Down_Devices'] ?? 0),
        'archived' => (int)($row['Archived_Devices'] ?? 0),
        'total'    => (int)($row['All_Devices'] ?? 0)
    ]);

    exit;
}
// --------------------------------------------------------------------
function getReportsCount() {

    header('Content-Type: application/json');

    $basePath = realpath(__DIR__ . '/../../reports');

    if ($basePath === false) {
        echo json_encode([
            'reports'  => 0,
            'archive'  => 0
        ]);
        exit;
    }

    $reportsPath = $basePath;
    $archivePath = $basePath . '/archived';

    // *.txt in reports/
    $reportsFiles = glob($reportsPath . '/*.txt');
    $reportsCount = is_array($reportsFiles) ? count($reportsFiles) : 0;

    // *.txt in reports/archived/
    $archiveFiles = glob($archivePath . '/*.txt');
    $archiveCount = is_array($archiveFiles) ? count($archiveFiles) : 0;

    echo json_encode([
        'reports' => $reportsCount,
        'archive' => $archiveCount
    ]);

    exit;
}
// --------------------------------------------------------------------
function getLatestReports() {

    header('Content-Type: application/json');

    $basePath = realpath(__DIR__ . '/../../reports');
    if ($basePath === false) {
        echo json_encode([]);
        exit;
    }

    $files = glob($basePath . '/*.txt');
    if (!$files) {
        echo json_encode([]);
        exit;
    }

    usort($files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    $latest = array_slice($files, 0, 10);
    $result = [];

    foreach ($latest as $file) {
        $result[] = [
            'name' => basename($file),
            'time' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }

    echo json_encode($result);
    exit;
}
// --------------------------------------------------------------------
function getReportContent() {

    header('Content-Type: text/plain');

    if (!isset($_GET['file'])) {
        exit;
    }

    $basePath = realpath(__DIR__ . '/../../reports');
    $file = basename($_GET['file']);
    $path = $basePath . '/' . $file;

    if (!is_file($path)) {
        echo 'Report not found';
        exit;
    }

    readfile($path);
    exit;
}
// --------------------------------------------------------------------
function getDeviceHistoryChart() {
    global $db;

    header('Content-Type: application/json');
    $source = $_GET['source'] ?? 'main_scan';
    $source = SQLite3::escapeString($source);

    $labels   = [];
    $online   = [];
    $offline  = [];
    $archived = [];

    // MAIN SCAN → aggregieren nach Timestamp
    if ($source === 'main_scan') {

        $sql = "
            SELECT
                Scan_Date,
                SUM(Online_Devices)   AS Online_Devices,
                SUM(Down_Devices)     AS Down_Devices,
                SUM(Archived_Devices) AS Archived_Devices
            FROM Online_History
            WHERE Data_Source LIKE 'main_scan%'
            GROUP BY Scan_Date
            ORDER BY Scan_Date DESC
            LIMIT 144
        ";

     // ANDERE QUELLEN (z. B. icmp_scan) → exakt matchen

    } else {

        $sql = "
            SELECT
                Scan_Date,
                Online_Devices,
                Down_Devices,
                Archived_Devices
            FROM Online_History
            WHERE Data_Source = '{$source}'
            ORDER BY Scan_Date DESC
            LIMIT 144
        ";
    }

    $results = $db->query($sql);
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $timePart = explode(' ', $row['Scan_Date'])[1];
        $time     = substr($timePart, 0, 5);

        // unshift → älteste links, neueste rechts
        array_unshift($labels,   $time);
        array_unshift($online,   (int)$row['Online_Devices']);
        array_unshift($offline,  (int)$row['Down_Devices']);
        array_unshift($archived, (int)$row['Archived_Devices']);
    }

    echo json_encode([
        'labels'   => $labels,
        'datasets' => [
            [
                'label'           => 'Online',
                'data'            => $online,
                'stack'           => 'devices',
                'backgroundColor' => '#2ecc71'
            ],
            [
                'label'           => 'Offline',
                'data'            => $offline,
                'stack'           => 'devices',
                'backgroundColor' => '#e74c3c'
            ],
            [
                'label'           => 'Archived',
                'data'            => $archived,
                'stack'           => 'devices',
                'backgroundColor' => '#95a5a6'
            ]
        ]
    ]);
    exit;
}
// --------------------------------------------------------------------
function getServiceStatusSummary() {
    global $db;
    header('Content-Type: application/json');

    $sql = "
        SELECT
            CASE
                WHEN mon_LastStatus = 0 THEN 'Offline'
                WHEN mon_LastStatus BETWEEN 100 AND 199 THEN '1xx'
                WHEN mon_LastStatus BETWEEN 200 AND 299 THEN '2xx'
                WHEN mon_LastStatus BETWEEN 300 AND 399 THEN '3xx'
                WHEN mon_LastStatus BETWEEN 400 AND 499 THEN '4xx'
                WHEN mon_LastStatus BETWEEN 500 AND 599 THEN '5xx'
                ELSE 'Other'
            END AS status_group,
            COUNT(*) AS cnt
        FROM Services
        GROUP BY status_group
        ORDER BY status_group
    ";

    $result = $db->query($sql);

    $labels = [];
    $values = [];

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $labels[] = $row['status_group'];
        $values[] = (int)$row['cnt'];
    }

    echo json_encode([
        'labels' => $labels,
        'data'   => $values
    ]);

    exit;
}



CloseDB();
CloseDB_Tools();

?>

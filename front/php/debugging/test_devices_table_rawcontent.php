<?php
session_start();

if ($_SESSION["login"] != 1) {
    header('Location: ../../index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debugging</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #e0e0e0;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        tr:nth-child(even) {
            background-color: #f0f0f0;
        }
        .info_head {
            font-size: 1.2em;
            font-weight: bold;
        }
        .info_box {
            margin-top: 20px;
        }
        .heading {
            font-size: 1.2em;
            margin-top: 20px;
        }
        td:hover::after {
            content: attr(data-column);
            position: absolute;
            background: #333;
            color: #fff;
            padding: 5px;
            border-radius: 3px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <h2>Show Main Device Table (raw)</h2>
    <div class="info_box">
        <span class="info_head">Pi.Alert-URL:</span><br>
        <div id="pialert_url"></div>
    </div>
<?php
$db = new SQLite3('../../../db/pialert.db');
$query = "SELECT * FROM Devices";
$result = $db->query($query);

$rowCount = 0;
while ($result->fetchArray(SQLITE3_ASSOC)) {
    $rowCount++;
}
?>
    <div class="info_box">
        <span class="info_head">Table summary:</span>
        <div id="summary"><?=$rowCount;?> rows</div>
    </div>
    <h2 class="heading">Raw Data</h2>
<?php
$query = "SELECT * FROM Devices";
$result = $db->query($query);

echo "<table border='0'>";
echo "<tr>";

$columns = [];
for ($i = 0; $i < $result->numColumns(); $i++) {
    $colName = $result->columnName($i);
    $columns[] = $colName;
    echo "<th>" . htmlspecialchars($colName) . "</th>";
}
echo "</tr>";
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    echo "<tr>";
    foreach ($columns as $col) {
        echo "<td data-column='" . htmlspecialchars($col) . "'>" . htmlspecialchars($row[$col]) . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

$db->close();
?>
<script type="text/javascript">
    function getBaseUrl() {
        const protocol = window.location.protocol;
        const host = window.location.host;
        const path = window.location.pathname;
        const scriptDir = path.substring(0, path.lastIndexOf('/') + 1).replace('php/debugging/', '');
        return `${protocol}//${host}${scriptDir}`;
    }
    const baseUrl = getBaseUrl();
    const pialertDiv = document.getElementById("pialert_url");
    if (pialertDiv) {
        const baseUrlLink = document.createElement("a");
        baseUrlLink.href = baseUrl;
        baseUrlLink.textContent = baseUrl;
        pialertDiv.appendChild(baseUrlLink);
    }
</script>
</body>
</html>

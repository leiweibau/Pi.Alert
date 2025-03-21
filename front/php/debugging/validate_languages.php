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
            padding: 0px;
            margin: 0px;
        }
        .heading {
            font-size: 1.2em;
            margin: 0px;
        }
        .info_head {
            font-size: 1.2em;
            font-weight: bold;
        }
        .info_box {
            margin-top: 40px;
            margin-bottom: 40px;
            box-shadow: 0px 0px 15px #bbb;
            width: auto;
            margin-left: 20px;
            margin-right: 20px;
            padding: 10px;
        }
        .short {
            width: 300px;
        }
        a {
            color: dodgerblue;
            text-decoration: none;
        }
        a:hover {
            color: deepskyblue; 
        }
        .languages {
            display: inline-block; width: 120px;
        }
        .topheader {
            width: 100%; background-color: #f0f0f0; position: relative; top: 0px; padding-top: 10px; padding-bottom: 10px; margin: 0px; text-align: center;
        }
        #pialert_url {
            margin-top: 10px;
        }
        .resultheader {
            width: 100%; background-color: #f0f0f0; position: relative; top: 0px; padding-top: 10px; padding-bottom: 10px; margin: 0px; text-align: center;
        }
    </style>
</head>
<body>
    <div class="topheader">
        <h2 style="margin: 0px">Language Array Compare</h2>
    </div>

    <div class="info_box short">
        <span class="info_head">Pi.Alert-URL:</span><br>
        <div id="pialert_url"></div>
    </div>

    <div class="resultheader">
        <h2 class="heading">Results</h2>
    </div>

    <div class="info_box">
        <h2 class="heading">Entry Count</h2>

<?php
require '../templates/language/de_de.php';
$dede = $pia_lang;
echo '<div class="languages">German: </div>' .sizeof($pia_lang). ' entries';
echo '<br>';

require '../templates/language/en_us.php';
$enus = $pia_lang;
echo '<div class="languages">English: </div>' .sizeof($pia_lang). ' entries';
echo '<br>';

require '../templates/language/es_es.php';
$eses = $pia_lang;
echo '<div class="languages">Spanish: </div>' .sizeof($pia_lang). ' entries';
echo '<br>';

require '../templates/language/fr_fr.php';
$frfr = $pia_lang;
echo '<div class="languages">French: </div>' .sizeof($pia_lang). ' entries';
echo '<br>';

require '../templates/language/it_it.php';
$itit = $pia_lang;
echo '<div class="languages">Italian: </div>' .sizeof($pia_lang). ' entries';

$all_keys = array_unique(array_merge(array_keys($dede), array_keys($enus), array_keys($eses), array_keys($frfr), array_keys($itit)));

$missing = [];

foreach ([
    'de_de' => $dede,
    'en_us' => $enus,
    'es_es' => $eses,
    'fr_fr' => $frfr,
    'it_it' => $itit
] as $lang => $arr) {
    foreach ($all_keys as $key) {
        if (!array_key_exists($key, $arr)) {
            $missing[$lang][] = $key;
        }
    }
}

echo '</div>';

echo '<div class="info_box">
        <h2 class="heading">Missing Entries</h2>';
foreach ($missing as $lang => $keys) {
    echo '<div class="languages"><strong>' . $lang . ':</strong></div> ' . implode(', ', $keys) . '<br>';
}
echo '</div>';
?>

    <script>
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
            baseUrlLink.href = baseUrl + 'maintenance.php';
            baseUrlLink.textContent = baseUrl;
            pialertDiv.appendChild(baseUrlLink);
        }
    </script>

</body>
</html>
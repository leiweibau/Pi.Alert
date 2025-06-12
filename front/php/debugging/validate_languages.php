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
            display: inline-block; width: 150px;
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
$languages = [
    'de_de' => 'German',
    'en_us' => 'English',
    'es_es' => 'Spanish',
    'fr_fr' => 'French',
    'it_it' => 'Italian',
    'pl_pl' => 'Polish',
    'nl_nl' => 'Dutch',
    'cz_cs' => 'Czech',
    'dk_da' => 'Danish'
];

foreach ($languages as $code => $label) {
    require "../templates/language/{$code}.php";

    // Optional: Spracharrays für später speichern (wie im Original)
    ${str_replace('_', '', $code)} = $pia_lang;
    ${str_replace('_', '', $code) . '_journ'} = $pia_journ_lang;

    echo "<div class=\"languages\">{$label}: </div>" . sizeof($pia_lang) . " entries<br>";
    echo "<div class=\"languages\">{$label} (Journal): </div>" . sizeof($pia_journ_lang) . " entries<br>";

    unset($pia_lang, $pia_journ_lang);
}


$all_keys_lang = array_unique(array_merge(array_keys($dede), array_keys($enus), array_keys($eses), array_keys($frfr), array_keys($itit)));
$all_keys_journ = array_unique(array_merge(array_keys($dede_journ), array_keys($enus_journ), array_keys($eses_journ), array_keys($frfr_journ), array_keys($itit_journ)));

$missing_lang = [];
$missing_journ = [];

foreach ([
    'de_de' => $dede,
    'en_us' => $enus,
    'es_es' => $eses,
    'fr_fr' => $frfr,
    'it_it' => $itit,
    'nl_nl' => $nlnl,
    'pl_pl' => $plpl,
    'cz_cs' => $czcs,
    'dk_da' => $dkda
] as $lang => $arr) {
    foreach ($all_keys_lang as $key) {
        if (!array_key_exists($key, $arr)) {
            $missing_lang[$lang][] = $key;
        }
    }
}

foreach ([
    'de_de' => $dede_journ,
    'en_us' => $enus_journ,
    'es_es' => $eses_journ,
    'fr_fr' => $frfr_journ,
    'it_it' => $itit_journ,
    'nl_nl' => $nlnl_journ,
    'pl_pl' => $plpl_journ,
    'cz_cs' => $czcs_journ,
    'dk_da' => $dkda_journ
] as $lang => $arr) {
    foreach ($all_keys_journ as $key) {
        if (!array_key_exists($key, $arr)) {
            $missing_journ[$lang][] = $key;
        }
    }
}

echo '</div>';

echo '<div class="info_box">
        <h2 class="heading">Missing Entries (pia_lang)</h2>';
foreach ($missing_lang as $lang => $keys) {
    echo '<div class="languages"><strong>' . $lang . ':</strong></div> ' . implode(', ', $keys) . '<br>';
}
echo '</div>';

echo '<div class="info_box">
        <h2 class="heading">Missing Entries (pia_journ_lang)</h2>';
foreach ($missing_journ as $lang => $keys) {
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
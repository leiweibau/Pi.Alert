<?php
$configDirectory = __DIR__ . "/../../config";
$languageDirectory = __DIR__ . "/../php/templates/language";
$selectedLanguage = "en_us";

// The language selector is intentionally resolved from known language files.
// A setting file can select a language, but cannot influence the include path.
foreach (glob($configDirectory . "/setting_language_*") ?: array() as $filename) {
    $candidate = substr(basename($filename), strlen("setting_language_"));
    if (preg_match("/^[a-z]{2}_[a-z]{2}$/", $candidate) && is_file($languageDirectory . "/" . $candidate . ".php")) {
        $selectedLanguage = $candidate;
        break;
    }
}
require $languageDirectory . "/" . $selectedLanguage . ".php";

$pattern = $configDirectory . "/setting_*";
$zip_name = __DIR__ . "/../../db/temp/uisettings.zip";
$files = glob($pattern) ?: array();
$existing_files = array_filter($files, "file_exists");

if (empty($existing_files)) {
    exit($pia_lang["UISettings_NoFiles"] . PHP_EOL);
}

$escaped_files = array_map("escapeshellarg", $existing_files);
$command = "zip -j " . escapeshellarg($zip_name) . " " . implode(" ", $escaped_files);
exec($command, $output, $result_code);

if ($result_code !== 0) {
    exit($pia_lang["UISettings_ZipCreateFailed"] . PHP_EOL);
}

if (file_exists($zip_name)) {
    header("Content-Type: application/zip");
    header("Content-Disposition: attachment; filename=\"" . basename($zip_name) . "\"");
    header("Content-Length: " . filesize($zip_name));
    flush();
    readfile($zip_name);
    unlink($zip_name);
    exit;
}

echo $pia_lang["UISettings_ZipNotFound"];
?>

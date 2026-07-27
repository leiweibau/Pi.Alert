<?php
session_start();

if ($_SESSION["login"] != 1) {
    header('Location: ../index.php');
    exit;
}

$dir = "../../config/";
$pattern = $dir . "setting_*";
$zip_name = "../../db/temp/uisettings.zip";

$files = glob($pattern);

$existing_files = array_filter($files, function($file) {
    return file_exists($file);
});

if (empty($existing_files)) {
    exit("None of the specified files were found.");
}

$escaped_files = array_map('escapeshellarg', $existing_files);
$command = "zip -j " . escapeshellarg($zip_name) . " " . implode(' ', $escaped_files);

exec($command, $output, $result_code);

if ($result_code !== 0) {
    exit("Error creating the ZIP file.");
}

if (file_exists($zip_name)) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($zip_name) . '"');
    header('Content-Length: ' . filesize($zip_name));
    flush();
    readfile($zip_name);
    unlink($zip_name);
    exit;
} else {
    echo "The ZIP file was not found.";
}
?>


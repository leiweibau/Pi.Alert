<?php

define('PIALERT_V4_PUBLIC_ENTRY', true);
require_once __DIR__ . '/../php/bootstrap.php';

pialert_v4_start_session();
if (($_SESSION['login'] ?? 0) != 1) {
    header('Location: ../index.php');
    exit;
}

$settingsFile = pialert_v4_ui_path();
if (!file_exists($settingsFile) && !is_link($settingsFile)) {
    $settingsFile = pialert_v4_ui_default_path();
}
if (is_link($settingsFile) || !is_file($settingsFile) || !is_readable($settingsFile)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit("UI settings file not found.\n");
}

$size = filesize($settingsFile);
if ($size === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit("UI settings file cannot be read.\n");
}

header('Content-Description: File Transfer');
header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="setting_ui_v4.json"');
header('X-Content-Type-Options: nosniff');
header('Expires: 0');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Length: ' . $size);
readfile($settingsFile);
exit;

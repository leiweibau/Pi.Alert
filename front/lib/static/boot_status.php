<?php
// Minimal read-only status for the reboot waiting page; works without app bootstrap.
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
$bootId = trim((string) @file_get_contents('/proc/sys/kernel/random/boot_id'));
if (!preg_match('/^[a-f0-9-]{36}$/D', $bootId)) {
    http_response_code(503);
    echo '{}';
    exit;
}
echo json_encode(array('bootId' => $bootId));

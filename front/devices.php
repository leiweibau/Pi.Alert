<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('PIALERT_V4_PUBLIC_ENTRY', true);
require_once __DIR__ . '/php/bootstrap.php';
pialert_v4_start_session();

if (($_SESSION['login'] ?? 0) != 1) {
    header('Location: ' . pialert_v4_route('login'));
    exit;
}

require __DIR__ . '/php/devices-page.php';

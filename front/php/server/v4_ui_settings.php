<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('PIALERT_V4_PUBLIC_ENTRY', true);
require_once __DIR__ . '/../bootstrap.php';
pialert_v4_start_session();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SESSION['login'] ?? 0) != 1) {
    http_response_code(401);
    echo json_encode(array('error'=>'Authentication required'));
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    echo json_encode(array('error'=>'Method not allowed'));
    exit;
}
if ($method === 'POST') pialert_validate_csrf();

try {
    if ($method === 'GET') {
        if (($_GET['action'] ?? '') !== 'get') throw new InvalidArgumentException('Unknown action');
        $settings = pialert_v4_ui_read();
    } else {
        if (($_POST['action'] ?? '') !== 'save' || !isset($_POST['patch']) || !is_string($_POST['patch']) || strlen($_POST['patch']) > 32768) throw new InvalidArgumentException('Invalid request');
        try { $patch = json_decode($_POST['patch'], true, 16, JSON_THROW_ON_ERROR); } catch (JsonException $e) { throw new InvalidArgumentException('Invalid JSON', 0, $e); }
        if (!is_array($patch)) throw new InvalidArgumentException('Invalid patch');
        $revision = $_POST['revision'] ?? null;
        if ($revision !== null && (!is_string($revision) || !ctype_digit($revision) || strlen($revision) > 12)) throw new InvalidArgumentException('Invalid revision');
        $settings = pialert_v4_ui_update_many($patch, $revision === null ? null : (int) $revision);
    }
    echo json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (DomainException $e) {
    http_response_code(409);
    echo json_encode(array('error'=>$e->getMessage()));
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(array('error'=>$e->getMessage()));
} catch (Throwable $e) {
    error_log('v4 UI settings: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(array('error'=>'UI settings unavailable'));
}

<?php
require_once __DIR__ . '/session.php';
pialert_start_session();
require_once __DIR__ . '/csrf.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
if (($_SESSION['login'] ?? null) != 1) {
    http_response_code(401);
    echo json_encode(array('ok'=>false,'error'=>'authentication_required'));
    exit;
}
require_once __DIR__ . '/timezone.php';
require_once __DIR__ . '/logical_devices_lib.php';
require_once __DIR__ . '/util.php';
$action = pialert_dispatch_action(array('list'), array('save','delete','assign'));
$request = pialert_request_data();
try {
    OpenDB();
    $db->enableExceptions(true);
    $trigger = (string)($_SERVER['REMOTE_ADDR'] ?? 'web') . ' / logical_devices.' . $action;
    if ($action === 'list') {
        OpenDB_Tools();
        $response = array('ok'=>true, 'devices'=>logical_list($db, $db_tools));
    } elseif ($action === 'save') {
        $response = array('ok'=>true,'device'=>logical_save($db,$request,$trigger));
    } elseif ($action === 'delete') {
        logical_delete($db, logical_id($request['id'] ?? null), $trigger);
        $response = array('ok'=>true);
    } elseif ($action === 'assign') {
        if (!isset($request['mac']) || !is_string($request['mac']) || !array_key_exists('logical_device_id',$request)) throw new InvalidArgumentException('invalid_input');
        logical_assign($db, $request['mac'], logical_id($request['logical_device_id'],true), $trigger);
        $response = array('ok'=>true);
    } else {
        throw new InvalidArgumentException('invalid_input');
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    $code = 'database_error'; $status = 500;
    if ($error instanceof InvalidArgumentException) { $code='invalid_input'; $status=400; }
    elseif ($error instanceof OutOfBoundsException) { $code='not_found'; $status=404; }
    elseif ($error instanceof DomainException) { $code='duplicate_name'; $status=409; }
    else error_log('Pi.Alert logical device operation failed: '.$error->getMessage());
    http_response_code($status);
    echo json_encode(array('ok'=>false,'error'=>$code));
}

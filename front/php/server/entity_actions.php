<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/journal.php';
require_once __DIR__ . '/../entity-actions.php';

pialert_start_session();
header('Content-Type: application/json; charset=UTF-8');

function entity_actions_response($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SESSION['login'] ?? null) != 1) {
    entity_actions_response(401, array('error' => 'auth_required'));
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'POST') {
    header('Allow: GET, POST');
    entity_actions_response(405, array('error' => 'method_not_allowed'));
}
if ($method === 'POST' && !pialert_csrf_is_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? ''))) {
    entity_actions_response(403, array('error' => 'csrf_invalid'));
}

try {
    OpenDB();
    if (!entity_actions_available($db)) {
        throw new EntityActionError('schema_unavailable', 503);
    }
    if ($method === 'GET') {
        $kind = $_GET['kind'] ?? null;
        $key = $_GET['key'] ?? null;
        $canonicalKey = entity_actions_resolve($db, $kind, $key);
        $actions = entity_actions_read($db, $kind, $canonicalKey);
        entity_actions_response(200, array('actions' => $actions,
            'version' => entity_actions_version($actions),
            'schema_available' => entity_actions_has_color($db) && entity_actions_has_text_color($db)));
    }

    if (!entity_actions_has_color($db) || !entity_actions_has_text_color($db)) {
        throw new EntityActionError('schema_unavailable', 503);
    }

    if (($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) {
        throw new EntityActionError('invalid_actions');
    }
    $request = json_decode(file_get_contents('php://input'), true);
    if (!is_array($request)) {
        throw new EntityActionError('invalid_actions');
    }
    $kind = $request['kind'] ?? null;
    $key = $request['key'] ?? null;
    $result = entity_actions_save($db, $kind, $key,
        $request['version'] ?? null, $request['actions'] ?? null);
    try {
        pialert_logging($kind === 'device' ? 'a_020' : 'a_031',
            $_SERVER['REMOTE_ADDR'] ?? 'webui', 'LogStr_0078', '',
            $kind . ': ' . $key . ' (' . count($result['actions']) . ')');
    } catch (Throwable $journalError) {
        error_log('Entity_Actions journal entry failed: ' . $journalError->getMessage());
    }
    entity_actions_response(200, $result);
} catch (EntityActionError $error) {
    $payload = array('error' => $error->errorCode);
    if ($error->rowIndex !== null) {
        $payload['row'] = $error->rowIndex;
    }
    entity_actions_response($error->httpStatus, $payload);
} catch (Throwable $error) {
    error_log('Entity_Actions endpoint failed: ' . $error->getMessage());
    entity_actions_response(500, array('error' => 'save_failed'));
}

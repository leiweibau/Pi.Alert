<?php

const PIALERT_CSRF_SESSION_KEY = 'pialert_csrf_token';

function pialert_csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('Session must be active before using CSRF protection');
    }
    $token = $_SESSION[PIALERT_CSRF_SESSION_KEY] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
        $token = bin2hex(random_bytes(32));
        $_SESSION[PIALERT_CSRF_SESSION_KEY] = $token;
    }
    return $token;
}

function pialert_csrf_rotate(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('Session must be active before rotating CSRF protection');
    }
    $_SESSION[PIALERT_CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
    return $_SESSION[PIALERT_CSRF_SESSION_KEY];
}

function pialert_require_method(string $method): void {
    $method = strtoupper($method);
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== $method) {
        header('Allow: ' . $method);
        http_response_code(405);
        echo 'Method Not Allowed';
        exit;
    }
}

function pialert_csrf_is_valid($provided): bool {
    $expected = $_SESSION[PIALERT_CSRF_SESSION_KEY] ?? '';
    return is_string($provided) && is_string($expected)
        && preg_match('/^[a-f0-9]{64}$/D', $provided) === 1
        && preg_match('/^[a-f0-9]{64}$/D', $expected) === 1
        && hash_equals($expected, $provided);
}

function pialert_validate_csrf(): void {
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    if (!pialert_csrf_is_valid($provided)) {
        header('X-PiAlert-CSRF: invalid');
        http_response_code(403);
        echo 'Invalid CSRF token';
        exit;
    }
}

function pialert_guard_operation_replay(string $action): void {
    $criticalActions = [
        'PialertReboot', 'PialertShutdown', 'RestoreDBfromArchive',
        'BackupDBtoArchive', 'BackupDBtoCSV', 'BackupConfigFile',
        'RestoreConfigFile', 'downloadGeoDB', 'updateGeoDB', 'wakeonlan'
    ];
    if (!in_array($action, $criticalActions, true)) {
        return;
    }

    $operationId = $_POST['_operation_id'] ?? '';
    if ($operationId === '') {
        return; // HTML forms and legacy same-origin callers remain compatible.
    }
    if (!is_string($operationId) || preg_match('/^[a-f0-9]{32}$/D', $operationId) !== 1) {
        http_response_code(400);
        exit('Invalid operation identifier');
    }

    $now = time();
    $used = $_SESSION['pialert_used_operations'] ?? [];
    $used = array_filter(is_array($used) ? $used : [], static function ($timestamp) use ($now) {
        return is_int($timestamp) && $timestamp >= $now - 600;
    });
    if (isset($used[$operationId])) {
        http_response_code(409);
        exit('Operation already processed');
    }
    $used[$operationId] = $now;
    $_SESSION['pialert_used_operations'] = $used;
}

function pialert_dispatch_action(array $getActions, array $postActions): ?string {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        $action = $_GET['action'] ?? null;
        $request = $_GET;
        $allowed = $getActions;
        $wrongMethod = $postActions;
    } elseif ($method === 'POST') {
        $action = $_POST['action'] ?? null;
        $request = $_POST;
        $allowed = $postActions;
        $wrongMethod = $getActions;
    } else {
        header('Allow: GET, POST');
        http_response_code(405);
        echo 'Method Not Allowed';
        exit;
    }

    if ($action === null || $action === '') {
        $GLOBALS['pialert_request'] = $request;
        return null;
    }
    if (!is_string($action)) {
        http_response_code(400);
        echo 'Invalid action';
        exit;
    }
    if (in_array($action, $wrongMethod, true)) {
        header('Allow: ' . ($method === 'GET' ? 'POST' : 'GET'));
        http_response_code(405);
        echo 'Method Not Allowed';
        exit;
    }
    if (!in_array($action, $allowed, true)) {
        http_response_code(400);
        echo 'Unknown action';
        exit;
    }
    if ($method === 'POST') {
        pialert_validate_csrf();
        pialert_guard_operation_replay($action);
    }
    $GLOBALS['pialert_request'] = $request;
    return $action;
}

function pialert_request_data(): array {
    return isset($GLOBALS['pialert_request']) && is_array($GLOBALS['pialert_request'])
        ? $GLOBALS['pialert_request'] : array();
}

?>

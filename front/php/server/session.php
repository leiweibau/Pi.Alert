<?php

function pialert_request_is_https(): bool {
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off' && $https !== '0') {
        return true;
    }
    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    // Pi.Alert is commonly deployed behind an LXC/reverse proxy. The proxy is
    // expected to replace this header rather than append a client value.
    $forwarded = explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return strtolower(trim($forwarded[0] ?? '')) === 'https';
}

function pialert_cookie_path(): string {
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    foreach (array('/php/server/', '/download/', '/php/debugging/') as $marker) {
        $position = strpos($script, $marker);
        if ($position !== false) {
            $base = substr($script, 0, $position);
            return $base === '' ? '/' : rtrim($base, '/') . '/';
        }
    }

    $base = str_replace('\\', '/', dirname($script));
    return $base === '' || $base === '.' || $base === '/' ? '/' : rtrim($base, '/') . '/';
}

function pialert_session_cookie_options(int $expires = 0): array {
    return array(
        'expires' => $expires,
        'path' => pialert_cookie_path(),
        'secure' => pialert_request_is_https(),
        'httponly' => true,
        // Lax is required for a session restored through the remember-me flow
        // after the user follows a link from another site. With Strict, the
        // complete redirect chain can remain cross-site in Firefox, causing
        // protected page -> login -> protected page redirect loops.
        'samesite' => 'Lax',
    );
}

function pialert_remember_cookie_options(int $expires = 0): array {
    $options = pialert_session_cookie_options($expires);
    $options['samesite'] = 'Lax';
    return $options;
}

// Compatibility for callers that create ordinary PHP session cookies.
function pialert_cookie_options(int $expires = 0): array {
    return pialert_session_cookie_options($expires);
}
function pialert_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    $sessionCookieOptions = pialert_session_cookie_options();
    unset($sessionCookieOptions["expires"]);
    $sessionCookieOptions["lifetime"] = 0;
    session_set_cookie_params($sessionCookieOptions);
    session_start();
    pialert_restore_remembered_session();
}

function pialert_restore_remembered_session(?SQLite3 $database = null): bool {
    // Mutating requests must retain their normal session and CSRF checks.
    if (session_status() !== PHP_SESSION_ACTIVE || ($_SESSION['login'] ?? 0) == 1
        || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
        || !isset($_COOKIE['PiAlert_SaveLogin'])) {
        return false;
    }

    require_once __DIR__ . '/auth.php';
    if (!preg_match('/^[a-f0-9]{64}$/D', pialert_remember_cookie_value())) {
        pialert_delete_auth_cookie(PIALERT_REMEMBER_COOKIE);
        return false;
    }

    $ownsDatabase = $database === null;
    try {
        if ($database === null) {
            $database = new SQLite3(__DIR__ . '/../../../db/pialert.db', SQLITE3_OPEN_READWRITE);
            $database->busyTimeout(2000);
        }
        $remembered = pialert_consume_remember_token($database);
        if ($ownsDatabase) {
            $database->close();
        }
        if (!$remembered || !session_regenerate_id(true)) {
            return false;
        }
    } catch (Throwable $exception) {
        error_log('Pi.Alert could not restore a remembered login: ' . $exception->getMessage());
        return false;
    }

    require_once __DIR__ . '/csrf.php';
    pialert_csrf_rotate();
    $_SESSION['login'] = 1;
    $_SESSION['WebProtection'] = 'true';
    return true;
}

function pialert_set_auth_cookie(string $name, string $value, int $expires): bool {
    return setcookie($name, $value, pialert_remember_cookie_options($expires));
}

function pialert_delete_auth_cookie(string $name): bool {
    return pialert_set_auth_cookie($name, '', time() - 3600);
}

?>

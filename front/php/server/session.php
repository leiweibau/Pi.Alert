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
        'samesite' => 'Strict',
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
}

function pialert_set_auth_cookie(string $name, string $value, int $expires): bool {
    return setcookie($name, $value, pialert_remember_cookie_options($expires));
}

function pialert_delete_auth_cookie(string $name): bool {
    return pialert_set_auth_cookie($name, '', time() - 3600);
}

?>

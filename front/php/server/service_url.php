<?php

const PIALERT_SERVICE_URL_MAX_LENGTH = 2048;

function pialert_validate_service_url($value, &$reason = null) {
    $reason = 'Invalid service URL';
    if (!is_string($value) || $value === '' || strlen($value) > PIALERT_SERVICE_URL_MAX_LENGTH) {
        return false;
    }
    if (preg_match('/[\x00-\x20\x7f"\'`]/', $value) || preg_match('/%(?![0-9A-Fa-f]{2})/', $value)) {
        return false;
    }
    $parts = parse_url($value);
    if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
        return false;
    }
    if (!in_array(strtolower($parts['scheme']), array('http', 'https'), true) ||
        $parts['host'] === '' || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
        return false;
    }
    if (isset($parts['port']) && ($parts['port'] < 1 || $parts['port'] > 65535)) {
        return false;
    }
    $host = trim($parts['host'], '[]');
    $isIp = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false;
    if (!$isIp && (preg_match('/^[0-9.]+$/D', $host) || strlen($host) > 253 ||
        !preg_match('/^(?=.{1,253}$)(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)(?:\.(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?))*\.?$/D', $host))) {
        return false;
    }
    $reason = null;
    return true;
}

// Existing rows must remain viewable and deletable without a network request.
function pialert_validate_service_key($value) {
    return is_string($value) && $value !== '' && strlen($value) <= PIALERT_SERVICE_URL_MAX_LENGTH &&
        !preg_match('/[\x00\r\n]/', $value);
}


function pialert_check_service_url($url) {
    $fallback = array(
        'status' => 0,
        'initial_status' => 0,
        'latency' => '99999999',
        'target_ip' => '',
        'final_url' => '',
        'redirect_count' => 0,
        'error_code' => 'connection_error',
        'error_message' => 'Service check failed',
        'note' => 'Service check failed',
        'diagnostic' => 'connection_error:',
        'ssl_subject' => '',
        'ssl_issuer' => '',
        'ssl_valid_from' => '',
        'ssl_valid_to' => '',
    );
    $script = realpath(__DIR__ . '/../../../back/service_url_check.py');
    if ($script === false || !function_exists('proc_open')) {
        return $fallback;
    }

    $descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $process = proc_open(array('/usr/bin/python3', $script, $url), $descriptors, $pipes);
    if (!is_resource($process)) {
        return $fallback;
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1], 32768);
    $stderr = stream_get_contents($pipes[2], 4096);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0 || $stderr !== '') {
        return $fallback;
    }

    $result = json_decode($stdout, true);
    $stringLimits = array(
        'target_ip' => 64,
        'final_url' => PIALERT_SERVICE_URL_MAX_LENGTH,
        'error_code' => 64,
        'error_message' => 256,
        'note' => 256,
        'diagnostic' => 8192,
        'ssl_subject' => 4096,
        'ssl_issuer' => 4096,
        'ssl_valid_from' => 128,
        'ssl_valid_to' => 128,
    );
    if (!is_array($result) || !isset($result['status'], $result['latency']) ||
        !is_int($result['status']) || $result['status'] < 0 || $result['status'] > 599 ||
        !is_scalar($result['latency']) || !isset($result['initial_status'], $result['redirect_count']) ||
        !is_int($result['initial_status']) || $result['initial_status'] < 0 || $result['initial_status'] > 599 ||
        !is_int($result['redirect_count']) || $result['redirect_count'] < 0 || $result['redirect_count'] > 10) {
        return $fallback;
    }
    foreach ($stringLimits as $key => $maximumLength) {
        if (!isset($result[$key]) || !is_string($result[$key]) || strlen($result[$key]) > $maximumLength) {
            return $fallback;
        }
    }
    $allowedErrors = array('', 'invalid_url', 'dns_error', 'connection_error', 'tls_error',
        'blocked_by_policy', 'redirect_loop', 'redirect_limit');
    if (!in_array($result['error_code'], $allowedErrors, true)) {
        return $fallback;
    }
    return array(
        'status' => $result['status'],
        'initial_status' => $result['initial_status'],
        'latency' => (string) $result['latency'],
        'target_ip' => $result['target_ip'],
        'final_url' => $result['final_url'],
        'redirect_count' => $result['redirect_count'],
        'error_code' => $result['error_code'],
        'error_message' => $result['error_message'],
        'note' => $result['note'],
        'diagnostic' => $result['diagnostic'],
        'ssl_subject' => $result['ssl_subject'],
        'ssl_issuer' => $result['ssl_issuer'],
        'ssl_valid_from' => $result['ssl_valid_from'],
        'ssl_valid_to' => $result['ssl_valid_to'],
    );
}
?>

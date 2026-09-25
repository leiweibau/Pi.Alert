<?php

const PIALERT_GEODB_URL = 'https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-Country.mmdb';
const PIALERT_GEODB_MAX_BYTES = 67108864;

function pialert_download_geodb(string $destination): void {
    $stream = @fopen($destination, 'wb');
    if ($stream === false) throw new RuntimeException('Cannot open temporary GeoLite2 database');

    $curl = curl_init(PIALERT_GEODB_URL);
    if ($curl === false) {
        fclose($stream);
        throw new RuntimeException('Cannot start GeoLite2 download');
    }

    $bytes = 0;
    try {
        if (!curl_setopt_array($curl, array(
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 50,
            CURLOPT_FAILONERROR => true,
            CURLOPT_USERAGENT => 'Pi.Alert GeoLite2 updater',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use ($stream, &$bytes): int {
                $length = strlen($chunk);
                if ($bytes + $length > PIALERT_GEODB_MAX_BYTES) return 0;
                $written = fwrite($stream, $chunk);
                if ($written === false) return 0;
                $bytes += $written;
                return $written;
            },
        ))) throw new RuntimeException('Cannot configure GeoLite2 download');
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $secure = curl_setopt($curl, CURLOPT_PROTOCOLS_STR, 'https')
                && curl_setopt($curl, CURLOPT_REDIR_PROTOCOLS_STR, 'https');
        } else {
            $secure = curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS)
                && curl_setopt($curl, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
        }
        if (!$secure) throw new RuntimeException('Cannot restrict GeoLite2 download to HTTPS');

        $ok = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($ok !== true || $status !== 200 || $bytes === 0 || !fflush($stream)) {
            throw new RuntimeException('GeoLite2 download failed');
        }
    } finally {
        curl_close($curl);
        fclose($stream);
    }
}

function pialert_validate_geodb(string $path): bool {
    $size = @filesize($path);
    if ($size === false || $size < 1048576 || $size > PIALERT_GEODB_MAX_BYTES || !function_exists('exec')) return false;

    $output = array();
    $exitCode = 1;
    exec('mmdblookup --file ' . escapeshellarg($path) . ' --ip 1.1.1.1 --verbose 2>&1', $output, $exitCode);
    return $exitCode === 0 && preg_match('/^\s*Type:\s*GeoLite2-Country\s*$/m', implode("\n", $output)) === 1;
}

function pialert_replace_geodb(string $target, ?callable $downloader = null): void {
    $directory = realpath(dirname($target));
    if ($directory === false || !is_writable($directory)) throw new RuntimeException('GeoLite2 database directory is not writable');
    $lock = @fopen($target . '.lock', 'c');
    if ($lock === false) throw new RuntimeException('Cannot lock GeoLite2 database');

    $temporary = false;
    try {
        if (!flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('GeoLite2 update already running');
        $temporary = tempnam($directory, '.geodb-');
        if ($temporary === false || dirname($temporary) !== $directory) {
            throw new RuntimeException('Cannot create temporary GeoLite2 database in its directory');
        }

        ($downloader ?? 'pialert_download_geodb')($temporary);
        if (!pialert_validate_geodb($temporary)) throw new RuntimeException('Invalid GeoLite2 database');
        if (!chmod($temporary, 0644)) throw new RuntimeException('Cannot set GeoLite2 database permissions');
        if (!rename($temporary, $target)) throw new RuntimeException('Cannot replace GeoLite2 database');
        $temporary = false;
    } finally {
        if ($temporary !== false && is_file($temporary)) @unlink($temporary);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

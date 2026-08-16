<?php
// Shared, testable helpers for safe pialert.conf read/write operations.

if (!defined('PIALERT_CONFIG_MAX_BYTES')) {
    define('PIALERT_CONFIG_MAX_BYTES', 524288);
}

function pialert_config_helper_path() {
    $helper = realpath(__DIR__ . '/../../../back/config_editor.py');
    if ($helper === false) {
        throw new RuntimeException('Configuration editor helper is unavailable');
    }
    return $helper;
}

function pialert_config_validator_path() {
    $validator = realpath(__DIR__ . '/../../../back/validate_pialert_config.py');
    if ($validator === false) {
        throw new RuntimeException('Configuration validator is unavailable');
    }
    return $validator;
}

function pialert_config_root_path($configfile) {
    $directory = realpath(dirname($configfile));
    if ($directory === false) {
        throw new RuntimeException('Configuration directory is unavailable');
    }
    return dirname($directory);
}

function pialert_acquire_config_lock($configfile) {
    $directory = realpath(dirname($configfile));
    if ($directory === false) {
        throw new RuntimeException('Configuration directory is unavailable');
    }
    $handle = fopen($directory . '/.pialert.conf.lock', 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        throw new RuntimeException('Unable to lock configuration');
    }
    return $handle;
}

function pialert_release_config_lock($handle) {
    if (is_resource($handle)) {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function pialert_flush_file($path, $content) {
    $handle = fopen($path, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Unable to open temporary configuration file');
    }
    try {
        $length = strlen($content);
        $written = 0;
        while ($written < $length) {
            $result = fwrite($handle, substr($content, $written));
            if ($result === false || $result === 0) {
                throw new RuntimeException('Unable to write complete configuration file');
            }
            $written += $result;
        }
        if (!fflush($handle)) {
            throw new RuntimeException('Unable to flush configuration file');
        }
        if (function_exists('fsync') && !fsync($handle)) {
            throw new RuntimeException('Unable to synchronize configuration file');
        }
    } finally {
        fclose($handle);
    }
}

function pialert_create_verified_config_backup($configfile, $backupfile) {
    $directory = realpath(dirname($configfile));
    if ($directory === false || realpath(dirname($backupfile)) !== $directory) {
        throw new RuntimeException('Invalid configuration backup path');
    }
    $source = file_get_contents($configfile);
    if ($source === false) {
        throw new RuntimeException('Unable to read current configuration');
    }

    $temporary = tempnam($directory, '.pialert.backup.');
    if ($temporary === false) {
        throw new RuntimeException('Unable to create temporary configuration backup');
    }
    try {
        pialert_flush_file($temporary, $source);
        $verified = file_get_contents($temporary);
        if ($verified === false || strlen($verified) !== strlen($source) ||
                !hash_equals(hash('sha256', $source), hash('sha256', $verified))) {
            throw new RuntimeException('Unable to verify configuration backup');
        }
        $mode = @fileperms($configfile);
        if ($mode !== false) {
            @chmod($temporary, $mode & 0777);
        }
        if (!rename($temporary, $backupfile)) {
            throw new RuntimeException('Unable to install configuration backup');
        }
        $temporary = null;
        $installed = file_get_contents($backupfile);
        if ($installed === false || strlen($installed) !== strlen($source) ||
                !hash_equals(hash('sha256', $source), hash('sha256', $installed))) {
            throw new RuntimeException('Unable to verify installed configuration backup');
        }
    } finally {
        if ($temporary !== null && file_exists($temporary)) {
            @unlink($temporary);
        }
    }
}

function pialert_run_config_command($command, &$output = null) {
    $lines = array();
    $status = 1;
    exec($command, $lines, $status);
    $output = implode("\n", $lines);
    if ($status !== 0) {
        throw new InvalidArgumentException('Invalid configuration');
    }
}

function pialert_mask_config_for_editor($configfile) {
    $directory = realpath(dirname($configfile));
    if ($directory === false) {
        throw new RuntimeException('Configuration directory is unavailable');
    }
    $outputFile = tempnam($directory, '.pialert.masked.');
    if ($outputFile === false) {
        throw new RuntimeException('Unable to create masked configuration');
    }
    try {
        $command = 'python3 ' . escapeshellarg(pialert_config_helper_path()) .
            ' mask --input ' . escapeshellarg($configfile) .
            ' --output ' . escapeshellarg($outputFile);
        pialert_run_config_command($command, $ignoredOutput);
        $content = file_get_contents($outputFile);
        if ($content === false || strlen($content) > PIALERT_CONFIG_MAX_BYTES) {
            throw new RuntimeException('Unable to read masked configuration');
        }
        return $content;
    } finally {
        if (file_exists($outputFile)) {
            @unlink($outputFile);
        }
    }
}

function pialert_prepare_editor_candidate($content, $backupfile, $configfile) {
    if (!is_string($content) || strlen($content) > PIALERT_CONFIG_MAX_BYTES) {
        throw new InvalidArgumentException('Invalid configuration input');
    }
    $directory = realpath(dirname($configfile));
    if ($directory === false) {
        throw new RuntimeException('Configuration directory is unavailable');
    }
    $inputFile = tempnam($directory, '.pialert.editor.');
    $outputFile = tempnam($directory, '.pialert.candidate.');
    if ($inputFile === false || $outputFile === false) {
        if ($inputFile !== false) @unlink($inputFile);
        if ($outputFile !== false) @unlink($outputFile);
        throw new RuntimeException('Unable to create configuration editor files');
    }
    try {
        pialert_flush_file($inputFile, $content);
        $command = 'python3 ' . escapeshellarg(pialert_config_helper_path()) .
            ' prepare --input ' . escapeshellarg($inputFile) .
            ' --backup ' . escapeshellarg($backupfile) .
            ' --output ' . escapeshellarg($outputFile) .
            ' --expected-pialert-path ' . escapeshellarg(pialert_config_root_path($configfile));
        pialert_run_config_command($command, $metadataJson);
        $metadata = json_decode($metadataJson, true);
        $candidate = file_get_contents($outputFile);
        if (!is_array($metadata) || $candidate === false ||
                strlen($candidate) > PIALERT_CONFIG_MAX_BYTES) {
            throw new RuntimeException('Invalid configuration editor result');
        }
        return array('content' => $candidate, 'metadata' => $metadata);
    } finally {
        @unlink($inputFile);
        @unlink($outputFile);
    }
}

function validate_and_replace_pialert_config($configfile, $content,
        $createBackup = true, $existingLock = null) {
    if (!is_string($content) || strlen($content) > PIALERT_CONFIG_MAX_BYTES) {
        throw new RuntimeException('Unable to prepare configuration update');
    }
    $directory = realpath(dirname($configfile));
    if ($directory === false) {
        throw new RuntimeException('Configuration directory is unavailable');
    }

    $ownsLock = $existingLock === null;
    $lockHandle = $ownsLock ? pialert_acquire_config_lock($configfile) : $existingLock;
    $temporary = null;
    try {
        if ($createBackup) {
            pialert_create_verified_config_backup(
                $configfile, $directory . '/pialert-prev.bak');
        }
        $temporary = tempnam($directory, '.pialert.conf.');
        if ($temporary === false) {
            throw new RuntimeException('Unable to create temporary configuration');
        }
        pialert_flush_file($temporary, $content);

        $command = 'python3 ' . escapeshellarg(pialert_config_validator_path()) .
            ' ' . escapeshellarg($temporary) .
            ' --expected-pialert-path ' . escapeshellarg(pialert_config_root_path($configfile));
        pialert_run_config_command($command, $ignoredOutput);

        $mode = @fileperms($configfile);
        if ($mode !== false) {
            @chmod($temporary, $mode & 0777);
        }
        if (!rename($temporary, $configfile)) {
            throw new RuntimeException('Unable to replace configuration');
        }
        $temporary = null;
    } finally {
        if ($temporary !== null && file_exists($temporary)) {
            @unlink($temporary);
        }
        if ($ownsLock) {
            pialert_release_config_lock($lockHandle);
        }
    }
}
?>

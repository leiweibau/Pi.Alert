<?php

function pialert_v4_sysinfo_read(string $path): string {
    $value = @file_get_contents($path);
    return is_string($value) ? trim($value) : '';
}

function pialert_v4_sysinfo_cpu_list(string $list): array {
    $cpus = array();
    foreach (explode(',', trim($list)) as $part) {
        if (!preg_match('/^(\d+)(?:-(\d+))?$/D', trim($part), $match)) continue;
        $first = (int) $match[1];
        $last = isset($match[2]) ? (int) $match[2] : $first;
        for ($cpu = $first; $cpu <= $last; $cpu++) $cpus[$cpu] = true;
    }
    return array_keys($cpus);
}

function pialert_v4_sysinfo_cpu_mhz(array $values, float $divisor = 1000): array {
    $frequencies = array();
    foreach ($values as $value) {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value <= 0) continue;
        $frequency = (int) round((float) $value / $divisor);
        if ($frequency > 0) $frequencies[] = $frequency;
    }
    sort($frequencies, SORT_NUMERIC);
    return $frequencies;
}

// Keep parsing independent of the host so ARM, SMT and restricted containers
// can be checked without executing the page or touching its databases.
function pialert_v4_sysinfo_cpu_data(string $cpuInfo, array $summary, array $topology, string $status, array $frequencyLimits, array $currentReadings = array()): array {
    $fields = array();
    $walk = static function (array $rows) use (&$walk, &$fields): void {
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            if (isset($row['field']) && is_scalar($row['data'] ?? null)) {
                $fields[rtrim(trim($row['field']), ':')][] = trim((string) $row['data']);
            }
            if (isset($row['children']) && is_array($row['children'])) $walk($row['children']);
        }
    };
    $walk($summary['lscpu'] ?? array());

    $processors = array();
    foreach (preg_split('/\n\s*\n/', trim($cpuInfo)) ?: array() as $block) {
        $processor = array();
        foreach (explode("\n", $block) as $line) {
            $pair = explode(':', $line, 2);
            if (count($pair) === 2) $processor[trim($pair[0])] = trim($pair[1]);
        }
        if ($processor) $processors[] = $processor;
    }
    $names = array();
    // lscpu also decodes ARM implementer/part IDs into CPU model names.
    foreach ($fields['Model name'] ?? array() as $name) {
        if ($name !== '' && !in_array(strtolower($name), array('-', 'unknown', 'n/a'), true)) $names[] = $name;
    }
    if (!$names) {
        foreach ($processors as $processor) {
            // Capitalized "Model" is a board model on Raspberry Pi, not a CPU.
            if (($processor['model name'] ?? '') !== '') $names[] = $processor['model name'];
        }
    }
    if (!$names) {
        foreach ($processors as $processor) {
            $implementer = $processor['CPU implementer'] ?? '';
            $part = $processor['CPU part'] ?? '';
            if (preg_match('/^0x[0-9a-f]+$/iD', $implementer) && preg_match('/^0x[0-9a-f]+$/iD', $part)) {
                // Preserve the actual identifiers when lscpu is unavailable;
                // do not guess a SoC or substitute the board's product name.
                $names[] = 'ARM (' . strtolower($implementer . ':' . $part) . ')';
            }
        }
    }

    $cores = array();
    $completeTopology = !empty($topology['cpus']);
    foreach ($topology['cpus'] ?? array() as $cpu) {
        if (!isset($cpu['socket'], $cpu['core']) || !ctype_digit((string) $cpu['socket']) || !ctype_digit((string) $cpu['core'])) {
            $completeTopology = false;
            continue;
        }
        $cores[$cpu['socket'] . ':' . $cpu['core']] = true;
    }
    $physicalCores = $completeTopology ? count($cores) : null;
    if ($physicalCores === null) {
        $sockets = array();
        foreach ($processors as $processor) {
            if (isset($processor['physical id'], $processor['cpu cores']) && ctype_digit($processor['physical id']) && ctype_digit($processor['cpu cores']) && (int) $processor['cpu cores'] > 0) {
                $sockets[$processor['physical id']] = (int) $processor['cpu cores'];
            }
        }
        if ($sockets) $physicalCores = array_sum($sockets);
    }

    $online = array();
    foreach ($processors as $processor) {
        if (isset($processor['processor']) && ctype_digit($processor['processor'])) $online[] = (int) $processor['processor'];
    }
    if (!$online) {
        foreach ($fields['On-line CPU(s) list'] ?? array() as $list) $online = array_merge($online, pialert_v4_sysinfo_cpu_list($list));
    }
    $available = $online;
    if (preg_match('/^Cpus_allowed_list:[ \t]*([^\r\n]*)/m', $status, $match)) {
        $allowed = pialert_v4_sysinfo_cpu_list($match[1]);
        $available = $online ? array_intersect($online, $allowed) : $allowed;
    }

    $currentFrequencies = array();
    foreach ($currentReadings as $reading) {
        // Prefer hardware feedback; scaling_cur_freq is the driver's current
        // reported value and can represent its most recently requested clock.
        foreach (array('cpuinfo_cur_freq', 'scaling_cur_freq') as $source) {
            $value = pialert_v4_sysinfo_cpu_mhz(array($reading[$source] ?? ''));
            if (!$value) continue;
            $currentFrequencies[] = $value[0];
            break;
        }
    }
    if (!$currentFrequencies) {
        $procFrequencies = array();
        foreach ($processors as $processor) {
            $procFrequencies[] = str_replace(',', '.', $processor['cpu MHz'] ?? '');
        }
        $currentFrequencies = pialert_v4_sysinfo_cpu_mhz($procFrequencies, 1);
    }
    sort($currentFrequencies, SORT_NUMERIC);

    return array(
        'name' => $names ? implode(' / ', array_unique($names)) : null,
        'physical_cores' => $physicalCores,
        'logical_available' => $available ? count(array_unique($available)) : null,
        'frequency_limits_mhz' => pialert_v4_sysinfo_cpu_mhz($frequencyLimits),
        'frequency_current_mhz' => $currentFrequencies,
    );
}

function pialert_v4_sysinfo_cpu(): array {
    $run = static function (string $command): array {
        $output = function_exists('shell_exec') ? @shell_exec($command) : null;
        $data = is_string($output) ? json_decode($output, true) : null;
        return is_array($data) ? $data : array();
    };
    $summary = $run('LC_ALL=C lscpu --json 2>/dev/null');
    // Include offline CPUs and count unique socket/core pairs, not SMT threads.
    $topology = $run('LC_ALL=C lscpu --all --extended=CPU,SOCKET,CORE --json 2>/dev/null');
    if (empty($topology['cpus'])) {
        foreach (glob('/sys/devices/system/cpu/cpu[0-9]*', GLOB_ONLYDIR) ?: array() as $path) {
            $topology['cpus'][] = array(
                'socket' => pialert_v4_sysinfo_read($path . '/topology/physical_package_id'),
                'core' => pialert_v4_sysinfo_read($path . '/topology/core_id'),
            );
        }
    }
    $policyPaths = glob('/sys/devices/system/cpu/cpufreq/policy*', GLOB_ONLYDIR) ?: array();
    if (!$policyPaths) $policyPaths = glob('/sys/devices/system/cpu/cpu[0-9]*/cpufreq', GLOB_ONLYDIR) ?: array();
    $frequencyLimits = $currentReadings = array();
    foreach ($policyPaths as $path) {
        $frequencyLimits[] = pialert_v4_sysinfo_read($path . '/scaling_max_freq');
        $currentReadings[] = array(
            'cpuinfo_cur_freq' => pialert_v4_sysinfo_read($path . '/cpuinfo_cur_freq'),
            'scaling_cur_freq' => pialert_v4_sysinfo_read($path . '/scaling_cur_freq'),
        );
    }
    return pialert_v4_sysinfo_cpu_data(
        pialert_v4_sysinfo_read('/proc/cpuinfo'), $summary, $topology,
        pialert_v4_sysinfo_read('/proc/self/status'), $frequencyLimits, $currentReadings
    );
}

function pialert_v4_sysinfo_cpu_frequency(array $frequencies, string $unknown): string {
    if (!$frequencies) return $unknown;
    // Keep repeated readings: equal clocks from separate policies/CPUs still
    // contribute to the arithmetic mean.
    return (string) (int) round(array_sum($frequencies) / count($frequencies)) . ' MHz';
}

function pialert_v4_sysinfo_cpu_cores(array $cpu, string $unknown): string {
    $current = !empty($cpu['frequency_current_mhz']);
    $frequencies = $current ? $cpu['frequency_current_mhz'] : $cpu['frequency_limits_mhz'];
    return (string) ($cpu['physical_cores'] ?? $unknown) . ' @ '
        . pialert_v4_sysinfo_cpu_frequency($frequencies, $unknown)
        . (!$current && $frequencies ? ' (max)' : '');
}

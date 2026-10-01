<?php

function pialert_geodb_lookup_string(string $database, string $ip, array $path): ?string {
    if (!function_exists('exec')) return null;

    $command = 'mmdblookup --file ' . escapeshellarg($database)
        . ' --ip ' . escapeshellarg($ip)
        . ' ' . implode(' ', array_map('escapeshellarg', $path))
        . ' 2>/dev/null';
    $output = array();
    $exitCode = 0;
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) return null;

    $scalar = trim(implode("\n", $output));
    if (!preg_match('/^("(?:\\\\.|[^"\\\\])*")\s+<utf8_string>$/u', $scalar, $matches)) return null;
    $value = json_decode($matches[1], true);
    return is_string($value) && $value !== '' ? $value : null;
}

function pialert_geodb_service_location(string $database, string $ip, string $language): array {
    $empty = array('country' => null, 'country_code' => null, 'continent' => null);
    if (!is_file($database) || !is_readable($database) || filter_var($ip, FILTER_VALIDATE_IP) === false) return $empty;

    $locale = strtolower(substr($language, 0, 2));
    if (!preg_match('/^[a-z]{2}$/D', $locale)) $locale = 'en';
    $locales = array_values(array_unique(array($locale, 'en')));

    $country = null;
    $countryCode = null;
    foreach (array('country', 'registered_country') as $section) {
        $code = pialert_geodb_lookup_string($database, $ip, array($section, 'iso_code'));
        $countryCode = is_string($code) && preg_match('/^[A-Z]{2}$/D', $code) ? $code : null;
        foreach ($locales as $nameLocale) {
            $country = pialert_geodb_lookup_string($database, $ip, array($section, 'names', $nameLocale));
            if ($country !== null) break;
        }
        // Keep the displayed name and highlighted ISO code from the same record.
        if ($country === null) $country = $countryCode;
        if ($country !== null) break;
    }

    $continent = null;
    if ($country !== null) {
        foreach ($locales as $nameLocale) {
            $continent = pialert_geodb_lookup_string($database, $ip, array('continent', 'names', $nameLocale));
            if ($continent !== null) break;
        }
    }
    return array('country' => $country, 'country_code' => $countryCode, 'continent' => $continent);
}

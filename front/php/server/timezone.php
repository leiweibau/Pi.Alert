<?php
// Some restricted runtime environments cannot allocate executable memory for PCRE JIT.
// Disabling it prevents noisy warnings; regular expressions still work normally.
ini_set('pcre.jit', '0');
function ValidateTimezone($timezone) {
	return in_array($timezone, timezone_identifiers_list());
}

function GetConfigPath() {
	if (file_exists('../../../config/pialert.conf')) {
		$configfile = '../../../config/pialert.conf';
	} elseif (file_exists('../../config/pialert.conf')) {
	    $configfile = '../../config/pialert.conf';
	} elseif (file_exists('../config/pialert.conf')) {
	    $configfile = '../config/pialert.conf';
	} else {
		$configfile = "";
	}
	return $configfile;
}

function GetTimezoneFromConfig($configfile) {
	$fallback_tz = 'Europe/Berlin';
	if ($configfile === '') {
		return $fallback_tz;
	}
	$configContent = @file_get_contents($configfile);
	if (!is_string($configContent)) {
		return $fallback_tz;
	}
	$configContent = preg_replace('/^\s*#.*$/m', '', $configContent);
	$configArray = @parse_ini_string($configContent);
	$configuredTimezone = is_array($configArray) ? ($configArray['SYSTEM_TIMEZONE'] ?? null) : null;
	return is_string($configuredTimezone) && ValidateTimezone($configuredTimezone)
		? $configuredTimezone : $fallback_tz;
}

// SYSTEM_TIMEZONE is authoritative even when PHP already has a non-UTC default.
date_default_timezone_set(GetTimezoneFromConfig(GetConfigPath()));

?>

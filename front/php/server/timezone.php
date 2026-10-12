<?php
require_once __DIR__ . '/config_read.php';
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
	$configuredTimezone = pialert_config_value('SYSTEM_TIMEZONE', $configfile);
	return is_string($configuredTimezone) && ValidateTimezone($configuredTimezone)
		? $configuredTimezone : $fallback_tz;
}

// SYSTEM_TIMEZONE is authoritative even when PHP already has a non-UTC default.
date_default_timezone_set(GetTimezoneFromConfig(GetConfigPath()));

?>

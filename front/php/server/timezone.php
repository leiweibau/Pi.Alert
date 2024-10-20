<?php
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
	if ($configfile != "") {
		$configContent = file_get_contents($configfile);
		$configContent = preg_replace('/^\s*#.*$/m', '', $configContent);
		$configArray = parse_ini_string($configContent);
		if (ValidateTimezone($configArray['SYSTEM_TIMEZONE'])) {
			$systemtimezone = $configArray['SYSTEM_TIMEZONE'];
		} else {
			$systemtimezone = $fallback_tz;
		}	
	} else {
		$systemtimezone = $fallback_tz;
	}
	return $systemtimezone;
}
// Get current PHP TZ
$systemtimezone = date_default_timezone_get();
// If TZ is UTC (not set), get TZ Config from configfile or fallback to 
if ($systemtimezone == "UTC") {
	$configfile = GetConfigPath();
	$systemtimezone = GetTimezoneFromConfig($configfile);
}
// Set TZ
date_default_timezone_set($systemtimezone);

?>
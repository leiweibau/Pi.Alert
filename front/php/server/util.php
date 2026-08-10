<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  util.php - Front module. Server side. Common generic functions
//------------------------------------------------------------------------------
//  Puche 2021        pi.alert.application@gmail.com        GNU GPLv3
//  leiweibau 2025+   https://github.com/leiweibau          GNU GPLv3
//------------------------------------------------------------------------------
// error_reporting(E_ERROR | E_PARSE);
// ini_set('display_errors', '0');
// ini_set('log_errors', '1');

// Formatting data functions
function formatDate($date1) {
	return date_format(new DateTime($date1), 'Y-m-d   H:i');
}

function formatDateDiff($date1, $date2) {
	return date_diff(new DateTime($date1), new DateTime($date2))->format('%ad   %H:%I');
}

function formatDateISO($date1) {
	return date_format(new DateTime($date1), 'c');
}

function formatEventDate($date1, $eventType) {
	if (!empty($date1)) {
		$ret = formatDate($date1);
	} elseif ($eventType == '<missing event>') {
		$ret = '<missing event>';
	} else {
		$ret = '<Still Connected>';
	}

	return $ret;
}

function formatIPlong($IP) {
	return sprintf('%u', ip2long($IP));
}

// Others functions
function getDateFromPeriodValue() {
	$request = function_exists('pialert_request_data') ? pialert_request_data() : $_GET;
	$period = $request['period'] ?? '1 day';
	if (!is_scalar($period)) {
		$period = '1 day';
	}

	$period = trim((string) $period);
	if (!preg_match('/^([1-9][0-9]{0,3})\s+(minute|hour|day|week|month|year)s?$/i', $period)) {
		$period = '1 day';
	}

	return date('Y-m-d', strtotime('+1 day -' . $period));
}

function getDateFromPeriod() {
	return '"' . getDateFromPeriodValue() . '"';
}

function logServerConsole($text) {
	$x = array();
	$y = $x['__________' . $text . '__________'];
}

// Encode a value for an HTML text or quoted attribute context.
function h($value): string {
	return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


function h_with_line_breaks($value): string {
	$parts = preg_split('/(?:<br\s*\/?>|\r\n|\r|\n)/i', (string) $value);
	if ($parts === false) {
		return h($value);
	}

	return implode('<br>', array_map('h', $parts));
}

// Allow only HTTP(S) and same-site relative URLs in browser URL attributes.
function safe_web_url($value, string $fallback = ''): string {
	if (!is_scalar($value)) {
		return $fallback;
	}

	$url = trim((string) $value);
	if ($url === '' || preg_match('/[\\x00-\\x1F\\x7F]/', $url)) {
		return $fallback;
	}

	$scheme = parse_url($url, PHP_URL_SCHEME);
	if ($scheme !== null) {
		return in_array(strtolower($scheme), ['http', 'https'], true) ? $url : $fallback;
	}

	return str_starts_with($url, '//') ? $fallback : $url;
}

?>

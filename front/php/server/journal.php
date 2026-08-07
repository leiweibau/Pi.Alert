<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector
//
//  journal.php - Front module. Application logging
//------------------------------------------------------------------------------
//  leiweibau  2025+       https://github.com/leiweibau     GNU GPLv3
//------------------------------------------------------------------------------

// detect file changes
function calc_configfile_hash() {
	$Configfile = '../../../config/pialert.conf';
	return hash_file('md5', $Configfile);
}
function calc_configfile_hash_top() {
	$Configfile = '../config/pialert.conf';
	return hash_file('md5', $Configfile);
}
// Save Journal
function pialert_journal_insert($record) {
	global $db;

	$statement = $db->prepare('INSERT INTO pialert_journal (Journal_DateTime, LogClass, Trigger, LogString, Hash, Additional_Info)
		VALUES (:date_time, :class, :trigger, :message, :hash, :additional_info)');
	if ($statement === false) {
		return false;
	}
	foreach (array(':date_time' => 'date_time', ':class' => 'class', ':trigger' => 'trigger', ':message' => 'message', ':hash' => 'hash', ':additional_info' => 'additional_info') as $placeholder => $key) {
		if (!$statement->bindValue($placeholder, $record[$key], SQLITE3_TEXT)) {
			return false;
		}
	}
	// A locked SQLite database is an expected transient condition here. The
	// caller persists the record in the JSON buffer when this execution fails.
	return @$statement->execute() !== false;
}

function pialert_logging($LogClass, $Trigger, $LogString, $Hash, $Additional_Info) {
	if (file_exists('../../../config/pialert.conf')) {
		$journalFile = '../../../db/pialert_journal_buffer';
	} else {
		$journalFile = '../db/pialert_journal_buffer';
	}

	$filehash = '';
	if ($Hash == 1) {
		$filehash = calc_configfile_hash();
		if ($filehash == '') {
			$filehash = calc_configfile_hash_top();
		}
	}
	$record = array(
		'date_time' => date('Y-m-d H:i:s'),
		'class' => (string) $LogClass,
		'trigger' => (string) $Trigger,
		'message' => (string) $LogString,
		'hash' => (string) $filehash,
		'additional_info' => (string) $Additional_Info,
	);

	if (!pialert_journal_insert($record)) {
		file_put_contents($journalFile, json_encode($record) . PHP_EOL, FILE_APPEND | LOCK_EX);
		return;
	}

	if (!file_exists($journalFile) || filesize($journalFile) === 0) {
		return;
	}
	$remaining = array();
	foreach (file($journalFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
		$bufferedRecord = json_decode($line, true);
		if (!is_array($bufferedRecord)) {
			error_log('Pi.Alert: discarded legacy SQL journal buffer entry.');
			continue;
		}
		if (!pialert_journal_insert($bufferedRecord)) {
			$remaining[] = $line;
		}
	}
	if (empty($remaining)) {
		unlink($journalFile);
	} else {
		file_put_contents($journalFile, implode(PHP_EOL, $remaining) . PHP_EOL, LOCK_EX);
	}
}
?>
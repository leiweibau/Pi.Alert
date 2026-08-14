<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('pcre.jit', '0');

require_once __DIR__ . "/php/server/session.php";
pialert_start_session();

if ($_SESSION["login"] != 1) {
	header('Location: ./index.php');
	exit;
}

require 'php/server/db.php';
$DBFILE = '../db/pialert.db';
OpenDB();
require 'php/server/journal.php';
require_once __DIR__ . '/php/server/csrf.php';
require_once __DIR__ . '/php/server/util.php';
if (isset($_GET['remove_report']) || isset($_GET['archive_report'])) {
    header('Allow: POST');
    http_response_code(405);
    exit('Method Not Allowed');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    pialert_validate_csrf();
    archive_single_webgui_report();
    delete_single_webgui_report();
    delete_single_archive_report();
    $target = (($_POST['report_source'] ?? '') === 'archive')
        ? './reports.php?report_source=archive' : './reports.php';
    header('Location: ' . $target, true, 303);
    exit;
}
require 'php/templates/header.php';

function report_filename_from_request($value) {
	if (!is_string($value) || strlen($value) > 240) {
		return null;
	}
	if (preg_match('/\A[0-9]+-[0-9]+_[\p{L}\p{N} _()\-]+\z/uD', $value) !== 1) {
		return null;
	}
	return $value . '.txt';
}

function delete_single_webgui_report() {
	global $db;
	if (isset($_POST['remove_report'])) {
		$prep_remove_report = report_filename_from_request($_POST['remove_report']);
		if ($prep_remove_report !== null) {
			if (is_file('./reports/' . $prep_remove_report) && unlink('./reports/' . $prep_remove_report)) {
				// Logging
				pialert_logging('a_050', $_SERVER['REMOTE_ADDR'], 'LogStr_0503', '', $prep_remove_report);
			}
		}
	}
}

function delete_single_archive_report() {
	global $db;
	if (isset($_POST['remove_report'])) {
		$prep_remove_report = report_filename_from_request($_POST['remove_report']);
		if ($prep_remove_report !== null) {
			if (is_file('./reports/archived/' . $prep_remove_report) && unlink('./reports/archived/' . $prep_remove_report)) {
				// Logging
				pialert_logging('a_050', $_SERVER['REMOTE_ADDR'], 'LogStr_0505', '', $prep_remove_report);
			}
		}
	}
}

function archive_single_webgui_report() {
	global $db;
	if (isset($_POST['archive_report'])) {
		$prep_remove_report = report_filename_from_request($_POST['archive_report']);
		if ($prep_remove_report !== null) {
			if (is_file('./reports/' . $prep_remove_report)
				&& rename('./reports/' . $prep_remove_report, './reports/archived/' . $prep_remove_report)) {
				// Logging
				pialert_logging('a_050', $_SERVER['REMOTE_ADDR'], 'LogStr_0507', '', $prep_remove_report);
			}
		}
	}
}

function get_Report_Headline_Colors() {
	global $db;

  $result = $db->query("SELECT par_Long_Value FROM Parameters WHERE par_ID = 'report_headline_colors'");
  $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
  $defaults = array("#30bbbb", "#d81b60", "#00c0ef", "#831cff", "#00a65a", "#cc6600");
  $Headline_Colors = $row ? explode(',', (string) $row['par_Long_Value']) : $defaults;
  foreach ($defaults as $index => $fallback) {
      $candidate = $Headline_Colors[$index] ?? '';
      $Headline_Colors[$index] = is_string($candidate) && preg_match('/^#[0-9a-f]{6}$/i', $candidate)
          ? $candidate
          : $fallback;
  }
  return $Headline_Colors;
}

function ssl_code_tooltip($sslcode) {
	$sslinfo = array();
	if ($sslcode >= 8) {
		$sslinfo[] = "Subject";
		$sslcode = $sslcode-8;
	}
	if ($sslcode >= 4) {
		$sslinfo[] = "Issuer";
		$sslcode = $sslcode-4;
	}
	if ($sslcode >= 2) {
		$sslinfo[] = "Valid from";
		$sslcode = $sslcode-2;
	}
	if ($sslcode >= 1) {
		$sslinfo[] = "Valid to";
		$sslcode = $sslcode-1;
	} else {
		$sslinfo[] = "none";
	}
	return 'Values changed: '.implode(', ', $sslinfo);
}

function get_notification_class($filename) {
	$headtitle = explode("-", $filename);
	$headeventtype = explode("_", $filename);
	if (!isset($headtitle[1], $headeventtype[1])) {
		return null;
	}
	$temp_class[0] = substr($headeventtype[1], 0, -4);
	if ($temp_class[0] == "Events" || $temp_class[0] == "Devices Down" || $temp_class[0] == "New Devices") {
		$temp_class[1] = 'arp';
		$temp_class[2] = substr($headtitle[0], 6, 2) . '.' . substr($headtitle[0], 4, 2) . '.' . substr($headtitle[0], 2, 2) . '/' . substr($headtitle[1], 0, 2) . ':' . substr($headtitle[1], 2, 2);
		return $temp_class;
	}
	if ($temp_class[0] == "Internet") {
		$temp_class[1] = 'internet';
		$temp_class[2] = substr($headtitle[0], 6, 2) . '.' . substr($headtitle[0], 4, 2) . '.' . substr($headtitle[0], 2, 2) . '/' . substr($headtitle[1], 0, 2) . ':' . substr($headtitle[1], 2, 2);
		return $temp_class;
	}
	if ($temp_class[0] == "Services Events" || $temp_class[0] == "Services Down" || $temp_class[0] == "Services Up") {
		$temp_class[1] = 'webmon';
		$temp_class[2] = substr($headtitle[0], 6, 2) . '.' . substr($headtitle[0], 4, 2) . '.' . substr($headtitle[0], 2, 2) . '/' . substr($headtitle[1], 0, 2) . ':' . substr($headtitle[1], 2, 2);
		return $temp_class;
	}
	if ($temp_class[0] == "Host Down (ICMP Monitoring)" || $temp_class[0] == "Host Events (ICMP Monitoring)") {
		$temp_class[1] = 'icmpmon';
		$temp_class[2] = substr($headtitle[0], 6, 2) . '.' . substr($headtitle[0], 4, 2) . '.' . substr($headtitle[0], 2, 2) . '/' . substr($headtitle[1], 0, 2) . ':' . substr($headtitle[1], 2, 2);
		return $temp_class;
	}
	if ($temp_class[0] == "Test") {
		$temp_class[1] = 'test';
		$temp_class[2] = substr($headtitle[0], 6, 2) . '.' . substr($headtitle[0], 4, 2) . '.' . substr($headtitle[0], 2, 2) . '/' . substr($headtitle[1], 0, 2) . ':' . substr($headtitle[1], 2, 2);
		return $temp_class;
	}
	if ($temp_class[0] == "Nmap") {
		$temp_class[1] = 'nmap';
		$temp_class[2] = substr($headtitle[0], 6, 2) . '.' . substr($headtitle[0], 4, 2) . '.' . substr($headtitle[0], 2, 2) . '/' . substr($headtitle[1], 0, 2) . ':' . substr($headtitle[1], 2, 2);
		return $temp_class;
	}
	if ($temp_class[0] == "Rogue DHCP Server") {
		$temp_class[1] = 'rogueDHCP';
		$temp_class[2] = substr($headtitle[0], 6, 2) . '.' . substr($headtitle[0], 4, 2) . '.' . substr($headtitle[0], 2, 2) . '/' . substr($headtitle[1], 0, 2) . ':' . substr($headtitle[1], 2, 2);
		return $temp_class;
	}
}

function process_standard_notifications($class_name, $event_time, $filename, $directory, $color, $notification_icon) {
	$lines = file($directory . $filename);
	if ($lines === false) {
		return '';
	}
	$webgui_report = '';
	$x = 0;
	foreach ($lines as $line) {
		$x++;
		if ($x < (sizeof($lines) - 1)) {
			if (stristr($line, "MAC:")) {
				// edit MAC line - add link
				$tempmac = explode(": ", $line);
				$mac_full = $tempmac[1];
				$mac_text = $mac_full;
				if (strpos(trim($mac_full), 'Internet') === 0) {
				    if (strlen($mac_full) > 22) {
				        $mac_text = substr($mac_full, 0, 22) . "...\n";
				    } else {
				        $mac_text = "Internet\n";
				    }
				}
				$webgui_report .= "\tMAC: <a href=\"./deviceDetails.php?mac=" . rawurlencode(trim($mac_full)) . "\">" . h($mac_text) . "</a>";
			} elseif (stristr($line, "Service:")) {
				// edit Service line - add link
				$tempmac = explode(": ", $line);
				$serviceUrl = trim((string) ($tempmac[1] ?? ''));
				$webgui_report .= "Service: <a href=\"./serviceDetails.php?url=" . rawurlencode($serviceUrl) . "\">" . h($serviceUrl) . "</a>\n";
			} elseif (stristr($line, "Event:")) {
				// edit Event line - add color depending on status
				$tempmac = explode(": ", $line);
				$tempmac[1] = trim($tempmac[1]);
				if ($tempmac[1] == "Disconnected") {
					$webgui_report .= "\tEvent:\t\t<span class=\"text-red\">" . h($tempmac[1]) . "</span>\n";
				} elseif ($tempmac[1] == "Connected") {
					$webgui_report .= "\tEvent:\t\t<span class=\"text-green\">" . h($tempmac[1]) . "</span>\n";
				} else { $webgui_report .= "\tEvent:\t\t" . h($tempmac[1]) . "\n";}
			} elseif (stristr($line, "\tHTTP Status Code:")) {
				// edit Event line - add color depending on status
				$tempmac = explode(": ", $line);
				$tempmac[1] = trim($tempmac[1]);
				if ($tempmac[1] != "200") {$code_color = 'red';} else {$code_color = 'green';}
				$webgui_report .= "\tHTTP Status Code:\t<span class=\"text-".$code_color."\">" . h($tempmac[1]) . "</span>\n";
			} elseif (stristr($line, "\tSSL Status:")) {
				// edit Event line - add color depending on status
				$tempmac = explode(": ", $line);
				$tempmac[1] = trim($tempmac[1]);
				if ($tempmac[1] != "0") {$code_color = 'red';} else {$code_color = 'green';}
				$webgui_report .= "\t<span style=\"cursor:pointer\" data-toggle=\"tooltip\" data-placement=\"top\" title=\"". h(ssl_code_tooltip($tempmac[1])) . "\">SSL Status:\t\t<span class=\"text-".$code_color."\">" . h($tempmac[1]) . "</span></span>\n";
			} else {
				// Default handling
				$webgui_report .= h($line);
			}
			//$webgui_report .= $line;
		} elseif (trim($line) != "") {
			$webgui_report .= h($line);
		}
	}

	return '<div class="box box-solid">
	          <div class="box-header">
	            <h3 class="box-title" style="color: ' . h($color) . '"><i class="fa ' . $notification_icon . '"></i>&nbsp;&nbsp;' . h($event_time) . ' - ' . h($class_name) . '</h3>
	        </div>
	        <div class="box-body" style="height:250px;"><pre style="background-color: transparent; border: none; overflow: auto; height:240px">' . $webgui_report . '</pre></div>
            <div class="box-footer text-center">
                '. report_footer_buttons($directory, $filename) .'
            </div>
	        </div>';
}

function report_footer_buttons($source, $filename) {
    $archived = $source === './reports/archived/';
    $reportSource = $archived ? 'archive' : '';
    $report = substr($filename, 0, -4);
    $csrf = h(pialert_csrf_token());
    $sourceField = $reportSource === '' ? '' : '<input type="hidden" name="report_source" value="archive">';
    $archiveDisabled = $archived ? ' disabled' : '';

    return '<a href="./download/report.php?report=' . rawurlencode($report)
        . ($archived ? '&amp;report_source=archive' : '')
        . '" class="btn btn-sm btn-success" target="_blank" role="button" style="width:70px;margin:0 5px;"><i class="fa fa-fw fa-download"></i></a>'
        . '<form method="post" action="./reports.php" style="display:inline;">'
        . '<input type="hidden" name="_csrf" value="' . $csrf . '">' . $sourceField
        . '<input type="hidden" name="remove_report" value="' . h($report) . '">'
        . '<button type="submit" class="btn btn-sm btn-danger" style="width:70px;margin:0 60px;"><i class="fa fa-fw fa-trash"></i></button></form>'
        . '<form method="post" action="./reports.php" style="display:inline;">'
        . '<input type="hidden" name="_csrf" value="' . $csrf . '">'
        . '<input type="hidden" name="archive_report" value="' . h($report) . '">'
        . '<button type="submit" class="btn btn-sm btn-default" style="width:70px;margin:0 5px;"' . $archiveDisabled . '><i class="fa-regular fa-folder"></i></button></form>';
}

function process_icmp_notifications($class_name, $event_time, $filename, $directory, $color) {
	$lines = file($directory . $filename);
	if ($lines === false) {
		return '';
	}
	$webgui_report = '';
	foreach ($lines as $line) {
		if (stristr($line, 'IP:')) {
			$parts = explode(': ', $line, 2);
			$hostIp = trim((string) ($parts[1] ?? ''));
			$webgui_report .= 'IP: <a href="./icmpmonitorDetails.php?hostip=' . rawurlencode($hostIp) . '">' . h($hostIp) . "</a>\n";
		} elseif (stristr($line, 'Status:')) {
			$parts = explode(':', $line, 2);
			$status = trim((string) ($parts[1] ?? ''));
			if ($status === 'Down') {
				$webgui_report .= "\tStatus:\t\t<span class=\"text-red\">Disconnected</span>\n";
			} elseif ($status === 'Up') {
				$webgui_report .= "\tStatus:\t\t<span class=\"text-green\">Connected</span>\n";
			} else {
				$webgui_report .= "\tStatus:\t\t" . h($status) . "\n";
			}
		} else {
			$webgui_report .= h($line);
		}
	}

	return '<div class="box box-solid">
	          <div class="box-header">
	            <h3 class="box-title" style="color: ' . h($color) . '"><i class="fa fa-laptop"></i>&nbsp;&nbsp;' . h($event_time) . ' - ' . h($class_name) . '</h3>
	          </div>
	          <div class="box-body" style="height:250px;"><pre style="background-color:transparent;border:none;overflow:auto;height:240px">' . $webgui_report . '</pre></div>
	          <div class="box-footer text-center">' . report_footer_buttons($directory, $filename) . '</div>
	        </div>';
}

function process_test_notifications($class_name, $event_time, $filename, $directory, $color) {
	$content = file_get_contents($directory . $filename);
	$webgui_report = h(str_replace("\n\n\n", '', $content === false ? '' : $content));
	return '<div class="box box-solid">
	          <div class="box-header">
	            <h3 class="box-title" style="color: ' . h($color) . '"><i class="fa fa-regular fa-envelope"></i>&nbsp;&nbsp;' . h($event_time) . ' - System Message</h3>
	          </div>
	          <div class="box-body" style="height:250px;"><pre style="background-color:transparent;border:none;overflow:auto;height:240px">' . $webgui_report . '</pre></div>
	          <div class="box-footer text-center">' . report_footer_buttons($directory, $filename) . '</div>
	        </div>';
}

function process_rogueDHCP_notifications($class_name, $event_time, $filename, $directory) {
	global $pia_lang;
	$content = file_get_contents($directory . $filename);
	$webgui_report = h(str_replace("\n\n\n", '', $content === false ? '' : $content));
	return '<div class="box box-solid bg-red-active">
	          <div class="box-header">
	            <h3 class="box-title"><i class="fa fa-warning"></i>&nbsp;&nbsp;' . h($event_time) . ' - ' . h($class_name) . '</h3>
	          </div>
	          <div class="box-body"><pre style="background-color:transparent;border:none;">' . $webgui_report . '</pre>
	            <p style="font-size:16px;text-align:center;">' . h($pia_lang['REP_Rogue_hint'] ?? '') . '</p>
	          </div>
	          <div class="box-footer text-center">' . report_footer_buttons($directory, $filename) . '</div>
	        </div>';
}

function reports_archive_counter() {
	$entries = is_dir('./reports/archived/') ? scandir('./reports/archived/') : false;
	return $entries === false ? 0 : count(array_diff($entries, array('..', '.')));
}

function generate_report_button($source) {
	global $pia_lang;
	if ($source === 'archive') {
		echo '<div class="box"><div class="box-body report_archive_btn" id="ShowArchivedReports">'
			. '<a href="./reports.php" class="btn btn-default">' . h($pia_lang['REP_show_cur']) . '</a></div></div>';
	} else {
		echo '<div class="box"><div class="box-body report_archive_btn" id="ShowArchivedReports">'
			. '<a href="./reports.php?report_source=archive" class="btn btn-default"><i class="fa-regular fa-folder"></i>&nbsp;&nbsp;'
			. h($pia_lang['REP_show_archive']) . ' (' . reports_archive_counter() . ')</a>';
		$archiveDays = (int) get_config_parmeter('REPORT_TO_ARCHIVE');
		if ($archiveDays > 0) {
			echo '<p style="text-align:center;margin-top:10px;margin-bottom:5px;color:#888;">'
				. h($pia_lang['Auto_Archive_note_a']) . $archiveDays . h($pia_lang['Auto_Archive_note_b']) . '</p>';
		}
		echo '</div></div>';
	}

	$deleteHandler = $source === 'archive' ? 'askdeleteAllNotificationsArchive()' : 'askdeleteAllNotifications()';
	$deleteSuffix = $source === 'archive' ? ' (Archive)' : '';
	echo '<div class="box"><div class="box-body report_delete_btn" id="RemoveAllNotifications">'
		. '<button type="button" class="btn btn-danger" onclick="' . $deleteHandler . '">'
		. h($pia_lang['REP_delete_all']) . $deleteSuffix . '</button></div></div>';
}

$headline_colors = get_Report_Headline_Colors();
$reportSource = (($_GET['report_source'] ?? '') === 'archive') ? 'archive' : '';
$directory = $reportSource === 'archive' ? './reports/archived/' : './reports/';
$ext_headline = $reportSource === 'archive'
	? ' - <span class="text-danger">' . h($pia_lang['Device_Shortcut_Archived']) . '</span>'
	: '';

$entries = is_dir($directory) ? scandir($directory) : false;
$scanned_directory = $entries === false ? array() : array_diff($entries, array('..', '.', 'archived'));
rsort($scanned_directory);

$standard_notification = array();
$special_notification = array();
foreach ($scanned_directory as $file) {
	if (substr(strtolower($file), -4) !== '.txt') {
		continue;
	}
	$notification_class = get_notification_class($file);
	if (!is_array($notification_class) || count($notification_class) < 3) {
		continue;
	}
	switch ($notification_class[1]) {
		case 'arp':
			$report = process_standard_notifications($notification_class[0], $notification_class[2], $file, $directory, $headline_colors[1], 'fa-laptop');
			break;
		case 'internet':
			$report = process_standard_notifications($notification_class[0], $notification_class[2], $file, $directory, $headline_colors[0], 'fa-globe');
			break;
		case 'webmon':
			$report = process_standard_notifications($notification_class[0], $notification_class[2], $file, $directory, $headline_colors[2], 'fa-server');
			break;
		case 'icmpmon':
			$report = process_icmp_notifications($notification_class[0], $notification_class[2], $file, $directory, $headline_colors[3]);
			break;
		case 'test':
			$report = process_test_notifications($notification_class[0], $notification_class[2], $file, $directory, $headline_colors[4]);
			break;
		case 'nmap':
			$report = process_standard_notifications($notification_class[0], $notification_class[2], $file, $directory, $headline_colors[5], 'fa-search');
			break;
		case 'rogueDHCP':
			$special_notification[] = process_rogueDHCP_notifications($notification_class[0], $notification_class[2], $file, $directory);
			continue 2;
		default:
			continue 2;
	}
	if ($report !== '') {
		$standard_notification[] = $report;
	}
}
?>

<div class="content-wrapper">
  <section class="content-header">
    <?php require 'php/templates/notification.php';?>
    <h1 id="pageTitle" style="display:inline-block;"><?=$pia_lang['REP_Title'] . $ext_headline;?></h1>
    <a href="#" class="btn btn-xs btn-link" role="button" data-toggle="modal" data-target="#modal-set-report-colors" style="display:inline-block;margin-top:-5px;margin-left:15px;"><i class="fa-solid fa-paintbrush text-green" style="font-size:1.5rem"></i></a>
  </section>

  <section class="content">
    <div class="modal fade" id="modal-set-report-colors">
      <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
          <h4 class="modal-title"><?=$pia_journ_lang['Journal_CustomColor_Head'];?></h4>
        </div>
        <div class="modal-body">
          <link rel="stylesheet" href="lib/Coloris/dist/coloris.min.css"/>
          <script src="lib/Coloris/dist/coloris.min.js"></script>
          <h4>Report Type</h4>
          <div id="Container">
<?php
$reportColorLabels = array('Internet', 'Devices', 'WebServices', 'ICMP Monitoring', 'Test / System', 'Nmap');
foreach ($reportColorLabels as $index => $label) {
	echo '<div style="margin-bottom:5px"><label style="width:140px">' . h($label) . '</label>'
		. '<input type="text" name="HeadLineColors[]" class="report_custom_colors_input" placeholder="Headline Color" value="'
		. h($headline_colors[$index]) . '" data-coloris></div>';
}
?>
          </div>
          <button type="button" class="btn btn-danger" style="margin-top:5px;margin-right:10px;" onclick="SetReportColors()"><?=$pia_lang['Gen_Save']?></button>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal" onclick="ReportReload()"><?=$pia_lang['Gen_Close']?></button></div>
      </div></div>
    </div>

<?php
generate_report_button($reportSource);
foreach ($special_notification as $notification) {
	echo $notification;
}

for ($x = 0; $x < count($standard_notification); $x += 2) {
	$rightColumn = $standard_notification[$x + 1] ?? '';
	echo '<div class="row">
	        <div class="col-lg-6 col-xs-12">' . $standard_notification[$x] . '</div>
	        <div class="col-lg-6 col-xs-12">' . $rightColumn . '</div>
	      </div>';
}
?>

    <div style="width: 100%; height: 20px;"></div>
    </section>
  </div>

<!-- ----------------------------------------------------------------------- -->
<?php
require 'php/templates/footer.php';
?>

<script>
// --------------------------------------------------------------------------
$(document).ready(function () {
    Coloris({
    	theme: 'pill',
        themeMode: 'dark',
        alpha: false,
        closeButton: true,
        closeLabel: '<?=$pia_lang['Gen_Okay']?>',
        clearButton: true,
        clearLabel: 'Clear',
    });
});
// --------------------------------------------------------------------------
function SetReportColors() {
    let HeadLineColors = $('input[name="HeadLineColors[]"]').map(function () { return $(this).val(); }).get();

    pialertPost('php/server/parameters.php', {
        action: 'setReportParameter',
        HeadLineColors: HeadLineColors
    }, function(msg) {
    showMessage (msg);
  });
}
// --------------------------------------------------------------------------
function askdeleteAllNotifications() {
  showModalWarning('<?=$pia_lang['REP_delete_all_noti'];?>', '<?=$pia_lang['REP_delete_all_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Delete'];?>', 'deleteAllNotifications');
}
function deleteAllNotifications()
{
  pialertPost('php/server/files.php?action=deleteAllNotifications', function(msg) {
    showMessage (msg);
  });
}
// --------------------------------------------------------------------------
function askdeleteAllNotificationsArchive() {
  showModalWarning('<?=$pia_lang['REP_delete_all_noti'];?>', '<?=$pia_lang['REP_delete_all_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Delete'];?>', 'deleteAllNotificationsArchive');
}
function deleteAllNotificationsArchive()
{
  pialertPost('php/server/files.php?action=deleteAllNotificationsArchive', function(msg) {
    showMessage (msg);
  });
}
// --------------------------------------------------------------------------
function ReportReload() {
    setTimeout(function() {
        location.reload();
    }, 1000)
};
</script>

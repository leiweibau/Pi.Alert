<?php
session_start();
error_reporting(0);
if ($_SESSION["login"] != 1) {
	header('Location: ./index.php');
	exit;
}

require 'php/server/db.php';
$DBFILE = '../db/pialert.db';
OpenDB();
require 'php/server/journal.php';
require 'php/templates/header.php';

function delete_single_webgui_report() {
	global $db;
	if (isset($_REQUEST['remove_report'])) {
		$prep_remove_report = str_replace(array('\'', '"', ',', ';', '<', '>', '.', '/', '&'), "", $_REQUEST['remove_report']) . '.txt';
		if (useRegex($prep_remove_report) == TRUE) {
			if (file_exists('./reports/' . $prep_remove_report)) {
				unlink('./reports/' . $prep_remove_report);
				// Logging
				pialert_logging('a_050', $_SERVER['REMOTE_ADDR'], 'LogStr_0503', '', $prep_remove_report);
			}
		}
	}
}

function delete_single_archive_report() {
	global $db;
	if (isset($_REQUEST['remove_report'])) {
		$prep_remove_report = str_replace(array('\'', '"', ',', ';', '<', '>', '.', '/', '&'), "", $_REQUEST['remove_report']) . '.txt';
		if (useRegex($prep_remove_report) == TRUE) {
			if (file_exists('./reports/archived/' . $prep_remove_report)) {
				unlink('./reports/archived/' . $prep_remove_report);
				// Logging
				pialert_logging('a_050', $_SERVER['REMOTE_ADDR'], 'LogStr_0505', '', $prep_remove_report);
			}
		}
	}
}

function archive_single_webgui_report() {
	global $db;
	if (isset($_REQUEST['archive_report'])) {
		$prep_remove_report = str_replace(array('\'', '"', ',', ';', '<', '>', '.', '/', '&'), "", $_REQUEST['archive_report']) . '.txt';
		if (useRegex($prep_remove_report) == TRUE) {
			if (file_exists('./reports/' . $prep_remove_report)) {
				rename('./reports/' . $prep_remove_report, './reports/archived/' . $prep_remove_report);
				// Logging
				pialert_logging('a_050', $_SERVER['REMOTE_ADDR'], 'LogStr_0507', '', $prep_remove_report);
			}
		}
	}
}

function get_Report_Headline_Colors() {
	global $db;

  $result = $db->query("SELECT par_Long_Value FROM Parameters WHERE par_ID = 'report_headline_colors'");
  $row = $result->fetchArray(SQLITE3_ASSOC);
  if ($row) {
      $responseData = $row['par_Long_Value'];
      $Headline_Colors = explode(',', $responseData);
  } else {
  	$Headline_Colors = array("#30bbbb","#d81b60","#00c0ef","#831cff","#00a65a");
  }
  return $Headline_Colors;
}

function ssl_code_tooltip($sslcode) {
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
	if ($temp_class[0] == "Rogue DHCP Server") {
		$temp_class[1] = 'rogueDHCP';
		$temp_class[2] = substr($headtitle[0], 6, 2) . '.' . substr($headtitle[0], 4, 2) . '.' . substr($headtitle[0], 2, 2) . '/' . substr($headtitle[1], 0, 2) . ':' . substr($headtitle[1], 2, 2);
		return $temp_class;
	}
}

function process_standard_notifications($class_name, $event_time, $filename, $directory, $color, $notification_icon) {
	$lines = file($directory . $filename);
	$x = 0;
	foreach ($lines as $line) {
		$x++;
		if ($x < (sizeof($lines) - 1)) {
			if (stristr($line, "MAC:")) {
				// edit MAC line - add link
				$tempmac = explode(": ", $line);
				$webgui_report .= "\tMAC: <a href=\"./deviceDetails.php?mac=" . $tempmac[1] . "\">" . $tempmac[1] . "</a>";
			} elseif (stristr($line, "Service:")) {
				// edit Service line - add link
				$tempmac = explode(": ", $line);
				$webgui_report .= "Service: <a href=\"./serviceDetails.php?url=" . $tempmac[1] . "\">" . $tempmac[1] . "</a>";
			} elseif (stristr($line, "Event:")) {
				// edit Event line - add color depending on status
				$tempmac = explode(": ", $line);
				$tempmac[1] = trim($tempmac[1]);
				if ($tempmac[1] == "Disconnected") {
					$webgui_report .= "\tEvent:\t\t<span class=\"text-red\">" . $tempmac[1] . "</span>\n";
				} elseif ($tempmac[1] == "Connected") {
					$webgui_report .= "\tEvent:\t\t<span class=\"text-green\">" . $tempmac[1] . "</span>\n";
				} else { $webgui_report .= "\tEvent:\t\t" . $tempmac[1] . "</span>\n";}
			} elseif (stristr($line, "\tHTTP Status Code:")) {
				// edit Event line - add color depending on status
				$tempmac = explode(": ", $line);
				$tempmac[1] = trim($tempmac[1]);
				if ($tempmac[1] != "200") {$code_color = 'red';} else {$code_color = 'green';}
				$webgui_report .= "\tHTTP Status Code:\t<span class=\"text-".$code_color."\">" . $tempmac[1] . "</span>\n";
			} elseif (stristr($line, "\tSSL Status:")) {
				// edit Event line - add color depending on status
				$tempmac = explode(": ", $line);
				$tempmac[1] = trim($tempmac[1]);
				if ($tempmac[1] != "0") {$code_color = 'red';} else {$code_color = 'green';}
				$webgui_report .= "\t<span style=\"cursor:pointer\" data-toggle=\"tooltip\" data-placement=\"top\" title=\"". ssl_code_tooltip($tempmac[1]) . "\">SSL Status:\t\t<span class=\"text-".$code_color."\">" . $tempmac[1] . "</span></span>\n";
			} else {
				// Default handling
				$webgui_report .= $line;
			}
			//$webgui_report .= $line;
		} elseif (trim($line) != "") {
			$webgui_report .= $line;
		}
	}

	return '<div class="box box-solid">
	          <div class="box-header">
	            <h3 class="box-title" style="color: ' . $color . '"><i class="fa ' . $notification_icon . '"></i>&nbsp;&nbsp;' . $event_time . ' - ' . $class_name . '</h3>
	        </div>
	        <div class="box-body" style="height:250px;"><pre style="background-color: transparent; border: none; overflow: auto; height:240px">' . $webgui_report . '</pre></div>
            <div class="box-footer text-center">
                '. report_footer_buttons($directory, $filename) .'
            </div>
	        </div>';
}

function report_footer_buttons($source, $filename) {
	if ($source == './reports/archived/') {$disable = 'disabled'; $report_source = '&report_source=archive';}
	$bt_a_c = 'style="width: 70px; margin: 0px 5px;"';
	$bt_b = 'style="width: 70px; margin: 0px 60px;"';
	return '<a href="./download/report.php?report=' . substr($filename, 0, -4) . $report_source . '" class="btn btn-sm btn-success" target="_blank" role="button" '.$bt_a_c.'><i class="fa fa-fw fa-download"></i></a>
          <a href="./reports.php?remove_report=' . substr($filename, 0, -4) . $report_source . '" class="btn btn-sm btn-danger" role="button" '.$bt_b.'><i class="fa fa-fw fa-trash"></i></a>
		  <a href="./reports.php?archive_report=' . substr($filename, 0, -4) . $report_source . '" class="btn btn-sm btn-default '.$disable.'" role="button" '.$bt_a_c.'><i class="fa-regular fa-folder"></i></a>';
}

function process_icmp_notifications($class_name, $event_time, $filename, $directory, $color) {
	$lines = file($directory . $filename);
	$x = 0;
	foreach ($lines as $line) {
		$x++;
		if ($x < (sizeof($lines))) {
			if (stristr($line, "IP:")) {
				// edit MAC line - add link
				$tempmac = explode(": ", $line);
				$webgui_report .= "IP: <a href=\"./icmpmonitorDetails.php?hostip=" . $tempmac[1] . "\">" . $tempmac[1] . "</a>";
			} elseif (stristr($line, "Status:")) {
				// edit Status line - add color depending on status
				$tempmac = explode(":", $line);
				$tempmac[1] = trim($tempmac[1]);
				if ($tempmac[1] == "Down") {
					$webgui_report .= "\tStatus:\t\t<span class=\"text-red\">Disconnected</span>\n";
				} elseif ($tempmac[1] == "Up") {
					$webgui_report .= "\tStatus:\t\t<span class=\"text-green\">Connected</span>\n";
				} else { $webgui_report .= "\tStatus:\t\t" . $tempmac[1] . "</span>\n";}
			} else {
				// Default handling
				$webgui_report .= $line;
			}
		} elseif (trim($line) != "") {
			$webgui_report .= $line;
		}
	}

	return '<div class="box box-solid">
	          <div class="box-header">
	            <h3 class="box-title" style="color: ' . $color . '"><i class="fa fa-laptop"></i>&nbsp;&nbsp;' . $event_time . ' - ' . $class_name . '</h3>
	          </div>
	        <div class="box-body" style="height:250px;"><pre style="background-color: transparent; border: none; overflow: auto; height:240px">' . $webgui_report . '</pre></div>
            <div class="box-footer text-center">
                '. report_footer_buttons($directory, $filename) .'
            </div>
	        </div>';
}

function process_test_notifications($class_name, $event_time, $filename, $directory, $color) {
	$webgui_report = file_get_contents($directory . $filename);
	$webgui_report = str_replace("\n\n\n", "", $webgui_report);
	return '<div class="box box-solid">
            <div class="box-header">
              <h3 class="box-title" style="color: ' . $color . '"><i class="fa fa-regular fa-envelope"></i>&nbsp;&nbsp;' . $event_time . ' - System Message</h3>
            </div>
            <div class="box-body" style="height:250px;"><pre style="background-color: transparent; border: none; overflow: auto; height:240px">' . $webgui_report . '</pre></div>
            <div class="box-footer text-center">
                '. report_footer_buttons($directory, $filename) .'
            </div>
            </div>';
}

function process_rogueDHCP_notifications($class_name, $event_time, $filename, $directory) {
	global $pia_lang;
	$webgui_report = file_get_contents($directory . $filename);
	$webgui_report = str_replace("\n\n\n", "", $webgui_report);
	return '<div class="box box-solid bg-red-active">
            <div class="box-header">
              <h3 class="box-title"><i class="fa fa-warning"></i>&nbsp;&nbsp;' . $event_time . ' - ' . $class_name . '</h3>
                <div class="pull-right">
                  <a href="./download/report.php?report=' . substr($filename, 0, -4) . '" class="btn btn-sm btn-success" target="_blank"><i class="fa fa-fw fa-download"></i></a>
                  <a href="./reports.php?remove_report=' . substr($filename, 0, -4) . '" class="btn btn-sm btn-danger" style=" border: solid 1px #ddd;"><i class="fa fa-fw fa-trash"></i></a>
                </div>
            </div>
            <div class="box-body"><pre style="background-color: transparent; border: none;">' . $webgui_report . '</pre>
            <p style="font-size: 16px; text-align: center;">' . $pia_lang['REP_Rogue_hint'] . '</p>
            </div>
            </div>';
}

function reports_archive_couter() {
	$directory = './reports/archived/';
	$scanned_directory = array_diff(scandir($directory), array('..', '.'));
	return sizeof($scanned_directory);
}

function generate_report_button($source) {
	global $pia_lang;

	if ($source == "archive") {
		$archive_btn = '<div class="box">
                          <div class="box-body report_archive_btn" id="ShowArchivedReports">
                             <a href="./reports.php" class="btn btn-default">'.$pia_lang['REP_show_cur'].'</a>
                          </div>
                        </div>';
	} else {
		$archive_btn = '<div class="box">
                          <div class="box-body report_archive_btn" id="ShowArchivedReports">
                             <a href="./reports.php?report_source=archive" class="btn btn-default"><i class="fa-regular fa-folder"></i>&nbsp;&nbsp;'.$pia_lang['REP_show_archive'].' ('. reports_archive_couter() . ')</a>';
		if (get_config_parmeter('REPORT_TO_ARCHIVE') > 0) {
			$archive_btn .= '<p style="text-align: center; margin-top: 10px; margin-bottom: 5px; color: #888;">'.$pia_lang['Auto_Archive_note_a'].get_config_parmeter('REPORT_TO_ARCHIVE').$pia_lang['Auto_Archive_note_b'].'</p>';
		}
        $archive_btn .= '</div>
                        </div>';

	}
	echo $archive_btn;

	if ($source == "archive") {
		$delete_btn = '<div class="box">
				          <div class="box-body report_delete_btn" id="RemoveAllNotifications">
				              <button type="button" id="rqwejwedewjpjo" class="btn btn-danger" onclick="askdeleteAllNotificationsArchive()">'.$pia_lang['REP_delete_all'].' (Archive)</button>
				          </div>
				       </div>';
	} else {
		$delete_btn = '<div class="box">
				          <div class="box-body report_delete_btn" id="RemoveAllNotifications">
				              <button type="button" id="rqwejwedewjpjo" class="btn btn-danger" onclick="askdeleteAllNotifications()">'.$pia_lang['REP_delete_all'].'</button>
				          </div>
				       </div>';
	}

	echo $delete_btn;
}

// Archive Reports
archive_single_webgui_report();
// Delete Reports
delete_single_webgui_report();
// Delete Archived Reports
delete_single_archive_report();

$headline_colors = get_Report_Headline_Colors();

if ($_REQUEST['report_source'] == "" || $_REQUEST['report_source'] != "archive") {
	$directory = './reports/';
	$ext_headline = '';
} else {
	$directory = './reports/archived/';
	$ext_headline = ' - <span class="text-danger">'.$pia_lang['Device_Shortcut_Archived'].'</span>';
}

$scanned_directory = array_diff(scandir($directory), array('..', '.', 'archived'));
rsort($scanned_directory);	


$standard_notification = array();
$special_notification = array();
foreach ($scanned_directory as $file) {
	if (substr(strtolower($file), -4) == '.txt') {
		$notification_class = get_notification_class($file);
		if ($notification_class[1] == "arp") {
			array_push($standard_notification, process_standard_notifications($notification_class[0], $notification_class[2], $file, $directory, $headline_colors[1], 'fa-laptop'));
		} elseif ($notification_class[1] == "internet") {
			array_push($standard_notification, process_standard_notifications($notification_class[0], $notification_class[2], $file, $directory, $headline_colors[0], 'fa-globe'));
		} elseif ($notification_class[1] == "webmon") {
			array_push($standard_notification, process_standard_notifications($notification_class[0], $notification_class[2], $file, $directory, $headline_colors[2], 'fa-server'));
		} elseif ($notification_class[1] == "icmpmon") {
			array_push($standard_notification, process_icmp_notifications($notification_class[0], $notification_class[2], $file, $directory, $headline_colors[3]));
		} elseif ($notification_class[1] == "test") {
			array_push($standard_notification, process_test_notifications($notification_class[0], $notification_class[2], $file, $directory, $headline_colors[4]));
		} elseif ($notification_class[1] == "rogueDHCP") {
			array_push($special_notification, process_rogueDHCP_notifications($notification_class[0], $notification_class[2], $file, $directory));
		}
	}
}
?>

<!-- Page ------------------------------------------------------------------ -->
<div class="content-wrapper">

<!-- Content header--------------------------------------------------------- -->
    <section class="content-header">
    <?php require 'php/templates/notification.php';?>
      <h1 id="pageTitle" style="display: inline-block;">
         <?=$pia_lang['REP_Title'].$ext_headline;?>
      </h1> <a href="#" class="btn btn-xs btn-link" role="button" data-toggle="modal" data-target="#modal-set-report-colors" style="display: inline-block; margin-top: -5px; margin-left: 15px;"><i class="fa-solid fa-paintbrush text-green" style="font-size:1.5rem"></i></a>
    </section>

<!-- Main content ---------------------------------------------------------- -->
    <section class="content">
        <div class="modal fade" id="modal-set-report-colors">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                        <h4 class="modal-title"><?=$pia_journ_lang['Journal_CustomColor_Head'];?></h4>
                    </div>
                    <div class="modal-body">
                        <link rel="stylesheet" href="lib/Coloris/dist/coloris.min.css"/>
                        <script src="lib/Coloris/dist/coloris.min.js"></script>

                        <h4>Report Type</h4>
                        <div id="Container">
							<div id="Internet" style="margin-bottom: 5px">
				                <label style="width: 140px">Internet</label>
				                <input type="text" name="HeadLineColors[]" class="report_custom_colors_input" placeholder="Headline Color" value="<?=$headline_colors[0]?>" data-coloris>
				            </div>
							<div id="Device" style="margin-bottom: 5px">
				                <label style="width: 140px">Devices</label>
				                <input type="text" name="HeadLineColors[]" class="report_custom_colors_input" placeholder="Headline Color" value="<?=$headline_colors[1]?>" data-coloris>
				            </div>
							<div id="WebServices" style="margin-bottom: 5px">
				                <label style="width: 140px">WebServices</label>
				                <input type="text" name="HeadLineColors[]" class="report_custom_colors_input" placeholder="Headline Color" value="<?=$headline_colors[2]?>" data-coloris>
				            </div>
							<div id="ICMP_Monitoring" style="margin-bottom: 5px">
				                <label style="width: 140px">ICMP Monitoring</label>
				                <input type="text" name="HeadLineColors[]" class="report_custom_colors_input" placeholder="Headline Color" value="<?=$headline_colors[3]?>" data-coloris>
				            </div>
							<div id="Test" style="margin-bottom: 5px">
				                <label style="width: 140px">Test / System</label>
				                <input type="text" name="HeadLineColors[]" class="report_custom_colors_input" placeholder="Headline Color" value="<?=$headline_colors[4]?>" data-coloris>
				            </div>
                        </div>

                        <input type="submit" class="btn btn-danger" value="<?=$pia_lang['Gen_Save']?>" style="margin-top:5px; margin-right:10px;" onclick="SetReportColors()">

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal" onclick="ReportReload()"><?=$pia_lang['Gen_Close']?></button>
                    </div>
                </div>
            </div>
        </div>

<?php
generate_report_button($_REQUEST['report_source']);

for ($x = 0; $x < sizeof($special_notification); $x++) {
	echo $special_notification[$x];
}

// for ($x = 0; $x < sizeof($standard_notification); $x++) {
// 	echo $standard_notification[$x];
// }

for ($x = 0; $x < sizeof($standard_notification); $x=$x+2) {
	echo '<div class="row">
        	<div class="col-lg-6 col-xs-12">'.$standard_notification[$x].'</div>
        	<div class="col-lg-6 col-xs-12">'.$standard_notification[$x+1].'</div>
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
$(document).ready(function () {
    $(function () {
      $('[data-toggle="tooltip"]').tooltip()
    });
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
function SetReportColors() {
    let HeadLineColors = $('input[name="HeadLineColors[]"]').map(function () { return $(this).val(); }).get();

    $.post('php/server/parameters.php', {
        action: 'setReportParameter',
        HeadLineColors: HeadLineColors
    }, function(msg) {
    showMessage (msg);
  });
}

function askdeleteAllNotifications() {
  showModalWarning('<?=$pia_lang['REP_delete_all_noti'];?>', '<?=$pia_lang['REP_delete_all_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Delete'];?>', 'deleteAllNotifications');
}
function deleteAllNotifications()
{
  $.get('php/server/files.php?action=deleteAllNotifications', function(msg) {
    showMessage (msg);
  });
}

function askdeleteAllNotificationsArchive() {
  showModalWarning('<?=$pia_lang['REP_delete_all_noti'];?>', '<?=$pia_lang['REP_delete_all_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Delete'];?>', 'deleteAllNotificationsArchive');
}
function deleteAllNotificationsArchive()
{
  $.get('php/server/files.php?action=deleteAllNotificationsArchive', function(msg) {
    showMessage (msg);
  });
}

function ReportReload() {
    setTimeout(function() {
        location.reload();
    }, 1000)
};

</script>

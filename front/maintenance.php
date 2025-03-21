<!-- --------------------------------------------------------------------------
#  Pi.Alert
#  Open Source Network Guard / WIFI & LAN intrusion detector
#
#  maintenance.php - Front module. Server side. Manage Devices
#------------------------------------------------------------------------------
#  Puche      2021        pi.alert.application@gmail.com   GNU GPLv3
#  jokob-sk   2022        jokob.sk@gmail.com               GNU GPLv3
#  leiweibau  2024+       https://github.com/leiweibau     GNU GPLv3
#-------------------------------------------------------------------------- -->

<?php
session_start();
error_reporting(0);

if ($_SESSION["login"] != 1) {
	header('Location: ./index.php');
	exit;
}

require 'php/templates/header.php';
require 'php/templates/maintenance_func.php';
require 'php/server/journal.php';

?>
<!-- Page ----------------------------------------------------------------- -->
<div class="content-wrapper">

<!-- Content header-------------------------------------------------------- -->
    <section class="content-header">
    <?php require 'php/templates/notification.php';?>
      <h1 id="pageTitle">
         <?=$pia_lang['MT_Title'];?>
      </h1>
    </section>

    <!-- Main content ----------------------------------------------------- -->
    <section class="content">

<?php
// Get API-Key ----------------------------------------------------------------
$APIKEY = get_config_parmeter('PIALERT_APIKEY');
if ($APIKEY == "") {$APIKEY = $pia_lang['MT_Tool_setapikey_false'];}

// Get Ignore List ------------------------------------------------------------
$MAC_IGNORE_LIST_LINE = get_config_parmeter('MAC_IGNORE_LIST');
if ($MAC_IGNORE_LIST_LINE == "" || $MAC_IGNORE_LIST_LINE == "[]") {$MAC_IGNORE_LIST = $pia_lang['MT_Tool_ignorelist_false'];} else {
	$MAC_IGNORE_LIST = str_replace("[", "", str_replace("]", "", str_replace("'", "", trim($MAC_IGNORE_LIST_LINE))));
	$MAC_IGNORE_LIST = str_replace(",", ", ", trim($MAC_IGNORE_LIST));
}
$IP_IGNORE_LIST_LINE = get_config_parmeter('IP_IGNORE_LIST');
if ($IP_IGNORE_LIST_LINE == "" || $IP_IGNORE_LIST_LINE == "[]") {$IP_IGNORE_LIST = $pia_lang['MT_Tool_ignorelist_false'];} else {
    $IP_IGNORE_LIST = str_replace("[", "", str_replace("]", "", str_replace("'", "", trim($IP_IGNORE_LIST_LINE))));
    $IP_IGNORE_LIST = str_replace(",", ", ", trim($IP_IGNORE_LIST));
}
// Get Notification Settings --------------------------------------------------
$CONFIG_FILE_SOURCE = "../config/pialert.conf";
$CONFIG_FILE_KEY_LINE = file($CONFIG_FILE_SOURCE);
$CONFIG_FILE_FILTER_VALUE_ARP = array_values(preg_grep("/(REPORT_MAIL|REPORT_NTFY|REPORT_WEBGUI|REPORT_PUSHSAFER|REPORT_PUSHOVER|REPORT_TELEGRAM)(?!_)/i", $CONFIG_FILE_KEY_LINE));
$CONFIG_FILE_FILTER_VALUE_WEB = array_values(preg_grep("/(REPORT_MAIL_WEBMON|REPORT_NTFY_WEBMON|REPORT_WEBGUI_WEBMON|REPORT_PUSHSAFER_WEBMON|REPORT_PUSHOVER_WEBMON |REPORT_TELEGRAM_WEBMON)/i", $CONFIG_FILE_KEY_LINE));

// Size and last mod of DB ----------------------------------------------------
$DB_SOURCE = str_replace('front', 'db', getcwd()) . '/pialert.db';
$DB_SIZE_DATA = number_format((filesize($DB_SOURCE) / 1000000), 2, ",", ".") . '&nbsp;MB';
$DB_MOD_DATA = date("d.m.Y, H:i:s", filemtime($DB_SOURCE)) . '';

// Find latest DB Backup for restore and download -----------------------------
$ARCHIVE_PATH = str_replace('front', 'db', getcwd()) . '/';
$LATEST_FILES = glob($ARCHIVE_PATH . "pialertdb_*.zip");
if (sizeof($LATEST_FILES) == 0) {
	$LATEST_BACKUP_DATE = $pia_lang['MT_Tool_restore_blocked'];
	$block_restore_button_db = true;
} else {
	natsort($LATEST_FILES);
	$LATEST_FILES = array_reverse($LATEST_FILES, False);
	$LATEST_BACKUP = $LATEST_FILES[0];
	$LATEST_BACKUP_DATE = date("Y-m-d H:i:s", filemtime($LATEST_BACKUP));
}

if (get_config_parmeter('FRITZBOX_ACTIVE') == 1) {$_SESSION['FRITZBOX_ACTIVE'] = 1;} else { $_SESSION['FRITZBOX_ACTIVE'] = 0;}
if (get_config_parmeter('MIKROTIK_ACTIVE') == 1) {$_SESSION['MIKROTIK_ACTIVE'] = 1;} else { $_SESSION['MIKROTIK_ACTIVE'] = 0;}
if (get_config_parmeter('UNIFI_ACTIVE') == 1) {$_SESSION['UNIFI_ACTIVE'] = 1;} else { $_SESSION['UNIFI_ACTIVE'] = 0;}
if (get_config_parmeter('OPENWRT_ACTIVE') == 1) {$_SESSION['OPENWRT_ACTIVE'] = 1;} else { $_SESSION['OPENWRT_ACTIVE'] = 0;}

// Buffer active --------------------------------------------------------------
	$file = '../db/pialert_journal_buffer';
	if (file_exists($file)) {
        $buffer_indicator = '<span style="cursor:pointer; text-decoration: underline dotted red; text-underline-position:under;" data-toggle="tooltip" data-placement="top" title="'.$pia_lang['MT_Stats_ToolTip_Jrn'].'">(<span style="color:red;">*</span>)</span>';
	} else {$buffer_indicator = '';}

// Set Tab --------------------------------------------------------------------
if ($_REQUEST['tab'] == '1') {
	$pia_tab_setting = 'active';
	$pia_tab_tool = $pia_tab_backup = $pia_tab_satellites = $pia_tab_gui = '';
} elseif ($_REQUEST['tab'] == '2') {
	$pia_tab_tool = 'active';
	$pia_tab_setting = $pia_tab_backup = $pia_tab_satellites = $pia_tab_gui = '';
} elseif ($_REQUEST['tab'] == '3') {
    $pia_tab_backup = 'active';
	$pia_tab_setting = $pia_tab_tool = $pia_tab_satellites = $pia_tab_gui = '';
} elseif ($_REQUEST['tab'] == '4') {
    $pia_tab_gui = 'active';
	$pia_tab_setting = $pia_tab_tool = $pia_tab_satellites = $pia_tab_backup = '';
} elseif ($_REQUEST['tab'] == '5') {
    $pia_tab_satellites = 'active';
    $pia_tab_setting = $pia_tab_tool = $pia_tab_backup = $pia_tab_gui = '';
} else {
	$pia_tab_setting = 'active';
	$pia_tab_tool = $pia_tab_backup = $pia_tab_gui = $pia_tab_satellites = '';}
?>

    <div class="row">
      <div class="col-md-12">

<!-- Status Box ----------------------------------------------------------- -->
    <div class="box" id="Maintain-Status">
        <div class="box-header with-border">
            <h3 class="box-title">Status</h3> <a href="./systeminfo.php"><i class="bi bi-info-circle text-aqua" style="position: relative; top: -5px; margin-left: 5px;"></i></a>
        </div>
        <div class="box-body" style="padding-bottom: 5px;">
            <div class="db_info_table">
                <div class="db_info_table_row">
                    <div class="db_info_table_cell"><?=$pia_lang['MT_database_lastmod'];?></div>
                    <div class="db_info_table_cell">
                        <?=$DB_MOD_DATA.' '.$buffer_indicator;?> /  <?=$DB_SIZE_DATA;?>
                    </div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_info_table_cell"><?=$pia_lang['MT_database_backup'];?></div>
                    <div class="db_info_table_cell"><span id="autobackupdbcount"></span>
                        <?=$ARCHIVE_COUNT . ' ' . $pia_lang['MT_database_backup_found'] . ' / ' . $pia_lang['MT_database_backup_total'];?>: <span id="autobackupdbsize"></span> 
                    </div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_info_table_cell"><?=$pia_lang['MT_config_backup'];?></div>
                    <div class="db_info_table_cell"><span id="autobackupconfcount"></span>
                        <?=$CONFIG_FILE_COUNT . ' ' . $pia_lang['MT_database_backup_found'];?>
                    </div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_info_table_cell"><?=$pia_lang['MT_arp_status'];?></div>
                    <div class="db_info_table_cell">
<?php 
echo $_SESSION['arpscan_result'];
read_arpscan_timer();
?>                  </div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_info_table_cell"><?=$pia_lang['MT_Stats_autobkp'];?></div>
                    <div class="db_info_table_cell">
<?php
if ($_SESSION['AUTO_DB_BACKUP']) {echo $pia_lang['MT_Stats_autobkp_on'].' / <span id="autobackupstatus"></span>';} else {echo $pia_lang['MT_Stats_autobkp_off'].' <span hidden id="autobackupstatus"></span>';}
?> 
                    </div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_info_table_cell">Api-Key</div>
                    <div class="db_info_table_cell" style="overflow-wrap: anywhere;">
                        <input readonly value="<?=$APIKEY;?>" class="statusbox_ro_inputs">
                    </div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_info_table_cell"><?=$pia_lang['MT_notification_config'];?></div>
                    <div class="db_info_table_cell">
                        <?=format_notifications($CONFIG_FILE_FILTER_VALUE_ARP);?>
                    </div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_info_table_cell"><?=$pia_lang['MT_notification_config_webmon'];?></div>
                    <div class="db_info_table_cell">
                        <?=format_notifications($CONFIG_FILE_FILTER_VALUE_WEB);?>
                    </div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_info_table_cell"><?=$pia_lang['MT_Tool_ignorelist'];?> (MAC)</div>
                    <div class="db_info_table_cell">
                        <?=$MAC_IGNORE_LIST;?>
                    </div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_info_table_cell"><?=$pia_lang['MT_Tool_ignorelist'];?> (IP)</div>
                    <div class="db_info_table_cell">
                        <?=$IP_IGNORE_LIST;?>
                    </div>
                </div>
            </div>
        </div>
          <!-- /.box-body -->
    </div>

      </div>
    </div>

<!-- Log Viewer ----------------------------------------------------------- -->
    <div class="box">
        <div class="box-header with-border">
            <h3 class="box-title">Log Viewer</h3>
        </div>
        <div class="box-body main_logviwer_buttonbox" id="logviewer">
            <button type="button" id="oisjmofeirfj" class="btn btn-primary main_logviwer_button_m" data-toggle="modal" data-target="#modal-logviewer-scan"><?=$pia_lang['MT_Tools_Logviewer_Scan'];?></button>
            <button type="button" id="wefwfwefewdf" class="btn btn-primary main_logviwer_button_m" data-toggle="modal" data-target="#modal-logviewer-iplog"><?=$pia_lang['MT_Tools_Logviewer_IPLog'];?></button>
            <button type="button" id="tzhrsreawefw" class="btn btn-primary main_logviwer_button_m" data-toggle="modal" data-target="#modal-logviewer-vendor"><?=$pia_lang['MT_Tools_Logviewer_Vendor'];?></button>
            <button type="button" id="arzuozhrsfga" class="btn btn-primary main_logviwer_button_m" data-toggle="modal" data-target="#modal-logviewer-cleanup"><?=$pia_lang['MT_Tools_Logviewer_Cleanup'];?></button>
<?php
if ($_SESSION['Scan_WebServices'] == True) {
	echo '<button type="button" id="erftttwrdwqqq" class="btn btn-primary main_logviwer_button_m" data-toggle="modal" data-target="#modal-logviewer-webservices">' . $pia_lang['MT_Tools_Logviewer_WebServices'] . '</button>';
}
?>
      	</div>
    </div>

<?php
// Log Viewer - Modals
// Scan
print_logviewer_modal_head('scan', 'pialert.1.log');
print_logviewer_modal_foot();
// // Internet IP
print_logviewer_modal_head('iplog', 'pialert.IP.log');
print_logviewer_modal_foot();
// // Vendor Update
print_logviewer_modal_head('vendor', 'pialert.vendors.log');
print_logviewer_modal_foot();
// // Cleanup
print_logviewer_modal_head('cleanup', 'pialert.cleanup.log');
print_logviewer_modal_foot();
// // WebServices
if ($_SESSION['Scan_WebServices'] == True) {
 	print_logviewer_modal_head('webservices', 'pialert.webservices.log');
 	print_logviewer_modal_foot();
}
// // Inactive Hosts
print_logviewer_modal_head('inactivehosts', 'Inactive Hosts');
print_logviewer_modal_foot();
?>

<!-- Tabs ----------------------------------------------------------------- -->
    <div class="nav-tabs-custom">
    <ul class="nav nav-tabs">
        <li class="<?=$pia_tab_setting?>"><a href="#tab_Settings" data-toggle="tab" onclick="update_tabURL(window.location.href,'1')"><?=$pia_lang['MT_Tools_Tab_Settings']?></a></li>
        <li class="<?=$pia_tab_gui?>"><a href="#tab_GUI" data-toggle="tab" onclick="update_tabURL(window.location.href,'4')"><?=$pia_lang['MT_Tools_Tab_GUI']?></a></li>
        <li class="<?=$pia_tab_tool?>"><a href="#tab_DBTools" data-toggle="tab" onclick="update_tabURL(window.location.href,'2')"><?=$pia_lang['MT_Tools_Tab_Tools']?></a></li>
        <li class="<?=$pia_tab_backup?>"><a href="#tab_BackupRestore" data-toggle="tab" onclick="update_tabURL(window.location.href,'3')"><?=$pia_lang['MT_Tools_Tab_BackupRestore']?></a></li>

<?php
if ($_SESSION['SATELLITES_ACTIVE'] == True) {
    echo '<li class="'.$pia_tab_satellites.'"><a href="#tab_satellites" data-toggle="tab" onclick="update_tabURL(window.location.href,\'5\')">'.$pia_lang['MT_Tool_satellites'].'</a></li>';
}

?>

    </ul>
    <div class="tab-content">

<?php
require 'php/templates/maintenance_settings.php';
require 'php/templates/maintenance_gui.php';
require 'php/templates/maintenance_main.php';
require 'php/templates/maintenance_backup.php';
require 'php/templates/maintenance_sat.php';
?>

    </div>
</div>

<!-- Config Editor -------------------------------------------------------- -->
 <div class="box">
        <div class="box-body" id="configeditor">
           <button type="button" id="oisggfjergfeirfj" class="btn btn-danger" data-toggle="modal" data-target="#modal-config-editor"><?=$pia_lang['MT_ConfEditor_Start'];?></button>
      </div>
    </div>

    <div class="box box-solid box-danger collapsed-box" style="margin-top: -15px;">
    <div class="box-header with-border" data-widget="collapse" id="configeditor_innerbox">
        <h3 class="box-title"><?=$pia_lang['MT_ConfEditor_Hint'];?></h3>
        <div class="box-tools pull-right">
            <button type="button" class="btn btn-box-tool"><i class="fa fa-plus"></i></button>
        </div>
    </div>
    <div class="box-body">
           <table class="table configeditor_help">
              <tbody>
                <tr>
                  <th scope="row" class="text-nowrap text-danger"><?=$pia_lang['MT_ConfEditor_Restore'];?></th>
                  <td class="db_tools_table_cell_b"><?=$pia_lang['MT_ConfEditor_Restore_info'];?></td>
                </tr>
                <tr>
                  <th scope="row" class="text-nowrap text-danger"><?=$pia_lang['MT_ConfEditor_Backup'];?></th>
                  <td class="db_tools_table_cell_b"><?=$pia_lang['MT_ConfEditor_Backup_info'];?></td>
                </tr>
                <tr>
                  <th scope="row" class="text-nowrap text-danger"><?=$pia_lang['Gen_Save'];?></th>
                  <td class="db_tools_table_cell_b"><?=$pia_lang['MT_ConfEditor_Save_info'];?></td>
                </tr>
              </tbody>
            </table>
    </div>
    <!-- /.box-body -->
</div>

<!-- Config Editor - Modals ----------------------------------------------- -->
    <div class="modal fade" id="modal-config-editor">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <form role="form" accept-charset="utf-8">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span></button>
                    <h4 class="modal-title">Config Editor</h4>
                     <input type="text" id="searchInput" placeholder="<?=$pia_lang['Device_Searchbox'];?>..." class="form-control" style="margin-top: 10px; max-width: 200px; display: inline-block;">
                    <button type="button" id="nextButton" class="btn btn-primary" style="margin-left: 10px;">Next</button>
                </div>
                <div class="modal-body" style="text-align: left;">
                    <textarea class="form-control" name="txtConfigFileEditor" id="ConfigFileEditor" spellcheck="false" wrap="off" style="resize: none; font-family: monospace; height: 70vh;"><?=file_get_contents('../config/pialert.conf');?></textarea>
                </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="btnPiaRestoreConfigFile" data-dismiss="modal" style="margin: 5px" onclick="askRestoreConfigFile()"><?=$pia_lang['MT_ConfEditor_Restore'];?></button>
                    <button type="button" class="btn btn-success" id="btnPiaBackupConfigFile" style="margin: 5px" onclick="BackupConfigFile('no')"><?=$pia_lang['MT_ConfEditor_Backup'];?></button>
                    <button type="button" class="btn btn-danger" id="btnConfigFileEditor" style="margin: 5px" onclick="SaveConfigFile()"><?=$pia_lang['Gen_Save'];?></button>
                    <button type="button" class="btn btn-default" id="btnPiaEditorClose" data-dismiss="modal" style="margin: 5px"><?=$pia_lang['Gen_Close'];?></button>
                  </div>
              </form>
            </div>
        </div>
    </div>

<div style="width: 100%; height: 20px;"></div>
    <!-- ------------------------------------------------------------------ -->

</section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->

<!-- ---------------------------------------------------------------------- -->
<?php
require 'php/templates/footer.php';
?>
<link rel="stylesheet" href="lib/AdminLTE/plugins/iCheck/all.css">
<script src="lib/AdminLTE/plugins/iCheck/icheck.min.js"></script>

<!-- Autoscroll-fix for Modals -->
<script>
$(document).ready(function () {
    $('#modal-config-editor').on('show.bs.modal', function () {
        // Save the current scroll position and apply styles to the body and modal
        var scrollPosition = $(window).scrollTop();
        $('body').css({
            position: 'fixed',
            width: '100%',
            top: -scrollPosition
        });
        $('#modal-config-editor').css('overflow-y', 'scroll');
    });

    $('#modal-config-editor').on('hidden.bs.modal', function () {
        // Reset styles when modal is hidden
        var scrollPosition = Math.abs(parseInt($('body').css('top')));
        $('body').css({
            position: '',
            width: '',
            top: ''
        });
        $(window).scrollTop(scrollPosition);
        $('#modal-config-editor').css('overflow-y', 'hidden');
    });
    $(function () {
      $('[data-toggle="tooltip"]').tooltip()
    })

    let searchIndex = 0;

    document.getElementById('searchInput').addEventListener('input', function () {
        searchIndex = 0;
        highlightNextMatch(false);
    });

    document.getElementById('nextButton').addEventListener('click', function () {
        highlightNextMatch(false);
    });

    function highlightNextMatch() {
        const searchText = document.getElementById('searchInput').value.toLowerCase();
        const textarea = document.getElementById('ConfigFileEditor');
        const text = textarea.value.toLowerCase();

        if (searchText) {
            const nextIndex = text.indexOf(searchText, searchIndex);
            if (nextIndex !== -1) {
                const beforeMatch = textarea.value.substring(0, nextIndex);
                const lineHeight = textarea.scrollHeight / textarea.value.split('\n').length;
                const lineNumber = beforeMatch.split('\n').length - 1;
                textarea.scrollTop = lineHeight * lineNumber;
                searchIndex = nextIndex + searchText.length;
            } else {
                searchIndex = 0;
                alert('<?=$pia_lang['MT_ConfEditor_SearchEnd'];?>');
            }
        }
    }

});
</script>

<script>
initializeiCheck();
// delete devices with emty macs
function askDeleteDevicesWithEmptyMACs() {
  showModalWarning('<?=$pia_lang['MT_Tool_del_empty_macs_noti'];?>', '<?=$pia_lang['MT_Tool_del_empty_macs_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Delete'];?>', 'deleteDevicesWithEmptyMACs');
}
function deleteDevicesWithEmptyMACs() {
	$.get('php/server/devices.php?action=deleteAllWithEmptyMACs', function(msg) {showMessage (msg);});
}

// Test Notifications
function askTestNotificationSystem() {
  showModalWarning('<?=$pia_lang['MT_Tool_test_notification_noti'];?>', '<?=$pia_lang['MT_Tool_test_notification_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Run'];?>', 'TestNotificationSystem');
}
function TestNotificationSystem() {
	$.get('php/server/devices.php?action=TestNotificationSystem', function(msg) {showMessage (msg);});
}

// delete all devices
function askDeleteAllDevices() {
  showModalWarning('<?=$pia_lang['MT_Tool_del_alldev_noti'];?>', '<?=$pia_lang['MT_Tool_del_alldev_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Delete'];?>', 'deleteAllDevices');
}
function deleteAllDevices() {
	$.get('php/server/devices.php?action=deleteAllDevices', function(msg) {showMessage (msg);});
}

// delete all webservices
function askDeleteAllWebServices() {
  showModalWarning('<?=$pia_lang['MT_Tool_del_allserv_noti'];?>', '<?=$pia_lang['MT_Tool_del_allserv_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Delete'];?>', 'DeleteAllWebServices');
}
function DeleteAllWebServices() {
    $.get('php/server/services.php?action=DeleteAllWebServices', function(msg) {showMessage (msg);});
}

// delete all (unknown) devices
function askDeleteUnknown() {
  showModalWarning('<?=$pia_lang['MT_Tool_del_unknowndev_noti'];?>', '<?=$pia_lang['MT_Tool_del_unknowndev_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Delete'];?>', 'deleteUnknownDevices');
}
function deleteUnknownDevices() {
	$.get('php/server/devices.php?action=deleteUnknownDevices', function(msg) {showMessage (msg);});
}

// Toggle Imports
function askToggleImport(fdeviceType,ftoggleState) {
  window.global_fdeviceType = fdeviceType;
  window.global_ftoggleState = ftoggleState;
  showModalWarning('<?=$pia_lang['MT_Tggl_Import_head'];?>', '<?=$pia_lang['MT_Tggl_Import_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Switch'];?>', 'ToggleImport');
}
function ToggleImport() {
  var fdeviceType = window.global_fdeviceType;
  var ftoggleState = window.global_ftoggleState;
  $.get('php/server/files.php?action=ToggleImport'
    + '&deviceType='    + fdeviceType
    + '&toggleState='   + ftoggleState
    , function(msg) {
    showMessage (msg);
  });
}

// delete all Events
function askDeleteEvents() {
  showModalWarning('<?=$pia_lang['MT_Tool_del_allevents_noti'];?>', '<?=$pia_lang['MT_Tool_del_allevents_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Delete'];?>', 'deleteEvents');
}
function deleteEvents() {
	$.get('php/server/devices.php?action=deleteEvents', function(msg) {showMessage (msg);});
}

// delete History
function askDeleteActHistory() {
  showModalWarning('<?=$pia_lang['MT_Tool_del_ActHistory_noti'];?>', '<?=$pia_lang['MT_Tool_del_ActHistory_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Delete'];?>', 'deleteActHistory');
}
function deleteActHistory() {
	$.get('php/server/devices.php?action=deleteActHistory', function(msg) {showMessage (msg);});
}

// delete Speedtest results
function askDeleteSpeedtestResults() {
  showModalWarning('<?=$pia_lang['MT_Tool_del_speedtest'];?>', '<?=$pia_lang['MT_Tool_del_speedtest_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Delete'];?>', 'DeleteSpeedtestResults');
}
function DeleteSpeedtestResults() {
	$.get('php/server/devices.php?action=DeleteSpeedtestResults', function(msg) {showMessage (msg);});
}

// delete Nmap results
function askDeleteNmapScansResults() {
  showModalWarning('<?=$pia_lang['MT_Tool_del_nmapscans'];?>', '<?=$pia_lang['MT_Tool_del_nmapscans_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Delete'];?>', 'DeleteNmapScansResults');
}
function DeleteNmapScansResults() {
	$.get('php/server/devices.php?action=DeleteNmapScansResults', function(msg) {showMessage (msg);});
}

// reset VOIDED
function askresetVoidedEvents() {
  showModalWarning('<?=$pia_lang['MT_Tool_reset_voided'];?>', '<?=$pia_lang['MT_Tool_reset_voided_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Run'];?>', 'resetVoidedEvents');
}
function resetVoidedEvents() {
    $.get('php/server/devices.php?action=resetVoidedEvents', function(msg) {showMessage (msg);});
}

// Backup DB to Archive
function askBackupDBtoArchive() {
  showModalWarning('<?=$pia_lang['MT_Tool_backup_noti'];?>', '<?=$pia_lang['MT_Tool_backup_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Backup'];?>', 'BackupDBtoArchive');
}
function BackupDBtoArchive() {
	$.get('php/server/files.php?action=BackupDBtoArchive', function(msg) {showMessage (msg);});
}

// Restore DB from Archive
function askRestoreDBfromArchive() {
  showModalWarning('<?=$pia_lang['MT_Tool_restore_noti'];?>', '<?=$pia_lang['MT_Tool_restore_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Restore'];?>', 'RestoreDBfromArchive');
}
function RestoreDBfromArchive() {
	$.get('php/server/files.php?action=RestoreDBfromArchive', function(msg) {showMessage (msg);});
}

// Purge Backups
function askPurgeDBBackups() {
  showModalWarning('<?=$pia_lang['MT_Tool_purgebackup_noti'];?>', '<?=$pia_lang['MT_Tool_purgebackup_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Purge'];?>', 'PurgeDBBackups');
}
function PurgeDBBackups() {
	$.get('php/server/files.php?action=PurgeDBBackups', function(msg) {showMessage (msg);});
}

// Backup DB to CSV
function askBackupDBtoCSV() {
  showModalWarning('<?=$pia_lang['MT_Tool_backupcsv_noti'];?>', '<?=$pia_lang['MT_Tool_backupcsv_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Backup'];?>', 'BackupDBtoCSV');
}
function BackupDBtoCSV() {
	$.get('php/server/files.php?action=BackupDBtoCSV', function(msg) {showMessage (msg);});
}

// Switch Darkmode
function askEnableDarkmode() {
  showModalWarning('<?=$pia_lang['MT_Tool_darkmode_noti'];?>', '<?=$pia_lang['MT_Tool_darkmode_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Switch'];?>', 'EnableDarkmode');
}
function EnableDarkmode() {
	$.get('php/server/files.php?action=EnableDarkmode', function(msg) {showMessage (msg);});
}

// Switch Web Service Monitor
function askEnableWebServiceMon() {
  showModalWarning('<?=$pia_lang['MT_Tool_webservicemon_noti'];?>', '<?=$pia_lang['MT_Tool_webservicemon_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Switch'];?>', 'EnableWebServiceMon');
}
function EnableWebServiceMon() {
	$.get('php/server/services.php?action=EnableWebServiceMon', function(msg) {showMessage (msg);});
}

// Switch ICMP Monitor
function askEnableICMPMon() {
  showModalWarning('<?=$pia_lang['MT_Tool_icmpmon_noti'];?>', '<?=$pia_lang['MT_Tool_icmpmon_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Switch'];?>', 'EnableICMPMon');
}
function EnableICMPMon() {
	$.get('php/server/icmpmonitor.php?action=EnableICMPMon', function(msg) {showMessage (msg);});
}

// Switch MainScan
function askEnableMainScan() {
  showModalWarning('<?=$pia_lang['MT_Tool_mainscan_noti'];?>', '<?=$pia_lang['MT_Tool_mainscan_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Switch'];?>', 'EnableMainScan');
}
function EnableMainScan() {
	$.get('php/server/devices.php?action=EnableMainScan', function(msg) {showMessage (msg);});
}

// Switch Satellites
function askEnableSatelliteScan() {
  showModalWarning('<?=$pia_lang['MT_Tool_satellites_noti'];?>', '<?=$pia_lang['MT_Tool_satellites_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Switch'];?>', 'EnableSatelliteScan');
}
function EnableSatelliteScan() {
    $.get('php/server/devices.php?action=EnableSatelliteScan', function(msg) {showMessage (msg);});
}

// Toggle Graph
function askEnableOnlineHistoryGraph() {
  showModalWarning('<?=$pia_lang['MT_Tool_onlinehistorygraph_noti'];?>', '<?=$pia_lang['MT_Tool_onlinehistorygraph_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Switch'];?>', 'EnableOnlineHistoryGraph');
}
function EnableOnlineHistoryGraph() {
	$.get('php/server/files.php?action=EnableOnlineHistoryGraph', function(msg) {showMessage (msg);});
}

// Set API-Key
function askSetAPIKey() {
  showModalWarning('<?=$pia_lang['MT_Tool_setapikey_noti'];?>', '<?=$pia_lang['MT_Tool_setapikey_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Okay'];?>', 'SetAPIKey');
}
function SetAPIKey() {
	$.get('php/server/files.php?action=SetAPIKey', function(msg) {showMessage (msg);});
}

// Enable Login
function askPiAlertLoginEnable() {
  showModalWarning('<?=$pia_lang['MT_Tool_loginenable_noti'];?>', '<?=$pia_lang['MT_Tool_loginenable_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Switch'];?>', 'PiAlertLoginEnable');
}
function PiAlertLoginEnable() {
	$.get('php/server/files.php?action=LoginEnable', function(msg) {showMessage (msg);});
}

// Disable Login
function askPiAlertLoginDisable() {
  showModalWarning('<?=$pia_lang['MT_Tool_logindisable_noti'];?>', '<?=$pia_lang['MT_Tool_logindisable_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Switch'];?>', 'PiAlertLoginDisable');
}
function PiAlertLoginDisable() {
	$.get('php/server/files.php?action=LoginDisable', function(msg) {showMessage (msg);});
}

function setTextValue (textElement, textValue) {
  $('#'+textElement).val (textValue);
}

// Set Theme
function setPiAlertTheme () {
	$.get('php/server/files.php?action=setTheme&SkinSelection='+ $('#txtSkinSelection').val(), function(msg) {showMessage (msg);});
}

// Set Language
function setPiAlertLanguage() {
	$.get('php/server/files.php?action=setLanguage&LangSelection='+ $('#txtLangSelection').val(), function(msg) {showMessage (msg);});
}

// Set FavIcon
function setFavIconURL() {
	$.get('php/server/files.php?action=setFavIconURL&FavIconURL='+ $('#txtFavIconURL').val(), function(msg) {showMessage (msg);});
}

// Set FavIcon
function setPiholeURL() {
    $.get('php/server/files.php?action=setPiholeURL&PiholeURL='+ $('#txtPiholeURL').val(), function(msg) {showMessage (msg);});
}

// Set ArpScanTimer
function setPiAlertArpTimer() {
  $.ajax({
        method: "GET",
        url: "./php/server/files.php?action=setArpTimer&ArpTimer=" + $('#txtPiaArpTimer').val(),
        data: "",
        beforeSend: function() { $('#Timeralertspinner').removeClass("disablespinner"); $('#TimeralertText').addClass("disablespinner");  },
        complete: function() { $('#Timeralertspinner').addClass("disablespinner"); $('#TimeralertText').removeClass("disablespinner"); },
        success: function(data, textStatus) {
            showMessage (data);
        }
    })
}

// Backup Configfile
function BackupConfigFile(reload)  {
	if (reload == 'yes') {
		$.get('php/server/files.php?action=BackupConfigFile&reload=yes', function(msg) {showMessage (msg);});
	} else {
		$.get('php/server/files.php?action=BackupConfigFile&reload=no', function(msg) {showMessage (msg);});
	}
}

// Restore Configfile
function askRestoreConfigFile() {
  showModalWarning('<?=$pia_lang['MT_ConfEditor_Restore_noti'];?>', '<?=$pia_lang['MT_ConfEditor_Restore_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Run'];?>', 'RestoreConfigFile');
}
function RestoreConfigFile() {
	$.get('php/server/files.php?action=RestoreConfigFile', function(msg) {showMessage (msg);});
}

function SaveConfigFile() {
	var postData = {
		action: 'SaveConfigFile',
		configfile: $('#ConfigFileEditor').val()
	};
	$.post('php/server/files.php', postData, function(msg) {showMessage(msg);});
}

// Set Device List Column
function askDeviceListCol() {
  showModalWarning('<?=$pia_lang['MT_Tool_DevListCol_noti'];?>', '<?=$pia_lang['MT_Tool_DevListCol_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Save'];?>', 'setDeviceListCol');
}
function setDeviceListCol() {
    $.get('php/server/files.php?action=setDeviceListCol&'
    + '&connectiontype=' + ($('#chkConnectionType')[0].checked * 1)
    + '&favorite='       + ($('#chkFavorite')[0].checked * 1)
    + '&group='          + ($('#chkGroup')[0].checked * 1)
    + '&type='           + ($('#chkType')[0].checked * 1)
    + '&owner='          + ($('#chkOwner')[0].checked * 1)
    + '&firstsess='      + ($('#chkfirstSess')[0].checked * 1)
    + '&lastsess='       + ($('#chklastSess')[0].checked * 1)
    + '&lastip='         + ($('#chklastIP')[0].checked * 1)
    + '&mactype='        + ($('#chkMACtype')[0].checked * 1)
    + '&macaddress='     + ($('#chkMACaddress')[0].checked * 1)
    + '&macvendor='      + ($('#chkMACVendor')[0].checked * 1)
    + '&location='       + ($('#chkLocation')[0].checked * 1)
    + '&wakeonlan='      + ($('#chkWakeOnLAN')[0].checked * 1)
    , function(msg) {
    showMessage (msg);
  });
}
// Delete Inactive Hosts
function askDeleteInactiveHosts() {
  showModalWarning('<?=$pia_lang['MT_Tool_del_Inactive_Hosts'];?>', '<?=$pia_lang['MT_Tool_del_Inactive_Hosts_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Delete'];?>', 'DeleteInactiveHosts');
}
function DeleteInactiveHosts() {
	$.get('php/server/devices.php?action=DeleteInactiveHosts', function(msg) {showMessage (msg);});
}
// Update Check
function check_github_for_updates() {
    $("#updatecheck").empty();
    $.ajax({
        method: "POST",
        url: "./php/server/updatecheck.php",
        data: "",
        beforeSend: function() { $('#updatecheck').addClass("ajax_scripts_loading"); },
        complete: function() { $('#updatecheck').removeClass("ajax_scripts_loading"); },
        success: function(data, textStatus) {
            $("#updatecheck").html(data);
        }
    })
}
// Update URL when using the tabs
function update_tabURL(url, tab) {
    let stateObj = { id: "100" };

    url = url.replace('?tab=1','');
    url = url.replace('?tab=2','');
    url = url.replace('?tab=3','');
    url = url.replace('?tab=4','');
    url = url.replace('?tab=5','');
    url = url.replace('#','');
    window.history.pushState(stateObj,
             "Tab"+tab, url + "?tab=" + tab);
}
function initializeiCheck () {
   // Blue
   $('input[type="checkbox"].blue').iCheck({
     checkboxClass: 'icheckbox_flat-blue',
     radioClass:    'iradio_flat-blue',
     increaseArea:  '20%'
   });
}

// JS created by php while loop
<?=create_filter_editor_js();?>

var StatusBoxARPCountdown; 

function startCountdown() {
    var currentTime = new Date();
    var minutes = currentTime.getMinutes();
    var seconds = currentTime.getSeconds();

    // Calculate the time until the next 5-minute interval
    var countdownMinutes = (4 - (minutes % 5)) % 5;
    var countdownSeconds = 60 - seconds;

    // Stop Countdown
    clearInterval(StatusBoxARPCountdown);

    // Display initial countdown
    displayCountdown(countdownMinutes, countdownSeconds);

    // Update countdown every second
    StatusBoxARPCountdown = setInterval(function() {
        countdownSeconds--;
        if (countdownSeconds < 0) {
            countdownSeconds = 59;
            countdownMinutes--;

            if (countdownMinutes < 0) {
                // Reset countdown for the next 5-minute interval
                countdownMinutes = 4;
                countdownSeconds = 59;
            }
        }
        displayCountdown(countdownMinutes, countdownSeconds);
    }, 1000);
}
function displayCountdown(minutes, seconds) {
    var countdownElement = document.getElementById('nextscancountdown');
    countdownElement.textContent = '(next Scan in: ' + formatTime(minutes) + ':' + formatTime(seconds) + ')';
    if (minutes == 4 && seconds == 59) {
        GetARPStatus();
        GetAutoBackupStatus();
    }
}
function formatTime(time) {
    return time < 10 ? '0' + time : time;
}
function GetARPStatus() {
  $.get('php/server/files.php?action=GetARPStatus', function(data) {
    var arpproccount = JSON.parse(data);
    
    $('#arpproccounter').html(arpproccount[0].toLocaleString());
  } );
}
function GetAutoBackupStatus() {
  $.get('php/server/files.php?action=GetAutoBackupStatus', function(data) {
    var backupproccount = JSON.parse(data);
    
    $('#autobackupstatus').html(backupproccount[0].toLocaleString());
    $('#autobackupdbcount').html(backupproccount[1].toLocaleString());
    $('#autobackupconfcount').html(backupproccount[2].toLocaleString());
    $('#autobackupdbsize').html(backupproccount[3].toLocaleString());
  } );
}
function GetModalLogContent() {
  $.get('php/server/files.php?action=GetLogfiles', function(data) {
    var logcollection = JSON.parse(data);

    $('#modal_scan_content').html(logcollection[0].toLocaleString());
    $('#modal_iplog_content').html(logcollection[1].toLocaleString());
    $('#modal_vendor_content').html(logcollection[2].toLocaleString());
    $('#modal_cleanup_content').html(logcollection[3].toLocaleString());
    $('#modal_webservices_content').html(logcollection[4].toLocaleString());
  } );
}
function GetModalInactiveHosts() {
  $.get('php/server/devices.php?action=ListInactiveHosts', function(data) {
    var logcollection = JSON.parse(data);

    $('#modal_inactivehosts_content').html(logcollection[0].toLocaleString());
  } );
}
function UpdateStatusBox() {
	GetModalLogContent();
	GetARPStatus();
    GetAutoBackupStatus();
	startCountdown();
}
function askCreateNewSatellite() {
  showModalWarning('<?=$pia_lang['MT_SET_SatCreate_noti'];?>', '<?=$pia_lang['MT_SET_SatCreate_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Save'];?>', 'CreateNewSatellite');
}
function CreateNewSatellite() {
    $.get('php/server/devices.php?action=CreateNewSatellite&'
    + '&new_satellite_name=' + $('#txtNewSatelliteName').val()
    , function(msg) {
    showMessage (msg);
  });
}
function SaveSatellite(func_sat_name, func_sat_id) {
    $.get('php/server/devices.php?action=SaveSatellite&'
    + '&changed_satellite_name=' + $('#txtChangedSatelliteName_' + func_sat_id).val()
    + '&satellite_name=' + func_sat_name
    + '&sat_id=' + func_sat_id
    , function(msg) {
    showMessage (msg);
  });
}
function DeleteSatellite(func_sat_name, func_sat_id) {
    $.get('php/server/devices.php?action=DeleteSatellite&'
    + '&changed_satellite_name=' + $('#txtChangedSatelliteName_' + func_sat_id).val()
    + '&satellite_name=' + func_sat_name
    + '&sat_id=' + func_sat_id
    , function(msg) {
    showMessage (msg);
  });
}

setInterval(UpdateStatusBox, 15000);
GetModalLogContent();
GetARPStatus();
GetAutoBackupStatus();
GetModalInactiveHosts();
startCountdown();
</script>


<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
session_start();

if ($_SESSION["login"] != 1) {
  header('Location: ./index.php');
  exit;
}

$conf_file = '../config/version.conf';
$conf_data = parse_ini_file($conf_file);

require 'php/server/timezone.php';

function set_userimage($skinname) {
    if ($skinname == 'skin-black-light' || $skinname == 'skin-black'|| $skinname == 'leiweibau_light') {
        $_SESSION['UserLogo'] = 'pialertLogoBlack';
    } else {$_SESSION['UserLogo'] = 'pialertLogoWhite';}
}

// Darkmode
if (file_exists('../config/setting_darkmode')) {$ENABLED_DARKMODE = True;} else { $ENABLED_DARKMODE = False;}
// Use saved AdminLTE Skin
foreach (glob("../config/setting_skin*") as $filename) {
    $skinname_file = str_replace('setting_', '', basename($filename));
    $skin_selected_head = '<link rel="stylesheet" href="lib/AdminLTE/dist/css/skins/' . $skinname_file . '.min.css">';
    $skin_selected_body = '<body class="hold-transition ' . $skinname_file . '" >';
    set_userimage($skinname_file);
}
// Use fallback AdminLTE Skin
if (strlen($skin_selected_head) == 0) {
    $skin_selected_head = '<link rel="stylesheet" href="lib/AdminLTE/dist/css/skins/skin-blue.min.css">';
    $skin_selected_body = '<body class="hold-transition skin-blue">';
    set_userimage("skin-blue");
}
// UI - Language
foreach (glob("../config/setting_language*") as $filename) {
    $pia_lang_selected = str_replace('setting_language_', '', basename($filename));
}
if (strlen($pia_lang_selected) == 0) {$pia_lang_selected = 'en_us';}
require 'php/templates/language/' . $pia_lang_selected . '.php';
// UI - FavIcon
if (file_exists('../config/setting_favicon')) {
    $FRONTEND_FAVICON = file('../config/setting_favicon', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)[0];
} else {
    $FRONTEND_FAVICON = 'img/favicons/flat_blue_white.png';
}
// UI - Pihole Button
if (file_exists('../config/setting_piholebutton')) {
    $FRONTEND_PHBUTTON = file('../config/setting_piholebutton', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)[0];
} else {
    $FRONTEND_PHBUTTON = '';
}

?>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="x-dns-prefetch-control" content="off">
    <meta http-equiv="cache-control" content="max-age=60,private">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="manifest" href="img/manifest.json">
    <title>Pi.Alert - Dashboard</title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <!-- Bootstrap 3.4.1 -->
    <link rel="stylesheet" href="lib/AdminLTE/bower_components/bootstrap/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons v1.10.3 -->
    <link href="lib/AdminLTE/bower_components/bootstrap-icons/font/bootstrap-icons.css" media="all" rel="stylesheet" type="text/css">
    <!-- Font Awesome 6.40 -->
    <link rel="stylesheet" href="lib/AdminLTE/bower_components/font-awesome/css/font-awesome.min.css">
    <!-- Ionicons -->
    <link rel="stylesheet" href="lib/AdminLTE/bower_components/Ionicons/css/ionicons.min.css">
    <!-- Material Design Icons -->
    <link rel="stylesheet" href="lib/AdminLTE/bower_components/material-design-icons/css/materialdesignicons.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="lib/AdminLTE/dist/css/AdminLTE.min.css">
    <!-- AdminLTE Skins. -->
    <?=$skin_selected_head;?>
    <!-- Pi.Alert CSS -->
    <link rel="stylesheet" href="css/pialert.css?v=<?=$conf_data['VERSION_DATE'];?>">
    <!-- Offline Font -->
    <link rel="stylesheet" href="css/offline-font.css">
    <!-- Fav / Homescreen Icon -->
    <link rel="icon" type="image/x-icon" href="<?=$FRONTEND_FAVICON?>">
    <link rel="apple-touch-icon" href="<?=$FRONTEND_FAVICON?>">
    <link rel="manifest" href="img/manifest.json">
<?php
if ($ENABLED_DARKMODE === True) {echo '<link rel="stylesheet" href="css/dark-patch.css?' . $conf_data['VERSION_DATE'] . '">';} else {$wrapper_color = 'style="background-color:white"';}
if ($ENABLED_THEMEMODE === True) {echo $theme_selected_head;}
?>
    <script src="lib/AdminLTE/bower_components/chart.js/Chart.js"></script>
    <script src="lib/AdminLTE/bower_components/jquery/dist/jquery.min.js"></script>
    <script src="lib/AdminLTE/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="lib/AdminLTE/bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css">
    <script src="lib/AdminLTE/bower_components/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="lib/AdminLTE/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js"></script>
    <script src="js/hotkeys.js"></script>
</head>

<?=$skin_selected_body;?>

<div class="wrapper" <?=$wrapper_color;?>>
  <!-- Main Header -->
  <header class="main-header">
    <div class="logo">
      <span>Pi<b>.Alert</b></span>
    </div>

    <!-- Header Navbar -->
    <nav class="navbar navbar-static-top" role="navigation">
      <div  class="sidebar-toggle"><span class="sr-only"></span></div>
      <a id="navbar-reload-button" href="" role="button" onclick="window.location.reload(true)" style="padding-top: 17px;"><i class="fa fa-repeat"></i></a>
      <script>
          function toggle_systeminfobox() {
            $("#sidebar_systeminfobox").toggleClass("collapse");

            if ( $('.custom_filter').css('display') == 'none')
              $('.custom_filter').css('display','block');
            else
              $('.custom_filter').css('display','none');
          }
      </script>
      <!-- Navbar Right Menu -->
      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">
          <li>
            <div id="dashboardRefreshCountdown" class="a navbar-servertime text-muted" style="font-size:12px;">
                <?=$pia_lang['DASH_refresh_counter']?> <strong><span id="dashboardRefreshCountdownValue">--</span>s</strong>
            </div>
          </li>
          <?php
          if ($FRONTEND_PHBUTTON != '') {
            echo '<li><a id="navbar-pihole-button" class="a navbar-servertime" href="'.$FRONTEND_PHBUTTON.'" role="button" target="blank"><i class="mdi mdi-pi-hole"></i></a></li>';
          }
          ?>
          <li><a id="navbar-help-button" class="navbar-servertime" href="https://github.com/leiweibau/Pi.Alert/tree/main/docs" target="_blank">
                <i class="fa-regular fa-circle-question"></i>
              </a>
          </li>
          <li><div class="a navbar-servertime"><?php echo gethostname(); ?> <span id="PIA_Servertime_place"></span></div></li>
          <!-- Header right info -->
          <li class="dropdown user user-menu">
            <!-- Menu Toggle Button -->
            <a href="#" class="dropdown-toggle" data-toggle="dropdown" style="height: 50px; padding-top: 15px">
              <img src="img/<?=$_SESSION['UserLogo'];?>.png" class="user-image" style="border-radius: initial" alt="Pi.Alert Logo">
              <span class="label label-danger" id="Menu_Report_Counter_Badge"></span>
            </a>
            <ul class="dropdown-menu zoom-menu" style="width: 240px;">
              <!-- Menu Body -->
              <li class="user-footer">
                <div style="text-align: center;">
                  <div class="zoom-controls">
                    <button class="btn btn-xs btn-primary" onclick="zoomOut()">−</button>
                    <span id="zoom-percent">100%</span>
                    <button class="btn btn-xs btn-primary" onclick="zoomIn()">+</button>
                    <button class="btn btn-xs btn-success" onclick="zoomReset()">Reset</button>
                  </div>
                </div>
              </li>
              <li class="user-footer">
                <div style="text-align: center;">
                  <a href="./deviceDetails.php?mac=Internet" id="custom-menu-default-button" class="btn btn-default" style="width:190px;"><i class="fa-solid fa-globe custom-menu-button-icon"></i><div class="custom-menu-button-text">Internet</div></a>
                </div>
              </li>
              <li class="user-footer">
                <div style="text-align: center;">
                  <a href="./reports.php" id="custom-menu-report-button" class="btn btn-warning" style="width:190px;"><i class="fa-regular fa-envelope-open custom-menu-button-icon" id="Menu_Report_Envelope_Icon"></i><div class="custom-menu-button-text"><?=$pia_lang['About_Reports'];?> (r)</div></a>
                </div>
              </li>
              <li class="user-footer">
                <div style="text-align: center;">
                  <a href="./index.php?action=logout" id="custom-menu-logout-button" class="btn btn-danger" style="width:190px;"><i class="fa-solid fa-arrow-right-from-bracket custom-menu-button-icon"></i><div class="custom-menu-button-text"><?=$pia_lang['About_Exit'];?></div></a>
                </div>
              </li>
              <li class="user-footer">
                <div style="text-align: center;">
                  <a href="./devices.php" id="custom-menu-dashboard-button" class="btn btn-success" style="width:190px;"><i class="fa-solid fa-globe custom-menu-button-icon"></i><div class="custom-menu-button-text">Pi.<span style="font-weight: bold;">Alert</span></div></a>
                </div>
              </li>
              <li class="user-footer">
                <div class="custom-menu-icon-links"><a href="https://github.com/leiweibau/Pi.Alert" class="btn btn-default" target="blank"><i class="fa-brands fa-github"></i></a></div>
                <div class="custom-menu-icon-links"><a href="https://github.com/sponsors/leiweibau" class="btn btn-default" target="blank"><i class="fa-regular fa-heart text-maroon"></i></a></div>
                <div class="custom-menu-icon-links"><a href="https://leiweibau.net/archive/pialert/" class="btn btn-default" target="blank"><i class="fa-solid fa-house"></i></a></div>
              </li>
            </ul>
          </li>
        </ul>
      </div>
    </nav>
  </header>

<div class="row">
    <div class="col-md-9">
      <div class="box box-solid">
        <div class="box-header with-border">
          <h3 class="box-title text-aqua"><i class="bi bi-speedometer2"></i> <?=$pia_lang['ookla_devdetails_tab_title']?></h3>
        </div>
        <div class="box-body">

            <div id="speedtestChartWrapper" style="height:200px;"><canvas id="speedtestChart"></canvas></div>

            <div class="btn-group btn-group-xs" role="group" style="margin-top: 10px; margin-bottom:10px">
              <button class="btn btn-default" onclick="loadSpeedtestChart(7)">7 <?=$pia_lang['DASH_days']?></button>
              <button class="btn btn-default active" onclick="loadSpeedtestChart(14)">14 <?=$pia_lang['DASH_days']?></button>
              <button class="btn btn-default" onclick="loadSpeedtestChart(21)">21 <?=$pia_lang['DASH_days']?></button>
            </div>

        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="box box-solid">
        <div class="box-header with-border">
          <h3 class="box-title text-aqua"><i class="bi bi-journal-text"></i> Logs</h3>
        </div>

        <div class="box-body" style="height:240px;">
            <div class="form-group" style="margin-top: 10px;">
              <label for="logfileSelect">Logfile</label>
              <select id="logfileSelect" class="form-control">
                <option value=""><?=$pia_lang['DASH_select_log']?></option>
                <option value="pialert.1.log"><?=$pia_lang['MT_Tools_Logviewer_Scan'];?></option>
                <option value="pialert.IP.log"><?=$pia_lang['MT_Tools_Logviewer_IPLog'];?></option>
                <option value="pialert.cleanup.log"><?=$pia_lang['MT_Tools_Logviewer_Cleanup'];?></option>
                <option value="pialert.vendors.log"><?=$pia_lang['MT_Tools_Logviewer_Vendor'];?></option>
                <option value="pialert.webservices.log"><?=$pia_lang['MT_Tools_Logviewer_WebServices']?></option>
                <option value="pialert.speedtest.log">Speedtest (Cron)</option>
              </select>
            </div>
            <div class="form-group" style="margin-top: 10px;">
              <label for="dateSelect"><?=$pia_lang['EVE_TableHead_Date']?></label>
              <select id="dateSelect" class="form-control">
                <option value=""><?=$pia_lang['DASH_select_date']?></option>
              </select>
            </div>
            <button class="btn btn-primary" onclick="showLogModal()" style="margin-top: 20px;"><?=ucfirst($pia_lang['Gen_show'])?></button>
        </div>

      </div>
    </div>

</div>

<div class="row">
    <div class="col-md-3">
      <div class="box box-solid">
        <div class="box-header with-border">
          <h3 class="box-title text-aqua"><i class="bi bi-pie-chart"></i> <?=$pia_lang['NAV_Devices']?> - Total</h3>
          <div class="box-tools pull-right"><a href="./devices.php"><i class="fa-solid fa-up-right-from-square text-yellow"></i></a></div>
        </div>

        <div class="box-body" style="height:280px;">
            <div style="width:260px; height:260px; margin:auto; padding-top:20px">
                <canvas id="devicesDonut"></canvas>
            </div>
        </div>

      </div>
    </div>

    <div class="col-md-3">
      <div class="box box-solid">
        <div class="box-header with-border">
          <h3 class="box-title text-aqua"><i class="bi bi-pie-chart"></i> <?=$pia_lang['NAV_ICMPScan']?></h3>
          <div class="box-tools pull-right"><a href="./icmpmonitor.php"><i class="fa-solid fa-up-right-from-square text-yellow"></i></a></div>
        </div>

        <div class="box-body" style="height:280px;">
            <div style="width:260px; height:260px; margin:auto; padding-top:20px"><canvas id="devicesDonutIcmp"></canvas></div>
        </div>

      </div>
    </div>

    <div class="col-md-3">
      <div class="box box-solid">
        <div class="box-header with-border">
          <h3 class="box-title text-aqua"><i class="bi bi-pie-chart"></i> <?=$pia_lang['NAV_Services']?></h3>
          <div class="box-tools pull-right"><a href="./services.php"><i class="fa-solid fa-up-right-from-square text-yellow"></i></a></div>
        </div>

        <div class="box-body" style="height:280px;">
            <div style="width:260px; height:260px; margin:auto; padding-top:20px"><canvas id="servicesStatusDonut"></canvas></div>
        </div>

      </div>
    </div>

    <div class="col-md-3">
      <div class="box box-solid">
        <div class="box-header with-border">
          <h3 class="box-title text-aqua"><i class="bi bi-journal-text"></i> <?=$pia_lang['DASH_reports_head']?></h3>
          <div class="box-tools pull-right"><a href="./reports.php"><i class="fa-solid fa-up-right-from-square text-yellow"></i></a></div>
        </div>

        <div class="box-body" style="height:280px;">
            <div style="display: inline-flex; width: 49%;"><?=$pia_lang['REP_Title']?>:&nbsp;<strong id="reportsCount">0</strong></div>
            <div style="display: inline-flex; width: 49%;"><?=$pia_lang['Device_Shortcut_Archived']?>:&nbsp;<strong id="reportsArchiveCount">0</strong></div>
            <hr style="margin:10px 0;">
            <div id="latestReports" style="max-height:225px; overflow-y:auto;">
                <em>Loading reports…</em>
            </div>
        </div>

      </div>
    </div>

</div>

<div class="row">
    <div class="col-md-6">
      <div class="box box-solid">
        <div class="box-header with-border">
          <h3 class="box-title text-aqua"><i class="bi bi-calendar-event"></i> <?=$pia_lang['DASH_events_head']?></h3>
          <div class="box-tools pull-right"><a href="./devicesEvents.php"><i class="fa-solid fa-up-right-from-square text-yellow"></i></a></div>
        </div>

        <div class="box-body" style="padding:0;">
          <div style="height:350px; overflow-y:auto; overflow-x:hidden;">
            <table id="tableEvents" class="table table-striped table-hover table-condensed" style="width:100%; font-size: 12px;">
              <thead>
                <tr>
                  <th><?=$pia_lang['EVE_TableHead_Order'];?></th>
                  <th><?=$pia_lang['EVE_TableHead_Device'];?></th>
                  <th><?=$pia_lang['EVE_TableHead_Owner'];?></th>
                  <th><?=$pia_lang['EVE_TableHead_Date'];?></th>
                  <th><?=$pia_lang['EVE_TableHead_EventType'];?></th>
                  <th><?=$pia_lang['EVE_TableHead_Connection'];?></th>
                  <th><?=$pia_lang['EVE_TableHead_Disconnection'];?></th>
                  <th><?=$pia_lang['EVE_TableHead_Duration'];?></th>
                  <th><?=$pia_lang['EVE_TableHead_DurationOrder'];?></th>
                  <th><?=$pia_lang['EVE_TableHead_IP'];?></th>
                  <th><?=$pia_lang['EVE_TableHead_IPOrder'];?></th>
                  <th><?=$pia_lang['EVE_TableHead_AdditionalInfo'];?></th>
                </tr>
              </thead>
              <tbody>
                <!-- AJAX Content -->
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>

    <div class="col-md-6">
      <div class="box box-solid">
        <div class="box-header with-border">
          <h3 class="box-title text-aqua"><i class="bi bi-calendar-event"></i> <?=$pia_lang['Device_Shortcut_OnlineChart_a']?> 12 <?=$pia_lang['Device_Shortcut_OnlineChart_b'] ?></h3>
        </div>

        <div class="box-body" style="padding:0;">
            <div style="height:160px; width:100%;">
              <div id="historyChartsContainer"></div>
            </div>
        </div>

      </div>
    </div>

</div>

<!-- Modal -->
<div class="modal fade" id="logModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title" id="logModalTitle">Logfile</h4>
      </div>

      <div class="modal-body">
        <pre id="logContent" style="max-height:500px; overflow:auto; border: none;"></pre>
      </div>

      <div class="modal-footer">
        <button class="btn btn-default" onclick="navigateLog(1)">← <?=$pia_lang['Device_Table_nav_prev']?></button>
        <button class="btn btn-default" onclick="navigateLog(-1)"><?=$pia_lang['Device_Table_nav_next']?> →</button>
        <button class="btn btn-primary" data-dismiss="modal"><?=$pia_lang['Gen_Close']?></button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="reportModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title" id="reportModalTitle">Report</h4>
      </div>

      <div class="modal-body">
        <pre id="reportModalContent"
             style="max-height:500px; overflow:auto; white-space:pre-wrap; font-size:12px; border: none;">
Loading…
        </pre>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?=$pia_lang['Gen_Close']?></button>
      </div>

    </div>
  </div>
</div>

<script>
// --------------------------------------------------------------------------
let logfileDates   = [];
let currentIndex   = -1;
let currentLogfile = '';
var devicesDonutChart = null;
var devicesDonutIcmpChart = null;
var servicesStatusDonutChart = null;
let speedtestChart = null;
let currentDays = 7;
var eventsTable = null;
var historyStackedCharts = {};
var dashboardRefreshTimer   = null;
var dashboardCountdownTimer = null;
var DASHBOARD_REFRESH_INTERVAL = 120000; // 2 Minuten
var dashboardCountdownSeconds  = DASHBOARD_REFRESH_INTERVAL / 1000;
// --------------------------------------------------------------------------
$(document).ready(function () {
    loadSpeedtestChart(7);
    initializeDatatable();
    getEvents('all');
    getLocalDeviceStatus();
    getIcmpDeviceStatus();
    // startDashboardRefresh();
    getReportsCount();
    loadLatestReports();
    loadHistoryStackedChart('main_scan');
    loadHistoryStackedChart('icmp_scan');
    getReportTotalsBadge();
});
// --------------------------------------------------------------------------
$('#logfileSelect').on('change', function () {
    currentLogfile = $(this).val();
    logfileDates   = [];
    currentIndex   = -1;
    $('#dateSelect').html('<option value="">lade...</option>');
    if (!currentLogfile) {
        $('#dateSelect').html('<option value="">-- Datum wählen --</option>');
        return;
    }
    $.ajax({
        url: 'php/server/dashboard.php',
        type: 'GET',
        dataType: 'json',
        data: {
            action: 'getLogfileDatesAsJson',
            logfile: currentLogfile
        },
        success: function (data) {
            logfileDates = Array.isArray(data) ? data : [];
            let html = '<option value="">-- Datum wählen --</option>';
            logfileDates.forEach(function (date) {
                html += '<option value="' + date + '">' + date + '</option>';
            });

            $('#dateSelect').html(html);
        },
        error: function () {
            $('#dateSelect').html('<option value="">Fehler beim Laden</option>');
        }
    });
});

$(document).on('click', '.dropdown-menu.zoom-menu', function (e) {
  e.stopPropagation();
});
// --------------------------------------------------------------------------
function showLogModal()
{
    const date = $('#dateSelect').val();
    if (!currentLogfile || !date) {
        return;
    }
    currentIndex = logfileDates.indexOf(date);
    if (currentIndex === -1) {
        return;
    }
    loadLogfile(currentLogfile, date);
    $('#logModal').modal('show');
}
// --------------------------------------------------------------------------
function loadLogfile(logfile, date)
{
    $('#logContent').text('Lade Logfile...');

    $.ajax({
        url: 'php/server/dashboard.php',
        type: 'GET',
        dataType: 'text',
        data: {
            action: 'getLogfileContent',
            logfile: logfile,
            date: date
        },
        success: function (data) {
            $('#logModalTitle').text(logfile + ' – ' + date);
            $('#logContent').text(data);
            updateNavButtons();
        },
        error: function () {
            $('#logContent').text('Fehler beim Laden des Logfiles');
        }
    });
}
// --------------------------------------------------------------------------
function navigateLog(direction)
{
    const newIndex = currentIndex + direction;
    if (newIndex < 0 || newIndex >= logfileDates.length) {
        return;
    }
    currentIndex = newIndex;
    const date = logfileDates[currentIndex];
    $('#dateSelect').val(date);
    loadLogfile(currentLogfile, date);
}
// --------------------------------------------------------------------------
function updateNavButtons()
{
    $('.btn-prev').prop('disabled', currentIndex <= 0);
    $('.btn-next').prop('disabled', currentIndex >= logfileDates.length - 1);
}
// --------------------------------------------------------------------------
function loadSpeedtestChart(days)
{
    currentDays = days || currentDays;

    $('.btn-group button').removeClass('active');
    $('.btn-group button').each(function () {
        if ($(this).text().startsWith(currentDays.toString())) {
            $(this).addClass('active');
        }
    });
    $.ajax({
        url: 'php/server/dashboard.php',
        type: 'GET',
        dataType: 'json',
        data: {
            action: 'getSpeedtestHistory',
            days: currentDays
        },
        success: function (data) {
            renderSpeedtestChart(data);
        },
        error: function () {
            console.error('Failed to load speedtest data');
        }
    });
}
// --------------------------------------------------------------------------
function renderSpeedtestChart(data)
{
    const ctx = document.getElementById('speedtestChart').getContext('2d');

    if (speedtestChart) {
        speedtestChart.destroy();
    }

    speedtestChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Ping (ms)',
                    data: data.ping,
                    borderColor: '#3498db',   // blau
                    backgroundColor: 'rgba(52,152,219,0.15)',
                    fill: false,
                    borderWidth: 1,
                    pointRadius: 2,
                    tension: 0.2
                },
                {
                    label: 'Download (Mbps)',
                    data: data.down,
                    borderColor: '#2ecc71',   // grün
                    backgroundColor: 'rgba(46,204,113,0.15)',
                    fill: false,
                    borderWidth: 1,
                    pointRadius: 2,
                    tension: 0.2
                },
                {
                    label: 'Upload (Mbps)',
                    data: data.up,
                    borderColor: '#e74c3c',   // rot
                    backgroundColor: 'rgba(231,76,60,0.15)',
                    fill: false,
                    borderWidth: 1,
                    pointRadius: 2,
                    tension: 0.2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true
                }
            },
            scales: {
                xAxes: [{
                    ticks: {
                        callback: function (value) {
                            if (typeof value !== 'string') {
                                return value;
                            }

                            const parts = value.split(' ');
                            if (parts.length !== 2) {
                                return value;
                            }

                            const dateParts = parts[0].split('-');
                            if (dateParts.length !== 3) {
                                return value;
                            }

                            const month = dateParts[1];
                            const day   = dateParts[2];
                            const time = parts[1].substring(0, 5); // HH:MM

                            return [month + '.' + day, time];
                        }
                    }
                }],
                yAxes: [{
                    ticks: {
                        beginAtZero: true,
                        maxTicksLimit: 4
                    }
                }]
            }
        }
    });
}
// --------------------------------------------------------------------------
function initializeDatatable () {

  eventsTable = $('#tableEvents').DataTable({
    paging        : false,
    searching     : false,
    info          : false,
    lengthChange  : false,

    ordering      : true,
    order         : [[0, "desc"], [3, "desc"], [5, "desc"]],

    scrollY       : '310',
    scrollX       : true,
    scrollCollapse: true,

    autoWidth     : false,

    pageLength    : 50,

    columnDefs: [
      { visible: false, targets: [0,2,5,6,7,8,10,11] },
      {targets: [1],
        "createdCell": function (td, cellData, rowData, row, col) {
          if (rowData[13]) {
              $(td).html('<b><a href="deviceDetails.php?mac=' + rowData[13] + '" class="">' + cellData + '</a></b>');
          } else {
              
              if (String(cellData).endsWith("**")) {
                  const mainText = String(cellData).slice(0, -2);

                  $(td).html(
                      '<b><a href="icmpmonitorDetails.php?hostip=' + rowData[9] + '" class="">' +
                      mainText +
                      '<span class="text-warning">**</span>' +
                      '</a></b>'
                  );
              } else {
                  // default
                  $(td).html('<b><a href="icmpmonitorDetails.php?hostip=' + rowData[9] + '" class="">' + cellData + '</a></b>');
              }
          }
      } },
      {
        targets: [3,4,5,6,7],
        createdCell: function (td, cellData) {
          $(td).html(translateHTMLcodes(cellData));
        }
      }
    ],

    processing : true,
    language: {
      processing : '<i class="ion ion-ios-sync fa-spin fa-2x"></i>',
      emptyTable : 'No data'
    }
  });
}
// --------------------------------------------------------------------------
function getEvents () {

  const table = $('#tableEvents').DataTable();

  table.clear();
  table.order([[0, "desc"], [3, "desc"], [5, "desc"]]);

  table.ajax
    .url('php/server/events.php?action=getEvents&type=all&period=1 day')
    .load();
}
// --------------------------------------------------------------------------
function translateHTMLcodes(text)
{
    if (typeof text !== 'string') {
        return text;
    }

    return text
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&')
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'");
}
// --------------------------------------------------------------------------
function getLocalDeviceStatus() {
    $.ajax({
        url: 'php/server/dashboard.php',
        type: 'GET',
        dataType: 'json',
        data: {
            action: 'getLocalDeviceStatus'
        },
        success: function (data) {
            if (!data || typeof data.online === 'undefined') {
                return;
            }
            renderDevicesDonut({
                online:   data.online,
                offline:  data.offline,
                archived: data.archived
            }, data.total);
        }
    });
}
// --------------------------------------------------------------------------
function renderDevicesDonut(values, total) {
    const ctx = document.getElementById('devicesDonut').getContext('2d');
    if (devicesDonutChart) {
        devicesDonutChart.destroy();
    }

    devicesDonutChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [
                'Online',
                'Offline',
                'Archived'
            ],
            datasets: [{
                data: [
                    values.online,
                    values.offline,
                    values.archived
                ],
                backgroundColor: [
                    '#2ecc71', // Online
                    '#e74c3c', // Offline
                    '#95a5a6'  // Archived
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutoutPercentage: 60,
            centerText: {
                textTop: total.toLocaleString(),
                textBottom: 'Devices',
                fontSize: 22,
                color: '#888'
            },

            legend: {
                position: 'bottom',
                labels: {
                    fontSize: 12
                }
            }
        }
    });
}
// --------------------------------------------------------------------------
Chart.plugins.register({
  beforeDraw: function (chart) {

    if (!chart.config.options.centerText) {
      return;
    }

    const ctx = chart.chart.ctx;
    const centerConfig = chart.config.options.centerText;
    const txtTop    = centerConfig.textTop || '';
    const txtBottom = centerConfig.textBottom || '';
    const fontSize  = centerConfig.fontSize || 18;
    const fontColor = centerConfig.color || '#888';

    ctx.save();
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = fontColor;

    const centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
    const centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2;

    // obere Zeile (groß)
    ctx.font = 'bold ' + fontSize + 'px Arial';
    ctx.fillText(txtTop, centerX, centerY - 8);

    // untere Zeile (klein)
    ctx.font = 'normal ' + Math.round(fontSize * 0.6) + 'px Arial';
    ctx.fillText(txtBottom, centerX, centerY + 12);

    ctx.restore();
  }
});
// --------------------------------------------------------------------
function getIcmpDeviceStatus() {

    $.ajax({
        url: 'php/server/dashboard.php',
        type: 'GET',
        dataType: 'json',
        data: {
            action: 'getIcmpDeviceStatus'
        },
        success: function (data) {
            if (!data || typeof data.online === 'undefined') {
                return;
            }
            renderIcmpDevicesDonut({
                online:   data.online,
                offline:  data.offline,
                archived: data.archived
            }, data.total);
        }
    });
}
// --------------------------------------------------------------------
function renderIcmpDevicesDonut(values, total) {
    const ctx = document.getElementById('devicesDonutIcmp').getContext('2d');
    if (devicesDonutIcmpChart) {
        devicesDonutIcmpChart.destroy();
    }

    devicesDonutIcmpChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [
                'Online',
                'Offline',
                'Archived'
            ],
            datasets: [{
                data: [
                    values.online,
                    values.offline,
                    values.archived
                ],
                backgroundColor: [
                    '#2ecc71', // Online
                    '#e74c3c', // Offline
                    '#95a5a6'  // Archived
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutoutPercentage: 60,

            centerText: {
                textTop: total.toLocaleString(),
                textBottom: 'ICMP Devices',
                fontSize: 22,
                color: '#888'
            },

            legend: {
                position: 'bottom',
                labels: {
                    fontSize: 12
                }
            }
        }
    });
}
// --------------------------------------------------------------------------
function getReportsCount() {

    $.ajax({
        url: 'php/server/dashboard.php',
        type: 'GET',
        dataType: 'json',
        data: {
            action: 'getReportsCount'
        },
        success: function (data) {

            if (!data) {
                return;
            }
            $('#reportsCount').text(data.reports);
            $('#reportsArchiveCount').text(data.archive);
        },
        error: function () {
            console.error('Failed to load report counts');
        }
    });
}
// --------------------------------------------------------------------
function loadLatestReports() {

    $.ajax({
        url: 'php/server/dashboard.php',
        type: 'GET',
        dataType: 'json',
        data: {
            action: 'getLatestReports'
        },
        success: function (data) {
            if (!data || data.length === 0) {
                $('#latestReports').html('<em>No reports found</em>');
                return;
            }
            let html = '<ul class="list-unstyled" style="margin-bottom:0;">';
            data.forEach(function (item) {
                var displayName = formatReportFilename(item.name);

                html +=
                    '<li style="display:flex; justify-content:space-between; align-items:center;">' +
                        '<a href="#" onclick="showReportModal(\'' + item.name + '\');return false;">' +
                            displayName +
                        '</a>' +
                        '<small class="text-muted">' + item.time + '</small>' +
                    '</li>' +
                    '<hr style="margin:6px 0;">';
            });
            html += '</ul>';
            $('#latestReports').html(html);
        }
    });
}
// --------------------------------------------------------------------
function showReportModal(filename) {
    $('#reportModalTitle').text(filename);
    $('#reportModalContent').text('Loading…');
    $('#reportModal').modal('show');
    $.ajax({
        url: 'php/server/dashboard.php',
        type: 'GET',
        dataType: 'text',
        data: {
            action: 'getReportContent',
            file: filename
        },
        success: function (data) {
            $('#reportModalContent').text(data);
        },
        error: function () {
            $('#reportModalContent').text('Failed to load report');
        }
    });
}
// --------------------------------------------------------------------------
function formatReportFilename(filename) {
    // Entfernt führenden Zeitstempel: YYYYMMDD-HHMMSS_
    return filename.replace(/^[0-9]{8}-[0-9]{6}_/, '');
}
// --------------------------------------------------------------------------
function refreshEventsTable() {
    if (!eventsTable) return;
    if (document.visibilityState !== 'visible') return;

    eventsTable.ajax.reload(null, false);
}
// --------------------------------------------------------------------------
function refreshDashboardData() {
    getLocalDeviceStatus();
    getIcmpDeviceStatus();
    getReportsCount();
    loadLatestReports();
    refreshEventsTable();
    loadHistoryStackedChart('main_scan');
    loadHistoryStackedChart('icmp_scan');
    loadServicesStatusDonut();
    getReportTotalsBadge();
}
// --------------------------------------------------------------------------
function startDashboardRefresh() {
    if (dashboardRefreshTimer !== null) {
        return;
    }
    refreshDashboardData();
    startDashboardCountdown();
    dashboardRefreshTimer = setInterval(function () {
        refreshDashboardData();
        startDashboardCountdown();
    }, DASHBOARD_REFRESH_INTERVAL);
}
// --------------------------------------------------------------------------
function stopDashboardRefresh() {
    if (dashboardRefreshTimer !== null) {
        clearInterval(dashboardRefreshTimer);
        dashboardRefreshTimer = null;
    }
    stopDashboardCountdown();
}
// --------------------------------------------------------------------------
function stopDashboardCountdown() {
    if (dashboardCountdownTimer !== null) {
        clearInterval(dashboardCountdownTimer);
        dashboardCountdownTimer = null;
    }
}
// --------------------------------------------------------------------------
function startDashboardCountdown() {
    stopDashboardCountdown();
    dashboardCountdownSeconds = DASHBOARD_REFRESH_INTERVAL / 1000;
    $('#dashboardRefreshCountdownValue').text(dashboardCountdownSeconds);

    dashboardCountdownTimer = setInterval(function () {
        dashboardCountdownSeconds--;
        if (dashboardCountdownSeconds <= 0) {
            stopDashboardCountdown();
            return;
        }
        $('#dashboardRefreshCountdownValue').text(dashboardCountdownSeconds);
    }, 1000);
}
// --------------------------------------------------------------------------
var historyDataSourceLabels = {
    'main_scan' : 'Main Scan',
    'icmp_scan' : 'ICMP Scan'
};

function getHistoryDataSourceLabel(dataSource) {
    return historyDataSourceLabels[dataSource] || dataSource;
}

function loadHistoryStackedChart(dataSource) {
    if (!dataSource) {
        dataSource = 'main_scan';
    }
    var chartId = 'historyChart_' + dataSource;
    // Canvas existiert noch nicht → erzeugen
    if (!document.getElementById(chartId)) {

        var html =
            '<div class="history-chart-wrapper" style="height:160px; margin-bottom:20px;">' +
                '<h5 style="margin-bottom:8px;"><?=$pia_lang['DASH_charts_history']?>: ' + getHistoryDataSourceLabel(dataSource) + '</h5>' +
                '<canvas id="' + chartId + '"></canvas>' +
            '</div>';

        $('#historyChartsContainer').append(html);
    }

    $.getJSON(
        'php/server/dashboard.php',
        {
            action: 'getDeviceHistoryChart',
            source: dataSource
        },
        function (chartData) {

            var ctx = document
                .getElementById(chartId)
                .getContext('2d');

            // vorhandenen Chart für diese Quelle zerstören
            if (historyStackedCharts[dataSource]) {
                historyStackedCharts[dataSource].destroy();
            }

            historyStackedCharts[dataSource] = new Chart(ctx, {
                type: 'bar',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        xAxes: [{ 
                            stacked: true,
                        }],
                        yAxes: [{
                            stacked: true,
                            ticks: { 
                                beginAtZero: true,
                                stepSize: 1,
                            }
                        }]
                    },
                    legend: {
                        position: 'bottom'
                    },
                    tooltips: {
                        mode: 'index',
                        intersect: false
                    }
                }
            });
        }
    );
}
// --------------------------------------------------------------------------
function loadServicesStatusDonut() {

    $.getJSON(
        'php/server/dashboard.php',
        { action: 'getServiceStatusSummary' },
        function (resp) {

            if (!resp || !resp.labels || !resp.data) {
                return;
            }

            var ctx = document
                .getElementById('servicesStatusDonut')
                .getContext('2d');

            if (servicesStatusDonutChart) {
                servicesStatusDonutChart.destroy();
            }

            var serviceStatusColors = {
                'Offline': '#e74c3c',
                '1xx':     '#3498db',
                '2xx':     '#2ecc71',
                '3xx':     '#f1c40f',
                '4xx':     '#e67e22',
                '5xx':     '#ff4c3c',
                'Other':   '#7f8c8d'
            };

            var bgColors = resp.labels.map(function (lbl) {
                return serviceStatusColors[lbl] || '#7f8c8d';
            });

            var total = resp.data.reduce(function (sum, value) {
                return sum + value;
            }, 0);

            servicesStatusDonutChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: resp.labels,
                    datasets: [{
                        data: resp.data,
                        backgroundColor: bgColors,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutoutPercentage: 60,
                    centerText: {
                        textTop: total.toLocaleString(),
                        textBottom: 'Services',
                        fontSize: 22,
                        color: '#888'
                    },
                    legend: {
                        position: 'bottom',
                        labels: {
                            fontSize: 12
                        }
                    },
                }
            });

        }
    );
}
// --------------------------------------------------------------------------
function getReportTotalsBadge() {
  // get totals and put in boxes
  $.get('php/server/files.php?action=getReportTotals', function(data) {
    var totalsReportbadge = JSON.parse(data);
    var unsetbadge = "";
    if (totalsReportbadge[0] > 0) {
      $('#Menu_Report_Counter_Badge').html(totalsReportbadge[0].toLocaleString());
      $('#Menu_Report_Envelope_Icon' ).addClass("text-red");
    } else {
      $('#Menu_Report_Counter_Badge').html(unsetbadge.toLocaleString());
      $('#Menu_Report_Envelope_Icon' ).removeClass("text-red");
    }
    document.title = document.title.replace(/\(\d*\)/, `(${totalsReportbadge[0].toLocaleString()})`);
  });
}
// --------------------------------------------------------------------------
let zoomLevel = 100;

function applyZoom() {
  document.body.style.zoom = zoomLevel + '%';
  document.getElementById('zoom-percent').innerText = zoomLevel + '%';
}

function zoomIn() {
  if (zoomLevel < 150) {
    zoomLevel += 10;
    applyZoom();
  }
}
function zoomReset() {
  zoomLevel = 100;
  applyZoom();
}
function zoomOut() {
  if (zoomLevel > 50) {
    zoomLevel -= 10;
    applyZoom();
  }
}
// --------------------------------------------------------------------------
document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') {
        stopDashboardRefresh();
        return;
    }
    if (document.visibilityState === 'visible') {
        refreshDashboardData();   // EINMAL sofort
        startDashboardRefresh();  // Timer + Countdown neu starten
    }
});
// --------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {
    refreshDashboardData();
    startDashboardRefresh();
});

</script>

</body>
</html>

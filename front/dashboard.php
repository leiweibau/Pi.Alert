<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
define('PIALERT_V4_PUBLIC_ENTRY', true);
require_once __DIR__ . '/php/bootstrap.php';
pialert_v4_start_session();
if (($_SESSION['login'] ?? 0) != 1) {
    header('Location: ' . pialert_v4_route('login'));
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Method Not Allowed');
}
pialert_v4_load_language();
require_once __DIR__ . '/php/shell.php';
$L = static fn(string $key, string $fallback): string => (string) ($pia_lang[$key] ?? $fallback);
$title = $L('DASH_Title', 'Dashboard');
$config = array('historyTitle'=>$L('DASH_charts_history','History'),'days'=>$L('DASH_days','days'), 'labels'=>array(
    'online'=>$pia_lang['V4_Online'], 'offline'=>$pia_lang['V4_Offline'], 'otherStatus'=>$pia_lang['V4_Other_Status'],
    'archived'=>$L('Device_Shortcut_Archived','Archived'), 'devices'=>$L('NAV_Devices','Devices'),
    'icmpDevices'=>$L('NAV_ICMPScan','ICMP Devices'), 'services'=>$L('NAV_Services','Services'),
    'mainScan'=>$pia_lang['V4_Main_Scan'], 'icmpScan'=>$pia_lang['V4_ICMP_Scan'],
    'ping'=>$pia_lang['V4_Ping'], 'download'=>$pia_lang['V4_Download'], 'upload'=>$pia_lang['V4_Upload'],
    'loading'=>$pia_lang['V4_Loading'], 'loadingReports'=>$pia_lang['V4_Loading_Reports'],
    'loadingLog'=>$pia_lang['V4_Loading_Log'], 'selectDate'=>$pia_lang['V4_Select_Date'],
    'reportError'=>$pia_lang['V4_Report_Load_Failed'], 'logError'=>$pia_lang['V4_Log_Load_Failed'],
    'noReports'=>$pia_lang['V4_No_Reports'], 'noData'=>$pia_lang['V4_No_Data']));
pialert_v4_shell_start($title, 'dashboard', array('lib/datatables/datatables.net-bs5-2.3.8/css/dataTables.bootstrap5.min.css','css/dashboard.css'));
?>
<script type="application/json" id="dashboard-page-config"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<section id="dashboard-page" aria-label="<?= h($title); ?>">
  <h1 class="visually-hidden"><?= h($title); ?></h1>
  <div class="row g-3 mb-3">
    <div class="col-12 col-lg-9"><section class="card h-100"><div class="card-header"><h2 class="card-title"><i class="bi bi-speedometer2 me-2" aria-hidden="true"></i><?= h($L('ookla_devdetails_tab_title','Speedtest')); ?></h2></div><div class="card-body"><div class="dashboard-speedtest-chart"><canvas id="speedtestChart"></canvas></div><div class="btn-group mt-3" role="group" aria-label="<?= h($pia_lang['V4_Speedtest_Time_Range']); ?>"><?php foreach (array(7,14,21) as $days): ?><button class="btn btn-outline-primary dashboard-speedtest-range<?= $days === 7 ? ' active' : ''; ?>" type="button" data-days="<?= $days; ?>" aria-pressed="<?= $days === 7 ? 'true' : 'false'; ?>"><?= $days; ?> <?= h($config['days']); ?></button><?php endforeach; ?></div></div></section></div>
    <div class="col-12 col-lg-3"><section class="card h-100"><div class="card-header"><h2 class="card-title"><i class="bi bi-journal-text me-2" aria-hidden="true"></i><?= h($pia_lang['V4_Logs']); ?></h2></div><div class="card-body"><label class="form-label" for="logfileSelect"><?= h($pia_lang['V4_Logfile']); ?></label><select id="logfileSelect" class="form-select mb-3"><option value=""><?= h($L('DASH_select_log','Select log')); ?></option><?php foreach (array('pialert.1.log'=>'MT_Tools_Logviewer_Scan','pialert.IP.log'=>'MT_Tools_Logviewer_IPLog','pialert.cleanup.log'=>'MT_Tools_Logviewer_Cleanup','pialert.vendors.log'=>'MT_Tools_Logviewer_Vendor','pialert.webservices.log'=>'MT_Tools_Logviewer_WebServices','pialert.speedtest.log'=>null,'pialert.nmap.log'=>'MT_Tools_Logviewer_Nmap') as $file=>$key): ?><option value="<?= h($file); ?>"><?= h($key ? $L($key,$file) : $pia_lang['V4_Speedtest_Cron']); ?></option><?php endforeach; ?></select><label class="form-label" for="dateSelect"><?= h($L('EVE_TableHead_Date','Date')); ?></label><select id="dateSelect" class="form-select mb-3"><option value=""><?= h($L('DASH_select_date','Select date')); ?></option></select><button id="showLog" class="btn btn-primary" type="button"><?= h(ucfirst($L('Gen_show','Show'))); ?></button></div></section></div>
  </div>
  <div class="row g-3 mb-3">
    <?php foreach (array(
      array('devicesDonut','NAV_Devices','Devices','devices.php'),
      array('devicesDonutIcmp','NAV_ICMPScan','ICMP Monitoring','icmpmonitor.php'),
      array('servicesStatusDonut','NAV_Services','Services','services.php'),
    ) as [$id,$key,$fallback,$route]): ?><div class="col-12 col-sm-6 col-xl-3"><section class="card h-100"><div class="card-header d-flex align-items-center gap-2"><h2 class="card-title"><i class="bi bi-pie-chart me-2" aria-hidden="true"></i><?= h($L($key,$fallback)); ?></h2><a href="<?= h($route); ?>" aria-label="<?= h($L($key,$fallback)); ?>"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i></a></div><div class="card-body"><div class="dashboard-donut-chart"><canvas id="<?= h($id); ?>"></canvas></div></div></section></div><?php endforeach; ?>
    <div class="col-12 col-sm-6 col-xl-3"><section class="card h-100"><div class="card-header"><h2 class="card-title"><i class="bi bi-journal-text me-2" aria-hidden="true"></i><?= h($L('DASH_reports_head','Reports')); ?></h2></div><div class="card-body"><div class="d-flex justify-content-between gap-2"><span><?= h($L('REP_Title','Reports')); ?>: <strong id="reportsCount">0</strong></span><span><?= h($L('Device_Shortcut_Archived','Archived')); ?>: <strong id="reportsArchiveCount">0</strong></span></div><hr><div id="latestReports" class="dashboard-report-list"><em><?= h($pia_lang['V4_Loading_Reports']); ?></em></div></div></section></div>
  </div>
  <div class="row g-3">
    <div class="col-12 col-xl-6"><section class="card h-100"><div class="card-header"><h2 class="card-title"><i class="bi bi-calendar-event me-2" aria-hidden="true"></i><?= h($L('Device_Shortcut_OnlineChart_a','Network activity over the last ') . '12 ' . $L('Device_Shortcut_OnlineChart_b','hours')); ?></h2></div><div class="card-body" id="historyChartsContainer"></div></section></div>
    <div class="col-12 col-xl-6"><section class="card h-100"><div class="card-header d-flex align-items-center gap-2"><h2 class="card-title"><i class="bi bi-calendar-event me-2" aria-hidden="true"></i><?= h($L('DASH_events_head','Recent events')); ?></h2><a href="devicesEvents.php" aria-label="<?= h($L('DASH_events_head','Recent events')); ?>"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i></a></div><div class="card-body"><div class="table-responsive"><table id="tableEvents" class="table table-striped table-hover table-sm align-middle w-100"><thead><tr><?php foreach (array('EVE_TableHead_Order','EVE_TableHead_Device','EVE_TableHead_Owner','EVE_TableHead_Date','EVE_TableHead_EventType','EVE_TableHead_Connection','EVE_TableHead_Disconnection','EVE_TableHead_Duration','EVE_TableHead_DurationOrder','EVE_TableHead_IP','EVE_TableHead_IPOrder','EVE_TableHead_AdditionalInfo') as $key): ?><th><?= h($L($key,$key)); ?></th><?php endforeach; ?></tr></thead><tbody></tbody></table></div></div></section></div>
  </div>
  <div class="modal fade" id="logModal" tabindex="-1" aria-labelledby="logModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="logModalTitle"><?= h($pia_lang['V4_Logfile']); ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h($pia_lang['Gen_Close']); ?>"></button></div><div class="modal-body"><pre id="logContent" class="dashboard-modal-content"></pre></div><div class="modal-footer"><button class="btn btn-outline-secondary btn-prev" type="button" data-log-direction="1">← <?= h($L('Device_Table_nav_prev','Previous')); ?></button><button class="btn btn-outline-secondary btn-next" type="button" data-log-direction="-1"><?= h($L('Device_Table_nav_next','Next')); ?> →</button><button class="btn btn-primary" type="button" data-bs-dismiss="modal"><?= h($L('Gen_Close','Close')); ?></button></div></div></div></div>
  <div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="reportModalTitle"><?= h($pia_lang['V4_Report']); ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h($pia_lang['Gen_Close']); ?>"></button></div><div class="modal-body"><pre id="reportModalContent" class="dashboard-modal-content"><?= h($pia_lang['V4_Loading']); ?></pre></div><div class="modal-footer"><button class="btn btn-primary" type="button" data-bs-dismiss="modal"><?= h($L('Gen_Close','Close')); ?></button></div></div></div></div>
</section>
<?php pialert_v4_shell_end(array('lib/datatables/datatables.net-2.3.8/dataTables.min.js','lib/datatables/datatables.net-bs5-2.3.8/js/dataTables.bootstrap5.min.js','lib/chart.js-4.5.1/chart.umd.js','js/dashboard.js')); ?>

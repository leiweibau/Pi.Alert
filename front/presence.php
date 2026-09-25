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
require_once __DIR__ . '/php/server/db.php';
require_once __DIR__ . '/php/server/graph.php';
require_once __DIR__ . '/php/server/journal.php';

$requestedSource = $_GET['scansource'] ?? 'local';
$SCANSOURCE = is_string($requestedSource) && preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $requestedSource) ? $requestedSource : 'local';
$sourceLabel = pialert_v4_satellite_name($SCANSOURCE);
$DBFILE = '../db/pialert.db';
OpenDB();
$uiSettings = pialert_v4_ui_read();
$historyEnabled = $uiSettings['appearance']['activity_history'];
$history = $historyEnabled ? prepare_graph_arrays_history($SCANSOURCE) : array(array(), array(), array(), array(), array());
$showHistory = $historyEnabled && !empty($history[0]);
$widgets = $uiSettings['appearance']['header_widgets']['presence'];
$widgetColumnClass = pialert_v4_header_widget_column_class($widgets);
$L = static fn(string $key, string $fallback): string => (string) ($pia_lang[$key] ?? $fallback);
$widgetDefs = array(
    array('all','devicesAll','PRE_Shortcut_AllDevices','All devices','primary','fa-solid fa-laptop'),
    array('con','devicesConnected','PRE_Shortcut_Connected','Connected','success','mdi mdi-lan-connect'),
    array('fav','devicesFavorites','PRE_Shortcut_Favorites','Favorites','warning','fa-solid fa-star'),
    array('new','devicesNew','PRE_Shortcut_NewDevices','New devices','warning','fa-solid fa-plus'),
    array('dnw','devicesDown','PRE_Shortcut_DownAlerts','Down alerts','danger','mdi mdi-lan-disconnect'),
    array('arc','devicesHidden','PRE_Shortcut_Archived','Archived','secondary','fa-solid fa-eye-slash'),
);
$config = array(
    'scanSource'=>$SCANSOURCE,
    'history'=>array('time'=>array_reverse($history[0]),'online'=>array_reverse($history[3]),'down'=>array_reverse($history[1]),'archived'=>array_reverse($history[4])),
    'labels'=>array(
        'all'=>$L('PRE_Shortcut_AllDevices','All devices'), 'connected'=>$L('PRE_Shortcut_Connected','Connected'),
        'favorites'=>$L('PRE_Shortcut_Favorites','Favorites'), 'new'=>$L('PRE_Shortcut_NewDevices','New devices'),
        'down'=>$L('PRE_Shortcut_DownAlerts','Down alerts'), 'archived'=>$L('PRE_Shortcut_Archived','Archived'),
        'resource'=>$L('PRE_CallHead_Devices','Devices'), 'locale'=>$L('PRE_CalHead_lang','en'),
        'year'=>$L('PRE_CalHead_year','Year'), 'quarter'=>$L('PRE_CalHead_quarter','Quarter'),
        'month'=>$L('PRE_CalHead_month','Month'), 'week'=>$L('PRE_CalHead_week','Week'), 'day'=>$L('PRE_CalHead_day','Day'),
    ),
);
$title = $L('PRE_Title','Presence') . ' / ' . $sourceLabel;
pialert_v4_shell_start($title, 'presence', array(
    'lib/legacy-calendar/fullcalendar-3.10.5/fullcalendar.min.css',
    'lib/legacy-calendar/fullcalendar-scheduler-3.10.4/scheduler.min.css',
    'css/presence.css',
));
?>
<script type="application/json" id="presence-page-config"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<section id="presence-page" aria-label="<?= h($title); ?>">
  <div class="row g-3 mb-4">
  <?php foreach ($widgetDefs as [$key,$id,$labelKey,$fallback,$tone,$icon]): if (empty($widgets[$key])) continue; $status = array('con'=>'connected','fav'=>'favorites','dnw'=>'down','arc'=>'archived')[$key] ?? $key; ?>
    <div class="<?= h($widgetColumnClass); ?>"><button class="small-box text-bg-<?= h($tone); ?> presence-filter w-100 border-0 text-start" type="button" data-presence-status="<?= h($status); ?>" aria-pressed="false"><div class="inner"><h2 id="<?= h($id); ?>" class="mb-1">--</h2><p class="mb-0"><?= h($L($labelKey,$fallback)); ?></p></div><i class="small-box-icon <?= h($icon); ?>" aria-hidden="true"></i></button></div>
  <?php endforeach; ?>
  </div>
  <?php if ($showHistory): ?><section class="card mb-4"><div class="card-header"><h2 class="card-title"><?= h($L('Device_Shortcut_OnlineChart_a','Network activity over the last ') . '12 ' . $L('Device_Shortcut_OnlineChart_b','hours')); ?></h2></div><div class="card-body"><div class="presence-history-chart"><canvas id="OnlineChart"></canvas></div></div></section><?php endif; ?>
  <section class="card card-primary card-outline" id="tableDevicesBox" aria-labelledby="tableDevicesTitle"><div class="card-header"><h2 class="card-title" id="tableDevicesTitle"><?= h($config['labels']['all']); ?></h2></div><div class="card-body position-relative"><div id="loading" class="presence-loading" hidden role="status"><?= h($pia_lang['V4_Loading']); ?></div><div id="calendar"></div></div></section>
</section>
<?php pialert_v4_shell_end(array(
    'lib/legacy-calendar/moment-2.24.0/moment.js',
    'lib/legacy-calendar/fullcalendar-3.10.5/fullcalendar.min.js',
    'lib/legacy-calendar/fullcalendar-3.10.5/locale-all.js',
    'lib/legacy-calendar/fullcalendar-scheduler-3.10.4/scheduler.min.js',
    'lib/chart.js-4.5.1/chart.umd.js',
    'js/presence.js',
)); ?>

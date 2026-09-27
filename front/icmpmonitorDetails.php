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
$requestHost = isset($_GET['hostip']) && is_scalar($_GET['hostip']) ? (string) $_GET['hostip'] : '';
if (!filter_var($requestHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !filter_var($requestHost, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
    header('Location: ' . pialert_v4_route('icmp'));
    exit;
}
$hostip = $requestHost;

pialert_v4_load_language();
require_once __DIR__ . '/php/shell.php';
require_once __DIR__ . '/php/server/db.php';
require_once __DIR__ . '/php/entity-actions-editor.php';
require_once __DIR__ . '/php/server/journal.php';

$db_file = '../db/pialert.db';
$db = new SQLite3($db_file);
$db->exec('PRAGMA journal_mode = wal;');

function get_hostip_details($hostip) {
    global $db;
    $mon_res = db_execute_prepared($db, 'SELECT * FROM ICMP_Mon WHERE icmp_ip = :ip', array(':ip' => (string) $hostip));
    return $mon_res ? $mon_res->fetchArray() : false;
}

function get_icmphost_events_table($icmp_ip, $icmpfilter) {
    global $db;
    $icmp_hostname = '';
    $icmp_res = db_execute_prepared($db, 'SELECT rowid, * FROM ICMP_Mon WHERE icmp_ip = :ip', array(':ip' => (string) $icmp_ip));
    while ($rowa = $icmp_res->fetchArray(SQLITE3_ASSOC)) $icmp_hostname = $rowa['icmp_hostname'];
    $icmpeve_res = db_execute_prepared($db, 'SELECT * FROM ICMP_Mon_Connections
        WHERE icmpeve_ip = :ip AND datetime(icmpeve_DateTime) >= :cutoff
        ORDER BY datetime(icmpeve_DateTime) DESC, rowid DESC',
        array(':ip' => (string) $icmp_ip, ':cutoff' => date('Y-m-d H:i:s', time() - 7 * 86400)));
    while ($row = $icmpeve_res->fetchArray()) {
        if ($icmp_hostname != '' && strlen($icmp_hostname) > 0) $icmpeve_ip = $icmp_hostname;
        else $icmpeve_ip = $row['icmpeve_ip'];
        echo '<tr><td>' . h($icmpeve_ip) . '</td><td>' . h($row['icmpeve_DateTime']) . '</td><td>' . h($row['icmpeve_EventType']) . '</td></tr>';
    }
}

function get_host_statistic($hostip) {
    global $db;
    $params = array(':ip' => (string) $hostip);
    $scalarQueries = array(
        'avg_rtt_all' => 'SELECT AVG(icmpeve_avgrtt) FROM ICMP_Mon_Events WHERE icmpeve_avgrtt != 99999 AND icmpeve_avgrtt != "" AND icmpeve_ip = :ip',
        'rtt_max_all' => 'SELECT MAX(icmpeve_avgrtt) FROM ICMP_Mon_Events WHERE icmpeve_avgrtt != 99999 AND icmpeve_avgrtt != "" AND icmpeve_ip = :ip',
        'rtt_min_all' => 'SELECT MIN(icmpeve_avgrtt) FROM ICMP_Mon_Events WHERE icmpeve_avgrtt != 99999 AND icmpeve_avgrtt != "" AND icmpeve_ip = :ip',
        'offline_all' => 'SELECT COUNT(*) FROM ICMP_Mon_Events WHERE icmpeve_Present = 0 AND icmpeve_ip = :ip',
        'online_all' => 'SELECT COUNT(*) FROM ICMP_Mon_Events WHERE icmpeve_Present = 1 AND icmpeve_ip = :ip',
    );
    $values = array();
    foreach ($scalarQueries as $key => $sql) {
        $result = db_execute_prepared($db, $sql, $params);
        $row = $result ? $result->fetchArray(SQLITE3_NUM) : array(0);
        $values[$key] = $row[0];
    }
    $statistic = array();
    $statistic['avg_rtt_all'] = round($values['avg_rtt_all'], 3) . ' ms';
    $statistic['rtt_max_all'] = '<i class="bi bi-speedometer2 flip-horizontal text-danger" aria-hidden="true"></i> ' . round($values['rtt_max_all'], 3) . ' ms';
    $statistic['rtt_min_all'] = '<i class="bi bi-speedometer2 text-success" aria-hidden="true"></i> ' . round($values['rtt_min_all'], 3) . ' ms';
    $statistic['offline_all'] = (int) $values['offline_all'];
    $statistic['online_all'] = (int) $values['online_all'];
    $total = $statistic['online_all'] + $statistic['offline_all'];
    $onlinePercent = $statistic['online_all'] > 0 ? round(($statistic['online_all'] * 100 / $total), 2) : 0;
    $statistic['online_percent_all'] = $onlinePercent . ' %';
    $statistic['offline_percent_all'] = (100 - $onlinePercent) . ' %';
    $windows = array('24h' => 24 - (date('Z') / 3600), '1w' => 168 - (date('Z') / 3600));
    foreach ($windows as $label => $hours) {
        $result = db_execute_prepared($db, 'SELECT * FROM ICMP_Mon_Events
            WHERE icmpeve_ip = :ip AND datetime(icmpeve_DateTime) >= datetime("now", :offset)
            ORDER BY datetime(icmpeve_DateTime) DESC', array(':ip' => (string) $hostip, ':offset' => '-' . $hours . ' hours'));
        $offline = 0; $online = 0; $minimum = 99999; $maximum = 0; $average = 0;
        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            if ($row['icmpeve_avgrtt'] != '' && $row['icmpeve_avgrtt'] != '99999') {
                $online++; $maximum = max($maximum, $row['icmpeve_avgrtt']);
                $minimum = min($minimum, $row['icmpeve_avgrtt']); $average += $row['icmpeve_avgrtt'];
            } else $offline++;
        }
        $statistic['rtt_min_' . $label] = $minimum == 99999 ? 'n.a.' : '<i class="bi bi-speedometer2 text-success" aria-hidden="true"></i> ' . round($minimum, 3) . ' ms';
        $statistic['rtt_max_' . $label] = $maximum == 0 ? 'n.a.' : '<i class="bi bi-speedometer2 flip-horizontal text-danger" aria-hidden="true"></i> ' . round($maximum, 3) . ' ms';
        $statistic['rtt_avg_' . $label] = $average > 0 ? round(($average / $online), 3) . ' ms' : 'n.a.';
        $statistic['online_' . $label] = $online; $statistic['offline_' . $label] = $offline;
        $total = $online + $offline;
        $onlinePercent = $online > 0 ? round(($online * 100 / $total), 2) : 0;
        $statistic['online_percent_' . $label] = $onlinePercent . ' %';
        $statistic['offline_percent_' . $label] = round(100 - $onlinePercent, 2) . ' %';
    }
    return $statistic;
}

$details = get_hostip_details($hostip);
if (!$details) {
    http_response_code(404);
    exit('ICMP host not found');
}
$hostNavigation = array();
$hostNavigationResult = $db->query("SELECT icmp_ip FROM ICMP_Mon ORDER BY COALESCE(NULLIF(icmp_hostname, ''), icmp_ip) COLLATE NOCASE ASC, icmp_ip COLLATE NOCASE ASC");
while ($hostNavigationResult && ($hostNavigationRow = $hostNavigationResult->fetchArray(SQLITE3_ASSOC))) {
    $navigationIp = (string) ($hostNavigationRow['icmp_ip'] ?? '');
    if ($navigationIp !== '') $hostNavigation[] = $navigationIp;
}
$statistic = get_host_statistic($hostip);
$L = static fn(string $key, string $fallback): string => (string) ($pia_lang[$key] ?? $fallback);
$title = ($details['icmp_hostname'] ?: $hostip) . ' (' . $hostip . ')';
$icmpfilter = $_GET['icmpfilter'] ?? '';
$fieldGroups = array(
    array(
        array('txtIP','ICMPMonitor_label_IP','IP','icmp_ip',true),
        array('txtHostname','ICMPMonitor_label_Hostname','Hostname','icmp_hostname',false),
        array('txtOwner','DevDetail_MainInfo_Owner','Owner','icmp_owner',false),
        array('txtDeviceType','DevDetail_MainInfo_Type','Type','icmp_type',false),
        array('txtVendor','DevDetail_MainInfo_Vendor','Vendor','icmp_vendor',false),
        array('txtModel','DevDetail_MainInfo_Model','Model','icmp_model',false),
        array('txtSerialnumber','DevDetail_MainInfo_Serialnumber','Serial number','icmp_serial',false),
        array('txtGroup','DevDetail_MainInfo_Group','Group','icmp_group',false),
        array('txtLocation','DevDetail_MainInfo_Location','Location','icmp_location',false),
        array('txtNotes','WEBS_label_Notes','Notes','icmp_Notes',false),
    ),
    array(
        array('txtLastScan','WEBS_label_ScanTime','Last scan','icmp_LastScan',true),
        array('txtavgrtt','ICMPMonitor_label_RTT','RTT','icmp_avgrtt',true),
        array('txtScanValidation','DevDetail_EveandAl_ScanValid','Scan validation','icmp_Scan_Validation',false),
    ),
);
$checkboxes = array(
    array('chkAlertEvents','WEBS_label_AlertEvents','Alert events','icmp_AlertEvents'),
    array('chkAlertDown','WEBS_label_AlertDown','Alert down','icmp_AlertDown'),
    array('chkFavorit','Device_TableHead_Favorite','Favorite','icmp_Favorite'),
    array('chkMQTTDevice','DevDetail_MainInfo_MQTTDevice','MQTT device','icmp_MQTTDevice'),
    array('chkArchived','DevDetail_EveandAl_Archived','Archived','icmp_Archived'),
);
pialert_v4_shell_start($title, 'icmp', array('lib/datatables/datatables.net-bs5-3.1.2/css/dataTables.bootstrap5.min.css','lib/coloris-0.25.0/coloris.min.css','css/icmp-details.css','css/presence-calendar.css','css/nmap-results.css','css/entity-actions.css'));
?>
<section id="icmp-details-page" data-host-ip="<?= h($hostip); ?>" data-endpoint="php/server/icmpmonitor.php" data-back-url="<?= h(pialert_v4_route('icmp')); ?>" data-filter-events="<?= $icmpfilter !== '' ? '1' : '0'; ?>" data-delete-title="<?= h($L('WEBS_button_Delete_label','Delete host')); ?>" data-delete-message="<?= h($L('WEBS_button_Delete_Warning','Delete this host?')); ?>" data-cancel="<?= h($L('Gen_Cancel','Cancel')); ?>" data-delete="<?= h($L('Gen_Delete','Delete')); ?>" data-close="<?= h($L('Gen_Close','Close')); ?>" data-reset="<?= h($L('DevDetail_button_Reset','Reset')); ?>" data-fast="<?= h($L('DevDetail_Tools_nmap_buttonFast','Fast scan')); ?>" data-normal="<?= h($L('DevDetail_Tools_nmap_buttonDefault','Normal scan')); ?>" data-nmap-loading="<?= h($L('nmap_results_loading','Loading scan results…')); ?>" data-nmap-error="<?= h($L('nmap_results_request_error','The scan request failed.')); ?>" data-length-menu="<?= h($L('EVE_Tablelenght','Show _MENU_ entries')); ?>" data-search="<?= h($L('EVE_Searchbox','Search')); ?>" data-next="<?= h($L('EVE_Table_nav_next','Next')); ?>" data-previous="<?= h($L('EVE_Table_nav_prev','Previous')); ?>" data-info="<?= h($L('EVE_Table_info','Showing _START_ to _END_ of _TOTAL_ entries')); ?>">
  <script id="icmp-host-navigation-data" type="application/json"><?= json_encode($hostNavigation, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
  <script id="icmp-calendar-config" type="application/json"><?= json_encode(array(
      'locale'=>$L('PRE_CalHead_lang','en'),
      'month'=>$L('PRE_CalHead_month','Month'),
      'week'=>$L('PRE_CalHead_week','Week'),
      'day'=>$L('PRE_CalHead_day','Day'),
  ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
  <div class="d-flex justify-content-start mb-3"><a class="btn btn-outline-secondary pialert-back-link" href="<?= h(pialert_v4_route('icmp')); ?>"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i><?= h($L('Device_Table_nav_prev','Back')); ?></a></div>
  <div class="row g-3 mb-4">
    <?php foreach (array(
        array('deviceStatus',$details['icmp_PresentLastScan'] == 1 ? $L('ICMPMonitor_Shortcut_Online','Online') : $L('ICMPMonitor_Shortcut_Offline','Offline'),$L('DevDetail_Shortcut_CurrentStatus','Current status'),'primary','fa-solid fa-signal'),
        array('eventspresence','--',$L('DevDetail_Shortcut_curPresence','Presence'),'warning','bi bi-check2-square'),
        array('eventsdown','--',$L('DevDetail_Shortcut_DownAlerts','Down alerts'),'danger','mdi mdi-lan-disconnect'),
    ) as [$id,$value,$label,$tone,$icon]): ?>
    <div class="col-6 col-lg"><div class="small-box text-bg-<?= h($tone); ?>"><div class="inner"><h2 id="<?= h($id); ?>" class="mb-1"><?= h($value); ?></h2><p class="mb-0"><?= h($label); ?></p></div><i class="small-box-icon <?= h($icon); ?>" aria-hidden="true"></i></div></div>
    <?php endforeach; ?>
  </div>
  <section class="card"><div class="card-header p-0"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 pt-3"><ul class="nav nav-tabs card-header-tabs" id="icmpDetailsTabs" role="tablist">
    <?php foreach (array(array('Details','panDetails','DevDetail_Tab_Details','Details'),array('Actions','panActions','EntityActions_Tab','Actions'),array('Nmap','panNmap','DevDetail_Tab_Nmap','Nmap'),array('Events','panEvents','DevDetail_Tab_Events','Events'),array('Presence','panPresence','DevDetail_Tab_Presence','Presence'),array('Graph','panGraph','WEBS_Tab_Graph','Graph')) as [$tab,$panel,$key,$fallback]): ?><li class="nav-item" role="presentation"><button class="nav-link<?= $tab === 'Details' ? ' active' : ''; ?>" id="tab<?= h($tab); ?>" data-bs-toggle="tab" data-bs-target="#<?= h($panel); ?>" type="button" role="tab" aria-controls="<?= h($panel); ?>" aria-selected="<?= $tab === 'Details' ? 'true' : 'false'; ?>"><?= h($L($key,$fallback)); ?></button></li><?php endforeach; ?>
  </ul><div class="btn-group mb-2" aria-label="<?= h($L('ICMPMonitor_Title','ICMP host')); ?>"><button type="button" class="btn btn-outline-secondary" id="btnPrevious" aria-label="<?= h($L('EVE_Table_nav_prev','Previous')); ?>" title="<?= h($L('EVE_Table_nav_prev','Previous')); ?>"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button><span class="btn btn-outline-secondary disabled" id="txtRecord" aria-live="polite">0 / 0</span><button type="button" class="btn btn-outline-secondary" id="btnNext" aria-label="<?= h($L('EVE_Table_nav_next','Next')); ?>" title="<?= h($L('EVE_Table_nav_next','Next')); ?>"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button></div></div></div><div class="card-body"><div class="tab-content">
    <div class="tab-pane fade show active" id="panDetails" role="tabpanel" aria-labelledby="tabDetails"><div class="row g-4">
      <div class="col-12 col-lg-6"><h2 class="h5 border-bottom pb-2"><?= h($L('DevDetail_MainInfo_Title','Main information')); ?></h2>
        <?php foreach ($fieldGroups[0] as [$id,$key,$fallback,$column,$readonly]): ?><div class="icmp-detail-field mb-3"><label class="form-label" for="<?= h($id); ?>"><?= h(pialert_v4_ui_plain_label($L($key,$fallback))); ?></label><?php if ($id === 'txtIP'): ?><div class="input-group"><input class="form-control" id="txtIP" value="<?= h($details[$column] ?? ''); ?>" readonly><button id="copyIP" class="btn btn-outline-secondary" type="button" data-copy-target="txtIP" aria-label="<?= h($pia_lang['V4_Copy_IP']); ?>"><i class="bi bi-clipboard" aria-hidden="true"></i></button></div><?php else: ?><input class="form-control" id="<?= h($id); ?>" value="<?= h($details[$column] ?? ''); ?>"<?= $readonly ? ' readonly' : ''; ?><?= in_array($id,array('txtOwner','txtDeviceType','txtGroup','txtLocation'),true) ? ' list="suggest-'.$id.'"' : ''; ?>><?php if (in_array($id,array('txtOwner','txtDeviceType','txtGroup','txtLocation'),true)): ?><datalist id="suggest-<?= h($id); ?>"></datalist><?php endif; ?><?php endif; ?></div><?php endforeach; ?>
      </div><div class="col-12 col-lg-6"><h2 class="h5 border-bottom pb-2"><?= h($L('DevDetail_EveandAl_Title','Events and alerts')); ?></h2>
        <?php foreach ($fieldGroups[1] as [$id,$key,$fallback,$column,$readonly]): ?><div class="icmp-detail-field mb-3"><label class="form-label" for="<?= h($id); ?>"><?= h($L($key,$fallback)); ?></label><input class="form-control" id="<?= h($id); ?>" value="<?= h($details[$column] ?? ''); ?>"<?= $readonly ? ' readonly' : ''; ?>></div><?php endforeach; ?>
        <?php foreach ($checkboxes as [$id,$key,$fallback,$column]): ?><div class="icmp-detail-field icmp-detail-switch mb-3"><div class="form-check form-switch m-0 ps-0"><input class="form-check-input ms-0 float-none<?= match ($id) { 'chkFavorit' => ' pialert-favorite-switch', 'chkMQTTDevice' => ' pialert-purple-switch', 'chkAlertDown' => ' pialert-down-switch', 'chkArchived' => ' pialert-archive-switch', default => '' }; ?>" type="checkbox" role="switch" id="<?= h($id); ?>"<?= ($details[$column] ?? 0) == 1 ? ' checked' : ''; ?>></div><label class="form-check-label" for="<?= h($id); ?>"><?= h($L($key,$fallback)); ?></label></div><?php endforeach; ?>
      </div>
    </div><div class="d-flex flex-wrap justify-content-end gap-2 mt-4"><button class="btn btn-danger" id="btnDelete" type="button"><?= h($L('Gen_Delete','Delete')); ?></button><button class="btn btn-secondary" id="btnRestore" type="button"><?= h($L('Gen_Close','Close')); ?></button><button class="btn btn-primary" id="btnSave" type="button" disabled><?= h($L('Gen_Save','Save')); ?></button></div></div>
    <div class="tab-pane fade" id="panActions" role="tabpanel" aria-labelledby="tabActions"><?php pialert_entity_actions_editor($L); ?></div>
    <div class="tab-pane fade" id="panNmap" role="tabpanel" aria-labelledby="tabNmap"><div class="row g-3"><div class="col-12 col-lg-6"><section class="card h-100 pialert-tool-card"><div class="card-header"><h2 class="card-title"><?= h($pia_lang['V4_Nmap_Scans']); ?></h2></div><div class="card-body"><div class="pialert-tool-button-row"><button class="btn btn-outline-primary" id="manualnmap_fast" type="button"></button><button class="btn btn-outline-primary" id="manualnmap_normal" type="button"></button></div><div id="nmapstatus" class="pialert-nmap-state" role="status" aria-live="polite"></div></div></section></div></div><div id="scanoutput" class="mt-3"></div></div>
    <div class="tab-pane fade" id="panEvents" role="tabpanel" aria-labelledby="tabEvents"><h2 class="h5 mb-3"><?= h($L('ICMPMonitor_Events_Last7Days','Events from the last 7 days')); ?></h2><div class="table-responsive"><table id="tableEvents" class="table table-bordered table-hover table-striped align-middle w-100"><thead><tr><th><?= h($L('WEBS_tablehead_TargetIP','Host')); ?></th><th><?= h($L('WEBS_tablehead_ScanTime','Time')); ?></th><th><?= h($pia_lang['EVE_TableHead_EventType']); ?></th></tr></thead><tbody><?php get_icmphost_events_table($hostip,$icmpfilter); ?></tbody></table></div></div>
    <div class="tab-pane fade" id="panPresence" role="tabpanel" aria-labelledby="tabPresence"><div id="icmp-calendar" class="presence-calendar" aria-label="<?= h($L('DevDetail_Tab_Presence','Presence')); ?>"></div></div>
    <div class="tab-pane fade" id="panGraph" role="tabpanel" aria-labelledby="tabGraph"><h2 class="h5 mb-3"><?= h($L('ICMPMonitor_Availability','Availability')); ?> · 24 <?= h($L('WEBS_Chart_b','hours')); ?></h2><div id="icmp-timeline" class="presence-calendar icmp-detail-timeline" aria-label="<?= h($L('ICMPMonitor_Availability','Availability')); ?>"></div><div class="icmp-timeline-legend small mt-2 mb-4" aria-label="<?= h($L('V4_Status','Status')); ?>"><span><i class="icmp-timeline-swatch icmp-timeline-online" aria-hidden="true"></i><?= h($L('ICMPMonitor_Shortcut_Online','Online')); ?></span><span><i class="icmp-timeline-swatch icmp-timeline-offline" aria-hidden="true"></i><?= h($L('ICMPMonitor_Shortcut_Offline','Offline')); ?></span></div>
      <div class="row g-3 mt-2">
        <div class="col-12 col-lg-6"><section class="card h-100 icmp-statistics-card"><div class="card-header"><h3 class="card-title"><?= h($L('WEBS_Stats_Time','Response times')); ?></h3></div><div class="card-body table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col"></th><th scope="col">&Oslash;</th><th scope="col"><?= h($L('V4_Min','Min')); ?></th><th scope="col"><?= h($L('V4_Max','Max')); ?></th></tr></thead><tbody>
          <?php foreach (array('24h'=>'24h','1w'=>'7d','all'=>$L('V4_All','All')) as $suffix=>$period): ?><tr><th scope="row"><?= h($period); ?></th><td><?= h($statistic[$suffix === 'all' ? 'avg_rtt_all' : 'rtt_avg_'.$suffix]); ?></td><td><?= $statistic[$suffix === 'all' ? 'rtt_min_all' : 'rtt_min_'.$suffix]; ?></td><td><?= $statistic[$suffix === 'all' ? 'rtt_max_all' : 'rtt_max_'.$suffix]; ?></td></tr><?php endforeach; ?></tbody></table></div></section></div>
        <div class="col-12 col-lg-6"><section class="card h-100"><div class="card-header"><h3 class="card-title"><?= h($L('ICMPMonitor_Availability','Availability')); ?></h3></div><div class="card-body table-responsive"><table class="table table-sm mb-0 pialert-availability-table"><thead><tr><th scope="col"></th><th scope="col"><?= h($L('ICMPMonitor_Shortcut_Online','Online')); ?></th><th scope="col"><?= h($L('ICMPMonitor_Shortcut_Offline','Offline')); ?></th></tr></thead><tbody>
          <?php foreach (array('24h'=>'24h','1w'=>'7d','all'=>$L('V4_All','All')) as $suffix=>$period): ?><tr><th scope="row"><?= h($period); ?></th><td class="text-success"><?= h($statistic['online_percent_'.$suffix]); ?></td><td class="text-danger"><?= h($statistic['offline_percent_'.$suffix]); ?></td></tr><?php endforeach; ?></tbody></table></div></section></div>
      </div>
    </div>
  </div></div></section>
</section>
<?php pialert_v4_shell_end(array('lib/datatables/datatables.net-3.1.2/dataTables.min.js','lib/datatables/datatables.net-bs5-3.1.2/js/dataTables.bootstrap5.min.js','lib/fullcalendar-scheduler-6.1.21/index.global.min.js','lib/fullcalendar-6.1.21/locales-all.global.min.js','lib/coloris-0.25.0/coloris.min.js','js/nmap-results.js','js/entity-actions-renderer.js','js/entity-actions-editor.js','js/icmp-details.js')); ?>

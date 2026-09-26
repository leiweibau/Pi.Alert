<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('PIALERT_V4_PUBLIC_ENTRY', true);
require_once __DIR__ . '/php/bootstrap.php';
pialert_v4_start_session();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Method Not Allowed');
}
if (($_SESSION['login'] ?? 0) != 1) {
    header('Location: ' . pialert_v4_route('login'));
    exit;
}

require_once __DIR__ . '/php/server/service_url.php';
$request_url = isset($_GET['url']) && is_scalar($_GET['url']) ? (string) $_GET['url'] : '';
if (!pialert_validate_service_key($request_url)) {
    header('Location: ' . pialert_v4_route('login'));
    exit;
}
$service_details_title = $request_url;
$service_details_title_array = explode('://', $request_url);

pialert_v4_load_language();
require_once __DIR__ . '/php/shell.php';
require_once __DIR__ . '/php/server/db.php';
require_once __DIR__ . '/php/server/graph.php';
require_once __DIR__ . '/php/server/journal.php';
require_once __DIR__ . '/php/server/geodb_location.php';

$db_file = '../db/pialert.db';
$db = new SQLite3($db_file);
$db->exec('PRAGMA journal_mode = wal;');

function get_service_details($service_URL) {
    global $db;
    $mon_res = db_execute_prepared($db, 'SELECT * FROM Services WHERE mon_URL = :url', array(':url' => (string) $service_URL));
    return $mon_res ? $mon_res->fetchArray() : false;
}

function localize_service_note($note) {
    global $pia_lang;
    $noteMap = array(
        'Invalid service URL' => 'WEBS_Note_InvalidURL',
        'DNS resolution failed' => 'WEBS_Note_DNSFailed',
        'Connection failed' => 'WEBS_Note_ConnectionFailed',
        'TLS connection failed' => 'WEBS_Note_TLSFailed',
        'Blocked by network policy' => 'WEBS_Note_Blocked',
        'Redirect loop detected' => 'WEBS_Note_RedirectLoop',
        'Redirect limit exceeded' => 'WEBS_Note_RedirectLimit',
        'Service check failed' => 'WEBS_Note_CheckFailed',
    );
    if (isset($noteMap[$note], $pia_lang[$noteMap[$note]])) return $pia_lang[$noteMap[$note]];
    if (preg_match('/^Redirected by ([1-5][0-9]{2}) \(([0-9]+) redirects?\)$/D', $note, $matches) && isset($pia_lang['WEBS_Note_Redirected'])) {
        return sprintf($pia_lang['WEBS_Note_Redirected'], $matches[1], (int) $matches[2]);
    }
    return $note;
}

function pialert_v4_geolite_credits_html(string $credits): string {
    $escaped = h($credits);
    // Only the two documented attribution links may become HTML; other markup stays escaped.
    return preg_replace_callback(
        '~&lt;a href=&quot;(https://github\.com/P3TERX/GeoLite\.mmdb|https://dev\.maxmind\.com/geoip/geolite2-free-geolocation-data)&quot; target=&quot;_blank&quot;&gt;(.*?)&lt;/a&gt;~',
        static fn(array $link): string => '<a href="' . $link[1] . '" target="_blank" rel="noopener noreferrer">' . $link[2] . '</a>',
        $escaped
    ) ?? $escaped;
}

$http_filter = $_GET['filter'] ?? 'all';
if (!in_array((string) $http_filter, array('all', '2', '3', '4', '5', '99999999'), true)) $http_filter = 'all';

function get_service_events_table($service_URL, $service_filter) {
    global $db, $current_service_IP;
    if ($service_filter == 'all') $filter_sql = '';
    elseif ($service_filter == 2) $filter_sql = 'AND moneve_StatusCode LIKE "2%"';
    elseif ($service_filter == 3) $filter_sql = 'AND moneve_StatusCode LIKE "3%"';
    elseif ($service_filter == 4) $filter_sql = 'AND moneve_StatusCode LIKE "4%"';
    elseif ($service_filter == 5) $filter_sql = 'AND moneve_StatusCode LIKE "5%"';
    elseif ($service_filter == '99999999') $filter_sql = 'AND moneve_Latency="99999999"';
    else $filter_sql = '';
    $moneve_res = db_execute_prepared($db, 'SELECT * FROM Services_Events WHERE moneve_URL = :url ' . $filter_sql . ' ORDER BY rowid DESC LIMIT 2000', array(':url' => (string) $service_URL));
    while ($moneve_res && ($row = $moneve_res->fetchArray())) {
        if ($row['moneve_TargetIP'] == '') $func_TargetIP = 'n.a.';
        else { $func_TargetIP = $row['moneve_TargetIP']; $current_service_IP = $row['moneve_TargetIP']; }
        echo '<tr><td>' . h($func_TargetIP) . '</td><td>' . h($row['moneve_DateTime']) . '</td><td>' . h($row['moneve_StatusCode']) . '</td><td>' . h($row['moneve_Latency']) . '</td><td>' . h($row['moneve_ssl_fc']) . '</td></tr>';
    }
}

function service_filter_label($service_filter) {
    global $pia_lang;
    if ($service_filter == 2) return $pia_lang['WEBS_EVE_Shortcut_HTTP2xx'];
    if ($service_filter == 3) return $pia_lang['WEBS_EVE_Shortcut_HTTP3xx'];
    if ($service_filter == 4) return $pia_lang['WEBS_EVE_Shortcut_HTTP4xx'];
    if ($service_filter == 5) return $pia_lang['WEBS_EVE_Shortcut_HTTP5xx'];
    if ($service_filter == '99999999') return $pia_lang['WEBS_EVE_Shortcut_Down'];
    return $pia_lang['WEBS_EVE_Shortcut_All'];
}

function get_service_statistic($service) {
    global $db;
    $params = array(':service' => (string) $service);
    $scalarQueries = array(
        'latency_avg' => 'SELECT AVG(moneve_Latency) FROM Services_Events WHERE moneve_Latency != 99999999 AND moneve_Latency IS NOT NULL AND moneve_URL = :service',
        'latency_max' => 'SELECT MAX(moneve_Latency) FROM Services_Events WHERE moneve_Latency != 99999999 AND moneve_Latency IS NOT NULL AND moneve_URL = :service',
        'latency_min' => 'SELECT MIN(moneve_Latency) FROM Services_Events WHERE moneve_Latency != 99999999 AND moneve_Latency IS NOT NULL AND moneve_URL = :service',
        'offline' => 'SELECT COUNT(*) FROM Services_Events WHERE moneve_Latency = 99999999 AND moneve_URL = :service',
        'online' => 'SELECT COUNT(*) FROM Services_Events WHERE moneve_Latency != 99999999 AND moneve_URL = :service',
    );
    $values = array();
    foreach ($scalarQueries as $key => $sql) {
        $result = db_execute_prepared($db, $sql, $params);
        $row = $result ? $result->fetchArray(SQLITE3_NUM) : array(0);
        $values[$key] = $row[0];
    }
    $statistic = array();
    $statistic['latency_avg'] = round($values['latency_avg'], 4) . ' ms';
    $statistic['latency_max'] = '<i class="bi bi-speedometer2 flip-horizontal text-danger"></i> ' . round($values['latency_max'], 4) . ' ms';
    $statistic['latency_min'] = '<i class="bi bi-speedometer2 text-success"></i> ' . round($values['latency_min'], 4) . ' ms';
    $statistic['offline'] = (int) $values['offline'];
    $statistic['online'] = (int) $values['online'];
    $total = $statistic['online'] + $statistic['offline'];
    $onlinePercent = $total > 0 && $statistic['online'] > 0 ? round(($statistic['online'] * 100 / $total), 2) : 0;
    $statistic['online_percent_all'] = $onlinePercent . ' %';
    $statistic['offline_percent_all'] = round(100 - $onlinePercent, 2) . ' %';
    $windows = array('24h' => 24 - (date('Z') / 3600), '1w' => 168 - (date('Z') / 3600));
    foreach ($windows as $label => $hours) {
        $result = db_execute_prepared($db, 'SELECT * FROM Services_Events
            WHERE moneve_URL = :service AND datetime(moneve_DateTime) >= datetime("now", :offset)
            ORDER BY datetime(moneve_DateTime) DESC', array(':service' => (string) $service, ':offset' => '-' . $hours . ' hours'));
        $offline = 0; $online = 0; $minimum = 99999999; $maximum = 0; $average = 0;
        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            if ($row['moneve_Latency'] != '' && $row['moneve_Latency'] != '99999999') {
                $online++; $maximum = max($maximum, $row['moneve_Latency']); $minimum = min($minimum, $row['moneve_Latency']); $average += $row['moneve_Latency'];
            } else $offline++;
        }
        $statistic['latency_min_' . $label] = $minimum == 99999999 ? 'n.a.' : '<i class="bi bi-speedometer2 text-success"></i> ' . round($minimum, 4) . ' ms';
        $statistic['latency_max_' . $label] = $maximum == 0 ? 'n.a.' : '<i class="bi bi-speedometer2 flip-horizontal text-danger"></i> ' . round($maximum, 4) . ' ms';
        $statistic['latency_avg_' . $label] = $average > 0 ? round(($average / $online), 4) . ' ms' : 'n.a.';
        $statistic['online_' . $label] = $online; $statistic['offline_' . $label] = $offline;
        $total = $online + $offline;
        $onlinePercent = $total > 0 && $online > 0 ? round(($online * 100 / $total), 2) : 0;
        $statistic['online_percent_' . $label] = $onlinePercent . ' %';
        $statistic['offline_percent_' . $label] = round(100 - $onlinePercent, 2) . ' %';
    }
    return $statistic;
}

$servicedetails = get_service_details($service_details_title);
$service_note_display = localize_service_note((string) ($servicedetails['mon_Notes'] ?? ''));
$graph_arrays = prepare_graph_arrays_webservice($service_details_title);
$statistic = get_service_statistic($service_details_title);
$devices = array();
$dev_res = $db->query('SELECT dev_MAC, dev_Name FROM Devices ORDER BY dev_Name ASC');
while ($dev_res && ($row = $dev_res->fetchArray())) $devices[] = $row;
$geoDatabase = dirname(__DIR__) . '/db/GeoLite2-Country.mmdb';
$geoDatabaseInstalled = is_file($geoDatabase);
$selectedLanguage = pathinfo(pialert_v4_language_file(), PATHINFO_FILENAME);
$location = $geoDatabaseInstalled
    ? pialert_geodb_service_location($geoDatabase, (string) ($servicedetails['mon_TargetIP'] ?? ''), $selectedLanguage)
    : array('country' => null, 'continent' => null);
$locationLabel = $location['country'] ?? 'IP not found in DB';
if ($location['country'] !== null && $location['continent'] !== null) $locationLabel .= ' (' . $location['continent'] . ')';
$displayTitle = '[' . strtoupper($service_details_title_array[0]) . '] ' . ($service_details_title_array[1] ?? '');

pialert_v4_shell_start($displayTitle, 'services', array(
    'lib/datatables/datatables.net-bs5-2.3.8/css/dataTables.bootstrap5.min.css',
    'css/service-details.css',
));
?>
<section id="service-details-page" data-service-url="<?= h($service_details_title); ?>" data-filter="<?= h((string) $http_filter); ?>"
  data-endpoint="php/server/services.php" data-delete-title="<?= h($pia_lang['WEBS_button_Delete_label']); ?>"
  data-delete-message="<?= h($pia_lang['WEBS_button_Delete_Warning']); ?>" data-cancel="<?= h($pia_lang['Gen_Cancel']); ?>" data-delete="<?= h($pia_lang['Gen_Delete']); ?>"
  data-length-menu="<?= h($pia_lang['EVE_Tablelenght']); ?>" data-search="<?= h($pia_lang['EVE_Searchbox']); ?>" data-next="<?= h($pia_lang['EVE_Table_nav_next']); ?>" data-previous="<?= h($pia_lang['EVE_Table_nav_prev']); ?>" data-info="<?= h($pia_lang['EVE_Table_info']); ?>">
  <div class="d-flex justify-content-start mb-3"><a class="btn btn-outline-secondary pialert-back-link" href="./services.php"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i><?= h($pia_lang['Device_Table_nav_prev']); ?></a></div>
  <div class="row g-3 mb-4" aria-label="<?= h($pia_lang['V4_Service_Event_Totals']); ?>">
  <?php foreach (array(
      array('all','eventsAll',$pia_lang['WEBS_EVE_Shortcut_All'],'primary','fa-solid fa-bolt'),
      array('2','events2xx',$pia_lang['WEBS_EVE_Shortcut_HTTP2xx'],'success','bi bi-check2-square'),
      array('3','events3xx',$pia_lang['WEBS_EVE_Shortcut_HTTP3xx'],'warning','bi bi-sign-turn-right'),
      array('4','events4xx',$pia_lang['WEBS_EVE_Shortcut_HTTP4xx'],'warning','bi bi-exclamation-square'),
      array('5','events5xx',$pia_lang['WEBS_EVE_Shortcut_HTTP5xx'],'warning','bi bi-database-x'),
      array('99999999','eventsDown',$pia_lang['WEBS_EVE_Shortcut_Down'],'danger','bi bi-exclamation-diamond-fill')) as [$filter,$id,$label,$tone,$icon]): ?>
    <div class="col-6 col-md-4 col-xl-2"><a class="small-box text-bg-<?= h($tone); ?> service-event-filter text-decoration-none" href="./serviceDetails.php?url=<?= rawurlencode($service_details_title); ?>&amp;filter=<?= h($filter); ?>">
      <div class="inner"><h2 id="<?= h($id); ?>" class="mb-1">--</h2><p class="mb-0"><?= h($label); ?></p></div><i class="small-box-icon <?= h($icon); ?>" aria-hidden="true"></i>
    </a></div>
  <?php endforeach; ?>
  </div>

  <div class="card card-primary card-outline">
    <div class="card-header p-0"><ul class="nav nav-tabs" id="serviceDetailsTabs" role="tablist">
      <li class="nav-item"><button class="nav-link active" id="tabDetails" data-bs-toggle="tab" data-bs-target="#panDetails" type="button" role="tab"><?= h($pia_lang['DevDetail_Tab_Details']); ?></button></li>
      <li class="nav-item"><button class="nav-link" id="tabEvents" data-bs-toggle="tab" data-bs-target="#panEvents" type="button" role="tab"><?= h($pia_lang['DevDetail_Tab_Events']); ?></button></li>
      <li class="nav-item"><button class="nav-link" id="tabGraph" data-bs-toggle="tab" data-bs-target="#panGraph" type="button" role="tab"><?= h($pia_lang['WEBS_Tab_Graph']); ?></button></li>
    </ul></div>
    <div class="card-body tab-content">
      <div class="tab-pane fade show active" id="panDetails" role="tabpanel" aria-labelledby="tabDetails">
        <div class="row g-4">
          <div class="col-12 col-lg-6"><h2 class="h5 border-bottom border-primary pb-2"><?= h($pia_lang['DevDetail_MainInfo_Title']); ?></h2>
            <div class="service-fields">
              <label for="txtURL"><?= h($pia_lang['WEBS_label_URL']); ?></label><input class="form-control" id="txtURL" readonly value="<?= h($servicedetails['mon_URL'] ?? ''); ?>">
              <label for="txtTags"><?= h($pia_lang['WEBS_label_Tags']); ?></label><input class="form-control" id="txtTags" value="<?= h($servicedetails['mon_Tags'] ?? ''); ?>">
              <label for="txtMAC"><?= h($pia_lang['WEBS_label_MAC']); ?></label><div class="input-group"><input class="form-control" id="txtMAC" list="service-device-options" value="<?= h($servicedetails['mon_MAC'] ?? ''); ?>"><datalist id="service-device-options"><?php foreach ($devices as $device): ?><option value="<?= h($device['dev_MAC']); ?>"><?= h($device['dev_Name']); ?></option><?php endforeach; ?></datalist></div>
              <label for="txtNotes"><?= h($pia_lang['WEBS_label_Notes']); ?></label><input class="form-control" id="txtNotes" readonly value="<?= h($service_note_display); ?>">
            </div>
          </div>
          <div class="col-12 col-lg-6"><h2 class="h5 border-bottom border-primary pb-2"><?= h($pia_lang['DevDetail_EveandAl_Title']); ?></h2>
            <div class="service-fields">
              <label for="txtLastStatus"><?= h($pia_lang['WEBS_label_StatusCode']); ?></label><input class="form-control" id="txtLastStatus" readonly value="<?= h($servicedetails['mon_LastStatus'] ?? ''); ?>">
              <label for="txtSSLStatus"><?= h($pia_lang['V4_SSL_Status']); ?></label><input class="form-control" id="txtSSLStatus" readonly value="<?= h($servicedetails['mon_ssl_fc'] ?? ''); ?>">
              <label for="txtLastIP"><?= h($pia_lang['WEBS_label_TargetIP']); ?></label><input class="form-control" id="txtLastIP" readonly value="<?= h($servicedetails['mon_TargetIP'] ?? ''); ?>">
              <label for="txtLastScan"><?= h($pia_lang['WEBS_label_ScanTime']); ?></label><input class="form-control" id="txtLastScan" readonly value="<?= h($servicedetails['mon_LastScan'] ?? ''); ?>">
              <label for="txtLastLatency"><?= h($pia_lang['WEBS_label_Response_Time']); ?></label><input class="form-control" id="txtLastLatency" readonly value="<?= h($servicedetails['mon_LastLatency'] ?? ''); ?>">
            </div>
            <div class="d-flex flex-wrap gap-4 mt-3">
              <div class="form-check form-switch"><input class="form-check-input" id="chkAlertEvents" type="checkbox" <?= ($servicedetails['mon_AlertEvents'] ?? 0) == 1 ? 'checked' : ''; ?>><label class="form-check-label" for="chkAlertEvents"><?= h($pia_lang['WEBS_label_AlertEvents']); ?></label></div>
              <div class="form-check form-switch"><input class="form-check-input" id="chkAlertDown" type="checkbox" <?= ($servicedetails['mon_AlertDown'] ?? 0) == 1 ? 'checked' : ''; ?>><label class="form-check-label" for="chkAlertDown"><?= h($pia_lang['WEBS_label_AlertDown']); ?></label></div>
              <div class="form-check form-switch"><input class="form-check-input" id="chkAlertUp" type="checkbox" <?= ($servicedetails['mon_AlertUp'] ?? 0) == 1 ? 'checked' : ''; ?>><label class="form-check-label" for="chkAlertUp"><?= h($pia_lang['WEBS_label_AlertUp']); ?></label></div>
            </div>
          </div>
        </div>
        <section class="mt-4"><h2 class="h5 border-bottom border-primary pb-2"><?= h($pia_lang['V4_SSL_Certificate_Info']); ?></h2><div class="service-fields service-fields-wide">
          <label for="txtSSLSubject"><?= h($pia_lang['V4_Subject']); ?></label><input class="form-control" id="txtSSLSubject" readonly value="<?= h(str_replace('<Name(', '', str_replace(')>', '', (string) ($servicedetails['mon_ssl_subject'] ?? '')))); ?>">
          <label for="txtSSLIssuer"><?= h($pia_lang['V4_Issuer']); ?></label><input class="form-control" id="txtSSLIssuer" readonly value="<?= h(str_replace('<Name(', '', str_replace(')>', '', (string) ($servicedetails['mon_ssl_issuer'] ?? '')))); ?>">
          <label for="txtSSLFrom"><?= h($pia_lang['V4_Valid_From']); ?></label><input class="form-control" id="txtSSLFrom" readonly value="<?= h($servicedetails['mon_ssl_valid_from'] ?? ''); ?>">
          <label for="txtSSLTo"><?= h($pia_lang['V4_Valid_To']); ?></label><input class="form-control" id="txtSSLTo" readonly value="<?= h($servicedetails['mon_ssl_valid_to'] ?? ''); ?>">
        </div></section>
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4"><button class="btn btn-danger" id="btnDelete" type="button"><?= h($pia_lang['Gen_Delete']); ?></button><button class="btn btn-secondary" id="btnRestore" type="button"><?= h($pia_lang['Gen_Cancel']); ?></button><button class="btn btn-primary" id="btnSave" type="button"><?= h($pia_lang['Gen_Save']); ?></button></div>
      </div>

      <div class="tab-pane fade" id="panEvents" role="tabpanel" aria-labelledby="tabEvents"><h2 class="h5 text-primary mb-3" id="service-events-heading"><?= h(service_filter_label($http_filter)); ?></h2><div class="table-responsive">
        <table id="tableEvents" class="table table-bordered table-hover table-striped align-middle w-100"><thead><tr><th><?= h($pia_lang['WEBS_tablehead_TargetIP']); ?></th><th><?= h($pia_lang['WEBS_tablehead_ScanTime']); ?></th><th><?= h($pia_lang['WEBS_tablehead_Status_Code']); ?></th><th><?= h($pia_lang['WEBS_tablehead_Response_Time']); ?></th><th><?= h($pia_lang['V4_SSL_Status']); ?></th></tr></thead><tbody><?php get_service_events_table($service_details_title, $http_filter); ?></tbody></table>
      </div></div>

      <div class="tab-pane fade" id="panGraph" role="tabpanel" aria-labelledby="tabGraph">
        <h2 class="h5 text-primary mb-3"><?= h($pia_lang['WEBS_Chart_a']); ?> <span class="maxlogage-interval">24</span> <?= h($pia_lang['WEBS_Chart_b']); ?></h2><div class="service-chart"><canvas id="ServiceChart"></canvas></div>
        <script id="service-chart-data" type="application/json"><?= json_encode(array('time' => array_reverse($graph_arrays[0]), 'down' => array_reverse($graph_arrays[1]), '2xx' => array_reverse($graph_arrays[2]), '3xx' => array_reverse($graph_arrays[3]), '4xx' => array_reverse($graph_arrays[4]), '5xx' => array_reverse($graph_arrays[5])), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <div class="service-code-legend mt-4"><?php foreach (array(array('success','2xx',$graph_arrays[7]),array('warning','3xx',$graph_arrays[8]),array('warning','4xx',$graph_arrays[9]),array('orange','5xx',$graph_arrays[10]),array('danger',$pia_lang['WEBS_Page_down'],$graph_arrays[6])) as [$tone,$label,$count]): ?><span><i class="fa-solid fa-circle text-<?= h($tone); ?>" aria-hidden="true"></i> <?= h($label); ?> (<?= h((string) $count); ?>)</span><?php endforeach; ?></div>
        <div class="row g-3 mt-2"><div class="col-12 col-lg-6"><section class="card h-100"><div class="card-header"><h3 class="card-title"><?= h($pia_lang['WEBS_Stats_Time']); ?></h3></div><div class="card-body table-responsive"><table class="table table-sm mb-0"><thead><tr><th></th><th>&Oslash;</th><th><?= h($pia_lang['V4_Min']); ?></th><th><?= h($pia_lang['V4_Max']); ?></th></tr></thead><tbody><?php foreach (array('24h'=>'24h','1w'=>'7d',''=>'All') as $key => $label): ?><tr><th><?= h($label); ?></th><td><?= $statistic['latency_avg' . ($key ? '_' . $key : '')]; ?></td><td><?= $statistic['latency_min' . ($key ? '_' . $key : '')]; ?></td><td><?= $statistic['latency_max' . ($key ? '_' . $key : '')]; ?></td></tr><?php endforeach; ?></tbody></table></div></section></div>
          <div class="col-12 col-lg-6"><section class="card h-100"><div class="card-header"><h3 class="card-title"><?= h($pia_lang['ICMPMonitor_Availability']); ?></h3></div><div class="card-body table-responsive"><table class="table table-sm mb-0"><thead><tr><th></th><th><?= h($pia_lang['ICMPMonitor_Shortcut_Online']); ?></th><th><?= h($pia_lang['ICMPMonitor_Shortcut_Offline']); ?></th></tr></thead><tbody><?php foreach (array('24h'=>'24h','1w'=>'7d','all'=>'All') as $key => $label): ?><tr><th><?= h($label); ?></th><td class="text-success"><?= h($statistic['online_percent_' . $key]); ?></td><td class="text-danger"><?= h($statistic['offline_percent_' . $key]); ?></td></tr><?php endforeach; ?></tbody></table></div></section></div></div>
        <section id="service-location" class="card mt-3"><div class="card-header"><h3 class="card-title"><?= h($pia_lang['WEBS_Stats_Location']); ?></h3></div><div class="card-body">
        <?php if ($geoDatabaseInstalled): ?><dl class="row mb-3"><dt class="col-sm-3"><?= h($pia_lang['WEBS_Stats_IP']); ?></dt><dd class="col-sm-9"><?= h($servicedetails['mon_TargetIP'] ?? ''); ?></dd><dt class="col-sm-3"><?= h($pia_lang['WEBS_Stats_IPLocation']); ?></dt><dd class="col-sm-9"><?= h($locationLabel); ?></dd></dl><button class="btn btn-outline-danger" id="deleteDB-button" type="button"><?= h($pia_lang['GeoLiteDB_button_del']); ?></button>
        <?php else: ?><div class="d-flex align-items-center gap-3"><span class="spinner-border" id="downloader" hidden aria-hidden="true"></span><button class="btn btn-outline-primary" id="downloadDB-button" type="button"><?= h($pia_lang['GeoLiteDB_button_ins']); ?></button></div><?php endif; ?><p class="text-body-secondary mt-3 mb-0"><?= pialert_v4_geolite_credits_html((string) $pia_lang['GeoLiteDB_credits']); ?></p></div></section>
      </div>
    </div>
  </div>
</section>
<?php pialert_v4_shell_end(array(
    'lib/datatables/datatables.net-2.3.8/dataTables.min.js',
    'lib/datatables/datatables.net-bs5-2.3.8/js/dataTables.bootstrap5.min.js',
    'lib/chart.js-4.5.1/chart.umd.js',
    'js/service-details.js',
)); ?>

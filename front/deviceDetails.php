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

$requestedMac = isset($_GET['mac']) && is_scalar($_GET['mac']) ? (string) $_GET['mac'] : '';
if ($requestedMac === '' || strlen($requestedMac) > 128
    || !preg_match('/^(?:[A-Za-z0-9:._-]+|Internet - [A-Za-z0-9_-]+)$/D', $requestedMac)) {
    header('Location: ' . pialert_v4_route('home'));
    exit;
}

pialert_v4_load_language();
require_once __DIR__ . '/php/shell.php';
require_once __DIR__ . '/php/server/db.php';
require_once __DIR__ . '/php/server/graph.php';
require_once __DIR__ . '/php/entity-actions-editor.php';

$L = static fn(string $key, string $fallback): string => (string) ($pia_lang[$key] ?? $fallback);
$isInternet = $requestedMac === 'Internet';
$speedtestInstalled = is_file('../back/speedtest/speedtest');
$speedtestRows = array();
$speedtestNote = '';
if ($isInternet) {
    $DBFILE_TOOLS = '../db/pialert_tools.db';
    OpenDB_Tools();
    $speedtestResults = $db_tools->query('SELECT * FROM Tools_Speedtest_History');
    while ($speedtestResults && ($speedtestRow = $speedtestResults->fetchArray(SQLITE3_ASSOC))) $speedtestRows[] = $speedtestRow;
    if ($speedtestInstalled) {
        $speedtestConfig = @file_get_contents('../config/pialert.conf');
        if (is_string($speedtestConfig) && preg_match('/^\s*SPEEDTEST_TASK_HOUR\s*=\s*\[([^\]]*)\]/m', $speedtestConfig, $speedtestMatch)) {
            $speedtestHours = array_values(array_filter(array_map('trim', explode(',', $speedtestMatch[1])), 'ctype_digit'));
            if ($speedtestHours) {
                $speedtestLastHour = array_pop($speedtestHours);
                $speedtestNote = $L('DevDetail_Speedtest_note_a','The automatic speed test starts at ')
                    . ($speedtestHours ? implode(', ', $speedtestHours) . $L('DevDetail_Speedtest_note_b',' and ') : '')
                    . $speedtestLastHour . $L('DevDetail_Speedtest_note_c', ' o’clock');
            }
        }
    }
}

$fields = array(
    array('txtName','DevDetail_MainInfo_Name','Name'), array('txtOwner','DevDetail_MainInfo_Owner','Owner','getOwners'),
    array('txtDeviceType','DevDetail_MainInfo_Type','Type','getDeviceTypes'), array('txtVendor','DevDetail_MainInfo_Vendor','Vendor'),
    array('txtModel','DevDetail_MainInfo_Model','Model'), array('txtSerialnumber','DevDetail_MainInfo_Serialnumber','Serial number'),
    array('txtGroup','DevDetail_MainInfo_Group','Group','getGroups'), array('txtLocation','DevDetail_MainInfo_Location','Location','getLocations'),
    array('txtComments','DevDetail_MainInfo_Comments','Comments','', 'textarea'),
);
$networkFields = array(
    array('txtStatus','DevDetail_MainInfo_Status','Status','', 'readonly'),
    array('txtFirstConnection','DevDetail_MainInfo_FirstConnection','First connection','', 'readonly'),
    array('txtLastConnection','DevDetail_MainInfo_LastConnection','Last connection','', 'readonly'),
    array('txtLastIP','DevDetail_MainInfo_LastIP','Last IP','', 'readonly'),
    array('txtNetworkNodeMac','DevDetail_MainInfo_Network_Node','Network node','getNetworkNodes'),
    array('txtNetworkPort','DevDetail_MainInfo_Network_Port','Network port'),
    array('txtConnectionType','DevDetail_MainInfo_Network_ConnectType','Connection type','getConnectionType'),
    array('txtLinkSpeed','DevDetail_MainInfo_Network_LinkSpeed','Link speed','getLinkSpeed'),
);
$checks = array(
    array('chkFavorite','DevDetail_MainInfo_Favorite','Favorite'),
    array('chkStaticIP','DevDetail_MainInfo_StaticIP','Static IP'),
    array('chkMQTTDevice','DevDetail_MainInfo_MQTTDevice','MQTT device'),
    array('chkAlertEvents','DevDetail_EveandAl_AlertAllEvents','Alert on events'),
    array('chkAlertDown','DevDetail_EveandAl_AlertDown','Alert when down'),
    array('chkNewDevice','DevDetail_EveandAl_NewDevice','New device'),
    array('chkArchived','DevDetail_EveandAl_Archived','Archived'),
    array('chkShowPresence','DevDetail_MainInfo_ShowPresence','Show on presence page'),
);
$renderField = static function(array $field) use ($L, $pia_lang): void {
    $id = $field[0]; $label = pialert_v4_ui_plain_label($L($field[1], $field[2])); $suggestions = $field[3] ?? ''; $kind = $field[4] ?? 'input';
    echo '<div class="device-detail-field mb-3"><label for="', h($id), '" class="form-label">', h($label), '</label><div class="device-detail-control">';
    if ($kind === 'textarea') echo '<textarea class="form-control" id="', h($id), '" rows="3"></textarea>';
    elseif ($id === 'txtLastIP') echo '<div class="input-group"><input class="form-control" id="txtLastIP" type="text" readonly><button id="copyIP" class="btn btn-outline-secondary" type="button" data-copy-target="txtLastIP" aria-label="', h($pia_lang['V4_Copy_IP']), '"><i class="bi bi-clipboard" aria-hidden="true"></i></button><button id="ignoreIP" class="btn btn-outline-danger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-label="', h($pia_lang['V4_Ignore_IP_Prefix']), '"></button><ul class="dropdown-menu dropdown-menu-end" id="ignoreIPOptions"></ul></div>';
    elseif ($suggestions !== '') echo '<div class="input-group"><input class="form-control" id="', h($id), '" type="text"><button class="btn btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-controls="menu-', h($id), '" aria-expanded="false" aria-label="', h($label), '"></button><ul class="dropdown-menu dropdown-menu-end device-detail-options" id="menu-', h($id), '" data-suggestion-action="', h($suggestions), '" data-suggestion-target="', h($id), '"></ul></div>';
    else echo '<input class="form-control" id="', h($id), '" type="text"', $kind === 'readonly' ? ' readonly' : '', '>';
    echo '</div></div>';
};
$renderCheck = static function(array $check) use ($L): void {
    $colorClass = match ($check[0]) {
        'chkFavorite' => ' pialert-favorite-switch',
        'chkMQTTDevice' => ' pialert-purple-switch',
        'chkAlertDown' => ' pialert-down-switch',
        'chkNewDevice' => ' pialert-new-switch',
        'chkArchived' => ' pialert-archive-switch',
        default => '',
    };
    echo '<div class="device-detail-field device-detail-switch mb-3"><div class="device-detail-control form-check form-switch ps-0"><input class="form-check-input', $colorClass, ' ms-0 float-none" id="', h($check[0]), '" type="checkbox" role="switch"></div><label for="', h($check[0]), '" class="form-check-label">', h($L($check[1], $check[2])), '</label></div>';
};

$labels = array(
    'close'=>$L('Gen_Close','Close'), 'reset'=>$L('DevDetail_button_Reset','Reset'),
    'deleteEventsTitle'=>$L('DevDetail_button_DeleteEvents','Delete events'), 'deleteEventsWarning'=>$L('DevDetail_button_DeleteEvents_Warning','Delete device events?'),
    'deleteTitle'=>$L('DevDetail_button_Delete','Delete device'), 'deleteWarning'=>$L('DevDetail_button_Delete_Warning','Delete this device?'),
    'cancel'=>$L('Gen_Cancel','Cancel'), 'delete'=>$L('Gen_Delete','Delete'), 'run'=>$L('Gen_Run','Run'),
    'wolTitle'=>$L('DevDetail_Tools_WOL_noti','Wake on LAN'), 'wolText'=>$L('DevDetail_Tools_WOL_noti_text','Wake this device?'),
    'nmapFast'=>$L('DevDetail_Tools_nmap_buttonFast','Fast scan'), 'nmapNormal'=>$L('DevDetail_Tools_nmap_buttonDefault','Normal scan'),
    'nmapDetail'=>$L('DevDetail_Tools_nmap_buttonDetail','Detailed scan'), 'nmapPending'=>$L('DevDetail_Tools_nmap_buttonPending','Pending'),
    'nmapQueued'=>$L('DevDetail_Tools_nmap_queueAdded','Detailed scan queued'), 'nmapError'=>$L('DevDetail_Tools_nmap_queueError','Could not queue detailed scan'),
    'nmapLoading'=>$L('nmap_results_loading','Loading scan results…'), 'nmapRequestError'=>$L('nmap_results_request_error','The scan request failed.'),
    'wol'=>$L('DevDetail_Tools_WOL','Wake on LAN'), 'lengthMenu'=>$L('EVE_Tablelenght','Show _MENU_'),
    'search'=>$L('EVE_Searchbox','Search'), 'next'=>$L('EVE_Table_nav_next','Next'), 'previous'=>$L('EVE_Table_nav_prev','Previous'),
    'info'=>$L('EVE_Table_info','Showing _START_ to _END_ of _TOTAL_'),
    'calendarMonth'=>$L('PRE_CalHead_month','Month'), 'calendarWeek'=>$L('PRE_CalHead_week','Week'), 'calendarDay'=>$L('PRE_CalHead_day','Day'),
    'notFound'=>$L('DevDetail_NotFound','Device not found'), 'ignore'=>$L('MT_Tool_ignorelist','Ignore list'),
    'ignoreText'=>$L('DevDetail_add_ignore_noti_text',''), 'saved'=>$L('BE_Dev_DBTools_UpdDev','Device updated'),
);
$config = array('mac'=>$requestedMac,'internet'=>$isInternet,'labels'=>$labels,
    'calendarLocale'=>$L('PRE_CalHead_lang','en'),'speedtestInstalled'=>$speedtestInstalled,
    'speedtestRows'=>array_map(static fn($row): array => array($row['speed_date'],$row['speed_isp'],$row['speed_server'],$row['speed_ping'],$row['speed_down'],$row['speed_up']), $speedtestRows));
$title = $L('DevDetail_Title','Device details');
pialert_v4_shell_start($title, 'home', array('lib/datatables/datatables.net-bs5-3.1.2/css/dataTables.bootstrap5.min.css','lib/coloris-0.25.0/coloris.min.css','css/device-details.css','css/presence-calendar.css','css/nmap-results.css','css/entity-actions.css'), mobileBackRoute: 'home');
?>
<script type="application/json" id="device-details-config"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<div id="device-details-page" class="mb-4">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <a id="deviceDetailsBack" href="<?= h(pialert_v4_route('home')); ?>" class="btn btn-outline-secondary pialert-back-link"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i><?= h($L('Device_Table_nav_prev','Back to devices')); ?></a>
    <div class="d-flex align-items-center gap-2"><label for="period" class="form-label mb-0"><?= h($L('DevDetail_Periodselect','Period')); ?></label><select id="period" class="form-select form-select-sm">
      <option value="1 day"><?= h($L('DevDetail_Periodselect_today','Today')); ?></option><option value="7 days"><?= h($L('DevDetail_Periodselect_LastWeek','Last week')); ?></option><option value="1 month" selected><?= h($L('DevDetail_Periodselect_LastMonth','Last month')); ?></option><option value="1 year"><?= h($L('DevDetail_Periodselect_LastYear','Last year')); ?></option><option value="100 years"><?= h($L('DevDetail_Periodselect_All','All')); ?></option>
    </select></div>
  </div>
  <div class="row g-3 mb-4">
    <?php foreach (array(
        array('deviceStatus','DevDetail_Shortcut_CurrentStatus','Status','text-bg-info','tabDetails'),
        array('deviceSessions','DevDetail_Shortcut_Sessions','Sessions','text-bg-success','tabSessions'),
        array('deviceEvents','DevDetail_Shortcut_Presence','Presence','text-bg-warning','tabPresence'),
        array('deviceDownAlerts','DevDetail_Shortcut_DownAlerts','Down alerts','text-bg-danger','tabEvents')
    ) as $card): ?>
    <div class="col-6 col-md-3"><button type="button" class="small-box w-100 border-0 text-start <?= h($card[3]); ?> device-summary" data-open-tab="<?= h($card[4]); ?>"><div class="inner"><h2 id="<?= h($card[0]); ?>" class="mb-1">--</h2><p class="mb-0"><?= h($L($card[1],$card[2])); ?></p></div></button></div>
    <?php endforeach; ?>
  </div>
  <section class="card shadow-sm"><div class="card-header p-0"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 pt-3"><ul class="nav nav-tabs card-header-tabs" id="deviceDetailsTabs" role="tablist">
    <?php foreach (array(array('tabDetails','panDetails','DevDetail_Tab_Details','Details'),array('tabActions','panActions','EntityActions_Tab','Actions'),array('tabNmap','panNmap','DevDetail_Tab_Nmap','Tools'),array('tabSessions','panSessions','DevDetail_Tab_Sessions','Sessions'),array('tabPresence','panPresence','DevDetail_Tab_Presence','Presence'),array('tabEvents','panEvents','DevDetail_Tab_Events','Events')) as $tab): ?>
    <li class="nav-item" role="presentation"><button class="nav-link<?= $tab[0]==='tabDetails' ? ' active' : ''; ?>" id="<?= h($tab[0]); ?>" type="button" data-bs-toggle="tab" data-bs-target="#<?= h($tab[1]); ?>" role="tab" aria-controls="<?= h($tab[1]); ?>" aria-selected="<?= $tab[0]==='tabDetails' ? 'true' : 'false'; ?>"><?= h($L($tab[2],$tab[3])); ?></button></li>
    <?php endforeach; ?>
    <?php if ($isInternet): ?><li class="nav-item" role="presentation"><button class="nav-link" id="tabSpeedtest" type="button" data-bs-toggle="tab" data-bs-target="#panSpeedtest" role="tab" aria-controls="panSpeedtest" aria-selected="false"><?= h($L('ookla_devdetails_tab_title','Speedtest')); ?></button></li><?php endif; ?>
  </ul><div class="btn-group mb-2" aria-label="<?= h($pia_lang['V4_Device_Navigation']); ?>"><button type="button" class="btn btn-outline-secondary" id="btnPrevious" aria-label="<?= h($pia_lang['V4_Previous_Device']); ?>"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button><span class="btn btn-outline-secondary disabled" id="txtRecord" aria-live="polite">0 / 0</span><button type="button" class="btn btn-outline-secondary" id="btnNext" aria-label="<?= h($pia_lang['V4_Next_Device']); ?>"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button></div></div></div>
    <div class="card-body"><div class="tab-content">
      <div class="tab-pane fade show active" id="panDetails" role="tabpanel" aria-labelledby="tabDetails">
        <div class="row g-4"><div class="col-12 col-lg-4"><h2 class="h5 border-bottom pb-2"><?= h($L('DevDetail_MainInfo_Title','Main information')); ?></h2><div class="device-detail-field mb-3"><label for="txtMAC" class="form-label"><?= h($L('DevDetail_MainInfo_mac','MAC')); ?></label><div class="device-detail-control input-group"><input id="txtMAC" class="form-control" type="text" readonly><button id="copyMAC" class="btn btn-outline-secondary" type="button" data-copy-target="txtMAC" aria-label="<?= h($pia_lang['V4_Copy_MAC']); ?>"><i class="bi bi-clipboard" aria-hidden="true"></i></button><button id="ignoreMAC" class="btn btn-outline-danger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-label="<?= h($pia_lang['V4_Ignore_MAC_Prefix']); ?>"></button><ul class="dropdown-menu dropdown-menu-end" id="ignoreMACOptions"></ul></div></div>
          <?php foreach ($fields as $field) $renderField($field); ?>
        </div><div class="col-12 col-lg-4"><h2 class="h5 border-bottom pb-2"><?= h($L('DevDetail_MainInfo_Network_Title','Network')); ?></h2>
          <?php foreach ($networkFields as $field) $renderField($field); ?>
          <?php foreach (array_slice($checks,0,3) as $check) $renderCheck($check); ?>
        </div><div class="col-12 col-lg-4"><h2 class="h5 border-bottom pb-2"><?= h($L('DevDetail_EveandAl_Title','Events and alerts')); ?></h2>
          <div class="device-detail-field mb-3"><label for="txtScanCycle" class="form-label"><?= h($L('DevDetail_EveandAl_ScanCycle','Scan cycle')); ?></label><div class="device-detail-control"><select class="form-select" id="txtScanCycle"><option value="1"><?= h(html_entity_decode($L('DevDetail_EveandAl_ScanCycle_a','Every scan'), ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?></option><option value="0"><?= h(html_entity_decode($L('DevDetail_EveandAl_ScanCycle_z','Never'), ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?></option></select></div></div>
          <div class="device-detail-field mb-3"><label for="txtSkipRepeated" class="form-label"><?= h($L('DevDetail_EveandAl_Skip','Skip repeated notifications')); ?></label><div class="device-detail-control"><select class="form-select" id="txtSkipRepeated"><option value="0">0 h</option><option value="1">1 h</option><option value="8">8 h</option><option value="24">24 h</option><option value="168">168 h</option></select></div></div>
          <div class="device-detail-field mb-3"><label for="txtScanValidation" class="form-label"><?= h($L('DevDetail_EveandAl_ScanValid','Scan validation')); ?></label><div class="device-detail-control"><input id="txtScanValidation" class="form-control" type="text"></div></div>
          <div class="device-detail-alert-switches">
            <?php foreach (array_slice($checks,3) as $check) $renderCheck($check); ?>
            <div class="device-detail-field device-detail-alert-info mt-3"><div class="device-detail-control device-detail-alert-info-indicator"><span id="iconRandomMAC" class="device-detail-random-mac-indicator" role="img" aria-label="<?= h($L('DevDetail_EveandAl_RandomMAC','Randomized MAC')); ?>" data-label="<?= h($L('DevDetail_EveandAl_RandomMAC','Randomized MAC')); ?>" data-active-label="<?= h($L('V4_True','Yes')); ?>" data-inactive-label="<?= h($L('V4_False','No')); ?>"><i class="bi bi-shuffle" aria-hidden="true"></i></span></div><div class="device-detail-alert-info-action"><span class="device-detail-alert-info-label"><?= h($L('DevDetail_EveandAl_RandomMAC','Randomized MAC')); ?></span><a href="https://github.com/leiweibau/Pi.Alert/blob/main/docs/RAMDOM_MAC.md" target="_blank" rel="noopener noreferrer" aria-label="<?= h($pia_lang['V4_Random_MAC_Info']); ?>"><i class="bi bi-info-circle"></i></a></div></div>
          </div>
        </div></div>
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4"><button type="button" class="btn btn-outline-warning" id="btnDeleteEvents"><?= h($L('DevDetail_button_DeleteEvents','Delete events')); ?></button><button type="button" class="btn btn-outline-danger" id="btnDelete"><?= h($L('DevDetail_button_Delete','Delete')); ?></button><button type="button" class="btn btn-outline-secondary" id="btnRestore"><?= h($labels['close']); ?></button><button type="button" class="btn btn-primary" id="btnSave" disabled><?= h($L('DevDetail_button_Save','Save')); ?></button></div>
      </div>
      <div class="tab-pane fade" id="panActions" role="tabpanel" aria-labelledby="tabActions"><?php pialert_entity_actions_editor($L); ?></div>
      <div class="tab-pane fade" id="panNmap" role="tabpanel" aria-labelledby="tabNmap">
        <div class="row g-3">
          <div class="col-12 col-lg-6"><section class="card h-100 pialert-tool-card"><div class="card-header"><h2 class="card-title"><?= h($isInternet ? $pia_lang['V4_Online_Speedtest'] : $pia_lang['DevDetail_Tools_WOL_noti']); ?></h2></div><div class="card-body"><div class="pialert-tool-button-row">
            <?php if ($isInternet): ?><button id="speedtestcli" type="button" class="btn btn-primary"><?= h($pia_lang['V4_Start_Speedtest']); ?></button><button id="speedtestcli_ookla" type="button" class="btn <?= $speedtestInstalled ? 'btn-success' : 'btn-outline-primary'; ?>" <?= $speedtestInstalled ? 'disabled' : ''; ?>><?= h($speedtestInstalled ? $pia_lang['V4_Speedtest_Installed'] : $pia_lang['V4_Speedtest_Download']); ?></button><?php else: ?><button type="button" id="btnwakeonlan" class="btn btn-primary"><?= h($labels['wol']); ?></button><?php endif; ?>
          </div></div></section></div>
          <div class="col-12 col-lg-6"><section class="card h-100 pialert-tool-card"><div class="card-header"><h2 class="card-title"><?= h($pia_lang['V4_Nmap_Scans']); ?></h2></div><div class="card-body"><div class="pialert-tool-button-row"><button type="button" id="manualnmap_fast" class="btn btn-outline-primary"><?= h($labels['nmapFast']); ?></button><button type="button" id="manualnmap_normal" class="btn btn-outline-primary"><?= h($labels['nmapNormal']); ?></button><button type="button" id="manualnmap_detail" class="btn btn-outline-primary" disabled><?= h($labels['nmapPending']); ?></button></div><div id="nmapstatus" class="pialert-nmap-state" role="status" aria-live="polite"></div></div></section></div>
        </div>
        <div id="scanoutput" class="device-tool-output mt-3"></div>
      </div>
      <div class="tab-pane fade" id="panSessions" role="tabpanel" aria-labelledby="tabSessions"><div class="table-responsive"><table id="tableSessions" class="table table-striped table-hover w-100"><thead><tr><th><?= h($L('DevDetail_SessionTable_Order','Order')); ?></th><th><?= h($L('DevDetail_SessionTable_Connection','Connection')); ?></th><th><?= h($L('DevDetail_SessionTable_Disconnection','Disconnection')); ?></th><th><?= h($L('DevDetail_SessionTable_Duration','Duration')); ?></th><th><?= h($L('DevDetail_SessionTable_IP','IP')); ?></th><th><?= h($L('DevDetail_SessionTable_Additionalinfo','Info')); ?></th></tr></thead></table></div></div>
      <div class="tab-pane fade" id="panPresence" role="tabpanel" aria-labelledby="tabPresence"><div id="calendar" class="presence-calendar" aria-label="<?= h($L('DevDetail_Tab_Presence','Presence')); ?>"></div></div>
      <div class="tab-pane fade" id="panEvents" role="tabpanel" aria-labelledby="tabEvents"><div class="form-check form-switch mb-3"><input class="form-check-input" id="chkHideConnectionEvents" type="checkbox" checked role="switch"><label for="chkHideConnectionEvents" class="form-check-label"><?= h($L('DevDetail_Events_CheckBox','Hide connection events')); ?></label></div><div class="table-responsive"><table id="tableEvents" class="table table-striped table-hover w-100"><thead><tr><th><?= h($L('EVE_TableHead_Date','Date')); ?></th><th><?= h($L('EVE_TableHead_EventType','Event')); ?></th><th><?= h($L('EVE_TableHead_IP','IP')); ?></th><th><?= h($L('EVE_TableHead_AdditionalInfo','Info')); ?></th></tr></thead></table></div></div>
      <?php if ($isInternet): ?><div class="tab-pane fade" id="panSpeedtest" role="tabpanel" aria-labelledby="tabSpeedtest"><div class="device-speed-chart mb-4"><canvas id="SpeedtestChart"></canvas></div><?php if ($speedtestNote !== ''): ?><p><?= h(html_entity_decode($speedtestNote, ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?></p><?php endif; ?><?php if ($speedtestInstalled): ?><div class="table-responsive"><table id="tableSpeedtest" class="table table-striped table-hover w-100"><thead><tr><th><?= h($L('ookla_devdetails_table_time','Time')); ?></th><th><?= h($L('ookla_devdetails_table_isp','ISP')); ?></th><th><?= h($L('ookla_devdetails_table_server','Server')); ?></th><th><?= h($L('ookla_devdetails_table_ping','Ping')); ?></th><th><?= h($L('ookla_devdetails_table_down','Down')); ?></th><th><?= h($L('ookla_devdetails_table_up','Up')); ?></th></tr></thead><tbody><?php foreach ($speedtestRows as $speedtestRow): ?><tr><td><?= h($speedtestRow['speed_date']); ?></td><td><?= h($speedtestRow['speed_isp']); ?></td><td><?= h($speedtestRow['speed_server']); ?></td><td><?= h($speedtestRow['speed_ping']); ?></td><td><?= h($speedtestRow['speed_down']); ?></td><td><?= h($speedtestRow['speed_up']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p><?= h($L('ookla_devdetails_required','Speedtest client required')); ?></p><?php endif; ?></div><?php endif; ?>
    </div></div>
  </section>
</div>
<?php pialert_v4_shell_end(array('lib/datatables/datatables.net-3.1.2/dataTables.min.js','lib/datatables/datatables.net-bs5-3.1.2/js/dataTables.bootstrap5.min.js','lib/fullcalendar-6.1.21/index.global.min.js','lib/fullcalendar-6.1.21/locales-all.global.min.js','lib/chart.js-4.5.1/chart.umd.js','lib/coloris-0.25.0/coloris.min.js','js/nmap-results.js','js/entity-actions-renderer.js','js/entity-actions-editor.js','js/device-details.js')); ?>

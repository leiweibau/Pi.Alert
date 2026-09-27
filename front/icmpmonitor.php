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

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, array('GET', 'POST'), true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method Not Allowed');
}
if ($method === 'POST') pialert_validate_csrf();
$pageRequest = $method === 'POST' ? $_POST : $_GET;

pialert_v4_load_language();
require_once __DIR__ . '/php/shell.php';
require_once PIALERT_V4_FRONT_ROOT . '/php/server/db.php';
require_once PIALERT_V4_FRONT_ROOT . '/php/server/graph.php';
require_once PIALERT_V4_FRONT_ROOT . '/php/server/journal.php';

$DBFILE = '../db/pialert.db';
OpenDB();
$bulkMode = (($pageRequest['mod'] ?? '') === 'bulkedit');
$L = static fn(string $key, string $fallback): string => (string) ($pia_lang[$key] ?? $fallback);

/* Bulk field names, columns, bindings and journal action mirror icmpmonitor.php. */
if ($bulkMode && (($pageRequest['savedata'] ?? '') === 'yes')) {
    $sql_queue = array();
    if (($pageRequest['en_bulk_owner'] ?? '') === 'on') $sql_queue['icmp_owner'] = htmlspecialchars((string) ($pageRequest['bulk_owner'] ?? ''), ENT_QUOTES);
    if (($pageRequest['en_bulk_type'] ?? '') === 'on') $sql_queue['icmp_type'] = htmlspecialchars((string) ($pageRequest['bulk_type'] ?? ''), ENT_QUOTES);
    if (($pageRequest['en_bulk_group'] ?? '') === 'on') $sql_queue['icmp_group'] = htmlspecialchars((string) ($pageRequest['bulk_group'] ?? ''), ENT_QUOTES);
    if (($pageRequest['en_bulk_location'] ?? '') === 'on') $sql_queue['icmp_location'] = htmlspecialchars((string) ($pageRequest['bulk_location'] ?? ''), ENT_QUOTES);
    if (($pageRequest['en_bulk_comments'] ?? '') === 'on') $sql_queue['icmp_Notes'] = htmlspecialchars((string) ($pageRequest['bulk_comments'] ?? ''), ENT_QUOTES);
    if (($pageRequest['en_bulk_AlertAllEvents'] ?? '') === 'on') $sql_queue['icmp_AlertEvents'] = (($pageRequest['bulk_AlertAllEvents'] ?? '') === 'on') ? 1 : 0;
    if (($pageRequest['en_bulk_AlertDown'] ?? '') === 'on') $sql_queue['icmp_AlertDown'] = (($pageRequest['bulk_AlertDown'] ?? '') === 'on') ? 1 : 0;
    if (($pageRequest['en_bulk_MQTTDevice'] ?? '') === 'on') {
        $mqtt = (($pageRequest['bulk_MQTTDevice'] ?? '') === 'on') ? 1 : 0;
        $sql_queue['icmp_MQTTDevice'] = $mqtt;
        $sql_queue['icmp_MQTTDevice_cleanup'] = $mqtt ? 0 : 1;
    }
    $modifiedHosts = '';
    if ($sql_queue) {
        $results = $db->query('SELECT icmp_hostname, icmp_ip FROM ICMP_Mon ORDER BY icmp_hostname COLLATE NOCASE ASC');
        while ($row = $results->fetchArray()) {
            $fieldName = str_replace('.', '_', (string) $row['icmp_ip']);
            if (!isset($pageRequest[$fieldName])) continue;
            $modifiedHosts .= $row['icmp_hostname'] . '; ';
            $assignments = array(); $parameters = array(':ip' => $row['icmp_ip']); $index = 0;
            foreach ($sql_queue as $column => $value) {
                $placeholder = ':value_' . $index++;
                $assignments[] = $column . ' = ' . $placeholder;
                $parameters[$placeholder] = $value;
            }
            db_execute_prepared($db, 'UPDATE ICMP_Mon SET ' . implode(', ', $assignments) . ' WHERE icmp_ip = :ip', $parameters);
        }
        pialert_logging('a_021', $_SERVER['REMOTE_ADDR'], 'LogStr_0002', '', $modifiedHosts);
    }
    header('Location: ./icmpmonitor.php?mod=bulkedit&saved=1', true, 303);
    exit;
}

$uiSettings = pialert_v4_ui_read();
$icmpWidgets = $uiSettings['appearance']['header_widgets']['icmp'];
$icmpWidgetColumnClass = pialert_v4_header_widget_column_class($icmpWidgets);
$historyEnabled = $uiSettings['appearance']['activity_history'];
$history = array(array(), array(), array(), array(), array());
if (!$bulkMode && $historyEnabled) $history = prepare_icmpscan_graph_history();
$showHistory = $historyEnabled && !empty($history[0]);
$labels = array(
    'all'=>$L('Device_Shortcut_AllDevices','All hosts'),'connected'=>$L('Device_Shortcut_Connected','Online'),'favorites'=>$L('Device_Shortcut_Favorites','Favorites'),
    'down'=>$L('Device_Shortcut_DownAlerts','Down alerts'),'archived'=>$L('Device_Shortcut_Archived','Archived'),'hosts'=>$L('ICMPMonitor_Title','ICMP monitoring'),
    'lengthAll'=>$L('Device_Tablelenght_all','All'),'lengthMenu'=>$L('Device_Tablelenght','Show _MENU_'),'search'=>$L('Device_Searchbox','Search'),
    'next'=>$L('Device_Table_nav_next','Next'),'previous'=>$L('Device_Table_nav_prev','Previous'),'info'=>$L('Device_Table_info','Showing _START_ to _END_ of _TOTAL_'),
    'cancel'=>$L('Gen_Cancel','Cancel'),'delete'=>$L('Gen_Delete','Delete'),'deleteTitle'=>$L('ICMPMonitor_headline_IP','ICMP host'),
    'deleteText'=>$L('Device_bulkDel_info_text','Delete the selected host?'),'selectAll'=>$L('Device_bulkEditor_selectall','Select all'),
    'selectNone'=>$L('Device_bulkEditor_selectnone','Select none'),'bulkDeleteTitle'=>$L('Device_bulkDel_info_head','Delete hosts'),
    'bulkDeleteText'=>$L('Device_bulkDel_info_text','Delete the selected hosts?')
);
$config = array(
    'labels'=>$labels,
    'columnIds'=>array_keys(pialert_v4_icmp_columns()),
    'hiddenColumns'=>pialert_v4_ui_icmp_hidden_columns($uiSettings),
    'pageLength'=>$uiSettings['icmp']['page_length'],
    'order'=>pialert_v4_ui_icmp_numeric_order($uiSettings),
    'history'=>array('time'=>array_reverse($history[0]),'down'=>array_reverse($history[1]),'online'=>array_reverse($history[3]),'archived'=>array_reverse($history[4])),
);
$title = $labels['hosts'] . ($bulkMode ? ' - ' . $L('Device_bulkEditor_mode','Bulk editor') : '');

pialert_v4_shell_start($title, 'icmp', array('lib/datatables/datatables.net-bs5-3.1.2/css/dataTables.bootstrap5.min.css','css/icmpmonitor.css','css/entity-actions.css'),
    $bulkMode ? null : static fn(): string => '<button class="btn btn-success" id="add-icmp-host" type="button" data-bs-toggle="modal" data-bs-target="#icmp-host-modal"><i class="fa-solid fa-plus me-2" aria-hidden="true"></i>' . h($GLOBALS['pia_lang']['V4_New_Host']) . '</button>');
?>
<script type="application/json" id="icmpmonitor-page-config"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<?php if ($bulkMode): ?>
<section id="icmpmonitor-bulk-page" aria-label="<?= h($title); ?>">
  <?php if (($pageRequest['saved'] ?? '') === '1'): ?><div class="alert alert-success" role="status"><?= h($L('Device_bulkEditor_savebox_title','Changes saved')); ?></div><?php endif; ?>
  <div class="d-flex justify-content-end mb-3"><a class="btn btn-success" href="icmpmonitor.php"><?= h($L('Device_bulkEditor_mode_quit','Quit bulk editor')); ?></a></div>
  <form method="post" action="icmpmonitor.php" id="icmpBulkEditForm">
    <input type="hidden" name="mod" value="bulkedit"><input type="hidden" name="savedata" value="yes"><input type="hidden" name="_csrf" value="<?= h(pialert_csrf_token()); ?>">
    <section class="card mb-3"><div class="card-header"><h2 class="card-title"><?= h($L('Device_bulkEditor_inputbox_title','Bulk fields')); ?></h2></div><div class="card-body"><div class="row g-3">
    <?php foreach (array('owner'=>$L('DevDetail_MainInfo_Owner','Owner'),'type'=>$L('DevDetail_MainInfo_Type','Type'),'group'=>$L('DevDetail_MainInfo_Group','Group'),'location'=>$L('DevDetail_MainInfo_Location','Location'),'comments'=>$L('DevDetail_MainInfo_Comments','Comments')) as $field=>$label): ?>
      <div class="col-md-6"><div class="icmp-bulk-field"><div class="form-check"><input class="form-check-input bulk-enable" type="checkbox" id="en_bulk_<?= h($field); ?>" name="en_bulk_<?= h($field); ?>" data-bulk-target="bulk_<?= h($field); ?>"><label class="form-check-label fw-semibold" for="en_bulk_<?= h($field); ?>"><?= h(pialert_v4_ui_plain_label($label)); ?></label></div><?php if ($field === 'comments'): ?><textarea class="form-control mt-2" rows="2" id="bulk_comments" name="bulk_comments" disabled></textarea><?php else: ?><input class="form-control mt-2" type="text" id="bulk_<?= h($field); ?>" name="bulk_<?= h($field); ?>" disabled><?php endif; ?></div></div>
    <?php endforeach; ?>
    <?php foreach (array('AlertAllEvents'=>$L('DevDetail_EveandAl_AlertAllEvents','Alert all events'),'AlertDown'=>$L('DevDetail_EveandAl_AlertDown','Alert down'),'MQTTDevice'=>$L('DevDetail_MainInfo_MQTTDevice','MQTT device')) as $field=>$label): ?>
      <div class="col-md-6"><div class="icmp-bulk-field d-flex align-items-center justify-content-between gap-3"><div class="form-check"><input class="form-check-input bulk-enable" type="checkbox" id="en_bulk_<?= h($field); ?>" name="en_bulk_<?= h($field); ?>" data-bulk-target="bulk_<?= h($field); ?>"><label class="form-check-label fw-semibold" for="en_bulk_<?= h($field); ?>"><?= h($label); ?></label></div><?php if ($field === 'MQTTDevice' || $field === 'AlertDown'): ?><div class="form-check form-switch m-0 p-0"><input class="form-check-input bulk-value <?= $field === 'MQTTDevice' ? 'pialert-purple-switch' : 'pialert-down-switch'; ?> m-0 float-none" type="checkbox" role="switch" id="bulk_<?= h($field); ?>" name="bulk_<?= h($field); ?>" disabled aria-label="<?= h($label); ?>"></div><?php else: ?><input class="form-check-input bulk-value" type="checkbox" id="bulk_<?= h($field); ?>" name="bulk_<?= h($field); ?>" disabled aria-label="<?= h($label); ?>"><?php endif; ?></div></div>
    <?php endforeach; ?>
    </div><div class="d-flex flex-wrap justify-content-between gap-2 mt-3"><button type="button" class="btn btn-danger" id="btnBulkDeletion"><?= h($L('Device_bulkDel_button','Delete selected')); ?></button><button type="submit" class="btn btn-warning"><?= h($L('Gen_Save','Save')); ?></button></div></div></section>
    <section class="card"><div class="card-header"><h2 class="card-title"><?= h($L('Device_bulkEditor_hostbox_title','Hosts')); ?></h2></div><div class="card-body"><label class="visually-hidden" for="icmpHostSearch"><?= h($labels['search']); ?></label><input class="form-control mx-auto mb-3" type="search" id="icmpHostSearch" placeholder="<?= h($labels['search']); ?>…"><div class="icmp-bulk-host-grid">
    <?php $hosts = $db->query('SELECT icmp_hostname, icmp_ip, icmp_PresentLastScan, icmp_AlertEvents, icmp_AlertDown, icmp_MQTTDevice FROM ICMP_Mon ORDER BY icmp_hostname COLLATE NOCASE ASC'); while ($row = $hosts->fetchArray(SQLITE3_ASSOC)): $id='icmp-host-'.hash('sha256',(string)$row['icmp_ip']); $tone=$row['icmp_PresentLastScan']==1?'icmp-host-online':'icmp-host-offline'; $alerts=($row['icmp_AlertEvents']==1&&$row['icmp_AlertDown']==1)?'icmp-alert-both':($row['icmp_AlertEvents']==1?'icmp-alert-all':($row['icmp_AlertDown']==1?'icmp-alert-down':'')); ?>
      <div class="icmp-bulk-host <?= h($tone); ?>"><input class="form-check-input hostselection" id="<?= h($id); ?>" name="<?= h(str_replace('.', '_', (string)$row['icmp_ip'])); ?>" data-host-id="<?= h($row['icmp_ip']); ?>" type="checkbox"><label class="<?= h($alerts); ?>" for="<?= h($id); ?>"><?= h($row['icmp_hostname'] !== '' ? $row['icmp_hostname'] : $row['icmp_ip']); ?><small><?= h($row['icmp_ip']); ?></small></label><?php if ($row['icmp_MQTTDevice'] == 1): ?><span class="pialert-bulk-indicators"><span class="pialert-bulk-indicator pialert-bulk-indicator-mqtt" role="img" aria-label="<?= h($L('DevDetail_MainInfo_MQTTDevice','MQTT device')); ?>" title="<?= h($L('DevDetail_MainInfo_MQTTDevice','MQTT device')); ?>"><span class="pialert-bulk-mqtt-icon" aria-hidden="true"></span></span></span><?php endif; ?></div>
    <?php endwhile; ?></div><div class="d-flex justify-content-end mt-3"><button type="button" class="btn btn-warning" id="icmpSelectAll"><?= h($labels['selectAll']); ?></button></div></div></section>
  </form>
</section>
<?php else: ?>
<section id="icmpmonitor-page" aria-label="<?= h($title); ?>">
  <div class="row g-3 mb-4"><?php foreach (array(array('all','devicesAll',$labels['all'],'primary','fa-solid fa-laptop'),array('connected','devicesConnected',$labels['connected'],'success','mdi mdi-lan-connect'),array('favorites','devicesFavorites',$labels['favorites'],'warning','fa-solid fa-star'),array('down','devicesDown',$labels['down'],'danger','mdi mdi-lan-disconnect'),array('archived','devicesArchived',$labels['archived'],'secondary','fa-solid fa-eye-slash')) as [$status,$id,$label,$tone,$icon]): $widgetKey=array('all'=>'all','connected'=>'con','favorites'=>'fav','down'=>'dnw','archived'=>'arc')[$status]; if (!$icmpWidgets[$widgetKey]) continue; ?><div class="<?= h($icmpWidgetColumnClass); ?>"><button type="button" class="small-box text-bg-<?= h($tone); ?> icmp-filter w-100 border-0 text-start" data-icmp-status="<?= h($status); ?>" aria-pressed="false"><div class="inner"><h2 id="<?= h($id); ?>" class="mb-1">--</h2><p class="mb-0"><?= h($label); ?></p></div><i class="small-box-icon <?= h($icon); ?>" aria-hidden="true"></i></button></div><?php endforeach; ?></div>
  <?php if ($showHistory): ?><section class="card mb-4"><div class="card-header"><h2 class="card-title"><?= h($L('Device_Shortcut_OnlineChart_a','Online history ') . '12 ' . $L('Device_Shortcut_OnlineChart_b','hours')); ?></h2></div><div class="card-body"><div class="icmp-history-chart"><canvas id="OnlineChart"></canvas></div></div></section><?php endif; ?>
  <section id="tableDevicesBox" class="card card-primary card-outline" aria-labelledby="tableDevicesTitle"><div class="card-header d-flex align-items-center gap-2"><h2 id="tableDevicesTitle" class="card-title me-auto"><?= h($labels['all']); ?></h2><a href="<?= h(pialert_v4_route('ui_settings')); ?>#icmp-columns-settings" class="btn btn-sm btn-outline-secondary" aria-label="<?= h($pia_lang['V4_Configure_Table_Columns']); ?>" title="<?= h($pia_lang['V4_Configure_Table_Columns']); ?>"><i class="fa-solid fa-table-columns" aria-hidden="true"></i></a><a href="icmpmonitor.php?mod=bulkedit" class="btn btn-sm btn-outline-warning" aria-label="<?= h($L('Device_bulkEditor_mode','Bulk editor')); ?>"><i class="fa-solid fa-pencil" aria-hidden="true"></i></a></div><div class="card-body"><div class="table-responsive"><table id="tableDevices" class="table table-bordered table-hover table-striped align-middle w-100"><thead><tr>
    <?php foreach (pialert_v4_icmp_columns() as $columnId => $column):
      $label = $column['label'] === null ? $column['fallback'] : $L($column['label'], $column['fallback']);
      $isFavorite = $columnId === 'Favorite';
      $heading = $isFavorite ? $L('Device_TableHead_Favorite_Symbol', '⭐️') : $label;
    ?><th<?= $isFavorite ? ' aria-label="' . h($label) . '" title="' . h($label) . '"' : ''; ?>><?= h(pialert_v4_ui_plain_label($heading)); ?></th><?php endforeach; ?>
  </tr></thead></table></div></div></section>
  <div class="modal fade" id="icmp-host-modal" tabindex="-1" aria-labelledby="icmp-host-modal-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="icmp-host-form"><div class="modal-header"><h2 class="modal-title fs-5" id="icmp-host-modal-title"><?= h($L('ICMPMonitor_headline_IP','Add ICMP host')); ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h($pia_lang['Gen_Close']); ?>"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label" for="icmphost_ip"><?= h($L('ICMPMonitor_label_IP','Host IP')); ?></label><input class="form-control" id="icmphost_ip" required></div><div class="mb-3"><label class="form-label" for="icmphost_name"><?= h($L('ICMPMonitor_label_Hostname','Hostname')); ?></label><input class="form-control" id="icmphost_name"></div><?php foreach (array('insFavorite'=>$L('Device_TableHead_Favorite','Favorite'),'insAlertEvents'=>$L('WEBS_label_AlertEvents','All events'),'insAlertDown'=>$L('WEBS_label_AlertDown','Down')) as $id=>$label): ?><div class="form-check mb-2"><input class="form-check-input <?= $id==='insFavorite'?'icmp-check-favorite':($id==='insAlertDown'?'icmp-check-down':'icmp-check-events'); ?>" id="<?= h($id); ?>" type="checkbox"><label class="form-check-label" for="<?= h($id); ?>"><?= h($label); ?></label></div><?php endforeach; ?></div><div class="modal-footer"><button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal"><?= h($L('Gen_Close','Close')); ?></button><button type="submit" class="btn btn-primary" id="btnInsert"><?= h($L('Gen_Save','Save')); ?></button></div></form></div></div></div>
</section>
<?php endif; ?>
<?php pialert_v4_shell_end(array('lib/datatables/datatables.net-3.1.2/dataTables.min.js','lib/datatables/datatables.net-bs5-3.1.2/js/dataTables.bootstrap5.min.js','lib/chart.js-4.5.1/chart.umd.js','js/entity-actions-renderer.js','js/icmpmonitor.js')); ?>

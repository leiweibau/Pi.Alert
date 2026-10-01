<?php

if (!defined('PIALERT_V4_PUBLIC_ENTRY')) {
    http_response_code(404);
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
require_once __DIR__ . '/shell.php';
require_once PIALERT_V4_FRONT_ROOT . '/php/server/db.php';
require_once PIALERT_V4_FRONT_ROOT . '/php/server/graph.php';
require_once PIALERT_V4_FRONT_ROOT . '/php/server/journal.php';

$DBFILE = '../db/pialert.db';
OpenDB();

$requestedSource = $pageRequest['scansource'] ?? ($_GET['scansource'] ?? 'local');
$SCANSOURCE = is_string($requestedSource) && preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $requestedSource) ? $requestedSource : 'local';
$sourceLabel = pialert_v4_satellite_name($SCANSOURCE);
$predefined_filter = isset($pageRequest['predefined_filter']) && is_scalar($pageRequest['predefined_filter']) ? (string) $pageRequest['predefined_filter'] : '';
$filter_id = filter_var($pageRequest['filter_id'] ?? null, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
if ($filter_id === false) $filter_id = null;
$filter_fields = array();
foreach (array_filter(explode(',', is_scalar($pageRequest['filter_fields'] ?? null) ? (string) $pageRequest['filter_fields'] : ''), 'strlen') as $field) {
    if (ctype_digit($field) && (int) $field >= 0 && (int) $field <= 17) $filter_fields[] = (int) $field;
}
$filter_fields = array_values(array_unique($filter_fields));
$bulkMode = (($pageRequest['mod'] ?? '') === 'bulkedit');

/*
 * BEGIN UNCHANGED BULK BUSINESS LOGIC (front/devices.php lines 101-216).
 * The v4 redirect target is outside the copied block; database columns,
 * request fields, query, bindings and journal event remain unchanged.
 */
if ($bulkMode && (($pageRequest['savedata'] ?? '') === 'yes')) {
    $sql_queue = array();
    if ($pageRequest['en_bulk_owner'] == 'on') {
        $set_bulk_owner = htmlspecialchars($pageRequest['bulk_owner'], ENT_QUOTES);
        $sql_queue['dev_Owner'] = $set_bulk_owner;}
    if ($pageRequest['en_bulk_type'] == 'on') {
        $set_bulk_type = htmlspecialchars($pageRequest['bulk_type'], ENT_QUOTES);
        $sql_queue['dev_DeviceType'] = $set_bulk_type;}
    if ($pageRequest['en_bulk_group'] == 'on') {
        $set_bulk_group = htmlspecialchars($pageRequest['bulk_group'], ENT_QUOTES);
        $sql_queue['dev_Group'] = $set_bulk_group;}
    if ($pageRequest['en_bulk_location'] == 'on') {
        $set_bulk_location = htmlspecialchars($pageRequest['bulk_location'], ENT_QUOTES);
        $sql_queue['dev_Location'] = $set_bulk_location;}
    if ($pageRequest['en_bulk_comments'] == 'on') {
        $set_bulk_comments = htmlspecialchars($pageRequest['bulk_comments'], ENT_QUOTES);
        $sql_queue['dev_Comments'] = $set_bulk_comments;}
    if ($pageRequest['en_bulk_connectiontype'] == 'on') {
        $set_bulk_connectiontype = htmlspecialchars($pageRequest['bulk_connectiontype'], ENT_QUOTES);
        $sql_queue['dev_ConnectionType'] = $set_bulk_connectiontype;}
    if ($pageRequest['en_bulk_linkspeed'] == 'on') {
        $set_bulk_linkspeed = htmlspecialchars($pageRequest['bulk_linkspeed'], ENT_QUOTES);
        $sql_queue['dev_LinkSpeed'] = $set_bulk_linkspeed;}
    if ($pageRequest['en_bulk_AlertAllEvents'] == 'on') {
        if ($pageRequest['bulk_AlertAllEvents'] == 'on') {$set_bulk_AlertAllEvents = 1;} else { $set_bulk_AlertAllEvents = 0;}
        $sql_queue['dev_AlertEvents'] = $set_bulk_AlertAllEvents;}
    if ($pageRequest['en_bulk_AlertDown'] == 'on') {
        if ($pageRequest['bulk_AlertDown'] == 'on') {$set_bulk_AlertDown = 1;} else { $set_bulk_AlertDown = 0;}
        $sql_queue['dev_AlertDeviceDown'] = $set_bulk_AlertDown;}
    if ($pageRequest['en_bulk_NewDevice'] == 'on') {
        if ($pageRequest['bulk_NewDevice'] == 'on') {$set_bulk_NewDevice = 1;} else { $set_bulk_NewDevice = 0;}
        $sql_queue['dev_NewDevice'] = $set_bulk_NewDevice;}
    if ($pageRequest['en_bulk_Archived'] == 'on') {
        if ($pageRequest['bulk_Archived'] == 'on') {$set_bulk_Archived = 1;} else { $set_bulk_Archived = 0;}
        $sql_queue['dev_Archived'] = $set_bulk_Archived;}
    if ($pageRequest['en_bulk_PresencePage'] == 'on') {
        if ($pageRequest['bulk_PresencePage'] == 'on') {$set_bulk_PresencePage = 1;} else { $set_bulk_PresencePage = 0;}
        $sql_queue['dev_PresencePage'] = $set_bulk_PresencePage;}
    if ($pageRequest['en_bulk_MQTTDevice'] == 'on') {
        if ($pageRequest['bulk_MQTTDevice'] == 'on') {
            $set_bulk_MQTTDevice = 1;
            $sql_queue['dev_MQTTDevice'] = $set_bulk_MQTTDevice;
            $sql_queue['dev_MQTTDevice_cleanup'] = 0;
        } else { 
            $set_bulk_MQTTDevice = 0;
            $sql_queue['dev_MQTTDevice'] = $set_bulk_MQTTDevice;
            $sql_queue['dev_MQTTDevice_cleanup'] = 1;
        }
    }
    if (sizeof($sql_queue) >= 1) {
        $sql = 'SELECT dev_Name, dev_MAC FROM Devices ORDER BY dev_Name COLLATE NOCASE ASC';
        $results = $db->query($sql);
        while ($row = $results->fetchArray()) {
            $matched_mac = str_replace(" ", "_",$row['dev_MAC']);
            if (isset($pageRequest[$matched_mac])) {
                $modified_hosts = $modified_hosts . $row['dev_Name'] . '; ';
                $assignments = array();
                $parameters = array(':mac' => $row['dev_MAC']);
                $index = 0;
                foreach ($sql_queue as $column => $value) {
                    $placeholder = ':value_' . $index++;
                    $assignments[] = $column . ' = ' . $placeholder;
                    $parameters[$placeholder] = $value;
                }
                $results_update = db_execute_prepared($db, 'UPDATE Devices SET ' . implode(', ', $assignments) . ' WHERE dev_MAC = :mac', $parameters);
            }
        }
        pialert_logging('a_021', $_SERVER['REMOTE_ADDR'], 'LogStr_0002', '', $modified_hosts);
    }
    header('Location: ./devices.php?mod=bulkedit&scansource=' . rawurlencode((string) $SCANSOURCE) . '&saved=1', true, 303);
    exit;
}
/* END UNCHANGED BULK BUSINESS LOGIC. */

$uiSettings = pialert_v4_ui_read();
$historyEnabled = $uiSettings['appearance']['activity_history'];
$history = array(array(), array(), array(), array(), array());
if (!$bulkMode && $historyEnabled) $history = prepare_graph_arrays_history($SCANSOURCE);

$hiddenColumns = pialert_v4_ui_hidden_columns($uiSettings);
$deviceColumns = pialert_v4_device_columns();
$headerWidgets = $uiSettings['appearance']['header_widgets']['devices'];
$headerWidgetColumnClass = pialert_v4_header_widget_column_class($headerWidgets);

$L = static fn(string $key, string $fallback): string => (string) ($pia_lang[$key] ?? $fallback);
$title = $L('Device_Title', 'Devices') . ' / ' . $sourceLabel;
if ($predefined_filter !== '') $title .= ' (' . $predefined_filter . ')';
if ($bulkMode) $title .= ' - ' . $L('Device_bulkEditor_mode', 'Bulk editor');
$labels = array(
    'all'=>$L('Device_Shortcut_AllDevices','All devices'),'connected'=>$L('Device_Shortcut_Connected','Connected'),'favorites'=>$L('Device_Shortcut_Favorites','Favorites'),
    'new'=>$L('Device_Shortcut_NewDevices','New devices'),'down'=>$L('Device_Shortcut_DownAlerts','Down alerts'),'archived'=>$L('Device_Shortcut_Archived','Archived'),
    'devices'=>$L('Device_Shortcut_Devices','Devices'),'lengthAll'=>$L('Device_Tablelenght_all','All'),'lengthMenu'=>$L('Device_Tablelenght','Show _MENU_'),
    'search'=>$L('Device_Searchbox','Search'),'next'=>$L('Device_Table_nav_next','Next'),'previous'=>$L('Device_Table_nav_prev','Previous'),'info'=>$L('Device_Table_info','Showing _START_ to _END_ of _TOTAL_'),
    'wolTitle'=>$L('DevDetail_Tools_WOL_noti','Wake on LAN'),'wolText'=>$L('DevDetail_Tools_WOL_noti_text',''),'filterDeleteTitle'=>$L('Device_del_table_filter_noti','Delete filter'),
    'filterDeleteText'=>$L('Device_del_table_filter_noti_text',''),'filterSavedPrefix'=>$L('BE_Dev_table_filter_ok_a','The filter '),'filterDeletedPrefix'=>$L('BE_Dev_table_delfilter_ok','This filter has been deleted: '),'cancel'=>$L('Gen_Cancel','Cancel'),'run'=>$L('Gen_Run','Run'),'delete'=>$L('Gen_Delete','Delete'),
    'selectAll'=>$L('Device_bulkEditor_selectall','Select all'),'selectNone'=>$L('Device_bulkEditor_selectnone','Select none'),'selectVisible'=>$L('Device_bulkEditor_selectvisall','Select visible'),
    'selectVisibleNone'=>$L('Device_bulkEditor_selectvisnone','Select no visible'),'allDevices'=>$L('Device_Shortcut_AllDevices','All devices'),'newDevices'=>$L('Device_Shortcut_NewDevices','New devices'),
    'bulkDeleteTitle'=>$L('Device_bulkDel_info_head','Delete devices'),'bulkDeleteText'=>$L('Device_bulkDel_info_text',''),
    'type'=>$L('Device_TableHead_Type','Type'),'lastIp'=>$L('Device_TableHead_LastIP','Last IP'),'mac'=>$L('Device_TableHead_MACaddress','MAC address'),
    'status'=>$L('Device_TableHead_Status','Status'),'details'=>$L('DevDetail_Tab_Details','Details'),'empty'=>$pia_lang['V4_No_Data'],
    'deleteDeviceTitle'=>$L('DevDetail_button_Delete','Delete device'),'deleteDeviceWarning'=>$L('DevDetail_button_Delete_Warning','Delete this device?')
);
$pageConfig = array('scanSource'=>$SCANSOURCE,'predefinedFilter'=>$predefined_filter,'filterId'=>$filter_id,'filterFields'=>$filter_fields,'hiddenColumns'=>$hiddenColumns,'columnIds'=>array_keys($deviceColumns),'pageLength'=>$uiSettings['devices']['page_length'],'order'=>pialert_v4_ui_numeric_order($uiSettings),'labels'=>$labels,
    'history'=>array('time'=>array_reverse($history[0]),'down'=>array_reverse($history[1]),'online'=>array_reverse($history[3]),'archived'=>array_reverse($history[4])));

pialert_v4_shell_start($title, 'home', array('lib/datatables/datatables.net-bs5-3.1.2/css/dataTables.bootstrap5.min.css','css/devices.css','css/entity-actions.css'));
?>
<script type="application/json" id="devices-page-config"><?= json_encode($pageConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<?php if ($bulkMode): require __DIR__ . '/devices-page-bulk.php'; else: require __DIR__ . '/devices-page-list.php'; endif; ?>
<?php pialert_v4_shell_end(array('lib/datatables/datatables.net-3.1.2/dataTables.min.js','lib/datatables/datatables.net-bs5-3.1.2/js/dataTables.bootstrap5.min.js','lib/chart.js-4.5.1/chart.umd.js','js/entity-actions-renderer.js','js/devices.js')); ?>

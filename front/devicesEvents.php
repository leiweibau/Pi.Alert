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

pialert_v4_load_language();
require_once __DIR__ . '/php/shell.php';

$title = $pia_lang['EVE_Title'] ?? 'Events';
$eventCards = array(
    array('all', 'eventsAll', $pia_lang['EVE_Shortcut_AllEvents'] ?? 'All events', 'primary', 'fa-solid fa-bolt'),
    array('sessions', 'eventsSessions', $pia_lang['EVE_Shortcut_Sessions'] ?? 'Sessions', 'success', 'mdi mdi-lan-connect'),
    array('missing', 'eventsMissing', $pia_lang['EVE_Shortcut_MissSessions'] ?? 'Missing sessions', 'warning', 'fa-solid fa-arrow-right-arrow-left'),
    array('voided', 'eventsVoided', $pia_lang['EVE_Shortcut_VoidSessions'] ?? 'Voided sessions', 'warning', 'fa-solid fa-circle-exclamation'),
    array('new', 'eventsNewDevices', $pia_lang['EVE_Shortcut_NewDevices'] ?? 'New devices', 'warning', 'fa-solid fa-plus'),
    array('down', 'eventsDown', $pia_lang['EVE_Shortcut_DownAlerts'] ?? 'Down alerts', 'danger', 'mdi mdi-lan-disconnect'),
);

pialert_v4_shell_start($title, 'events', array(
    'lib/datatables/datatables.net-bs5-3.1.2/css/dataTables.bootstrap5.min.css',
    'css/devices-events.css',
));
?>
<section id="devices-events-page"
  data-title-all="<?= h($pia_lang['EVE_Shortcut_AllEvents'] ?? 'All events'); ?>"
  data-title-sessions="<?= h($pia_lang['EVE_Shortcut_Sessions'] ?? 'Sessions'); ?>"
  data-title-missing="<?= h($pia_lang['EVE_Shortcut_MissSessions'] ?? 'Missing sessions'); ?>"
  data-title-voided="<?= h($pia_lang['EVE_Shortcut_VoidSessions'] ?? 'Voided sessions'); ?>"
  data-title-new="<?= h($pia_lang['EVE_Shortcut_NewDevices'] ?? 'New devices'); ?>"
  data-title-down="<?= h($pia_lang['EVE_Shortcut_DownAlerts'] ?? 'Down alerts'); ?>"
  data-title-events="<?= h($pia_lang['EVE_Shortcut_Events'] ?? 'Events'); ?>"
  data-length-menu="<?= h($pia_lang['EVE_Tablelenght'] ?? 'Show _MENU_ entries'); ?>"
  data-search="<?= h($pia_lang['EVE_Searchbox'] ?? 'Search'); ?>"
  data-next="<?= h($pia_lang['EVE_Table_nav_next'] ?? 'Next'); ?>"
  data-previous="<?= h($pia_lang['EVE_Table_nav_prev'] ?? 'Previous'); ?>"
  data-info="<?= h($pia_lang['EVE_Table_info'] ?? 'Showing _START_ to _END_ of _TOTAL_ entries'); ?>">
  <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-sm-end mb-3 gap-2">
    <label class="form-label fw-semibold mb-0" for="period"><?= h($pia_lang['EVE_Period'] ?? 'Period'); ?></label>
    <select class="form-select pialert-events-period" id="period">
      <option value="1 day"><?= h($pia_lang['EVE_Periodselect_today'] ?? 'Today'); ?></option>
      <option value="7 days"><?= h($pia_lang['EVE_Periodselect_LastWeek'] ?? 'Last week'); ?></option>
      <option value="1 month" selected><?= h($pia_lang['EVE_Periodselect_LastMonth'] ?? 'Last month'); ?></option>
      <option value="1 year"><?= h($pia_lang['EVE_Periodselect_LastYear'] ?? 'Last year'); ?></option>
      <option value="100 years"><?= h($pia_lang['EVE_Periodselect_All'] ?? 'All'); ?></option>
    </select>
  </div>

  <div class="row g-3 mb-4 pialert-event-widgets" aria-label="<?= h($title); ?>">
    <?php foreach ($eventCards as [$type, $countId, $label, $tone, $icon]): ?>
    <div class="col-6 col-md-4 col-xl-2">
      <button type="button" class="small-box text-bg-<?= h($tone); ?> pialert-event-filter w-100 border-0 text-start" data-event-type="<?= h($type); ?>" aria-pressed="false">
        <div class="inner"><h2 id="<?= h($countId); ?>" class="mb-1">--</h2><p class="mb-0"><?= h($label); ?></p></div>
        <i class="small-box-icon <?= h($icon); ?>" aria-hidden="true"></i>
      </button>
    </div>
    <?php endforeach; ?>
  </div>

  <section id="tableEventsBox" class="card card-primary card-outline" aria-labelledby="tableEventsTitle">
    <div class="card-header"><h2 id="tableEventsTitle" class="card-title"><?= h($pia_lang['EVE_Shortcut_AllEvents'] ?? 'All events'); ?></h2></div>
    <div class="card-body">
      <div class="table-responsive">
        <table id="tableEvents" class="table table-bordered table-hover table-striped align-middle w-100">
          <thead><tr>
            <th><?= h($pia_lang['EVE_TableHead_Order'] ?? 'Order'); ?></th>
            <th><?= h($pia_lang['EVE_TableHead_Device'] ?? 'Device'); ?></th>
            <th><?= h($pia_lang['EVE_TableHead_Owner'] ?? 'Owner'); ?></th>
            <th><?= h($pia_lang['EVE_TableHead_Date'] ?? 'Date'); ?></th>
            <th><?= h($pia_lang['EVE_TableHead_EventType'] ?? 'Event type'); ?></th>
            <th><?= h($pia_lang['EVE_TableHead_Connection'] ?? 'Connection'); ?></th>
            <th><?= h($pia_lang['EVE_TableHead_Disconnection'] ?? 'Disconnection'); ?></th>
            <th><?= h($pia_lang['EVE_TableHead_Duration'] ?? 'Duration'); ?></th>
            <th><?= h($pia_lang['EVE_TableHead_DurationOrder'] ?? 'Duration order'); ?></th>
            <th><?= h($pia_lang['EVE_TableHead_IP'] ?? 'IP'); ?></th>
            <th><?= h($pia_lang['EVE_TableHead_IPOrder'] ?? 'IP order'); ?></th>
            <th><?= h($pia_lang['EVE_TableHead_AdditionalInfo'] ?? 'Additional info'); ?></th>
          </tr></thead>
        </table>
      </div>
    </div>
  </section>
</section>
<?php pialert_v4_shell_end(array(
    'lib/datatables/datatables.net-3.1.2/dataTables.min.js',
    'lib/datatables/datatables.net-bs5-3.1.2/js/dataTables.bootstrap5.min.js',
    'js/devices-events.js',
)); ?>

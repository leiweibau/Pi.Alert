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
require_once __DIR__ . '/php/server/db.php';

function pialert_v4_service_state(array $service): string {
    $status = (string) ($service['mon_LastStatus'] ?? '0');
    $latency = (string) ($service['mon_LastLatency'] ?? '');
    if ($status === '0' || $latency === '99999999') return 'down';
    if (str_starts_with($status, '2')) return 'online';
    return 'warning';
}

function pialert_v4_service_status_class(string $state): string {
    return match ($state) {
        'online' => 'text-bg-success',
        'warning' => 'text-bg-warning',
        default => 'text-bg-danger',
    };
}

function pialert_v4_service_external_url(string $url): string {
    return preg_match('#^https?://#i', $url) === 1 ? $url : '';
}

$db = new SQLite3('../db/pialert.db', SQLITE3_OPEN_READONLY);
$devices = array();
$deviceResult = $db->query('SELECT dev_MAC, dev_Name FROM Devices ORDER BY dev_Name ASC');
while ($deviceResult && ($device = $deviceResult->fetchArray(SQLITE3_ASSOC))) {
    $devices[(string) $device['dev_MAC']] = (string) $device['dev_Name'];
}

$statusCodeFile = __DIR__ . '/lib/http-status-code/index.json';
$statusCodes = json_decode((string) @file_get_contents($statusCodeFile), true);
if (!is_array($statusCodes)) $statusCodes = array();

$services = array();
$groups = array();
$counts = array('all' => 0, 'online' => 0, 'warning' => 0, 'down' => 0);
$serviceResult = $db->query('SELECT * FROM Services ORDER BY mon_Tags COLLATE NOCASE ASC');
while ($serviceResult && ($service = $serviceResult->fetchArray(SQLITE3_ASSOC))) {
    $url = (string) ($service['mon_URL'] ?? '');
    $state = pialert_v4_service_state($service);
    $counts['all']++;
    $counts[$state]++;
    $service['v4_state'] = $state;
    $service['v4_history'] = array();

    $historyResult = db_execute_prepared($db,
        'SELECT * FROM Services_Events WHERE moneve_URL = :url ORDER BY moneve_DateTime DESC LIMIT 18',
        array(':url' => (string) $url));
    while ($historyResult && ($history = $historyResult->fetchArray(SQLITE3_ASSOC))) {
        array_unshift($service['v4_history'], $history);
    }
    while (count($service['v4_history']) < 18) array_unshift($service['v4_history'], null);

    $mac = (string) ($service['mon_MAC'] ?? '');
    $groupKey = $mac === '' ? '__standalone__' : $mac;
    if (!isset($groups[$groupKey])) $groups[$groupKey] = array();
    $groups[$groupKey][] = count($services);
    $services[] = $service;
}

$geoDbPath = __DIR__ . '/../db/GeoLite2-Country.mmdb';
$geoDbInstalled = is_file($geoDbPath);
$geoDbSize = $geoDbInstalled ? number_format((float) filesize($geoDbPath) / 1048576, 2) . ' MB' : '';
$title = $pia_lang['WEBS_Title'] ?? 'Web Services';

pialert_v4_shell_start($title, 'services', array(
    'lib/datatables/datatables.net-bs5-1.10.25/css/dataTables.bootstrap5.min.css',
    'css/services.css',
), static fn(): string => '<button type="button" id="add-service" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#service-editor-modal"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>' . h($GLOBALS['pia_lang']['V4_New_Service']) . '</button>');
?>
<section id="services-page"
  data-services-endpoint="php/server/services.php"
  data-cancel="<?= h($pia_lang['Gen_Cancel'] ?? 'Cancel'); ?>"
  data-delete="<?= h($pia_lang['Gen_Delete'] ?? 'Delete'); ?>"
  data-delete-title="<?= h($pia_lang['WEBS_button_Delete_label'] ?? 'Delete Service'); ?>"
  data-delete-message="<?= h($pia_lang['WEBS_button_Delete_Warning'] ?? 'Are you sure you want to delete this web service?'); ?>"
  data-details-route="serviceDetails.php">

  <div class="services-toolbar d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="btn-group flex-wrap" role="group" aria-label="<?= h($pia_lang['V4_Service_Status_Filter']); ?>" id="services-status-filter">
      <button type="button" class="btn btn-primary active" data-service-filter="all" aria-pressed="true"><?= h($pia_lang['WEBS_EVE_Shortcut_All'] ?? 'All'); ?> <span class="badge text-bg-light ms-1"><?= h((string) $counts['all']); ?></span></button>
      <button type="button" class="btn btn-outline-success" data-service-filter="online" aria-pressed="false"><?= h($pia_lang['WEBS_EVE_Shortcut_HTTP2xx'] ?? 'Online'); ?> <span class="badge text-bg-success ms-1"><?= h((string) $counts['online']); ?></span></button>
      <button type="button" class="btn btn-outline-warning" data-service-filter="warning" aria-pressed="false"><?= h($pia_lang['V4_Warning']); ?> <span class="badge text-bg-warning ms-1"><?= h((string) $counts['warning']); ?></span></button>
      <button type="button" class="btn btn-outline-danger" data-service-filter="down" aria-pressed="false"><?= h($pia_lang['WEBS_EVE_Shortcut_Down'] ?? 'Down'); ?> <span class="badge text-bg-danger ms-1"><?= h((string) $counts['down']); ?></span></button>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
      <section class="card card-primary card-outline h-100" aria-labelledby="services-journal-title">
        <div class="card-header"><h2 id="services-journal-title" class="card-title mb-0"><?= h($pia_lang['WEBS_EVE_Title'] ?? 'Web Services - Events'); ?></h2></div>
        <div class="card-body"><div class="table-responsive services-journal-wrap">
          <table id="servicesJournalTable" class="table table-bordered table-hover table-striped align-middle w-100">
            <thead><tr><th><?= h($pia_lang['EVE_TableHead_Date'] ?? 'Date'); ?></th><th><?= h($pia_lang['WEBS_label_URL'] ?? 'URL'); ?></th><th><?= h($pia_lang['EVE_TableHead_AdditionalInfo'] ?? 'Additional info'); ?></th></tr></thead>
          </table>
        </div></div>
      </section>
    </div>
    <div class="col-12 col-xl-4">
      <aside id="services-geodb-status" class="card card-info card-outline h-100" aria-labelledby="services-geodb-title">
        <div class="card-header"><h2 id="services-geodb-title" class="card-title mb-0"><?= h($pia_lang['GeoLiteDB_Title'] ?? 'GeoLite2 DB'); ?></h2></div>
        <div class="card-body d-flex flex-column justify-content-center">
          <p class="mb-2"><i class="fa-solid <?= $geoDbInstalled ? 'fa-circle-check text-success' : 'fa-circle-xmark text-danger'; ?> me-2" aria-hidden="true"></i><strong><?= $geoDbInstalled ? h($pia_lang['GeoLiteDB_cur'] ?? 'GeoLite2 DB loaded') : h($pia_lang['GeoLiteDB_absent'] ?? 'DB not installed'); ?></strong></p>
          <?php if ($geoDbInstalled): ?><p class="mb-2"><span class="font-monospace"><?= h(date('Y-m-d H:i:s', (int) filemtime($geoDbPath))); ?></span> · <?= h($geoDbSize); ?></p><?php endif; ?>
          <p class="text-body-secondary mb-0"><?= h($pia_lang['GeoLiteDB_Installnotes'] ?? 'Location details are available on the service details page.'); ?></p>
        </div>
      </aside>
    </div>
  </div>

  <div id="services-card-groups">
  <?php foreach ($groups as $groupKey => $serviceIndexes):
      $groupLabel = $groupKey === '__standalone__'
          ? ($pia_lang['WEBS_BoxTitle_General'] ?? 'General')
          : (($devices[$groupKey] ?? '') !== '' ? $devices[$groupKey] : ($pia_lang['WEBS_unknown_Device'] ?? 'Unknown Device') . ' (' . $groupKey . ')');
  ?>
    <section class="card services-group mb-3" data-service-group aria-labelledby="service-group-<?= h(substr(hash('sha256', $groupKey), 0, 12)); ?>">
      <div class="card-header"><h2 class="card-title mb-0" id="service-group-<?= h(substr(hash('sha256', $groupKey), 0, 12)); ?>"><?= h($groupLabel); ?></h2></div>
      <div class="card-body"><div class="services-card-grid">
      <?php foreach ($serviceIndexes as $serviceIndex):
          $service = $services[$serviceIndex];
          $url = (string) $service['mon_URL'];
          $status = (string) ($service['mon_LastStatus'] ?? '0');
          $state = (string) $service['v4_state'];
          $parts = parse_url($url);
          $protocol = strtoupper((string) ($parts['scheme'] ?? 'HTTP'));
          $displayUrl = isset($parts['host']) ? (($parts['host'] ?? '') . ($parts['path'] ?? '') . (isset($parts['query']) ? '?' . $parts['query'] : '')) : $url;
          $statusDescription = (string) ($statusCodes[$status]['description'] ?? $pia_lang['V4_No_Status_Code']);
          $externalUrl = pialert_v4_service_external_url($url);
          $notificationLabels = array_filter(array(
              (int) ($service['mon_AlertEvents'] ?? 0) ? ($pia_lang['WEBS_EVE_all'] ?? 'All Events') : '',
              (int) ($service['mon_AlertDown'] ?? 0) ? ($pia_lang['WEBS_EVE_down'] ?? 'Down') : '',
              (int) ($service['mon_AlertUp'] ?? 0) ? ($pia_lang['WEBS_EVE_up'] ?? 'Up') : '',
          ));
          $notificationText = $notificationLabels ? implode(', ', $notificationLabels) : ($pia_lang['WEBS_EVE_none'] ?? 'none');
      ?>
        <article class="service-card card shadow-sm" data-service-card data-service-state="<?= h($state); ?>" data-service-url="<?= h($url); ?>">
          <div class="card-body p-0 d-flex">
            <div class="service-status-panel <?= h(pialert_v4_service_status_class($state)); ?>" title="<?= h($statusDescription); ?>" data-bs-toggle="tooltip">
              <span class="service-protocol"><?= h($protocol); ?></span><strong class="service-code"><?= h($status); ?></strong><i class="fa-solid fa-globe" aria-hidden="true"></i>
            </div>
            <div class="service-card-content flex-grow-1 p-3 min-w-0">
              <div class="d-flex align-items-start gap-2">
                <a class="service-title text-truncate" href="serviceDetails.php?url=<?= rawurlencode($url); ?>" title="<?= h($url); ?>"><?= h($displayUrl); ?></a>
                <span class="badge text-bg-secondary ms-auto"><?= h((string) ($service['mon_Tags'] ?? '')); ?></span>
              </div>
              <div class="service-history my-3" aria-label="<?= h($pia_lang['V4_Service_Checks_18']); ?>">
              <?php for ($historyIndex = 0; $historyIndex < 18; $historyIndex++):
                  $history = $service['v4_history'][$historyIndex] ?? null;
                  $historyStatus = is_array($history) ? (string) ($history['moneve_StatusCode'] ?? '0') : '';
                  $historyLatency = is_array($history) ? (string) ($history['moneve_Latency'] ?? '') : '';
                  $historyState = $history === null ? 'empty' : pialert_v4_service_state(array('mon_LastStatus' => $historyStatus, 'mon_LastLatency' => $historyLatency));
                  $historyTitle = $history === null ? '' : ((string) ($history['moneve_DateTime'] ?? '') . ' / HTTP: ' . $historyStatus . ' / Latency: ' . ($historyLatency === '99999999' ? 'offline' : $historyLatency . 's'));
              ?><span class="service-history-segment service-history-<?= h($historyState); ?>" title="<?= h($historyTitle); ?>"<?= $historyTitle !== '' ? ' data-bs-toggle="tooltip"' : ''; ?>></span><?php endfor; ?>
              </div>
              <div class="d-flex flex-wrap align-items-center gap-2 small">
                <span><i class="fa-solid fa-location-dot me-1" aria-hidden="true"></i><?= h((string) ($service['mon_TargetIP'] ?? '')); ?></span>
                <span><i class="fa-regular <?= $notificationLabels ? 'fa-bell' : 'fa-bell-slash'; ?> me-1" aria-hidden="true"></i><?= h($notificationText); ?></span>
                <span class="ms-auto d-flex gap-1 service-actions">
                  <?php if ($externalUrl !== ''): ?><a class="btn btn-sm btn-outline-secondary" href="<?= h($externalUrl); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= h($pia_lang['V4_Open_Service']); ?>"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a><?php endif; ?>
                  <a class="btn btn-sm btn-outline-warning service-edit-link" href="serviceDetails.php?url=<?= rawurlencode($url); ?>" aria-label="<?= h($pia_lang['V4_Edit_Service']); ?>" title="<?= h($pia_lang['V4_Edit_Service']); ?>"><i class="fa-solid fa-pencil" aria-hidden="true"></i></a>
                  <button type="button" class="btn btn-sm btn-outline-danger delete-service" data-url="<?= h($url); ?>" aria-label="<?= h($pia_lang['WEBS_button_Delete_label']); ?>"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                </span>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
      </div></div>
    </section>
  <?php endforeach; ?>
    <div id="services-empty-filter" class="alert alert-secondary" hidden><?= h($pia_lang['V4_No_Services_Filter']); ?></div>
  </div>

  <div class="modal fade" id="service-editor-modal" tabindex="-1" aria-labelledby="service-editor-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
      <form id="service-editor-form">
        <div class="modal-header"><h2 class="modal-title fs-5" id="service-editor-title"><?= h($pia_lang['WEBS_headline_NewService'] ?? 'New Web Service'); ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h($pia_lang['Gen_Close']); ?>"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label" for="serviceURL"><?= h($pia_lang['WEBS_label_URL'] ?? 'URL'); ?></label><input type="url" class="form-control" id="serviceURL" required placeholder="https://example.test/"></div>
          <div class="mb-3"><label class="form-label" for="serviceTag"><?= h($pia_lang['WEBS_label_Tags'] ?? 'Tag'); ?></label><input type="text" class="form-control" id="serviceTag"></div>
          <div class="mb-3"><label class="form-label" for="serviceMAC"><?= h($pia_lang['WEBS_label_MAC'] ?? 'Device'); ?></label><input type="text" class="form-control" id="serviceMAC" list="service-device-options"><datalist id="service-device-options"><?php foreach ($devices as $mac => $name): ?><option value="<?= h($mac); ?>"><?= h($name); ?></option><?php endforeach; ?></datalist></div>
          <div class="form-check form-switch mb-2"><input class="form-check-input" id="insAlertEvents" type="checkbox"><label class="form-check-label" for="insAlertEvents"><?= h($pia_lang['WEBS_label_AlertEvents'] ?? 'All Events'); ?></label></div>
          <div class="form-check form-switch mb-2"><input class="form-check-input" id="insAlertUp" type="checkbox"><label class="form-check-label" for="insAlertUp"><?= h($pia_lang['WEBS_label_AlertUp'] ?? 'Up'); ?></label></div>
          <div class="form-check form-switch"><input class="form-check-input" id="insAlertDown" type="checkbox"><label class="form-check-label" for="insAlertDown"><?= h($pia_lang['WEBS_label_AlertDown'] ?? 'Down'); ?></label></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal"><?= h($pia_lang['Gen_Close'] ?? 'Close'); ?></button><button type="submit" id="save-service" class="btn btn-primary"><?= h($pia_lang['Gen_Save'] ?? 'Save'); ?></button></div>
      </form>
    </div></div>
  </div>
</section>
<?php pialert_v4_shell_end(array(
    'lib/datatables/datatables.net-1.10.25/jquery.dataTables.min.js',
    'lib/datatables/datatables.net-bs5-1.10.25/js/dataTables.bootstrap5.min.js',
    'js/services.js',
)); ?>

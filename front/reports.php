<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('pcre.jit', '0');

define('PIALERT_V4_PUBLIC_ENTRY', true);
require_once __DIR__ . '/php/bootstrap.php';
pialert_v4_start_session();

if (($_SESSION['login'] ?? 0) != 1) {
    header('Location: ' . pialert_v4_route('login'));
    exit;
}

pialert_v4_load_language();
require __DIR__ . '/php/server/db.php';
$DBFILE = '../db/pialert.db';
OpenDB();
require_once __DIR__ . '/php/server/journal.php';
require_once __DIR__ . '/php/shell.php';

function pialert_v4_report_filename($value): ?string {
    if (!is_string($value) || strlen($value) > 240 || preg_match('/\A[0-9]+-[0-9]+_[\p{L}\p{N} _()\-]+\z/uD', $value) !== 1) return null;
    return $value . '.txt';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    pialert_validate_csrf();
    $source = ($_POST['report_source'] ?? '') === 'archive' ? 'archive' : '';
    $filename = pialert_v4_report_filename($_POST['remove_report'] ?? $_POST['archive_report'] ?? null);
    if ($filename !== null) {
        if (isset($_POST['remove_report'])) {
            $path = $source === 'archive' ? './reports/archived/' . $filename : './reports/' . $filename;
            if (is_file($path) && unlink($path)) {
                pialert_logging('a_050', $_SERVER['REMOTE_ADDR'], $source === 'archive' ? 'LogStr_0505' : 'LogStr_0503', '', $filename);
            }
        } elseif (isset($_POST['archive_report']) && $source === '') {
            $from = './reports/' . $filename;
            $to = './reports/archived/' . $filename;
            if (is_file($from) && !file_exists($to) && rename($from, $to)) {
                pialert_logging('a_050', $_SERVER['REMOTE_ADDR'], 'LogStr_0507', '', $filename);
            }
        }
    }
    header('Location: ' . pialert_v4_route('reports') . ($source === 'archive' ? '?report_source=archive' : ''), true, 303);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method Not Allowed');
}

function pialert_v4_report_colors(): array {
    global $db;
    $defaults = array('#30bbbb', '#d81b60', '#00c0ef', '#831cff', '#00a65a', '#cc6600');
    $result = $db->query("SELECT par_Long_Value FROM Parameters WHERE par_ID = 'report_headline_colors'");
    $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
    $colors = $row ? explode(',', (string) $row['par_Long_Value']) : array();
    foreach ($defaults as $index => $fallback) {
        if (!isset($colors[$index]) || preg_match('/^#[0-9a-f]{6}$/i', (string) $colors[$index]) !== 1) $colors[$index] = $fallback;
    }
    return array_slice($colors, 0, 6);
}

function pialert_v4_report_config(string $name) {
    $contents = @file_get_contents('../config/pialert.conf');
    if (!is_string($contents)) return false;
    $values = @parse_ini_string(preg_replace('/^\s*#.*$/m', '', $contents));
    return is_array($values) && array_key_exists($name, $values) ? $values[$name] : false;
}

function pialert_v4_report_class(string $filename): ?array {
    $headtitle = explode('-', $filename);
    $headeventtype = explode('_', $filename);
    if (!isset($headtitle[1], $headeventtype[1])) return null;
    $name = substr($headeventtype[1], 0, -4);
    $types = array(
        'Events' => 'arp', 'Devices Down' => 'arp', 'New Devices' => 'arp',
        'Internet' => 'internet',
        'Services Events' => 'webmon', 'Services Down' => 'webmon', 'Services Up' => 'webmon',
        'Host Down (ICMP Monitoring)' => 'icmpmon', 'Host Events (ICMP Monitoring)' => 'icmpmon',
        'Test' => 'test', 'Nmap' => 'nmap', 'Rogue DHCP Server' => 'rogueDHCP',
    );
    if (!isset($types[$name])) return null;
    $stamp = substr($headtitle[0], 6, 2) . '.' . substr($headtitle[0], 4, 2) . '.' . substr($headtitle[0], 2, 2)
        . '/' . substr($headtitle[1], 0, 2) . ':' . substr($headtitle[1], 2, 2);
    return array($name, $types[$name], $stamp);
}

function pialert_v4_ssl_tooltip($code): string {
    global $pia_lang;
    $code = (int) $code;
    $parts = array();
    if ($code >= 8) { $parts[] = $pia_lang['V4_Subject']; $code -= 8; }
    if ($code >= 4) { $parts[] = $pia_lang['V4_Issuer']; $code -= 4; }
    if ($code >= 2) { $parts[] = $pia_lang['V4_Valid_From']; $code -= 2; }
    if ($code >= 1) $parts[] = $pia_lang['V4_Valid_To']; else $parts[] = $pia_lang['V4_None'];
    return $pia_lang['V4_Values_Changed'] . ': ' . implode(', ', $parts);
}

function pialert_v4_standard_report(string $path): string {
    $lines = file($path);
    if ($lines === false) return '';
    $output = '';
    $count = count($lines);
    foreach ($lines as $index => $line) {
        if (($index + 1) < ($count - 1)) {
            if (stristr($line, 'MAC:')) {
                $parts = explode(': ', $line, 2);
                $mac = trim((string) ($parts[1] ?? ''));
                $label = $mac;
                if (strpos($mac, 'Internet') === 0) $label = strlen($mac) > 22 ? substr($mac, 0, 22) . "...\n" : "Internet\n";
                $output .= "\tMAC: <a href=\"./deviceDetails.php?mac=" . rawurlencode($mac) . '">' . h($label) . '</a>';
            } elseif (stristr($line, 'Service:')) {
                $parts = explode(': ', $line, 2);
                $url = trim((string) ($parts[1] ?? ''));
                $output .= 'Service: <a href="./serviceDetails.php?url=' . rawurlencode($url) . '">' . h($url) . "</a>\n";
            } elseif (stristr($line, 'Event:')) {
                $parts = explode(': ', $line, 2);
                $event = trim((string) ($parts[1] ?? ''));
                $class = $event === 'Disconnected' ? 'text-danger' : ($event === 'Connected' ? 'text-success' : '');
                $output .= "\tEvent:\t\t" . ($class ? '<span class="' . $class . '">' . h($event) . '</span>' : h($event)) . "\n";
            } elseif (stristr($line, "\tHTTP Status Code:")) {
                $parts = explode(': ', $line, 2);
                $status = trim((string) ($parts[1] ?? ''));
                $output .= "\tHTTP Status Code:\t<span class=\"" . ($status === '200' ? 'text-success' : 'text-danger') . '">' . h($status) . "</span>\n";
            } elseif (stristr($line, "\tSSL Status:")) {
                $parts = explode(': ', $line, 2);
                $status = trim((string) ($parts[1] ?? ''));
                $output .= "\t<span class=\"pialert-report-tooltip\" data-bs-toggle=\"tooltip\" title=\"" . h(pialert_v4_ssl_tooltip($status)) . "\">SSL Status:\t\t<span class=\"" . ($status === '0' ? 'text-success' : 'text-danger') . '">' . h($status) . "</span></span>\n";
            } else $output .= h($line);
        } elseif (trim($line) !== '') $output .= h($line);
    }
    return $output;
}

function pialert_v4_icmp_report(string $path): string {
    global $pia_lang;
    $lines = file($path);
    if ($lines === false) return '';
    $output = '';
    foreach ($lines as $line) {
        if (stristr($line, 'IP:')) {
            $parts = explode(': ', $line, 2); $ip = trim((string) ($parts[1] ?? ''));
            $output .= 'IP: <a href="./icmpmonitorDetails.php?hostip=' . rawurlencode($ip) . '">' . h($ip) . "</a>\n";
        } elseif (stristr($line, 'Status:')) {
            $parts = explode(':', $line, 2); $status = trim((string) ($parts[1] ?? ''));
            $display = $status === 'Down' ? '<span class="text-danger">' . h($pia_lang['V4_Disconnected']) . '</span>' : ($status === 'Up' ? '<span class="text-success">' . h($pia_lang['Device_Shortcut_Connected']) . '</span>' : h($status));
            $output .= "\t" . h($pia_lang['V4_Status']) . ":\t\t" . $display . "\n";
        } else $output .= h($line);
    }
    return $output;
}

function pialert_v4_report_actions(string $filename, bool $archived): void {
    global $pia_lang;
    $report = substr($filename, 0, -4);
    ?>
    <div class="card-footer d-flex flex-wrap justify-content-center gap-2">
      <a class="btn btn-sm btn-success pialert-report-download" href="./download/report.php?report=<?= rawurlencode($report); ?><?= $archived ? '&amp;report_source=archive' : ''; ?>" target="_blank" rel="noopener" aria-label="<?= h($pia_lang['V4_Download']); ?>"><i class="fa-solid fa-download" aria-hidden="true"></i></a>
      <form method="post" action="./reports.php" class="pialert-report-action-form" data-action-label="delete">
        <input type="hidden" name="_csrf" value="<?= h(pialert_csrf_token()); ?>"><?php if ($archived): ?><input type="hidden" name="report_source" value="archive"><?php endif; ?>
        <input type="hidden" name="remove_report" value="<?= h($report); ?>"><button type="submit" class="btn btn-sm btn-danger" aria-label="<?= h($pia_lang['Gen_Delete']); ?>"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
      </form>
      <form method="post" action="./reports.php" class="pialert-report-action-form" data-action-label="archive">
        <input type="hidden" name="_csrf" value="<?= h(pialert_csrf_token()); ?>"><input type="hidden" name="archive_report" value="<?= h($report); ?>"><button type="submit" class="btn btn-sm btn-secondary" aria-label="<?= h($pia_lang['V4_Archive']); ?>"<?= $archived ? ' disabled' : ''; ?>><i class="fa-regular fa-folder" aria-hidden="true"></i></button>
      </form>
    </div>
    <?php
}

$colors = pialert_v4_report_colors();
$source = (($_GET['report_source'] ?? '') === 'archive') ? 'archive' : '';
$archived = $source === 'archive';
$directory = $archived ? './reports/archived/' : './reports/';
$entries = is_dir($directory) ? scandir($directory) : false;
$files = $entries === false ? array() : array_values(array_diff($entries, array('.', '..', 'archived')));
rsort($files);
$reports = array();
$colorIndex = array('internet' => 0, 'arp' => 1, 'webmon' => 2, 'icmpmon' => 3, 'test' => 4, 'nmap' => 5);
$icon = array('internet' => 'fa-solid fa-globe', 'arp' => 'fa-solid fa-laptop', 'webmon' => 'fa-solid fa-server', 'icmpmon' => 'fa-solid fa-laptop', 'test' => 'fa-regular fa-envelope', 'nmap' => 'fa-solid fa-magnifying-glass', 'rogueDHCP' => 'fa-solid fa-triangle-exclamation');
foreach ($files as $filename) {
    if (substr(strtolower($filename), -4) !== '.txt') continue;
    $class = pialert_v4_report_class($filename);
    if ($class === null) continue;
    [$name, $type, $stamp] = $class;
    if ($type === 'icmpmon') $content = pialert_v4_icmp_report($directory . $filename);
    elseif ($type === 'test' || $type === 'rogueDHCP') {
        $raw = file_get_contents($directory . $filename);
        $content = h(str_replace("\n\n\n", '', $raw === false ? '' : $raw));
    } else $content = pialert_v4_standard_report($directory . $filename);
    $reports[] = array('filename' => $filename, 'name' => $name, 'type' => $type, 'stamp' => $stamp, 'content' => $content, 'special' => $type === 'rogueDHCP');
}
$reports = array_merge(
    array_values(array_filter($reports, static fn(array $report): bool => $report['special'])),
    array_values(array_filter($reports, static fn(array $report): bool => !$report['special']))
);
$archiveEntries = is_dir('./reports/archived/') ? scandir('./reports/archived/') : false;
$archiveCount = $archiveEntries === false ? 0 : count(array_diff($archiveEntries, array('.', '..')));
$title = $pia_lang['REP_Title'] ?? 'Notifications';
pialert_v4_shell_start($title, 'reports', array('lib/coloris-0.24.0/coloris.min.css', 'css/reports.css'));
?>
<section id="reports-page" data-source="<?= $archived ? 'archive' : 'current'; ?>" data-cancel="<?= h($pia_lang['Gen_Cancel'] ?? 'Cancel'); ?>" data-delete="<?= h($pia_lang['Gen_Delete'] ?? 'Delete'); ?>" data-okay="<?= h($pia_lang['Gen_Okay'] ?? 'Ok'); ?>" data-confirm-title="<?= h($pia_lang['REP_delete_all_noti'] ?? 'Delete notifications'); ?>" data-confirm-message="<?= h($pia_lang['REP_delete_all_noti_text'] ?? 'The selected notification will be deleted.'); ?>">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex flex-wrap gap-2">
      <a id="ShowArchivedReports" class="btn btn-outline-secondary" href="<?= $archived ? './reports.php' : './reports.php?report_source=archive'; ?>"><i class="fa-regular fa-folder me-2" aria-hidden="true"></i><?= h($archived ? ($pia_lang['REP_show_cur'] ?? 'Show current reports') : ($pia_lang['REP_show_archive'] ?? 'Show report archive') . ' (' . $archiveCount . ')'); ?></a>
      <button type="button" id="report-color-settings" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modal-set-report-colors"><i class="fa-solid fa-paintbrush me-2" aria-hidden="true"></i><?= h($pia_journ_lang['Journal_CustomColor_Head'] ?? 'Color Management'); ?></button>
    </div>
    <button type="button" id="RemoveAllNotifications" class="btn btn-danger"><i class="fa-solid fa-trash me-2" aria-hidden="true"></i><?= h($pia_lang['REP_delete_all'] ?? 'Delete all notifications'); ?><?= $archived ? ' (' . $pia_lang['V4_Archive_Label'] . ')' : ''; ?></button>
  </div>
  <?php if (!$archived && (int) pialert_v4_report_config('REPORT_TO_ARCHIVE') > 0): ?><p class="text-body-secondary text-center"><?= h(($pia_lang['Auto_Archive_note_a'] ?? 'Automatic archiving after ') . (int) pialert_v4_report_config('REPORT_TO_ARCHIVE') . ($pia_lang['Auto_Archive_note_b'] ?? ' hour(s)')); ?></p><?php endif; ?>

  <div class="card mb-3" id="report-filter-panel"><div class="card-body row g-2 align-items-end">
    <div class="col-12 col-md-8"><label class="form-label" for="report-filter"><?= h($pia_lang['V4_Filter']); ?></label><input class="form-control" id="report-filter" type="search" autocomplete="off" placeholder="<?= h($pia_lang['EVE_Searchbox'] ?? 'Search'); ?>"></div>
    <div class="col-12 col-md-4"><label class="form-label" for="report-type-filter"><?= h($pia_lang['V4_Report_Type']); ?></label><select class="form-select" id="report-type-filter"><option value=""><?= h($pia_lang['V4_All']); ?></option><?php foreach (array_unique(array_column($reports, 'type')) as $type): ?><option value="<?= h($type); ?>"><?= h($type); ?></option><?php endforeach; ?></select></div>
  </div></div>

  <div id="report-empty-state" class="alert alert-secondary<?= count($reports) ? ' d-none' : ''; ?>" role="status"><?= h($pia_lang['V4_No_Reports_Available']); ?></div>
  <div id="Container" class="row g-3">
  <?php foreach ($reports as $report): ?>
    <article class="col-12 col-xl-6 pialert-report" data-report-type="<?= h($report['type']); ?>" data-report-search="<?= h(strtolower($report['stamp'] . ' ' . $report['name'] . ' ' . strip_tags($report['content']))); ?>">
      <div class="card h-100<?= $report['special'] ? ' text-bg-danger' : ''; ?>">
        <div class="card-header"><h2 class="card-title mb-0"<?= !$report['special'] ? ' style="color:' . h($colors[$colorIndex[$report['type']]] ?? '#d81b60') . '"' : ''; ?>><i class="<?= h($icon[$report['type']] ?? 'fa-regular fa-file-lines'); ?> me-2" aria-hidden="true"></i><?= h($report['stamp']); ?> - <?= h($report['special'] ? $report['name'] : ($report['type'] === 'test' ? $pia_lang['V4_System_Message'] : $report['name'])); ?></h2></div>
        <div class="card-body pialert-report-body"><pre><?= $report['content']; ?></pre><?php if ($report['special']): ?><p class="text-center fw-semibold"><?= h($pia_lang['REP_Rogue_hint'] ?? ''); ?></p><?php endif; ?></div>
        <?php pialert_v4_report_actions($report['filename'], $archived); ?>
      </div>
    </article>
  <?php endforeach; ?>
  </div>

  <div class="modal fade" id="modal-set-report-colors" tabindex="-1" aria-labelledby="report-colors-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title fs-5" id="report-colors-title"><?= h($pia_journ_lang['Journal_CustomColor_Head'] ?? 'Color Management'); ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h($pia_lang['Gen_Close']); ?>"></button></div>
    <div class="modal-body"><h3 class="h6"><?= h($pia_lang['V4_Report_Type']); ?></h3><div class="report-color-list"><?php foreach (array($pia_lang['V4_Internet'], $pia_lang['V4_Shortcut_Devices'], $pia_lang['V4_Shortcut_Services'], $pia_lang['V4_Shortcut_ICMP'], $pia_lang['V4_Test_System'], 'Nmap') as $index => $label): ?><label class="report-color-row"><span><?= h($label); ?></span><input type="text" name="HeadLineColors[]" class="form-control report_custom_colors_input" value="<?= h($colors[$index]); ?>" data-coloris></label><?php endforeach; ?></div></div>
    <div class="modal-footer"><button type="button" id="save-report-colors" class="btn btn-danger"><?= h($pia_lang['Gen_Save'] ?? 'Save'); ?></button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= h($pia_lang['Gen_Close'] ?? 'Close'); ?></button></div>
  </div></div></div>
</section>
<?php pialert_v4_shell_end(array('lib/coloris-0.24.0/coloris.min.js', 'js/reports.js')); ?>

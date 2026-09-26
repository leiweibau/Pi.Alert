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

$title = $pia_journ_lang['Title'] ?? 'Application Journal';
$db = new SQLite3('../db/pialert.db');
$db->exec('PRAGMA journal_mode = wal;');
$journalRows = array();
$result = $db->query('SELECT * FROM pialert_journal ORDER BY Journal_DateTime DESC Limit 500');
if ($result !== false) {
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $logKey = (string) ($row['LogString'] ?? '');
        $classKey = (string) ($row['LogClass'] ?? '');
        $hash = (string) ($row['Hash'] ?? '');
        $additionalMarkup = h_with_line_breaks($pia_journ_lang[$logKey] ?? $logKey);
        if ($classKey === 'a_000') {
            $additionalMarkup .= '<br>' . h($pia_journ_lang['File_hash'] ?? 'File hash') . ': <span class="text-danger">' . h($hash) . '</span>';
        }
        $additionalMarkup .= '<br>' . h_with_line_breaks($row['Additional_Info'] ?? '');
        $journalRows[] = array(
            'date' => (string) ($row['Journal_DateTime'] ?? ''),
            'class' => (string) ($pia_journ_lang[$classKey] ?? $classKey),
            'trigger' => (string) ($row['Trigger'] ?? ''),
            'hash' => $hash,
            'additional' => $additionalMarkup,
        );
    }
}

pialert_v4_shell_start($title, 'journal', array(
    'lib/datatables/datatables.net-bs5-2.3.8/css/dataTables.bootstrap5.min.css',
    'lib/coloris-0.25.0/coloris.min.css',
    'css/journal.css',
), static fn(): string => '<button type="button" id="journal-color-settings" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modal-set-journal-colors" aria-label="' . h($GLOBALS['pia_lang']['V4_Color_Settings']) . '"><i class="fa-solid fa-paintbrush me-2" aria-hidden="true"></i>' . h($GLOBALS['pia_lang']['V4_Color_Settings']) . '</button>');
?>
<section id="journal-page"
  data-method-label="<?= h($pia_journ_lang['Journal_TableHead_Class'] ?? 'Method'); ?>"
  data-trigger-label="<?= h($pia_journ_lang['Journal_TableHead_Trigger'] ?? 'Trigger'); ?>"
  data-length-menu="<?= h($pia_lang['EVE_Tablelenght'] ?? 'Show _MENU_ entries'); ?>"
  data-length-all="<?= h($pia_lang['EVE_Tablelenght_all'] ?? 'All'); ?>"
  data-search="<?= h($pia_lang['EVE_Searchbox'] ?? 'Search'); ?>"
  data-next="<?= h($pia_lang['EVE_Table_nav_next'] ?? 'Next'); ?>"
  data-previous="<?= h($pia_lang['EVE_Table_nav_prev'] ?? 'Previous'); ?>"
  data-info="<?= h($pia_lang['EVE_Table_info'] ?? 'Showing _START_ to _END_ of _TOTAL_ entries'); ?>"
  data-okay="<?= h($pia_lang['Gen_Okay'] ?? 'Ok'); ?>">
  <div class="modal fade" id="modal-set-journal-colors" tabindex="-1" aria-labelledby="journal-colors-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5" id="journal-colors-title"><?= h($pia_journ_lang['Journal_CustomColor_Head'] ?? 'Color Management'); ?></h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h($pia_lang['Gen_Close']); ?>"></button>
        </div>
        <div class="modal-body">
          <section aria-labelledby="journal-method-colors-title">
            <h3 class="h6" id="journal-method-colors-title"><?= h($pia_lang['Gen_column'] ?? 'Column'); ?>: <?= h($pia_journ_lang['Journal_TableHead_Class'] ?? 'Method'); ?></h3>
            <div id="methodContainer" class="journal-color-list"></div>
            <div class="d-flex flex-wrap gap-2 mt-2">
              <button type="button" id="addMethod" class="btn btn-success" aria-label="<?= h($pia_lang['V4_Add_Method_Color']); ?>"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
              <button type="button" id="saveMethodColors" class="btn btn-danger"><?= h($pia_lang['Gen_Save'] ?? 'Save'); ?> (<?= h($pia_journ_lang['Journal_TableHead_Class'] ?? 'Method'); ?>)</button>
            </div>
          </section>
          <hr>
          <section aria-labelledby="journal-trigger-colors-title">
            <h3 class="h6" id="journal-trigger-colors-title"><?= h($pia_lang['Gen_column'] ?? 'Column'); ?>: <?= h($pia_journ_lang['Journal_TableHead_Trigger'] ?? 'Trigger'); ?></h3>
            <div id="triggerContainer" class="journal-color-list"></div>
            <div class="d-flex flex-wrap gap-2 mt-2">
              <button type="button" id="addTrigger" class="btn btn-success" aria-label="<?= h($pia_lang['V4_Add_Trigger_Color']); ?>"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
              <button type="button" id="saveTriggerColors" class="btn btn-danger"><?= h($pia_lang['Gen_Save'] ?? 'Save'); ?> (<?= h($pia_journ_lang['Journal_TableHead_Trigger'] ?? 'Trigger'); ?>)</button>
            </div>
          </section>
        </div>
        <div class="modal-footer"><button type="button" id="closeJournalColors" class="btn btn-secondary" data-bs-dismiss="modal"><?= h($pia_lang['Gen_Close'] ?? 'Close'); ?></button></div>
      </div>
    </div>
  </div>

  <section id="tableJournalBox" class="card card-primary card-outline" aria-labelledby="tableJournalTitle">
    <div class="card-header d-flex align-items-center">
      <h2 id="tableJournalTitle" class="card-title mb-0"><?= h($pia_lang['NAV_Journal']); ?></h2>
      <button type="button" id="reset_joursearch" class="btn btn-sm btn-link text-danger ms-auto" aria-label="<?= h($pia_lang['V4_Clear_Journal_Search']); ?>"><i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i></button>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table id="tableJournal" class="table table-bordered table-hover table-striped align-middle w-100">
          <thead><tr>
            <th><?= h($pia_lang['EVE_TableHead_Date'] ?? 'Date'); ?></th>
            <th><?= h($pia_journ_lang['Journal_TableHead_Class'] ?? 'Method'); ?></th>
            <th><?= h($pia_journ_lang['Journal_TableHead_Trigger'] ?? 'Trigger'); ?></th>
            <th><?= h($pia_lang['V4_Hash']); ?></th>
            <th><?= h($pia_lang['EVE_TableHead_AdditionalInfo'] ?? 'Additional info'); ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($journalRows as $row): ?>
            <tr><td><?= h($row['date']); ?></td><td><?= h($row['class']); ?></td><td><?= h($row['trigger']); ?></td><td><?= h($row['hash']); ?></td><td><?= $row['additional']; ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</section>
<?php pialert_v4_shell_end(array(
    'lib/datatables/datatables.net-2.3.8/dataTables.min.js',
    'lib/datatables/datatables.net-bs5-2.3.8/js/dataTables.bootstrap5.min.js',
    'lib/coloris-0.25.0/coloris.min.js',
    'js/journal.js',
)); ?>

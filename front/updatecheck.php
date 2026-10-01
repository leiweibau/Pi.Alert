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

$title = $pia_lang['Updatecheck_Title'] ?? 'Update Check';
$autoUpdateFile = PIALERT_V4_FRONT_ROOT . '/auto_Update.info';
$autoUpdateRows = array();
if (is_file($autoUpdateFile)) {
    $contents = file_get_contents($autoUpdateFile);
    if (is_string($contents)) {
        $autoUpdateRows = array_values(array_filter(explode("\n", $contents), static fn($row): bool => trim($row) !== ''));
    }
}

function pialert_v4_update_note(string $row): string {
    $safeRow = h(rtrim($row, "\r"));
    $safeRow = str_replace('BREAKING CHANGES', '<span class="text-red">BREAKING CHANGES</span>', $safeRow);
    foreach (array('Update Notes: ', 'New:', 'Fixed:', 'Updated:', 'Changed:', 'Note:', 'Removed:') as $heading) {
        if (stripos($row, $heading) !== false) {
            $underline = $heading === 'Update Notes: ' ? ' text-decoration-underline' : '';
            return '<div class="updatechk_font_a mt-3' . $underline . '">' . $safeRow . '</div>';
        }
    }
    return '<div class="pialert-update-note-item">' . str_replace('* ', '', $safeRow) . '</div>';
}

pialert_v4_shell_start($title, 'updatecheck', array('css/updatecheck.css'));
?>
<section class="card card-primary card-outline" aria-labelledby="updatecheck-action-title">
  <div class="card-header">
    <h2 class="card-title" id="updatecheck-action-title"><?= h($title); ?></h2>
  </div>
  <div class="card-body text-center" id="updatecheck">
    <button type="button" id="rewwejwejpjo" class="btn btn-primary" onclick="check_github_for_updates()">
      <i class="fa-solid fa-rotate me-2" aria-hidden="true"></i><?= h($pia_lang['MT_Tools_Updatecheck'] ?? 'Check for Updates'); ?>
    </button>
    <span class="pialert-update-spinner spinner-border spinner-border-sm ms-2" role="status" aria-label="<?= h($pia_lang['V4_Loading']); ?>" hidden></span>
  </div>
</section>

<div id="updatecheck_result" class="pialert-update-results" aria-live="polite" data-error-message="<?= h($pia_lang['V4_Update_Check_Failed']); ?>" data-geodb-error-message="<?= h($pia_lang['V4_GeoDB_Update_Failed']); ?>"></div>

<?php if ($autoUpdateRows !== array()): ?>
<section class="card mt-3" id="auto_update_releasenotes">
  <div class="card-body">
    <h2 class="h4 text-aqua text-center"><?= h($pia_lang['Auto_Updatecheck_RN'] ?? 'Automatic Update Check'); ?></h2>
    <?php foreach ($autoUpdateRows as $row) echo pialert_v4_update_note((string) $row); ?>
    <?php
    if (!is_dir('/opt/pialert')) {
        $updateCommand = 'sudo bash -c &quot;$(wget -qLO - https://github.com/leiweibau/Pi.Alert/raw/main/install/pialert_update_old.sh)&quot;';
        $updateEnvironment = ' (' . $pia_lang['V4_Outdated'] . ')';
    } else {
        $updateCommand = 'bash -c &quot;$(curl -fsSL https://github.com/leiweibau/Pi.Alert/raw/main/install/pialert_update.sh)&quot; -s';
        $updateEnvironment = '';
    }
    ?>
    <div class="mt-4">
      <label for="bashupdatecommand" class="text-red fst-italic"><?= h($pia_lang['V4_Update_Command']); ?><?= h($updateEnvironment); ?>:</label>
      <input id="bashupdatecommand" class="form-control-plaintext font-monospace" readonly value="<?= $updateCommand; ?>">
    </div>
  </div>
  <div class="card-footer">
    <a class="btn btn-outline-secondary" href="https://leiweibau.net/archive/pialert/" target="_blank" rel="noopener noreferrer"><?= h($pia_lang['V4_Version_History']); ?> (leiweibau.net)</a>
  </div>
</section>
<?php endif; ?>

<?php pialert_v4_shell_end(array('js/updatecheck.js')); ?>

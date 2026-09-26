<?php
// Shared renderer for the two standalone waiting pages. Do not load the app
// bootstrap: the pages must render even while Pi.Alert is restarting.
if (!defined('PIALERT_WAIT_ACTION') || !in_array(PIALERT_WAIT_ACTION, array('reboot', 'shutdown'), true)) {
    http_response_code(404);
    exit;
}

$language = $_GET['lang'] ?? 'en_us';
if (!is_string($language) || !preg_match('/^[a-z]{2}_[a-z]{2}$/D', $language) || !is_file(__DIR__ . '/../../php/language/' . $language . '.php')) {
    $language = 'en_us';
}
$pia_lang = array();
require __DIR__ . '/../../php/language/' . $language . '.php';
$prefix = PIALERT_WAIT_ACTION === 'reboot' ? 'Wait_Reboot_' : 'Wait_Shutdown_';
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$languageTags = array('cz_cs'=>'cs', 'dk_da'=>'da', 'se_sv'=>'sv', 'ua_uk'=>'uk');
$htmlLanguage = $languageTags[$language] ?? str_replace('_', '-', $language);
$script = PIALERT_WAIT_ACTION === 'reboot' ? 'static_reload.js' : 'static_stop_spinner.js';
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="<?= $escape($htmlLanguage); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $escape($pia_lang[$prefix . 'Title']); ?></title>
  <link rel="icon" type="image/png" href="../../img/favicons/flat_blue_white.png">
  <script src="../../js/static_theme.js"></script>
  <link rel="stylesheet" href="../../css/static_wait.css">
  <link rel="stylesheet" href="../../css/static_theme.css">
  <script src="../../js/<?= $script; ?>"></script>
</head>
<body>
  <header>
    <h1>Pi.<span class="bold">Alert</span></h1>
    <span><?= $escape($pia_lang[$prefix . 'Status']); ?></span>
  </header>
  <main class="status-section">
    <div class="status-box">
      <h2><?= $escape($pia_lang[$prefix . 'Heading']); ?></h2>
      <p><?= $escape($pia_lang[$prefix . 'Message']); ?></p>
      <p><?= $escape($pia_lang[$prefix . 'Advice']); ?></p>
      <div class="spinner"<?= PIALERT_WAIT_ACTION === 'shutdown' ? ' id="pialert-spinner"' : ''; ?> aria-hidden="true"></div>
    </div>
  </main>
  <footer>&copy; <?= date('Y'); ?> Pi.<span class="bold">Alert</span></footer>
</body>
</html>

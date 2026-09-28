<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('PIALERT_V4_PUBLIC_ENTRY', true);
require_once __DIR__ . '/php/bootstrap.php';
pialert_v4_start_session();

require_once PIALERT_V4_FRONT_ROOT . '/php/server/db.php';
require_once PIALERT_V4_FRONT_ROOT . '/php/server/auth.php';
require_once PIALERT_V4_FRONT_ROOT . '/php/server/journal.php';

$DBFILE = PIALERT_V4_FRONT_ROOT . '/../db/pialert.db';
OpenDB();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    header('Allow: POST');
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    pialert_validate_csrf();
    pialert_logging('a_001', $_SERVER['REMOTE_ADDR'], 'LogStr_9002', '', '');
    pialert_revoke_current_remember_token($db);
    $_SESSION = array();
    $sessionCookieName = session_name();
    session_destroy();
    pialert_delete_auth_cookie($sessionCookieName);
    header('Location: ' . pialert_v4_route('login'), true, 303);
    exit;
}

$configFileLines = file(PIALERT_V4_FRONT_ROOT . '/../config/pialert.conf');
pialert_v4_load_language();

$protectionLines = array_values(preg_grep('/^PIALERT_WEB_PROTECTION\s.*/', $configFileLines));
$protectionLine = explode('=', $protectionLines[0]);
$Pia_WebProtection = strtolower(trim($protectionLine[1]));

if ($Pia_WebProtection !== 'true') {
    if (($_SESSION['login'] ?? 0) != 1) {
        pialert_csrf_rotate();
    }
    $_SESSION['login'] = 1;
    $_SESSION['WebProtection'] = $Pia_WebProtection;
    header('Location: ' . pialert_v4_route('home'));
    exit;
}

$passwordLines = array_values(preg_grep('/^PIALERT_WEB_PASSWORD\s.*/', $configFileLines));
$passwordLine = explode("'", $passwordLines[0]);
$Pia_Password = $passwordLine[1];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['loginpassword'])) {
    pialert_validate_csrf();
}
$submittedPassword = $_POST['loginpassword'] ?? null;
$passwordLogin = is_string($submittedPassword)
    && hash_equals((string) $Pia_Password, hash('sha256', $submittedPassword));

if ($passwordLogin) {
    session_regenerate_id(true);
    pialert_csrf_rotate();
    $_SESSION['login'] = 1;
    $_SESSION['WebProtection'] = $Pia_WebProtection;
    if (isset($_POST['PWRemember'])) {
        pialert_issue_remember_token($db);
    } else {
        pialert_revoke_current_remember_token($db);
    }
    pialert_logging('a_001', $_SERVER['REMOTE_ADDR'], 'LogStr_9001', '', '');
    header('Location: ' . pialert_v4_route('home'), true, 303);
    exit;
}

if (($_SESSION['login'] ?? 0) == 1) {
    header('Location: ' . pialert_v4_route('home'));
    exit;
}

if (($_SESSION['login'] ?? 0) != 1 && isset($_POST['loginpassword'])) {
    pialert_logging('a_001', $_SERVER['REMOTE_ADDR'], 'LogStr_9003', '', '');
    header('Location: ' . pialert_v4_route('login') . '?login=failed', true, 303);
    exit;
}

$appearance = pialert_v4_ui_read()['appearance'];
$darkMode = $appearance['dark_mode'];
$defaultPassword = $Pia_Password === '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92';
$loginFailed = ($_GET['login'] ?? '') === 'failed';
$assetVersion = rawurlencode(pialert_v4_asset_version());
?>
<!doctype html>
<html lang="<?= h(str_replace('_', '-', pathinfo(pialert_v4_language_file(), PATHINFO_FILENAME))); ?>" data-bs-theme="<?= $darkMode ? 'dark' : 'light'; ?>" data-lte-color-mode="off">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <title>Pi.Alert | <?= h($pia_lang['Login_Submit']); ?></title>
  <link rel="stylesheet" href="<?= h(pialert_v4_asset('lib/adminlte-4.9.1/css/adminlte.min.css')); ?>">
  <link rel="stylesheet" href="<?= h(pialert_v4_asset('css/pialert-v4.css')); ?>?v=<?= $assetVersion; ?>">
</head>
<body class="login-page bg-body-secondary">
<div class="login-box">
  <div class="login-logo"><a href="<?= h(pialert_v4_route('login')); ?>">Pi.<strong>Alert</strong></a></div>
  <div class="card card-outline card-primary">
    <div class="card-body login-card-body">
      <p class="login-box-msg"><?= h($pia_lang['Login_Box']); ?></p>
      <?php if ($loginFailed): ?><div class="alert alert-danger" role="alert"><?= h($pia_lang['Login_Failed']); ?></div><?php endif; ?>
      <form action="<?= h(pialert_v4_route('login')); ?>" method="post">
        <input type="hidden" name="_csrf" value="<?= h(pialert_csrf_token()); ?>">
        <div class="mb-3"><label for="loginpassword" class="form-label"><?= h($pia_lang['Login_Psw-box']); ?></label><input id="loginpassword" type="password" class="form-control" name="loginpassword" autocomplete="current-password" required autofocus></div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="PWRemember" id="PWRememberBox"><label class="form-check-label" for="PWRememberBox"><?= h($pia_lang['Login_Remember']); ?> <small><?= h($pia_lang['Login_Remember_small']); ?></small></label></div>
        <button type="submit" class="btn btn-primary w-100"><?= h($pia_lang['Login_Submit']); ?></button>
      </form>
      <button class="btn btn-link w-100 mt-2" type="button" data-pialert-toggle="password-info" aria-controls="password-info" aria-expanded="<?= $defaultPassword ? 'true' : 'false'; ?>"><?= h($pia_lang['Login_Toggle_Info']); ?></button>
    </div>
  </div>
  <div id="password-info" class="alert alert-<?= $defaultPassword ? 'danger' : 'info'; ?> mt-4<?= $defaultPassword ? '' : ' d-none'; ?>" role="status">
    <h2 class="h5"><?= h($defaultPassword ? $pia_lang['Login_Toggle_Alert_headline'] : $pia_lang['Login_Toggle_Info_headline']); ?></h2>
    <?php if ($defaultPassword): ?><p><?= h($pia_lang['Login_Default_Password_Active']); ?></p><?php endif; ?>
    <p><?= h($pia_lang['Login_Psw_run']); ?><br><code>./pialert-cli set_password <?= h($pia_lang['Login_Psw_new']); ?></code><br><?= h($pia_lang['Login_Psw_folder']); ?></p>
  </div>
</div>
<script src="<?= h(pialert_v4_asset('lib/bootstrap-5.3.8/js/bootstrap.bundle.min.js')); ?>"></script>
<script src="<?= h(pialert_v4_asset('lib/adminlte-4.9.1/js/adminlte.min.js')); ?>"></script>
<script src="<?= h(pialert_v4_asset('js/pialert-v4.js')); ?>?v=<?= $assetVersion; ?>"></script>
</body>
</html>

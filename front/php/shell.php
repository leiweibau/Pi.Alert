<?php

if (!defined('PIALERT_V4_PUBLIC_ENTRY')) {
    http_response_code(404);
    exit;
}

function pialert_v4_setting_suffix(string $prefix, array $allowed): string {
    $matches = glob(PIALERT_V4_FRONT_ROOT . '/../config/' . $prefix . '*') ?: array();
    sort($matches, SORT_STRING);
    foreach ($matches as $filename) {
        $value = substr(basename($filename), strlen($prefix));
        if (in_array($value, $allowed, true)) return $value;
    }
    return '';
}

function pialert_v4_theme_state(): array {
    $appearance = pialert_v4_ui_read()['appearance'];
    $name = $appearance['theme'];
    $dark = in_array($name, array('glas', 'piano', 'console'), true) || $appearance['dark_mode'];
    // Piano has a light page canvas, but its chrome and controls are dark.
    $pianoChrome = in_array($name, array('piano', 'console'), true) ? array('class'=>'bg-dark', 'mode'=>'dark') : null;
    return array(
        'name'=>$name,
        'mode'=>$dark ? 'dark' : 'light',
        'header'=>$pianoChrome ?? pialert_v4_ui_chrome_color($appearance['header_color'], $dark, false),
        'sidebar'=>$pianoChrome ?? pialert_v4_ui_chrome_color($appearance['sidebar_color'], $dark, true),
    );
}

function pialert_v4_header_widget_column_class(array $visible): string {
    $count = count(array_filter($visible));
    if ($count <= 2) return 'col-6 col-md-6 col-xl';
    if ($count === 4) return 'col-6 col-md-3 col-xl';
    return 'col-6 col-md-4 col-xl';
}

function pialert_v4_config_enabled(string $name): bool {
    if (!preg_match('/^[A-Z][A-Z0-9_]*$/D', $name)) return false;
    $contents = @file_get_contents(PIALERT_V4_FRONT_ROOT . '/../config/pialert.conf');
    return is_string($contents) && preg_match('/^\s*' . preg_quote($name, '/') . '\s*=\s*(?:1|true)\s*(?:#.*)?$/mi', $contents) === 1;
}

function pialert_v4_sidebar_data(): array {
    static $data = null;
    if ($data !== null) return $data;
    $data = array('filters' => array(), 'satellites' => array());
    $path = PIALERT_V4_FRONT_ROOT . '/../db/pialert.db';
    if (!is_file($path)) return $data;
    try {
        // Match the v2 sidebar's SQLite open mode; WAL databases may need writable sidecars for reads.
        $db = new SQLite3($path);
        foreach (array('filters' => 'SELECT * FROM Devices_table_filter ORDER BY reserve_a ASC, filtername ASC', 'satellites' => 'SELECT * FROM Satellites ORDER BY sat_name ASC') as $key => $sql) {
            $result = @$db->query($sql);
            if ($result) while ($row = $result->fetchArray(SQLITE3_ASSOC)) $data[$key][] = $row;
        }
        $db->close();
    } catch (Throwable $error) {
        // Navigation remains usable when an older or temporarily unavailable DB lacks these tables.
    }
    return $data;
}

function pialert_v4_satellite_name(string $source): string {
    if ($source === 'local' || !pialert_v4_config_enabled('SATELLITES_ACTIVE')) return $source;
    foreach (pialert_v4_sidebar_data()['satellites'] as $satellite) {
        if (($satellite['sat_token'] ?? '') === $source) return (string) ($satellite['sat_name'] ?? $source);
    }
    return $source;
}

function pialert_v4_sidebar_filters(): void {
    $filters = pialert_v4_sidebar_data()['filters'];
    if (!$filters) return;
    $groups = array();
    foreach ($filters as $row) {
        $group = trim((string) ($row['reserve_c'] ?? ''));
        if ($group !== '') $groups[$group][] = $row;
    }
    uksort($groups, 'strnatcasecmp');
    $selected = isset($_GET['predefined_filter']) && is_string($_GET['predefined_filter']) ? $_GET['predefined_filter'] : null;
    $selectedFields = isset($_GET['filter_fields']) && is_string($_GET['filter_fields']) ? $_GET['filter_fields'] : null;
    $selectedId = filter_var($_GET['filter_id'] ?? null, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
    if ($selectedId === false) $selectedId = null;
    $groupIndex = 0;
    foreach ($groups as $name => $members) {
        $open = isset($_GET['g']) && is_scalar($_GET['g']) && (string) $_GET['g'] === (string) $groupIndex;
        foreach ($members as $row) if (($selectedId !== null && $selectedId === (int) ($row['id'] ?? 0)) || ($selectedId === null && $selected !== null && $selected === (string) ($row['filterstring'] ?? '') && ($selectedFields === null || $selectedFields === (string) ($row['reserve_b'] ?? '')))) $open = true;
        ?>
        <li class="nav-item pialert-filter-group<?= $open ? ' menu-open' : ''; ?>"><a href="#" class="nav-link pialert-sidebar-subitem" aria-expanded="<?= $open ? 'true' : 'false'; ?>"><i class="nav-icon fa-solid fa-filter" aria-hidden="true"></i><p><?= h((string) $name); ?><i class="nav-arrow fa-solid fa-angle-right" aria-hidden="true"></i></p></a>
          <ul class="nav nav-treeview"<?= $open ? ' style="display: block"' : ''; ?>>
          <?php foreach ($members as $row) pialert_v4_sidebar_filter_item($row, $groupIndex, $selected, $selectedFields, $selectedId); ?>
          </ul></li>
        <?php
        $groupIndex++;
    }
    foreach ($filters as $row) if (trim((string) ($row['reserve_c'] ?? '')) === '') pialert_v4_sidebar_filter_item($row, null, $selected, $selectedFields, $selectedId);
}

function pialert_v4_sidebar_filter_item(array $row, ?int $group, ?string $selected, ?string $selectedFields, ?int $selectedId): void {
    $id = (int) ($row['id'] ?? 0);
    $filter = (string) ($row['filterstring'] ?? '');
    $fields = (string) ($row['reserve_b'] ?? '');
    $matchesSelection = $selectedId !== null ? $selectedId === $id : $selected !== null && $selected === $filter && ($selectedFields === null || $selectedFields === $fields);
    $active = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'devices.php' && $matchesSelection;
    $url = 'devices.php?predefined_filter=' . rawurlencode($filter) . '&filter_fields=' . rawurlencode($fields) . '&filter_id=' . $id;
    if ($group !== null) $url .= '&g=' . $group;
    ?>
    <li class="nav-item"><a href="<?= h($url); ?>" class="nav-link pialert-sidebar-subitem<?= $active ? ' active' : ''; ?>"<?= $active ? ' aria-current="page"' : ''; ?>><i class="nav-icon <?= $active ? 'fa-solid' : 'fa-regular'; ?> fa-circle" aria-hidden="true"></i><p><?= h((string) ($row['filtername'] ?? '')); ?></p></a></li>
    <?php
}

function pialert_v4_sidebar_satellites(string $page): void {
    if (!pialert_v4_config_enabled('SATELLITES_ACTIVE')) return;
    $selected = isset($_GET['scansource']) && is_string($_GET['scansource']) ? $_GET['scansource'] : 'local';
    foreach (pialert_v4_sidebar_data()['satellites'] as $row) {
        $token = (string) ($row['sat_token'] ?? '');
        if ($token === '' || !preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $token)) continue;
        $active = $selected === $token && basename($_SERVER['SCRIPT_NAME'] ?? '') === $page . '.php';
        $url = $page . '.php?scansource=' . rawurlencode($token);
        ?>
        <li class="nav-item"><a href="<?= h($url); ?>" class="nav-link pialert-sidebar-subitem<?= $active ? ' active' : ''; ?>"<?= $active ? ' aria-current="page"' : ''; ?>><i class="nav-icon fa-solid fa-satellite" aria-hidden="true"></i><p><?= h((string) ($row['sat_name'] ?? $token)); ?><?php if ($page === 'devices'): ?><span class="pialert-nav-badges ms-auto"><span class="badge text-bg-warning" id="header_<?= h($token); ?>_count_new"></span><span class="badge text-bg-danger" id="header_<?= h($token); ?>_count_down"></span><span class="badge text-bg-success" id="header_<?= h($token); ?>_count_on"></span></span><?php else: ?><span class="pialert-nav-badges ms-auto"><span class="badge text-bg-secondary" id="header_<?= h($token); ?>_presence"></span></span><?php endif; ?></p></a></li>
        <?php
    }
}

function pialert_v4_system_status(): array {
    $paused = file_exists(PIALERT_V4_FRONT_ROOT . '/../config/setting_stoppialert');
    $loads = sys_getloadavg();
    $load = is_array($loads) ? implode(' ', array_map(static fn($v): string => number_format((float) $v, 2), array_slice($loads, 0, 3))) : 'N/A';
    $memory = 'N/A';
    $lines = @file('/proc/meminfo');
    if (is_array($lines)) {
        $values = array();
        foreach ($lines as $line) if (preg_match('/^(MemTotal|MemFree|Buffers|Cached):\s+(\d+)/', $line, $m)) $values[$m[1]] = (int) $m[2];
        if (($values['MemTotal'] ?? 0) > 0) {
            $used = $values['MemTotal'] - ($values['MemFree'] ?? 0) - ($values['Buffers'] ?? 0) - ($values['Cached'] ?? 0);
            $memory = number_format(100 * $used / $values['MemTotal'], 1) . '%';
        }
    }
    $temperature = null;
    foreach (array('/sys/class/thermal/thermal_zone0/temp', '/sys/class/hwmon/hwmon0/temp1_input') as $path) {
        $raw = @file_get_contents($path);
        if (is_string($raw) && is_numeric(trim($raw))) {
            $temperature = (float) trim($raw);
            if ($temperature > 1000) $temperature /= 1000;
            break;
        }
    }
    return array('paused' => $paused, 'load' => $load, 'memory' => $memory, 'temperature' => $temperature);
}

function pialert_v4_unmigrated_item(string $label, string $icon, string $badges = ''): void { ?>
  <li class="nav-item"><span class="nav-link disabled pialert-nav-pending" aria-disabled="true" title="<?= h($GLOBALS['pia_lang']['V4_Pending_Title']); ?>"><i class="nav-icon <?= h($icon); ?>" aria-hidden="true"></i><p><?= h($label); ?><?= $badges; ?><span class="badge text-bg-secondary ms-auto pialert-pending-badge"><?= h($GLOBALS['pia_lang']['V4_Pending_Badge']); ?></span></p></span></li>
<?php }

function pialert_v4_enqueue_script(string $path): void {
    global $pialertV4PageScripts;
    // Validate at registration time and emit only a v4-local asset later.
    pialert_v4_asset($path);
    if (!isset($pialertV4PageScripts) || !is_array($pialertV4PageScripts)) $pialertV4PageScripts = array();
    if (!in_array($path, $pialertV4PageScripts, true)) $pialertV4PageScripts[] = $path;
}

function pialert_v4_enqueue_style(string $path): void {
    global $pialertV4PageStyles;
    pialert_v4_asset($path);
    if (!isset($pialertV4PageStyles) || !is_array($pialertV4PageStyles)) $pialertV4PageStyles = array();
    if (!in_array($path, $pialertV4PageStyles, true)) $pialertV4PageStyles[] = $path;
}

function pialert_v4_shell_start(string $title, string $activePage = 'home', array $pageStyles = array(), ?callable $pageHeaderAction = null, ?string $baseHref = null): void {
    global $pia_lang, $pialertV4PageStyles;
    $withoutSidebar = $activePage === 'dashboard';
    foreach ($pageStyles as $pageStyle) {
        if (!is_string($pageStyle)) throw new InvalidArgumentException('Invalid v4 page style');
        pialert_v4_enqueue_style($pageStyle);
    }
    $assetVersion = rawurlencode(pialert_v4_asset_version());
    $theme = pialert_v4_theme_state();
    $status = pialert_v4_system_status();
    $language = str_replace('_', '-', pathinfo(pialert_v4_language_file(), PATHINFO_FILENAME));
    $appearance = pialert_v4_ui_read()['appearance'];
    $favicon = safe_web_url($appearance['favicon'], 'img/favicons/flat_blue_white.png');
    $piholeUrl = safe_web_url($appearance['pihole_url']);
    ?>
<!doctype html>
<html lang="<?= h($language); ?>" data-bs-theme="<?= h($theme['mode']); ?>"<?= $theme['name'] !== 'standard' ? ' data-pialert-theme="' . h($theme['name']) . '"' : ''; ?><?= $appearance['high_contrast_status_badges'] ? ' data-pialert-high-contrast-status="1"' : ''; ?> data-lte-color-mode="off">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($baseHref !== null): ?><base href="<?= h($baseHref); ?>"><?php endif; ?>
  <meta name="csrf-token" content="<?= h(pialert_csrf_token()); ?>"><meta http-equiv="x-dns-prefetch-control" content="off"><meta http-equiv="cache-control" content="max-age=60,private">
  <title><?= h($title); ?> | Pi.Alert</title>
  <link rel="icon" type="image/x-icon" href="<?= h($favicon); ?>"><link rel="apple-touch-icon" href="<?= h($favicon); ?>">
  <link rel="stylesheet" href="<?= h(pialert_v4_asset('lib/adminlte-4.9.1/css/adminlte.min.css')); ?>">
  <link rel="stylesheet" href="<?= h(pialert_v4_asset('lib/adminlte-4.9.1/css/adminlte-colors.min.css')); ?>">
  <link rel="stylesheet" href="<?= h(pialert_v4_asset('lib/bootstrap-icons-1.13.1/font/bootstrap-icons.min.css')); ?>">
  <link rel="stylesheet" href="<?= h(pialert_v4_asset('lib/font-awesome-7.3.1/css/all.min.css')); ?>">
  <link rel="stylesheet" href="<?= h(pialert_v4_asset('lib/ionicons/css/ionicons.min.css')); ?>">
  <link rel="stylesheet" href="<?= h(pialert_v4_asset('lib/material-design-icons/css/materialdesignicons.min.css')); ?>">
  <link rel="stylesheet" href="<?= h(pialert_v4_asset('css/pialert-v4.css')); ?>?v=<?= $assetVersion; ?>">
  <?php foreach (($pialertV4PageStyles ?? array()) as $pageStyle): ?><link rel="stylesheet" href="<?= h(pialert_v4_asset($pageStyle)); ?>?v=<?= $assetVersion; ?>"><?php endforeach; ?>
  <?php if ($theme['name'] !== 'standard'): ?><link rel="stylesheet" href="<?= h(pialert_v4_asset('css/pialert-theme-layout.css')); ?>?v=<?= $assetVersion; ?>"><link rel="stylesheet" href="<?= h(pialert_v4_asset('css/themes/' . $theme['name'] . '.css')); ?>?v=<?= $assetVersion; ?>"><?php endif; ?>
  <link rel="stylesheet" href="<?= h(pialert_v4_asset('css/pialert-status-badges.css')); ?>?v=<?= $assetVersion; ?>">
</head>
<body class="layout-fixed fixed-header sidebar-expand-lg bg-body-tertiary<?= $withoutSidebar ? ' pialert-dashboard-no-sidebar' : ''; ?>">
<div class="app-wrapper">
  <nav class="app-header navbar navbar-expand <?= h($theme['header']['class']); ?>" data-bs-theme="<?= h($theme['header']['mode']); ?>" aria-label="<?= h($pia_lang['V4_Toolbar']); ?>"><div class="container-fluid">
    <ul class="navbar-nav align-items-center">
      <?php if ($withoutSidebar): ?><li class="nav-item"><span class="navbar-brand pialert-dashboard-brand">Pi.<strong>Alert</strong></span></li><?php else: ?><li class="nav-item"><button class="nav-link btn" type="button" data-lte-toggle="sidebar" aria-label="<?= h($pia_lang['V4_Toggle_Navigation']); ?>"><i class="fa-solid fa-bars" aria-hidden="true"></i></button></li><?php endif; ?>
      <?php if ($theme['name'] !== 'standard' && !$withoutSidebar): ?><li class="nav-item pa-mobile-brand"><span>Pi.<strong>Alert</strong></span><small><?= h($title); ?></small></li><?php endif; ?>
      <li class="nav-item d-none d-sm-block"><a id="navbar-reload-button" class="nav-link" href="<?= h($baseHref !== null ? (string) ($_SERVER['SCRIPT_NAME'] ?? '') : ''); ?>" aria-label="<?= h($pia_lang['V4_Reload_Page']); ?>"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></a></li>
    </ul>
    <ul class="navbar-nav ms-auto align-items-center">
      <?php if ($withoutSidebar): ?><li class="nav-item d-none d-sm-block"><span id="dashboardRefreshCountdown" class="nav-link small text-body-secondary"><?= h($pia_lang['DASH_refresh_counter'] ?? 'Refresh in'); ?> <strong><span id="dashboardRefreshCountdownValue">120</span>s</strong></span></li><?php endif; ?>
      <?php if ($piholeUrl !== ''): ?><li class="nav-item"><a id="navbar-pihole-button" class="nav-link" href="<?= h($piholeUrl); ?>" target="_blank" rel="noopener noreferrer" aria-label="Pi-hole"><i class="mdi mdi-pi-hole" aria-hidden="true"></i></a></li><?php endif; ?>
      <li class="nav-item"><a id="navbar-help-button" class="nav-link" href="https://github.com/leiweibau/Pi.Alert/tree/main/docs" target="_blank" rel="noopener noreferrer" aria-label="<?= h($pia_lang['V4_Help']); ?>"><i class="fa-regular fa-circle-question" aria-hidden="true"></i></a></li>
      <li class="nav-item d-none d-md-block"><span class="pialert-server-time" aria-live="off"><strong><?= h(gethostname()); ?></strong> <span id="PIA_Servertime_place"></span><small id="nextscancountdown" class="d-block"></small></span></li>
      <li class="nav-item dropdown">
        <button class="nav-link position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="<?= $withoutSidebar ? 'outside' : 'true'; ?>" aria-expanded="false" aria-label="<?= h($pia_lang['V4_User_Menu']); ?>"><img class="pialert-user-logo" src="img/pialertLogo<?= $theme['header']['mode'] === 'light' ? 'Black' : 'White'; ?>.png" data-logo-light="img/pialertLogoBlack.png" data-logo-dark="img/pialertLogoWhite.png" width="25" height="25" alt=""><span class="position-absolute top-0 end-0 badge rounded-pill text-bg-danger" id="Menu_Report_Counter_Badge"></span></button>
        <div class="dropdown-menu dropdown-menu-end p-3 pialert-user-menu" data-bs-theme="<?= h($theme['mode']); ?>">
          <?php if ($withoutSidebar): ?><div class="d-flex align-items-center justify-content-center gap-2 mb-3" role="group" aria-label="<?= h($pia_lang['V4_Dashboard_Zoom']); ?>"><button class="btn btn-sm btn-primary" type="button" data-dashboard-zoom="out" aria-label="<?= h($pia_lang['V4_Zoom_Out']); ?>">−</button><span id="zoom-percent" aria-live="polite">100%</span><button class="btn btn-sm btn-primary" type="button" data-dashboard-zoom="in" aria-label="<?= h($pia_lang['V4_Zoom_In']); ?>">+</button><button class="btn btn-sm btn-success" type="button" data-dashboard-zoom="reset"><?= h($pia_lang['V4_Reset']); ?></button></div><?php endif; ?>
          <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="autoReloadCheckbox"><label class="form-check-label" for="autoReloadCheckbox"><?= h($pia_lang['V4_Auto_Reload']); ?></label></div>
          <div class="d-flex align-items-center justify-content-between gap-3 mb-3"><label class="form-label mb-0" for="tempunit-selector"><?= h($pia_lang['V4_Temperature']); ?></label><select id="tempunit-selector" class="form-select form-select-sm w-auto"><option value="C">°C</option><option value="F">°F</option><option value="K">K</option></select></div>
          <a class="btn btn-outline-secondary w-100 mb-2" id="custom-menu-default-button" href="./deviceDetails.php?mac=Internet"><i class="fa-solid fa-globe me-2" aria-hidden="true"></i>Internet</a>
          <?php if ($withoutSidebar): ?><a class="btn btn-outline-secondary w-100 mb-2" id="custom-menu-dashboard-button" href="<?= h(pialert_v4_route('home')); ?>"><i class="fa-solid fa-laptop me-2" aria-hidden="true"></i>Pi.Alert <i>(1)</i></a><?php else: ?><a class="btn btn-outline-secondary w-100 mb-2" id="custom-menu-dashboard-button" href="<?= h(pialert_v4_route('dashboard')); ?>"><i class="fa-solid fa-gauge-high me-2" aria-hidden="true"></i><?= h($pia_lang['V4_Dashboard']); ?> <i>(D)</i></a><?php endif; ?>
          <a class="btn btn-outline-secondary w-100 mb-2" id="custom-menu-report-button" href="<?= h(pialert_v4_route('reports')); ?>"><i class="fa-regular fa-envelope-open me-2" id="Menu_Report_Envelope_Icon" aria-hidden="true"></i><?= h($pia_lang['About_Reports'] ?? 'Reports'); ?> <i>(R)</i></a>
          <hr class="dropdown-divider">
          <?php if ($appearance['settings_popup']): ?><a class="btn btn-outline-secondary w-100 mb-2" id="custom-menu-settings-button" href="<?= h(pialert_v4_route('maintenance')); ?>"<?= $activePage === 'maintenance' ? ' aria-current="page"' : ''; ?>><i class="fa-solid fa-gear me-2" aria-hidden="true"></i><?= h($pia_lang['NAV_Maintenance'] ?? 'Settings'); ?> <i>(0)</i></a><?php endif; ?>
          <form method="post" action="<?= h(pialert_v4_route('login')); ?>" class="m-0"><input type="hidden" name="action" value="logout"><input type="hidden" name="_csrf" value="<?= h(pialert_csrf_token()); ?>"><button type="submit" id="custom-menu-logout-button" class="btn btn-danger w-100"><i class="fa-solid fa-arrow-right-from-bracket me-2" aria-hidden="true"></i><?= h($pia_lang['About_Exit'] ?? 'Log out'); ?></button></form>
          <div class="pialert-user-links mt-2"><a class="btn btn-sm btn-outline-secondary" href="https://github.com/leiweibau/Pi.Alert" target="_blank" rel="noopener noreferrer" aria-label="GitHub"><i class="fa-brands fa-github" aria-hidden="true"></i></a><a class="btn btn-sm btn-outline-secondary" href="https://github.com/sponsors/leiweibau" target="_blank" rel="noopener noreferrer" aria-label="<?= h($pia_lang['V4_Sponsors']); ?>"><i class="fa-regular fa-heart" aria-hidden="true"></i></a><a class="btn btn-sm btn-outline-secondary" href="https://leiweibau.net/archive/pialert/" target="_blank" rel="noopener noreferrer" aria-label="<?= h($pia_lang['V4_Homepage']); ?>"><i class="fa-solid fa-house" aria-hidden="true"></i></a></div>
        </div>
      </li>
    </ul>
  </div></nav>
  <?php if (!$withoutSidebar): ?><aside class="app-sidebar <?= h($theme['sidebar']['class']); ?> shadow" data-bs-theme="<?= h($theme['sidebar']['mode']); ?>">
    <div class="sidebar-brand <?= h($theme['header']['class']); ?>" data-bs-theme="<?= h($theme['header']['mode']); ?>"><a href="<?= h(pialert_v4_route('home')); ?>" class="brand-link"><span class="brand-text fw-light">Pi.<strong>Alert</strong></span></a></div>
    <div class="sidebar-wrapper">
      <a id="sidebar_systeminfobox" class="pialert-system-status d-block text-decoration-none" href="<?= h(pialert_v4_route('systeminfo')); ?>" aria-label="<?= h($pia_lang['V4_System_Status_Info']); ?>"<?= $activePage === 'systeminfo' ? ' aria-current="page"' : ''; ?>>
        <div><span class="pialert-status-dot <?= $status['paused'] ? 'text-danger' : 'text-success'; ?>" aria-hidden="true">●</span> <?= h($status['paused'] ? $pia_lang['V4_Disabled'] : $pia_lang['V4_Active']); ?></div>
        <div><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> <?= h($pia_lang['V4_Load']); ?>: <?= h($status['load']); ?></div><div><i class="fa-solid fa-memory" aria-hidden="true"></i> <?= h($pia_lang['V4_Memory']); ?>: <?= h($status['memory']); ?></div>
        <?php if ($status['temperature'] !== null): ?><div><i class="fa-solid fa-fire" aria-hidden="true"></i> <?= h($pia_lang['V4_Temperature_Short']); ?>: <span id="rawtemp" hidden><?= h((string) $status['temperature']); ?></span><span id="tempdisplay"><?= h(number_format($status['temperature'], 1)); ?>&nbsp;°C</span></div><?php else: ?><span id="rawtemp" hidden></span><span id="tempdisplay" hidden></span><?php endif; ?>
      </a>
      <nav class="mt-2" aria-label="<?= h($pia_lang['V4_Main_Navigation']); ?>"><ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">
        <li class="nav-header"><?= h($pia_lang['NAV_Section_A'] ?? 'Main menu'); ?></li>
        <li class="nav-item"><a href="<?= h(pialert_v4_route('home')); ?>" class="nav-link<?= $activePage === 'home' ? ' active' : ''; ?>"><i class="nav-icon fa-solid fa-laptop" aria-hidden="true"></i><p><?= h($pia_lang['NAV_Devices'] ?? 'Devices'); ?><span class="pialert-nav-badges ms-auto"><span class="badge text-bg-warning" id="header_local_count_new"></span><span class="badge text-bg-danger" id="header_local_count_down"></span><span class="badge text-bg-success" id="header_local_count_on"></span></span></p></a></li>
        <?php pialert_v4_sidebar_filters(); pialert_v4_sidebar_satellites('devices'); ?>
        <li class="nav-item"><a href="<?= h(pialert_v4_route('network')); ?>" class="nav-link<?= $activePage === 'network' ? ' active' : ''; ?>"><i class="nav-icon fa-solid fa-network-wired" aria-hidden="true"></i><p><?= h($pia_lang['NAV_Network'] ?? 'Network'); ?></p></a></li>
        <?php if (pialert_v4_config_enabled('SCAN_WEBSERVICES')): ?><li class="nav-item"><a href="<?= h(pialert_v4_route('services')); ?>" class="nav-link<?= $activePage === 'services' ? ' active' : ''; ?>"><i class="nav-icon fa-solid fa-globe" aria-hidden="true"></i><p><?= h($pia_lang['NAV_Services'] ?? 'Web Services'); ?><span class="pialert-nav-badges ms-auto"><span class="badge text-bg-warning" id="header_services_count_warning"></span><span class="badge text-bg-danger" id="header_services_count_down"></span><span class="badge text-bg-success" id="header_services_count_on"></span></span></p></a></li><?php endif; ?>
        <?php if (pialert_v4_config_enabled('ICMPSCAN_ACTIVE')): ?><li class="nav-item"><a href="<?= h(pialert_v4_route('icmp')); ?>" class="nav-link<?= $activePage === 'icmp' ? ' active' : ''; ?>"><i class="nav-icon fa-solid fa-magnifying-glass" aria-hidden="true"></i><p><?= h($pia_lang['NAV_ICMPScan'] ?? 'ICMP Monitoring'); ?><span class="pialert-nav-badges ms-auto"><span class="badge text-bg-danger" id="header_icmp_count_down"></span><span class="badge text-bg-success" id="header_icmp_count_on"></span></span></p></a></li><?php endif; ?>
        <li class="nav-header"><?= h($pia_lang['NAV_Section_B'] ?? 'Events & journal'); ?></li>
        <li class="nav-item"><a href="<?= h(pialert_v4_route('events')); ?>" class="nav-link<?= $activePage === 'events' ? ' active' : ''; ?>"><i class="nav-icon fa-solid fa-list-check" aria-hidden="true"></i><p><?= h($pia_lang['NAV_Events'] ?? 'Events'); ?></p></a></li>
        <li class="nav-item"><a href="<?= h(pialert_v4_route('presence')); ?>" class="nav-link<?= $activePage === 'presence' ? ' active' : ''; ?>"><i class="nav-icon fa-regular fa-calendar" aria-hidden="true"></i><p><?= h($pia_lang['NAV_Presence'] ?? 'Presence'); ?><span class="pialert-nav-badges ms-auto"><span class="badge text-bg-secondary" id="header_local_presence"></span></span></p></a></li>
        <?php pialert_v4_sidebar_satellites('presence'); ?>
        <li class="nav-item"><a href="<?= h(pialert_v4_route('journal')); ?>" class="nav-link<?= $activePage === 'journal' ? ' active' : ''; ?>"><i class="nav-icon ion ion-md-list-box" aria-hidden="true"></i><p><?= h($pia_lang['NAV_Journal'] ?? 'Journal'); ?></p></a></li>
        <li class="nav-header"><?= h($pia_lang['NAV_Section_C'] ?? 'Other'); ?></li>
        <?php if ($appearance['settings_sidebar']): ?><li class="nav-item"><a href="<?= h(pialert_v4_route('maintenance')); ?>" class="nav-link<?= $activePage === 'maintenance' ? ' active' : ''; ?>"<?= $activePage === 'maintenance' ? ' aria-current="page"' : ''; ?>><i class="nav-icon fa-solid fa-gear" aria-hidden="true"></i><p><?= h($pia_lang['NAV_Maintenance'] ?? 'Settings'); ?></p></a></li><?php endif; ?>
        <li class="nav-item"><a href="<?= h(pialert_v4_route('updatecheck')); ?>" class="nav-link<?= $activePage === 'updatecheck' ? ' active' : ''; ?>"><i class="nav-icon fa-solid fa-rotate" aria-hidden="true"></i><p><?= h($pia_lang['NAV_UpdateCheck'] ?? 'Update check'); ?><span class="pialert-nav-badges ms-auto"><span class="badge text-bg-danger" id="header_updatecheck_notification"></span></span></p></a></li>
      </ul></nav>
    </div>
  </aside><?php endif; ?>
  <main class="app-main"><?php if (!$withoutSidebar): ?><div class="app-content-header"><div class="container-fluid<?= $pageHeaderAction !== null ? ' pialert-page-heading' : ''; ?>"><h1 id="pageTitle" class="mb-0"><?= h($title); ?></h1><?php if ($pageHeaderAction !== null): ?><div class="pialert-page-action"><?= $pageHeaderAction(); ?></div><?php endif; ?></div></div><?php endif; ?><div class="app-content"><div class="container-fluid">
<?php }

function pialert_v4_shell_end(array $pageScripts = array()): void {
    global $pialertV4PageScripts, $pia_lang;
    foreach ($pageScripts as $pageScript) {
        if (!is_string($pageScript)) throw new InvalidArgumentException('Invalid v4 page script');
        pialert_v4_enqueue_script($pageScript);
    }
    $config = @parse_ini_file(PIALERT_V4_FRONT_ROOT . '/../config/version.conf');
    $version = is_array($config) ? (string) ($config['VERSION_DATE'] ?? $config['VERSION'] ?? '') : '';
    $assetVersion = rawurlencode(pialert_v4_asset_version()); ?>
    </div></div></main>
  <footer class="app-footer"><a class="link-body-emphasis" href="https://leiweibau.net/" target="_blank" rel="noopener noreferrer">leiweibau</a><span class="float-end d-none d-sm-inline"><?= h($GLOBALS['pia_lang']['V4_Version']); ?>: <?= h($version); ?></span></footer>
</div>
<script type="application/json" id="pialert-v4-labels"><?= json_encode(array_filter($pia_lang, static fn($key): bool => str_starts_with((string) $key, 'V4_'), ARRAY_FILTER_USE_KEY), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?></script>
<script src="<?= h(pialert_v4_asset('lib/jquery-4.0.0/jquery.min.js')); ?>"></script>
<script src="<?= h(pialert_v4_asset('lib/bootstrap-5.3.8/js/bootstrap.bundle.min.js')); ?>"></script>
<script src="<?= h(pialert_v4_asset('lib/adminlte-4.9.1/js/adminlte.min.js')); ?>"></script>
<script src="<?= h(pialert_v4_asset('js/pialert-common.js')); ?>?v=<?= $assetVersion; ?>"></script>
<script src="<?= h(pialert_v4_asset('js/pialert-shell-runtime.js')); ?>?v=<?= $assetVersion; ?>"></script>
<?php foreach (($pialertV4PageScripts ?? array()) as $pageScript): ?>
<script src="<?= h(pialert_v4_asset($pageScript)); ?>?v=<?= $assetVersion; ?>"></script>
<?php if ($pageScript === 'lib/datatables/datatables.net-bs5-3.1.2/js/dataTables.bootstrap5.min.js'): ?><script src="<?= h(pialert_v4_asset('js/pialert-datatables.js')); ?>?v=<?= $assetVersion; ?>"></script><?php endif; ?>
<?php if ($pageScript === 'lib/chart.js-4.5.1/chart.umd.js'): ?><script src="<?= h(pialert_v4_asset('js/pialert-theme-chart.js')); ?>?v=<?= $assetVersion; ?>"></script><?php endif; ?>
<?php endforeach; ?>
<script src="<?= h(pialert_v4_asset('js/pialert-v4.js')); ?>?v=<?= $assetVersion; ?>"></script>
</body></html>
<?php }

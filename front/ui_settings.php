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
pialert_v4_load_language();

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    pialert_validate_csrf();
    try {
        $revision = $_POST['revision'] ?? '';
        if (!is_string($revision) || !ctype_digit($revision) || strlen($revision) > 12) throw new InvalidArgumentException('Invalid revision');
        $columns = array();
        foreach (pialert_v4_device_columns() as $id => $column) if (!empty($column['configurable'])) $columns[$id] = isset($_POST['columns'][$id]);
        $icmpColumns = array();
        foreach (pialert_v4_icmp_columns() as $id => $column) if (!empty($column['configurable'])) $icmpColumns[$id] = isset($_POST['icmp_columns'][$id]);
        $widgets = pialert_v4_ui_defaults()['appearance']['header_widgets'];
        foreach ($widgets as $group => $items) foreach ($items as $id => $_) $widgets[$group][$id] = isset($_POST['widgets'][$group][$id]);
        $pageLength = $_POST['page_length'] ?? null;
        $icmpPageLength = $_POST['icmp_page_length'] ?? null;
        $language = $_POST['language'] ?? null;
        $theme = $_POST['theme'] ?? null;
        $sidebarColor = $_POST['sidebar_color'] ?? null;
        $headerColor = $_POST['header_color'] ?? null;
        $piholeUrl = $_POST['pihole_url'] ?? null;
        $favicon = $_POST['favicon'] ?? null;
        if (!is_string($pageLength) || !preg_match('/^-?\d+$/D', $pageLength) || !is_string($icmpPageLength) || !preg_match('/^-?\d+$/D', $icmpPageLength) || !is_string($language) || !is_string($theme) || !is_string($sidebarColor) || !is_string($headerColor) || !is_string($piholeUrl) || !pialert_v4_ui_valid_favicon($favicon)) throw new InvalidArgumentException('Invalid form data');
        pialert_v4_ui_update_many(array(
            'devices.columns'=>$columns,
            'devices.page_length'=>(int) $pageLength,
            'icmp.columns'=>$icmpColumns,
            'icmp.page_length'=>(int) $icmpPageLength,
            'appearance.language'=>$language,
            'appearance.theme'=>$theme,
            'appearance.dark_mode'=>isset($_POST['dark_mode']),
            'appearance.sidebar_color'=>$sidebarColor,
            'appearance.header_color'=>$headerColor,
            'appearance.pihole_url'=>$piholeUrl,
            'appearance.favicon'=>$favicon,
            'appearance.header_widgets'=>$widgets,
        ), (int) $revision);
        header('Location: ./ui_settings.php?saved=1', true, 303);
        exit;
    } catch (DomainException $e) {
        http_response_code(409);
        $error = $pia_lang['V4_UI_Conflict'];
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        $error = $pia_lang['V4_UI_Invalid'];
    } catch (Throwable $e) {
        error_log('v4 UI settings page: ' . $e->getMessage());
        http_response_code(500);
        $error = $pia_lang['V4_UI_Save_Failed'];
    }
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method Not Allowed');
}

$settings = pialert_v4_ui_read();
require_once __DIR__ . '/php/shell.php';
$strings = array(
    'title'=>$pia_lang['V4_UI_Title'],
    'saved'=>$pia_lang['V4_UI_Saved'],
    'table'=>$pia_lang['V4_UI_Devices_Table'],
    'icmp_table'=>$pia_lang['V4_UI_ICMP_Table'],
    'table_help'=>$pia_lang['V4_UI_Table_Help'],
    'icmp_table_help'=>$pia_lang['V4_UI_ICMP_Table_Help'],
    'rows'=>$pia_lang['V4_UI_Rows'],
    'all_rows'=>$pia_lang['V4_All'],
    'appearance'=>$pia_lang['V4_UI_Appearance'],
    'displays'=>$pia_lang['V4_UI_Displays'],
    'language'=>$pia_lang['V4_UI_Language'],
    'dark'=>$pia_lang['V4_UI_Dark'],
    'sidebar_color'=>$pia_lang['V4_UI_Sidebar_Color'],
    'header_color'=>$pia_lang['V4_UI_Header_Color'],
    'previous_color'=>$pia_lang['V4_UI_Previous_Color'],
    'color_help'=>$pia_lang['V4_UI_Color_Help'],
    'pihole'=>$pia_lang['V4_UI_Pihole'],
    'pihole_help'=>$pia_lang['V4_UI_Pihole_Help'],
    'widgets'=>$pia_lang['V4_UI_Widgets'],
    'all'=>$pia_lang['V4_All'], 'connected'=>$pia_lang['Device_Shortcut_Connected'],
    'favorites'=>$pia_lang['Device_Shortcut_Favorites'], 'down'=>$pia_lang['V4_Down'],
    'archived'=>$pia_lang['Device_Shortcut_Archived'], 'new'=>$pia_lang['V4_New'],
    'save'=>$pia_lang['V4_UI_Save'],
    'back'=>$pia_lang['V4_UI_Back'],
);
$uiText = static fn(string $key): string => $strings[$key];
$colorLabel = static function (string $color) use (&$pia_lang): string {
    $key = 'V4_UI_Color_' . implode('_', array_map('ucfirst', explode('-', $color)));
    return $pia_lang[$key] ?? $color;
};
$faviconOptions = pialert_v4_ui_favicon_options();
$faviconLabel = static function (string $path, string $location) use (&$pia_lang): string {
    if (!preg_match('/\Aimg\/favicons\/(flat|glass)_(red|blue|green|yellow|purple|black|white)_(black|white)\.png\z/D', $path, $parts)) return $path;
    return ($pia_lang['FavIcon_color_' . $parts[2]] ?? $parts[2]) . ', '
        . ($pia_lang['FavIcon_logo_' . $parts[3]] ?? $parts[3]) . ', '
        . ($pia_lang['FavIcon_mode_' . $parts[1]] ?? $parts[1]) . ' ('
        . ($pia_lang['FavIcon_' . $location] ?? $location) . ')';
};
$title = $uiText('title');
pialert_v4_shell_start($title, 'ui_settings', array('css/ui-settings.css'));
?>
<section id="v4-ui-settings" class="container-fluid px-0" aria-label="<?= h($title); ?>">
  <?php if (($_GET['saved'] ?? '') === '1'): ?><div class="alert alert-success" role="status"><?= h($uiText('saved')); ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= h($error); ?></div><?php endif; ?>
  <form method="post" action="./ui_settings.php">
    <input type="hidden" name="_csrf" value="<?= h(pialert_csrf_token()); ?>">
    <input type="hidden" name="revision" value="<?= h((string) $settings['revision']); ?>">
    <div class="row g-3">
      <div class="col-12 col-xl-6"><section class="card"><div class="card-header"><h2 class="card-title"><?= h($uiText('table')); ?></h2></div><div class="card-body">
        <p class="text-body-secondary"><?= h($uiText('table_help')); ?></p>
        <div class="row g-2">
        <?php foreach (pialert_v4_device_columns() as $id => $column): if (empty($column['configurable'])) continue; $label = $column['label'] === null ? $column['fallback'] : ($pia_lang[$column['label']] ?? $column['fallback']); ?>
          <div class="col-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="col-<?= h($id); ?>" name="columns[<?= h($id); ?>]" value="1"<?= $settings['devices']['columns'][$id] ? ' checked' : ''; ?>><label class="form-check-label" for="col-<?= h($id); ?>"><?= h(pialert_v4_ui_plain_label($label)); ?></label></div></div>
        <?php endforeach; ?>
        </div>
        <label class="form-label mt-3" for="page-length"><?= h($uiText('rows')); ?></label><select class="form-select" id="page-length" name="page_length">
          <?php foreach (array(10,25,50,100,500,-1) as $length): ?><option value="<?= $length; ?>"<?= $settings['devices']['page_length'] === $length ? ' selected' : ''; ?>><?= $length === -1 ? h($uiText('all_rows')) : $length; ?></option><?php endforeach; ?>
        </select>
      </div></section>
      <section id="icmp-columns-settings" class="card mt-3"><div class="card-header"><h2 class="card-title"><?= h($uiText('icmp_table')); ?></h2></div><div class="card-body">
        <p class="text-body-secondary"><?= h($uiText('icmp_table_help')); ?></p>
        <div class="row g-2">
        <?php foreach (pialert_v4_icmp_columns() as $id => $column): if (empty($column['configurable'])) continue; $label = $column['label'] === null ? $column['fallback'] : ($pia_lang[$column['label']] ?? $column['fallback']); ?>
          <div class="col-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="icmp-col-<?= h($id); ?>" name="icmp_columns[<?= h($id); ?>]" value="1"<?= $settings['icmp']['columns'][$id] ? ' checked' : ''; ?>><label class="form-check-label" for="icmp-col-<?= h($id); ?>"><?= h(pialert_v4_ui_plain_label($label)); ?></label></div></div>
        <?php endforeach; ?>
        </div>
        <label class="form-label mt-3" for="icmp-page-length"><?= h($uiText('rows')); ?></label><select class="form-select" id="icmp-page-length" name="icmp_page_length">
          <?php foreach (array(10,25,50,100,500,-1) as $length): ?><option value="<?= $length; ?>"<?= $settings['icmp']['page_length'] === $length ? ' selected' : ''; ?>><?= $length === -1 ? h($uiText('all_rows')) : $length; ?></option><?php endforeach; ?>
        </select>
      </div></section></div>
      <div class="col-12 col-xl-6"><section class="card"><div class="card-header"><h2 class="card-title"><?= h($uiText('appearance')); ?></h2></div><div class="card-body">
        <label class="form-label h6" for="ui-language"><?= h($uiText('language')); ?></label><select class="form-select" id="ui-language" name="language">
          <?php foreach (pialert_v4_ui_language_options() as $language): ?><option value="<?= h($language); ?>"<?= $settings['appearance']['language'] === $language ? ' selected' : ''; ?>><?= h(str_replace('_', '-', $language)); ?></option><?php endforeach; ?>
        </select>
        <div class="mt-4 pt-3 border-top">
        <label class="form-label h6" for="ui-theme"><?= h($pia_lang['UI_Theme_Label'] ?? 'Theme'); ?></label><select class="form-select mb-1" id="ui-theme" name="theme">
          <option value="standard"<?= $settings['appearance']['theme'] === 'standard' ? ' selected' : ''; ?>><?= h($pia_lang['UI_Theme_Standard'] ?? 'Standard'); ?></option>
          <option value="glas"<?= $settings['appearance']['theme'] === 'glas' ? ' selected' : ''; ?>><?= h($pia_lang['UI_Theme_Glas'] ?? 'Glas'); ?></option>
          <option value="piano"<?= $settings['appearance']['theme'] === 'piano' ? ' selected' : ''; ?>><?= h($pia_lang['UI_Theme_Piano'] ?? 'Piano'); ?></option>
          <option value="console"<?= $settings['appearance']['theme'] === 'console' ? ' selected' : ''; ?>><?= h($pia_lang['UI_Theme_Console'] ?? 'Console'); ?></option>
        </select>
        <div class="form-text"><?= h($pia_lang['UI_Theme_Help'] ?? 'Choose between the standard, translucent Glas, and dark-on-light Piano surfaces.'); ?></div>
        </div>
        <div class="mt-4 pt-4 border-top">
          <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="ui-dark" name="dark_mode" value="1"<?= $settings['appearance']['dark_mode'] ? ' checked' : ''; ?>><label class="form-check-label h6 mb-0" for="ui-dark"><?= h($uiText('dark')); ?></label></div>
        </div>
        <div class="mt-4 pt-3 border-top">
        <?php foreach (array('sidebar_color'=>'sidebar','header_color'=>'header') as $field=>$chrome): ?>
          <label class="form-label h6" for="ui-<?= h($field); ?>"><?= h($uiText($field)); ?></label>
          <div class="d-flex gap-2 align-items-center mb-3">
            <select class="form-select" id="ui-<?= h($field); ?>" name="<?= h($field); ?>">
              <?php $currentColor = $settings['appearance'][$field]; if (array_key_exists($currentColor, pialert_v4_ui_legacy_colors())): ?>
                <option value="<?= h($currentColor); ?>" selected><?= h($uiText('previous_color') . ': ' . $colorLabel($currentColor)); ?></option>
              <?php endif; ?>
              <?php foreach (pialert_v4_ui_color_options() as $group=>$colors): ?><optgroup label="<?= h($group === 'Neutral' ? $pia_lang['V4_UI_Group_Neutral'] : $group); ?>">
                <?php foreach ($colors as $value=>$label): ?><option value="<?= h($value); ?>"<?= $settings['appearance'][$field] === $value ? ' selected' : ''; ?>><?= h($colorLabel($value)); ?></option><?php endforeach; ?>
              </optgroup><?php endforeach; ?>
            </select>
            <?php $preview = pialert_v4_ui_chrome_color($settings['appearance'][$field], $settings['appearance']['dark_mode'], $chrome === 'sidebar'); ?>
            <span id="ui-<?= h($chrome); ?>-swatch" class="pialert-ui-color-swatch rounded border <?= h($preview['class']); ?>" data-bs-theme="<?= h($preview['mode']); ?>" aria-hidden="true">Aa</span>
          </div>
        <?php endforeach; ?>
        <div class="form-text"><?= h($uiText('color_help')); ?></div>
        </div>
      </div></section></div>
      <div class="col-12"><section class="card"><div class="card-header"><h2 class="card-title"><?= h($uiText('displays')); ?></h2></div><div class="card-body">
        <label class="form-label h6" for="ui-pihole"><?= h($uiText('pihole')); ?></label><input class="form-control" id="ui-pihole" type="url" name="pihole_url" maxlength="2048" placeholder="https://pi.hole/" value="<?= h($settings['appearance']['pihole_url']); ?>"><div class="form-text"><?= h($uiText('pihole_help')); ?></div>
        <div class="mt-4 pt-3 border-top">
          <h3 class="h6"><?= h($pia_lang['MT_Tool_onlinehistorygraph'] ?? 'Activity history'); ?></h3>
          <p class="text-body-secondary mb-2"><?= h($pia_lang['UI_Activity_History_Help']); ?></p>
          <button type="button" class="btn btn-outline-primary" id="ui-activity-history"
            data-enabled="<?= $settings['appearance']['activity_history'] ? '1' : '0'; ?>"
            data-title="<?= h($pia_lang['MT_Tool_onlinehistorygraph_noti'] ?? 'Activity history'); ?>"
            data-confirm="<?= h($pia_lang['MT_Tool_onlinehistorygraph_noti_text'] ?? 'Toggle activity history?'); ?>"
            data-cancel-label="<?= h($pia_lang['Gen_Cancel'] ?? 'Cancel'); ?>"
            data-confirm-label="<?= h($pia_lang['Gen_Okay'] ?? 'OK'); ?>"
            data-error-label="<?= h($pia_lang['Gen_error'] ?? 'Error'); ?>">
            <?= h($pia_lang['MT_Tool_onlinehistorygraph'] ?? 'Activity history'); ?>
            (<?= $settings['appearance']['activity_history'] ? h($pia_lang['Gen_on'] ?? 'On') : h($pia_lang['Gen_off'] ?? 'Off'); ?>)
          </button>
        </div>
        <div class="mt-4 pt-3 border-top">
          <h3 class="h6"><?= h($pia_lang['MT_Tools_Tab_Subheadline_e']); ?></h3>
          <p class="text-body-secondary"><?= h($pia_lang['MT_Tools_Tab_Subheadline_e_Intro']); ?> (<a href="https://github.com/leiweibau/Pi.Alert/blob/main/docs/ICONS.md" target="_blank" rel="noopener noreferrer">GitHub</a>)</p>
          <div class="row g-3 align-items-end">
            <div class="col-12 col-md-8 col-lg-9">
              <label class="visually-hidden" for="ui-favicon-url"><?= h($pia_lang['V4_Favicon']); ?></label>
              <div class="input-group">
                <input class="form-control" id="ui-favicon-url" name="favicon" type="text" maxlength="2048" value="<?= h($settings['appearance']['favicon']); ?>" autocomplete="off" spellcheck="false">
                <div class="dropdown">
                  <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= h($pia_lang['V4_Favicon']); ?>"></button>
                  <ul class="dropdown-menu dropdown-menu-end ui-favicon-menu">
                    <li><h6 class="dropdown-header"><?= h($pia_lang['FavIcon_local']); ?></h6></li>
                    <?php foreach ($faviconOptions as $choice): ?><li><button type="button" class="dropdown-item" data-favicon-value="<?= h($choice); ?>"><?= h($faviconLabel($choice, 'local')); ?></button></li><?php endforeach; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header"><?= h($pia_lang['FavIcon_remote']); ?></h6></li>
                    <?php foreach ($faviconOptions as $choice): $remote = 'https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/' . $choice; ?><li><button type="button" class="dropdown-item" data-favicon-value="<?= h($remote); ?>"><?= h($faviconLabel($choice, 'remote')); ?></button></li><?php endforeach; ?>
                  </ul>
                </div>
              </div>
            </div>
            <div class="col-auto"><img id="ui-favicon-preview" src="<?= h($settings['appearance']['favicon']); ?>" alt="<?= h($pia_lang['V4_Favicon']); ?>" width="50" height="50"></div>
          </div>
        </div>
      </div></section></div>
      <div class="col-12"><section class="card"><div class="card-header"><h2 class="card-title"><?= h($uiText('widgets')); ?></h2></div><div class="card-body row g-3">
      <?php foreach ($settings['appearance']['header_widgets'] as $group => $items): ?>
        <fieldset class="col-12 col-md-4"><legend class="fs-6"><?= h(match ($group) { 'devices' => $pia_lang['V4_UI_Group_Devices'], 'services' => $pia_lang['V4_UI_Group_Services'], 'presence' => $pia_lang['NAV_Presence'], 'icmp' => 'ICMP', default => $group }); ?></legend>
        <?php foreach ($items as $id => $visible): ?><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="widget-<?= h($group . '-' . $id); ?>" name="widgets[<?= h($group); ?>][<?= h($id); ?>]" value="1"<?= $visible ? ' checked' : ''; ?>><label class="form-check-label" for="widget-<?= h($group . '-' . $id); ?>"><?= h($uiText(array('all'=>'all','con'=>'connected','fav'=>'favorites','dnw'=>'down','arc'=>'archived','new'=>'new')[$id])); ?></label></div><?php endforeach; ?>
        </fieldset>
      <?php endforeach; ?>
      </div></section></div>
    </div>
    <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit"><?= h($uiText('save')); ?></button><a class="btn btn-outline-secondary pialert-back-link" href="<?= h(pialert_v4_route('maintenance') . '?tab=4'); ?>"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i><?= h($uiText('back')); ?></a></div>
  </form>
</section>
<?php pialert_v4_shell_end(array('js/ui-settings.js', 'js/ui-activity-history.js')); ?>

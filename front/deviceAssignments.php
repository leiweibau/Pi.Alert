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
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET'); http_response_code(405); exit('Method Not Allowed');
}
pialert_v4_load_language();
require_once __DIR__ . '/php/shell.php';
$settings = pialert_v4_ui_read();
$returnUrl = 'devices.php';
$candidate = $_GET['return_url'] ?? '';
if (is_string($candidate) && strlen($candidate) <= 16384 && !preg_match('/[\x00-\x1f\x7f\\\\]/', $candidate)) {
    $parts = parse_url($candidate);
    if (is_array($parts) && ($parts['path'] ?? '') === 'devices.php' && !isset($parts['host']) && !isset($parts['scheme'])) {
        parse_str($parts['query'] ?? '', $returnQuery);
        $allowed = array_flip(array('scansource','predefined_filter','filter_fields','filter_id','list_status','list_search','list_order','list_length','list_page'));
        $returnQuery = array_filter(array_intersect_key($returnQuery, $allowed), 'is_scalar');
        $returnUrl = 'devices.php' . ($returnQuery ? '?' . http_build_query($returnQuery) : '');
    }
}
$labels = array_filter($pia_lang, static fn($key): bool => str_starts_with((string) $key, 'LD_'), ARRAY_FILTER_USE_KEY);
$labels['save'] = $pia_lang['Gen_Save']; $labels['cancel'] = $pia_lang['Gen_Cancel']; $labels['delete'] = $pia_lang['Gen_Delete'];
$labels['close'] = $pia_lang['Gen_Close'];
$labels['online'] = $pia_lang['V4_Online']; $labels['offline'] = $pia_lang['V4_Offline']; $labels['archived'] = $pia_lang['V4_Archived'];
$pageConfig = array('viewMode'=>$settings['devices']['view_mode'], 'labels'=>$labels, 'returnUrl'=>$returnUrl);
$suggestionActions = array('device_type'=>'getDeviceTypes', 'owner'=>'getOwners', 'location'=>'getLocations', 'group'=>'getGroups');
pialert_v4_shell_start($pia_lang['LD_Title'], 'home', array('css/devices.css'));
?>
<script type="application/json" id="logical-devices-config"><?= json_encode($pageConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<section id="logical-devices-page" aria-label="<?= h($pia_lang['LD_Title']); ?>">
  <section class="card mb-4"><div class="card-body"><div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
    <div class="form-check form-switch mb-0"><input type="checkbox" class="form-check-input" role="switch" id="logical-view-mode"<?= $settings['devices']['view_mode'] === 'devices' ? ' checked' : ''; ?>><label class="form-check-label" for="logical-view-mode"><?= h($pia_lang['LD_ShowDevices']); ?></label></div>
    <a id="logical-back" class="btn btn-outline-secondary ms-auto" href="<?= h($returnUrl); ?>"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i><?= h($pia_lang['LD_BackToDevices']); ?></a>
    <div id="logical-view-feedback" class="small w-100" role="status" hidden></div>
  </div></div></section>
  <p class="text-body-secondary"><?= h($pia_lang['LD_MembershipHelp']); ?></p>
  <div id="logical-feedback" role="status" aria-live="polite" class="alert" hidden></div>
  <div class="row g-4">
    <div class="col-12 col-lg-4"><section class="card h-100"><div class="card-header"><button id="logical-create" type="button" class="btn btn-primary w-100"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i><?= h($pia_lang['LD_Create']); ?></button></div><div class="card-body"><p id="logical-empty" hidden><?= h($pia_lang['LD_Empty']); ?></p><div id="logical-device-list" class="list-group"></div></div></section></div>
    <div class="col-12 col-lg-8"><section class="card"><div class="card-header"><h2 id="logical-editor-title" class="card-title"><?= h($pia_lang['LD_Create']); ?></h2><span id="logical-unsaved" class="badge text-bg-warning ms-2" hidden><?= h($pia_lang['LD_Unsaved']); ?></span></div><div class="card-body">
      <form id="logical-device-form"><div class="row g-3">
      <?php foreach (array('name'=>'LD_DeviceName','device_type'=>'DevDetail_MainInfo_Type','owner'=>'DevDetail_MainInfo_Owner','location'=>'DevDetail_MainInfo_Location','vendor'=>'DevDetail_MainInfo_Vendor','group'=>'DevDetail_MainInfo_Group') as $field=>$label):
        $fieldLabel = pialert_v4_ui_plain_label($pia_lang[$label]);
        $suggestionAction = $suggestionActions[$field] ?? '';
      ?>
        <div class="<?= $field === 'name' ? 'col-12' : 'col-12 col-md-6'; ?>">
          <label class="form-label" for="logical-field-<?= h($field); ?>"><?= h($fieldLabel); ?></label>
          <?php if ($suggestionAction !== ''): ?><div class="input-group"><?php endif; ?>
          <input class="form-control" type="text" id="logical-field-<?= h($field); ?>" name="<?= h($field); ?>" maxlength="250" autocomplete="off"<?= $field === 'name' ? ' required' : ($field === 'vendor' ? ' list="logical-options-vendor"' : ''); ?>>
          <?php if ($suggestionAction !== ''): ?>
            <button class="btn btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-controls="logical-options-<?= h($field); ?>" aria-expanded="false" aria-label="<?= h($fieldLabel); ?>"></button>
            <ul class="dropdown-menu dropdown-menu-end logical-device-options" id="logical-options-<?= h($field); ?>" data-suggestion-action="<?= h($suggestionAction); ?>" data-suggestion-field="<?= h($field); ?>"></ul>
          </div><?php elseif ($field === 'vendor'): ?><datalist id="logical-options-vendor"></datalist><?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div><div class="d-flex gap-2 flex-wrap mt-4"><button type="submit" id="logical-save" class="btn btn-primary"><?= h($pia_lang['Gen_Save']); ?></button><button type="button" id="logical-cancel" class="btn btn-outline-secondary"><?= h($pia_lang['Gen_Cancel']); ?></button><button type="button" id="logical-delete" class="btn btn-outline-danger ms-auto" hidden><?= h($pia_lang['Gen_Delete']); ?></button></div></form>
      <section id="logical-editor-members" class="mt-4" hidden><h3 id="logical-editor-members-title" class="h6"></h3><ul id="logical-editor-members-list" class="list-group"></ul></section>
    </div></section></div>
  </div>
</section>
<div class="modal fade" id="logical-delete-modal" tabindex="-1" aria-labelledby="logical-delete-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="logical-delete-title"><?= h($pia_lang['LD_DeleteTitle']); ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h($pia_lang['Gen_Close']); ?>"></button></div><div class="modal-body" id="logical-delete-text"></div><div class="modal-footer"><button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal"><?= h($pia_lang['Gen_Cancel']); ?></button><button type="button" class="btn btn-danger" id="logical-delete-confirm"><?= h($pia_lang['Gen_Delete']); ?></button></div></div></div></div>
<?php pialert_v4_shell_end(array('js/logical-devices.js')); ?>

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
require_once __DIR__ . '/php/server/journal.php';
$DBFILE = '../db/pialert.db';
OpenDB();

function pialert_v4_network_rows($result): array {
    $rows = array();
    if ($result !== false) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) $rows[] = $row;
    }
    return $rows;
}

// Keep the six queries and their ordering from networkSettings.php.
$sql = 'SELECT "device_id", "net_device_name", "net_device_typ", "net_device_port", "net_downstream_devices", "net_networkname" FROM "network_infrastructure" ORDER BY "net_networkname" ASC, "net_device_typ" ASC';
$managedEdit = pialert_v4_network_rows($db->query($sql));
$sql = 'SELECT "device_id", "net_device_name", "net_device_typ", "net_networkname" FROM "network_infrastructure" ORDER BY "net_networkname" ASC, "net_device_typ" ASC';
$managedDelete = pialert_v4_network_rows($db->query($sql));
$sql = 'SELECT "device_id", "net_device_name", "net_device_typ", "net_device_port", "net_downstream_devices" FROM "network_infrastructure" ORDER BY "net_device_typ" ASC';
$managedConnectAdd = pialert_v4_network_rows($db->query($sql));
$sql = 'SELECT * FROM "network_dumb_dev" ORDER BY "dev_Name" ASC';
$unmanagedEdit = pialert_v4_network_rows($db->query($sql));
$sql = 'SELECT "device_id", "net_device_name", "net_device_typ", "net_device_port", "net_downstream_devices" FROM "network_infrastructure" ORDER BY "net_device_typ" ASC';
$managedConnectEdit = pialert_v4_network_rows($db->query($sql));
$sql = 'SELECT "id", "dev_Name" FROM "network_dumb_dev" ORDER BY "dev_Name" ASC';
$unmanagedDelete = pialert_v4_network_rows($db->query($sql));

$managedEditData = array();
foreach ($managedEdit as $row) {
    if (!isset($row['device_id'])) continue;
    $managedEditData[(string) $row['device_id']] = array(
        (string) $row['net_device_name'], (string) $row['net_device_typ'],
        (string) $row['net_downstream_devices'], (string) $row['net_device_port'],
        (string) $row['net_networkname']
    );
}
$unmanagedEditData = array();
foreach ($unmanagedEdit as $row) {
    if (!isset($row['id'])) continue;
    $unmanagedEditData[(string) $row['id']] = array(
        (string) $row['dev_Name'], (string) $row['dev_Infrastructure'],
        (string) $row['dev_Infrastructure_port']
    );
}
$settingsData = json_encode(array('managed' => $managedEditData, 'unmanaged' => $unmanagedEditData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
pialert_v4_shell_start($pia_lang['NetworkSettings_Title'] ?? 'Network settings', 'network', array('css/network.css'));
?>
<section id="network-settings-page" data-downlink-placeholder="<?= h($pia_lang['NET_Man_Edit_Downlink_text'] ?? 'MAC,port'); ?>" data-downlink-alt-placeholder="<?= h($pia_lang['NET_Man_Edit_Downlink_alttext'] ?? 'MAC;'); ?>">
  <div class="d-flex justify-content-end mb-3"><a class="btn btn-outline-secondary" href="./network.php"><?= h($pia_lang['Gen_Close'] ?? 'Close'); ?></a></div>
  <script type="application/json" id="network-settings-data"><?= $settingsData ?: '{}'; ?></script>

  <div class="card mb-4" id="netedit">
    <div class="card-header"><h2 class="h5 mb-0"><?= h($pia_lang['NET_Man_Devices'] ?? 'Managed devices'); ?></h2></div>
    <div class="card-body">
      <p><?= h(strip_tags($pia_lang['NET_Man_Devices_Intro'] ?? '')); ?></p>
      <div class="row g-4">
        <div class="col-lg-4"><form id="network-managed-add" class="h-100">
          <h3 class="h6"><?= h($pia_lang['NET_Man_Add'] ?? 'Add'); ?></h3>
          <label class="form-label" for="txtNetworkDeviceName"><?= h($pia_lang['NET_Man_Add_Name'] ?? 'Name'); ?></label>
          <div class="input-group mb-3"><input class="form-control" id="txtNetworkDeviceName" type="text" maxlength="255" placeholder="<?= h($pia_lang['NET_Man_Add_Name_text'] ?? ''); ?>" required><button class="btn btn-outline-secondary dropdown-toggle" id="buttonNetworkNodeMac" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= h($pia_lang['V4_Select_Device']); ?>"></button><div id="dropdownNetworkNodeMac" class="dropdown-menu dropdown-menu-end"></div></div>
          <label class="form-label" for="txtNetworkDeviceTyp"><?= h($pia_lang['NET_Man_Add_Type'] ?? 'Type'); ?></label>
          <div class="input-group mb-3"><input class="form-control" id="txtNetworkDeviceTyp" type="text" readonly required placeholder="<?= h($pia_lang['NET_Man_Add_Type_text'] ?? ''); ?>"><button class="btn btn-outline-secondary dropdown-toggle" id="buttonNetworkDeviceTyp" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= h($pia_lang['V4_Select_Type']); ?>"></button><div id="dropdownNetworkDeviceTyp" class="dropdown-menu dropdown-menu-end"></div></div>
          <label class="form-label" for="NetworkDevicePort"><?= h($pia_lang['NET_Man_Add_Port'] ?? 'Ports'); ?></label><input class="form-control mb-3" id="NetworkDevicePort" type="number" min="0" max="1024" placeholder="<?= h($pia_lang['NET_Man_Add_Port_text'] ?? ''); ?>">
          <label class="form-label" for="txtNetworkGroupName"><?= h($pia_lang['NET_Man_Add_NetName'] ?? 'Network'); ?></label>
          <div class="input-group mb-3"><input class="form-control" id="txtNetworkGroupName" type="text" maxlength="255" placeholder="<?= h($pia_lang['NET_Man_Add_NetName_text'] ?? ''); ?>"><button class="btn btn-outline-secondary dropdown-toggle" id="buttonNetworkGroupName" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= h($pia_lang['V4_Select_Network']); ?>"></button><div id="dropdownNetworkGroupName" class="dropdown-menu dropdown-menu-end"></div></div>
          <button class="btn btn-success" type="submit"><?= h($pia_lang['NET_Man_Add_Submit'] ?? 'Add'); ?></button>
        </form></div>

        <div class="col-lg-4"><form id="network-managed-edit" class="h-100">
          <h3 class="h6"><?= h($pia_lang['NET_Man_Edit'] ?? 'Edit'); ?></h3>
          <label class="form-label" for="UpdNetworkDeviceID"><?= h($pia_lang['NET_Man_Edit_ID'] ?? 'Device'); ?></label>
          <select class="form-select mb-3" id="UpdNetworkDeviceID" required><option value=""><?= h($pia_lang['NET_Man_Edit_ID_text'] ?? 'Select'); ?></option>
            <?php foreach ($managedEdit as $row): if (!isset($row['device_id'])) continue; ?><option value="<?= h($row['device_id']); ?>"><?= h($row['net_networkname']); ?> - <?= h($row['net_device_name']); ?> / <?= h(substr((string) $row['net_device_typ'], 2)); ?></option><?php endforeach; ?>
          </select>
          <label class="form-label" for="NewNetworkDeviceName"><?= h($pia_lang['NET_Man_Edit_Name'] ?? 'Name'); ?></label><input class="form-control mb-3" id="NewNetworkDeviceName" type="text" maxlength="255">
          <label class="form-label" for="txtNewNetworkDeviceTyp"><?= h($pia_lang['NET_Man_Edit_Type'] ?? 'Type'); ?></label>
          <div class="input-group mb-3"><input class="form-control" id="txtNewNetworkDeviceTyp" type="text" readonly required><button class="btn btn-outline-secondary dropdown-toggle" id="buttonNewNetworkDeviceTyp" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= h($pia_lang['V4_Select_Type']); ?>"></button><div id="dropdownNewNetworkDeviceTyp" class="dropdown-menu dropdown-menu-end"></div></div>
          <label class="form-label" for="txtNewNetworkGroupName"><?= h($pia_lang['NET_Man_Edit_NetName'] ?? 'Network'); ?></label>
          <div class="input-group mb-3"><input class="form-control" id="txtNewNetworkGroupName" type="text" maxlength="255"><button class="btn btn-outline-secondary dropdown-toggle" id="buttonNewNetworkGroupName" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= h($pia_lang['V4_Select_Network']); ?>"></button><div id="dropdownNewNetworkGroupName" class="dropdown-menu dropdown-menu-end"></div></div>
          <label class="form-label" for="NewNetworkDevicePort"><?= h($pia_lang['NET_Man_Edit_Port'] ?? 'Ports'); ?></label><input class="form-control mb-3" id="NewNetworkDevicePort" type="number" min="0" max="1024">
          <label class="form-label" for="txtNetworkDeviceDownlinkMac"><?= h($pia_lang['NET_Man_Edit_Downlink'] ?? 'Downlink'); ?></label>
          <div class="input-group mb-3"><input class="form-control" id="txtNetworkDeviceDownlinkMac" type="text" maxlength="8192"><button class="btn btn-outline-secondary dropdown-toggle" id="buttonNetworkDeviceDownlinkMac" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= h($pia_lang['V4_Select_Downlink']); ?>"></button><div id="dropdownNetworkDeviceDownlinkMac" class="dropdown-menu dropdown-menu-end"></div></div>
          <button class="btn btn-warning" type="submit"><?= h($pia_lang['NET_Man_Edit_Submit'] ?? 'Save'); ?></button>
        </form></div>

        <div class="col-lg-4"><form id="network-managed-delete">
          <h3 class="h6"><?= h($pia_lang['NET_Man_Del'] ?? 'Delete'); ?></h3>
          <label class="form-label" for="DelNetworkDeviceID"><?= h($pia_lang['NET_Man_Del_Name'] ?? 'Device'); ?></label>
          <select class="form-select mb-3" id="DelNetworkDeviceID" required><option value=""><?= h($pia_lang['NET_Man_Del_Name_text'] ?? 'Select'); ?></option>
            <?php foreach ($managedDelete as $row): if (!isset($row['device_id'])) continue; ?><option value="<?= h($row['device_id']); ?>"><?= h($row['net_networkname']); ?> - <?= h($row['net_device_name']); ?> / <?= h(substr((string) $row['net_device_typ'], 2)); ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-danger" type="submit"><?= h($pia_lang['NET_Man_Del_Submit'] ?? 'Delete'); ?></button>
        </form></div>
      </div>
    </div>
  </div>

  <div class="card mb-4" id="hostedit">
    <div class="card-header"><h2 class="h5 mb-0"><?= h($pia_lang['NET_UnMan_Devices'] ?? 'Unmanaged devices'); ?></h2></div>
    <div class="card-body">
      <p><?= h(strip_tags($pia_lang['NET_UnMan_Devices_Intro'] ?? '')); ?></p>
      <div class="row g-4">
        <div class="col-lg-4"><form id="network-unmanaged-add">
          <h3 class="h6"><?= h($pia_lang['NET_Man_Add'] ?? 'Add'); ?></h3>
          <label class="form-label" for="txtNetworkUnmanagedDevName"><?= h($pia_lang['NET_Man_Add_Name'] ?? 'Name'); ?></label><input class="form-control mb-3" id="txtNetworkUnmanagedDevName" type="text" maxlength="255" required>
          <label class="form-label" for="txtNetworkUnmanagedDevConnect"><?= h($pia_lang['NET_UnMan_Devices_Connected'] ?? 'Connected to'); ?></label>
          <select class="form-select mb-3" id="txtNetworkUnmanagedDevConnect" required><option value=""><?= h($pia_lang['NET_UnMan_Devices_Connected_text'] ?? 'Select'); ?></option>
            <?php foreach ($managedConnectAdd as $row): if (!isset($row['device_id'])) continue; ?><option value="<?= h($row['device_id']); ?>"><?= h($row['net_device_name']); ?> / <?= h(substr((string) $row['net_device_typ'], 2)); ?></option><?php endforeach; ?>
          </select>
          <label class="form-label" for="NetworkUnmanagedDevPort"><?= h($pia_lang['NET_UnMan_Devices_Port'] ?? 'Port'); ?></label><input class="form-control mb-3" id="NetworkUnmanagedDevPort" type="text" maxlength="4096" placeholder="<?= h($pia_lang['NET_UnMan_Devices_Port_text'] ?? ''); ?>">
          <button class="btn btn-success" type="submit"><?= h($pia_lang['NET_Man_Add_Submit'] ?? 'Add'); ?></button>
        </form></div>

        <div class="col-lg-4"><form id="network-unmanaged-edit">
          <h3 class="h6"><?= h($pia_lang['NET_Man_Edit'] ?? 'Edit'); ?></h3>
          <label class="form-label" for="NetworkUnmanagedDevID"><?= h($pia_lang['NET_Man_Edit_ID'] ?? 'Device'); ?></label>
          <select class="form-select mb-3" id="NetworkUnmanagedDevID" required><option value=""><?= h($pia_lang['NET_Man_Edit_ID_text'] ?? 'Select'); ?></option>
            <?php foreach ($unmanagedEdit as $row): if (!isset($row['id'])) continue; ?><option value="<?= h($row['id']); ?>"><?= h($row['dev_Name']); ?></option><?php endforeach; ?>
          </select>
          <label class="form-label" for="NewNetworkUnmanagedDevName"><?= h($pia_lang['NET_Man_Edit_Name'] ?? 'Name'); ?></label><input class="form-control mb-3" id="NewNetworkUnmanagedDevName" type="text" maxlength="255">
          <label class="form-label" for="NewNetworkUnmanagedDevConnect"><?= h($pia_lang['NET_UnMan_Devices_Connected'] ?? 'Connected to'); ?></label>
          <select class="form-select mb-3" id="NewNetworkUnmanagedDevConnect" required><option value=""><?= h($pia_lang['NET_UnMan_Devices_Connected_text'] ?? 'Select'); ?></option>
            <?php foreach ($managedConnectEdit as $row): if (!isset($row['device_id'])) continue; ?><option value="<?= h($row['device_id']); ?>"><?= h($row['net_device_name']); ?> / <?= h(substr((string) $row['net_device_typ'], 2)); ?></option><?php endforeach; ?>
          </select>
          <label class="form-label" for="NewNetworkUnmanagedDevPort"><?= h($pia_lang['NET_UnMan_Devices_Port'] ?? 'Port'); ?></label><input class="form-control mb-3" id="NewNetworkUnmanagedDevPort" type="text" maxlength="4096">
          <button class="btn btn-warning" type="submit"><?= h($pia_lang['NET_Man_Edit_Submit'] ?? 'Save'); ?></button>
        </form></div>

        <div class="col-lg-4"><form id="network-unmanaged-delete">
          <h3 class="h6"><?= h($pia_lang['NET_Man_Del'] ?? 'Delete'); ?></h3>
          <label class="form-label" for="DelNetworkUnmanagedDevID"><?= h($pia_lang['NET_Man_Del_Name'] ?? 'Device'); ?></label>
          <select class="form-select mb-3" id="DelNetworkUnmanagedDevID" required><option value=""><?= h($pia_lang['NET_Man_Del_Name_text'] ?? 'Select'); ?></option>
            <?php foreach ($unmanagedDelete as $row): if (!isset($row['id'])) continue; ?><option value="<?= h($row['id']); ?>"><?= h($row['dev_Name']); ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-danger" type="submit"><?= h($pia_lang['NET_Man_Del_Submit'] ?? 'Delete'); ?></button>
        </form></div>
      </div>
    </div>
  </div>
</section>
<?php pialert_v4_shell_end(array('js/network-settings.js')); ?>

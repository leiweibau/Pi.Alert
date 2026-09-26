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

function pialert_v4_sysinfo_command(string $command): string {
    $result = shell_exec($command);
    return is_string($result) ? trim($result) : '';
}

function pialert_v4_sysinfo_bool($value): string {
    global $pia_lang;
    return $value == true ? $pia_lang['V4_Enabled'] : $pia_lang['V4_Disabled'];
}

function pialert_v4_sysinfo_metric(string $label, string $value, string $class = ''): void { ?>
  <div class="row g-0 pialert-sysinfo-row">
    <dt class="col-sm-4 col-lg-3"><?= h($label); ?></dt>
    <dd class="col-sm-8 col-lg-9 mb-0<?= $class !== '' ? ' ' . h($class) : ''; ?>"><?= $value; ?></dd>
  </div>
<?php }

function pialert_v4_sysinfo_tables(string $database, array $ignored): array {
    if (!class_exists('SQLite3') || !is_file($database)) return array();
    $rows = array();
    $db = new SQLite3($database, SQLITE3_OPEN_READONLY);
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name ASC");
    while ($tables && ($table = $tables->fetchArray(SQLITE3_ASSOC))) {
        $name = (string) ($table['name'] ?? '');
        if ($name === '' || in_array($name, $ignored, true)) continue;
        // Intentionally retained byte-for-byte from the legacy page: the
        // table name originates exclusively from sqlite_master above.
        $tableName = $name;
        $countResult = $db->query("SELECT COUNT(*) as count FROM $tableName");
        $count = $countResult ? $countResult->fetchArray(SQLITE3_ASSOC) : false;
        $rows[] = array('name' => $name, 'count' => (int) ($count['count'] ?? 0));
    }
    $db->close();
    return $rows;
}

function pialert_v4_sysinfo_timezone(string $database): string {
    if (!class_exists('SQLite3') || !is_file($database)) return 'unknown';
    $db = new SQLite3($database, SQLITE3_OPEN_READONLY);
    // Retained from get_local_system_tz() in the legacy header helper.
    $query = "SELECT par_Value FROM Parameters WHERE par_ID = 'Local_System_TZ'";
    $result = $db->query($query);
    $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
    $db->close();
    return is_array($row) ? (string) ($row['par_Value'] ?? 'unknown') : 'unknown';
}

$cronFile = __DIR__ . '/../log/usercron.log';
$cronContents = is_readable($cronFile) ? file_get_contents($cronFile) : '';
$userCron = implode("\n", array_filter(array_map('trim', explode("\n", is_string($cronContents) ? $cronContents : ''))));

$osRelease = @parse_ini_file('/etc/os-release');
$osVersion = is_array($osRelease) ? (string) ($osRelease['PRETTY_NAME'] ?? '') : '';
if ($osVersion === '') $osVersion = pialert_v4_sysinfo_command('uname -o');
$uptime = str_replace('up ', '', pialert_v4_sysinfo_command('uptime -p'));
$cpuInfo = is_readable('/proc/cpuinfo') ? (string) file_get_contents('/proc/cpuinfo') : '';
$cpuCount = preg_match_all('/^processor\s*:/m', $cpuInfo);
$cpuModel = '';
if (preg_match('/^Model\s*:\s*(.+)$/m', $cpuInfo, $cpuMatch) || preg_match('/^model name\s*:\s*(.+)$/m', $cpuInfo, $cpuMatch)) $cpuModel = trim($cpuMatch[1]);
$cpuFrequency = 'unknown';
$frequencyFile = '/sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq';
if (is_readable($frequencyFile) && is_numeric(trim((string) file_get_contents($frequencyFile)))) {
    $cpuFrequency = (string) round((float) trim((string) file_get_contents($frequencyFile)) / 1000);
} elseif (preg_match('/^cpu MHz\s*:\s*([0-9.,]+)/mi', $cpuInfo, $frequencyMatch)) {
    $cpuFrequency = (string) round((float) str_replace(',', '.', $frequencyMatch[1]));
}
$kernelArch = pialert_v4_sysinfo_command('dpkg --print-architecture');
if ($kernelArch === '') $kernelArch = php_uname('m');

$memoryInfo = is_readable('/proc/meminfo') ? (string) file_get_contents('/proc/meminfo') : '';
$memoryValues = array();
preg_match_all('/^(MemTotal|MemFree|Buffers|Cached):\s+(\d+)/m', $memoryInfo, $memoryMatches, PREG_SET_ORDER);
foreach ($memoryMatches as $match) $memoryValues[$match[1]] = (float) $match[2];
$memoryTotal = isset($memoryValues['MemTotal']) ? round($memoryValues['MemTotal'] / 1024 / 1024, 3) : 0;
$memoryUsed = 0;
if (($memoryValues['MemTotal'] ?? 0) > 0) {
    $usedMemory = $memoryValues['MemTotal'] - ($memoryValues['MemFree'] ?? 0) - ($memoryValues['Buffers'] ?? 0) - ($memoryValues['Cached'] ?? 0);
    $memoryUsed = round($usedMemory * 100 / $memoryValues['MemTotal'], 2);
}
$processCount = pialert_v4_sysinfo_command('ps -e --no-headers | wc -l');

$networkLines = is_readable('/proc/net/dev') ? file('/proc/net/dev', FILE_IGNORE_NEW_LINES) : array();
$networkInterfaces = array();
$interfacesWithApiAddresses = array();
foreach (array_slice(is_array($networkLines) ? $networkLines : array(), 2) as $line) {
    $parts = preg_split('/\s+/', trim($line));
    if (count($parts) < 10) continue;
    $name = rtrim((string) $parts[0], ':');
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/D', $name)) continue;
    $networkInterfaces[$name] = array('rx' => (float) $parts[1], 'tx' => (float) $parts[9], 'addresses' => array(), 'masks' => array());
}
if (function_exists('net_get_interfaces')) {
    foreach ((array) @net_get_interfaces() as $name => $interface) {
        if (!isset($networkInterfaces[$name])) continue;
        foreach ((array) ($interface['unicast'] ?? array()) as $address) {
            if (($address['family'] ?? null) !== 2 || filter_var($address['address'] ?? '', FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) continue;
            $display = (string) $address['address'];
            $netmask = (string) ($address['netmask'] ?? '');
            if (filter_var($netmask, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                $maskValue = (int) sprintf('%u', ip2long($netmask));
                $display .= '/' . substr_count(decbin($maskValue), '1');
                if ($name !== 'lo' && $netmask !== '255.255.255.255') $networkInterfaces[$name]['masks'][$netmask] = true;
            }
            $networkInterfaces[$name]['addresses'][] = $display;
            $interfacesWithApiAddresses[$name] = true;
        }
    }
}
// lighttpd can deny the AF_NETLINK socket used by net_get_interfaces(). Keep
// the existing procfs fallback and map local addresses to their best route.
$routeNetworks = array();
foreach (array_slice(@file('/proc/net/route', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: array(), 1) as $routeLine) {
    $fields = preg_split('/\s+/', trim($routeLine));
    if (count($fields) < 8 || !isset($networkInterfaces[$fields[0]]) || !preg_match('/^[0-9a-f]{8}$/iD', $fields[1]) || !preg_match('/^[0-9a-f]{8}$/iD', $fields[7]) || $fields[7] === '00000000') continue;
    $network = (int) hexdec(implode('', array_reverse(str_split($fields[1], 2))));
    $mask = (int) hexdec(implode('', array_reverse(str_split($fields[7], 2))));
    $routeNetworks[] = array('interface' => $fields[0], 'network' => $network, 'mask' => $mask, 'prefix' => substr_count(decbin($mask), '1'));
}
$localAddresses = array();
$candidateAddress = null;
foreach (@file('/proc/net/fib_trie', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: array() as $fibLine) {
    $trimmedLine = trim($fibLine);
    if (preg_match('/\|--\s+([0-9]+(?:\.[0-9]+){3})$/', $trimmedLine, $addressMatch)) {
        $candidateAddress = $addressMatch[1];
    } elseif ($candidateAddress !== null && preg_match('/^\/32\s+host\s+LOCAL$/', $trimmedLine)) {
        $localAddresses[$candidateAddress] = true;
        $candidateAddress = null;
    }
}
foreach (array_keys($localAddresses) as $address) {
    $addressValue = (int) sprintf('%u', ip2long($address));
    $bestRoute = null;
    foreach ($routeNetworks as $route) {
        if (($addressValue & $route['mask']) !== ($route['network'] & $route['mask'])) continue;
        if ($bestRoute === null || $route['prefix'] > $bestRoute['prefix']) $bestRoute = $route;
    }
    $name = $bestRoute['interface'] ?? (str_starts_with($address, '127.') && isset($networkInterfaces['lo']) ? 'lo' : null);
    if ($name === null || isset($interfacesWithApiAddresses[$name])) continue;
    $prefix = $bestRoute['prefix'] ?? 8;
    $networkInterfaces[$name]['addresses'][] = $address . '/' . $prefix;
    $mask = $bestRoute !== null ? long2ip($bestRoute['mask']) : '255.0.0.0';
    if ($name !== 'lo' && $mask !== '255.255.255.255') $networkInterfaces[$name]['masks'][$mask] = true;
}
$networkMasks = array();
foreach ($networkInterfaces as $name => $interface) {
    foreach (array_keys($interface['masks']) as $mask) $networkMasks[] = array('interface' => $name, 'mask' => $mask);
}

$diskUsage = array();
$dfOutput = pialert_v4_sysinfo_command('/usr/bin/df -P 2>/dev/null');
foreach (array_slice(preg_split('/\r?\n/', $dfOutput) ?: array(), 1) as $line) {
    $parts = preg_split('/\s+/', trim($line), 6);
    if (count($parts) !== 6 || !str_starts_with($parts[0], '/dev/') || str_contains($parts[0], '/loop')) continue;
    $diskUsage[] = $parts;
}
$blockDevices = array();
$lsblkOutput = pialert_v4_sysinfo_command('lsblk -P -o NAME,SIZE,TYPE,MOUNTPOINT,MODEL 2>/dev/null');
foreach (preg_split('/\r?\n/', $lsblkOutput) ?: array() as $line) {
    preg_match_all('/([A-Z]+)="((?:\\\\.|[^"])*)"/', $line, $matches, PREG_SET_ORDER);
    $device = array();
    foreach ($matches as $match) $device[strtolower($match[1])] = stripcslashes($match[2]);
    if (($device['type'] ?? '') !== 'loop' && ($device['name'] ?? '') !== '') $blockDevices[] = $device;
}

$usbDevices = array();
foreach (glob('/sys/bus/usb/devices/*') ?: array() as $usbPath) {
    $vendor = is_readable($usbPath . '/idVendor') ? trim((string) file_get_contents($usbPath . '/idVendor')) : '';
    $product = is_readable($usbPath . '/idProduct') ? trim((string) file_get_contents($usbPath . '/idProduct')) : '';
    if (!preg_match('/^[0-9a-f]{4}$/iD', $vendor) || !preg_match('/^[0-9a-f]{4}$/iD', $product)) continue;
    $manufacturer = is_readable($usbPath . '/manufacturer') ? trim((string) file_get_contents($usbPath . '/manufacturer')) : '';
    $productName = is_readable($usbPath . '/product') ? trim((string) file_get_contents($usbPath . '/product')) : '';
    $bus = is_readable($usbPath . '/busnum') ? trim((string) file_get_contents($usbPath . '/busnum')) : '';
    $device = is_readable($usbPath . '/devnum') ? trim((string) file_get_contents($usbPath . '/devnum')) : '';
    $label = ctype_digit($bus) && ctype_digit($device) ? sprintf('Bus %03d Dev. %03d', (int) $bus, (int) $device) : 'USB ' . basename($usbPath);
    $id = strtolower($vendor . ':' . $product);
    $name = trim($manufacturer . ' ' . $productName);
    $usbDevices[] = array('bus' => $label, 'device' => $name === '' ? $id : $name . ' (' . $id . ')');
}
usort($usbDevices, static fn(array $left, array $right): int => strcmp($left['bus'], $right['bus']));

$runningServices = array();
exec('systemctl --type=service --state=running --no-pager --no-legend 2>/dev/null', $serviceLines);
foreach ($serviceLines as $line) {
    $parts = preg_split('/\s+/', trim($line), 5);
    if (count($parts) >= 5 && str_ends_with($parts[0], '.service')) $runningServices[] = array('name' => substr($parts[0], 0, -8), 'description' => $parts[4]);
}

$satellites = array();
$databaseDirectory = dirname(__DIR__) . '/db';
$mainDatabase = $databaseDirectory . '/pialert.db';
$toolsDatabase = $databaseDirectory . '/pialert_tools.db';
if (($_SESSION['Scan_Satellite'] ?? false) == true && class_exists('SQLite3') && is_file($mainDatabase)) {
    $satelliteDb = new SQLite3($mainDatabase, SQLITE3_OPEN_READONLY);
    $satelliteResult = $satelliteDb->query('SELECT * FROM Satellites ORDER BY sat_name ASC');
    while ($satelliteResult && ($satellite = $satelliteResult->fetchArray(SQLITE3_ASSOC))) {
        $satellite['host'] = json_decode((string) ($satellite['sat_host_data'] ?? ''), true);
        if (!is_array($satellite['host'])) $satellite['host'] = array();
        $satellites[] = $satellite;
    }
    $satelliteDb->close();
}
$mainTables = pialert_v4_sysinfo_tables($mainDatabase, array('Tools_Speedtest_History', 'Tools_Nmap_ManScan', 'sqlite_sequence', 'sqlite_stat1'));
$toolsTables = pialert_v4_sysinfo_tables($toolsDatabase, array('sqlite_sequence', 'sqlite_stat1'));
$systemTimezone = pialert_v4_sysinfo_timezone($mainDatabase);

$title = $pia_lang['V4_System_Info'];
pialert_v4_shell_start($title, 'systeminfo', array('css/systeminfo.css'));
?>
<section class="pialert-systeminfo" aria-label="<?= h($title); ?>">
  <div class="row g-3 mb-3" aria-label="<?= h($pia_lang['V4_System_Actions']); ?>">
    <div class="col-sm-6"><button type="button" class="btn btn-danger pialert-system-action" onclick="askPialertShutdown()"><i class="fa-solid fa-power-off" aria-hidden="true"></i><span><?= $pia_lang['SysInfo_Shutdown'] ?? 'Shut down Pi.Alert after<br>the next scan'; ?></span></button></div>
    <div class="col-sm-6"><button type="button" class="btn btn-warning pialert-system-action" onclick="askPialertReboot()"><i class="fa-solid fa-power-off" aria-hidden="true"></i><span><?= $pia_lang['SysInfo_Reboot'] ?? 'Reboot Pi.Alert after<br>the next scan'; ?></span></button></div>
  </div>

  <section class="card" aria-labelledby="client-heading"><div class="card-header"><h2 class="card-title" id="client-heading"><i class="bi bi-globe me-2" aria-hidden="true"></i><?= h($pia_lang['V4_This_Client']); ?></h2></div><div class="card-body"><dl class="mb-0">
    <?php pialert_v4_sysinfo_metric($pia_lang['V4_User_Agent'], h($_SERVER['HTTP_USER_AGENT'] ?? '')); ?>
    <?php pialert_v4_sysinfo_metric($pia_lang['V4_Browser_Resolution'], '<span id="resolution"></span>'); ?>
  </dl></div></section>

  <section class="card" aria-labelledby="sys_info_gen_head"><div class="card-header p-0 border-bottom-0"><div class="pialert-sysinfo-card-title" id="sys_info_gen_head"><i class="bi bi-info-circle me-2" aria-hidden="true"></i><?= h($pia_lang['V4_General']); ?></div><ul class="nav nav-tabs px-3" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link active" id="general-local-tab" data-bs-toggle="tab" data-bs-target="#general-local" type="button" role="tab" aria-controls="general-local" aria-selected="true">Pi.Alert</button></li>
    <?php foreach ($satellites as $index => $satellite): ?><li class="nav-item" role="presentation"><button class="nav-link" id="general-satellite-<?= $index; ?>-tab" data-bs-toggle="tab" data-bs-target="#general-satellite-<?= $index; ?>" type="button" role="tab" aria-controls="general-satellite-<?= $index; ?>" aria-selected="false"><?= h($satellite['sat_name'] ?? 'Satellite'); ?></button></li><?php endforeach; ?>
  </ul></div><div class="card-body tab-content">
    <div class="tab-pane fade show active" id="general-local" role="tabpanel" aria-labelledby="general-local-tab" tabindex="0"><dl class="mb-0">
      <?php pialert_v4_sysinfo_metric($pia_lang['V4_Uptime'], h($uptime), 'text-success'); ?>
      <?php pialert_v4_sysinfo_metric($pia_lang['V4_Operating_System'], h($osVersion)); ?>
      <?php pialert_v4_sysinfo_metric($pia_lang['V4_Kernel_Architecture'], h($kernelArch)); ?>
      <?php pialert_v4_sysinfo_metric($pia_lang['V4_CPU_Name'], h($cpuModel)); ?>
      <?php pialert_v4_sysinfo_metric($pia_lang['V4_CPU_Cores'], h((string) $cpuCount . ' @ ' . $cpuFrequency . ' MHz')); ?>
      <?php pialert_v4_sysinfo_metric($pia_lang['V4_Memory'], h($memoryTotal . ' MB / ' . $memoryUsed . '% ' . $pia_lang['V4_Percent_Used'])); ?>
      <?php pialert_v4_sysinfo_metric($pia_lang['V4_Running_Processes'], h($processCount)); ?>
      <?php pialert_v4_sysinfo_metric($pia_lang['V4_Timezone_PHP_System'], h('"' . date_default_timezone_get() . '" / "' . $systemTimezone . '"')); ?>
      <?php pialert_v4_sysinfo_metric($pia_lang['V4_PHP_Version'], h(PHP_VERSION)); ?>
    </dl></div>
    <?php foreach ($satellites as $index => $satellite): $host = $satellite['host']; $lastUpdate = (string) ($satellite['sat_lastupdate'] ?? ''); $lastTimestamp = strtotime($lastUpdate); $fresh = $lastTimestamp !== false && abs(time() - $lastTimestamp) <= 600; ?>
      <div class="tab-pane fade" id="general-satellite-<?= $index; ?>" role="tabpanel" aria-labelledby="general-satellite-<?= $index; ?>-tab" tabindex="0"><dl class="mb-0">
        <?php pialert_v4_sysinfo_metric($pia_lang['V4_Uptime'], h((string) ($host['uptime'] ?? '') . ($lastUpdate !== '' ? ' (' . $lastUpdate . ')' : '')), $fresh ? 'text-success' : 'text-danger'); ?>
        <?php pialert_v4_sysinfo_metric($pia_lang['V4_Operating_System'], h($host['os_version'] ?? '')); ?>
        <?php pialert_v4_sysinfo_metric($pia_lang['V4_Kernel_Architecture'], h($host['cpu_arch'] ?? '')); ?>
        <?php pialert_v4_sysinfo_metric($pia_lang['V4_CPU_Name'], h($host['cpu_name'] ?? '')); ?>
        <?php pialert_v4_sysinfo_metric($pia_lang['V4_CPU_Cores'], h(($host['cpu_cores'] ?? '') . ' @ ' . ($host['cpu_freq'] ?? ''))); ?>
        <?php $satRam = is_numeric($host['ram_total'] ?? null) ? round((float) $host['ram_total'] / 1048576, 2) : 0; pialert_v4_sysinfo_metric($pia_lang['V4_Memory'], h($satRam . ' MB / ' . ($host['ram_used_percent'] ?? '') . '% ' . $pia_lang['V4_Percent_Used'])); ?>
        <?php pialert_v4_sysinfo_metric($pia_lang['V4_Running_Processes'], h($host['proc_count'] ?? '')); ?>
        <?php pialert_v4_sysinfo_metric($pia_lang['V4_Timezone_System'], h('"' . ($host['os_timezone'] ?? '') . '"')); ?>
        <?php $satMac = (string) ($host['satellite_mac'] ?? ''); pialert_v4_sysinfo_metric($pia_lang['V4_Satellite_Host'], h($pia_lang['V4_Name']) . ': ' . h($host['hostname'] ?? '') . ' / IP: ' . h($host['satellite_ip'] ?? '') . ' / MAC: <a href="./deviceDetails.php?mac=' . rawurlencode($satMac) . '">' . h($satMac) . '</a>'); ?>
        <?php pialert_v4_sysinfo_metric($pia_lang['V4_Proxy_Mode'], is_bool($host['satellite_proxymode'] ?? null) ? ($host['satellite_proxymode'] ? $pia_lang['V4_True'] : $pia_lang['V4_False']) : $pia_lang['V4_Unknown']); ?>
        <?php pialert_v4_sysinfo_metric($pia_lang['V4_API_URL'], h($host['satellite_url'] ?? $pia_lang['V4_Unknown'])); ?>
      </dl></div>
    <?php endforeach; ?>
  </div></section>

  <section class="card" aria-labelledby="database-heading"><div class="card-header p-0 border-bottom-0"><div class="pialert-sysinfo-card-title" id="database-heading"><i class="bi bi-database me-2" aria-hidden="true"></i><?= h($pia_lang['V4_Databases']); ?></div><ul class="nav nav-tabs px-3" role="tablist"><li class="nav-item"><button class="nav-link active" id="database-main-tab" data-bs-toggle="tab" data-bs-target="#database-main" type="button" role="tab"><?= h($pia_lang['V4_Main']); ?></button></li><li class="nav-item"><button class="nav-link" id="database-tools-tab" data-bs-toggle="tab" data-bs-target="#database-tools" type="button" role="tab"><?= h($pia_lang['V4_Tools']); ?></button></li></ul></div><div class="card-body tab-content">
    <?php foreach (array('main' => array($mainDatabase, $mainTables), 'tools' => array($toolsDatabase, $toolsTables)) as $databaseKey => $databaseData): ?><div class="tab-pane fade<?= $databaseKey === 'main' ? ' show active' : ''; ?>" id="database-<?= $databaseKey; ?>" role="tabpanel" aria-labelledby="database-<?= $databaseKey; ?>-tab" tabindex="0"><p><?= h($pia_lang['V4_Database_Directory']); ?> <strong class="text-break"><?= h($databaseData[0]); ?></strong></p><div class="table-responsive pialert-sysinfo-scroll"><table class="table table-sm table-striped table-hover"><thead><tr><th scope="col"><?= h($pia_lang['V4_Table_Name']); ?></th><th scope="col"><?= h($pia_lang['V4_Table_Entries']); ?></th></tr></thead><tbody><?php foreach ($databaseData[1] as $table): ?><tr><td><?= h($table['name']); ?></td><td><?= h((string) $table['count']); ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endforeach; ?>
  </div></section>

  <section class="card" aria-labelledby="user-cron-heading"><div class="card-header"><h2 class="card-title" id="user-cron-heading"><i class="bi bi-list-task me-2" aria-hidden="true"></i><?= h($pia_lang['V4_User_Crontab']); ?></h2></div><div class="card-body"><pre class="pialert-sysinfo-pre mb-0"><?= h($userCron); ?></pre></div></section>
  <section class="card" aria-labelledby="pialert-cron-heading"><div class="card-header"><h2 class="card-title" id="pialert-cron-heading"><i class="bi bi-list-task me-2" aria-hidden="true"></i><?= h($pia_lang['V4_PiAlert_Crons']); ?></h2></div><div class="card-body table-responsive"><table class="table table-sm table-striped table-hover mb-0"><thead><tr><th scope="col"><?= h($pia_lang['V4_Cron_Name']); ?></th><th scope="col">Cron</th><th scope="col"><?= h($pia_lang['V4_Status']); ?></th></tr></thead><tbody>
    <?php foreach (array(array($pia_lang['V4_Update_Check'], 'AUTO_UPDATE_CHECK_CRON', 'Auto_Update_Check'), array($pia_lang['V4_Backup'], 'AUTO_DB_BACKUP_CRON', 'AUTO_DB_BACKUP'), array($pia_lang['V4_Speedtest'], 'SPEEDTEST_TASK_CRON', 'SPEEDTEST_TASK_ACTIVE'), array($pia_lang['V4_Continuous_Notifications'], 'REPORT_NEW_CONTINUOUS_CRON', 'REPORT_NEW_CONTINUOUS')) as $cron): ?><tr><td><?= h($cron[0]); ?></td><td><?= h($_SESSION[$cron[1]] ?? ''); ?></td><td><?= h(pialert_v4_sysinfo_bool($_SESSION[$cron[2]] ?? false)); ?></td></tr><?php endforeach; ?>
  </tbody></table></div></section>

  <section class="card" aria-labelledby="storage-heading"><div class="card-header"><h2 class="card-title" id="storage-heading"><i class="bi bi-hdd me-2" aria-hidden="true"></i><?= h($pia_lang['V4_Storage']); ?></h2></div><div class="card-body"><div class="table-responsive"><table class="table table-sm table-striped mb-0"><thead><tr><th><?= h($pia_lang['V4_Mount_Model']); ?></th><th><?= h($pia_lang['V4_Device']); ?></th><th><?= h($pia_lang['V4_Size']); ?></th><th><?= h($pia_lang['V4_Type']); ?></th></tr></thead><tbody><?php foreach ($blockDevices as $device): ?><tr><td><?= h(($device['mountpoint'] ?? '') !== '' ? $device['mountpoint'] : ($device['model'] ?? '')); ?></td><td>/dev/<?= h($device['name'] ?? ''); ?></td><td><?= h($device['size'] ?? ''); ?></td><td><?= h($device['type'] ?? ''); ?></td></tr><?php endforeach; ?></tbody></table></div></div></section>
  <section class="card" aria-labelledby="storage-usage-heading"><div class="card-header"><h2 class="card-title" id="storage-usage-heading"><i class="bi bi-hdd me-2" aria-hidden="true"></i><?= h($pia_lang['V4_Storage_Usage']); ?></h2></div><div class="card-body"><div class="table-responsive"><table class="table table-sm table-striped mb-0"><thead><tr><th><?= h($pia_lang['V4_Mount_Point']); ?></th><th><?= h($pia_lang['V4_Total']); ?></th><th><?= h($pia_lang['V4_Used']); ?></th><th><?= h($pia_lang['V4_Free']); ?></th></tr></thead><tbody><?php foreach ($diskUsage as $disk): ?><tr><td><?= h($disk[5]); ?></td><td><?= h(number_format((float) $disk[1] / 1048576, 2, ',', '.') . ' GB'); ?></td><td><?= h(number_format((float) $disk[2] / 1048576, 2, ',', '.') . ' GB (' . $disk[4] . ')'); ?></td><td><?= h(number_format((float) $disk[3] / 1048576, 2, ',', '.') . ' GB'); ?></td></tr><?php endforeach; ?></tbody></table></div><p class="form-text mb-0 mt-2"><?= h($pia_lang['SysInfo_storage_note'] ?? ''); ?></p></div></section>

  <section class="card" aria-labelledby="network-heading">
    <div class="card-header"><h2 class="card-title" id="network-heading"><i class="bi bi-hdd-network me-2" aria-hidden="true"></i><?= h($pia_lang['V4_Network']); ?></h2></div>
    <div class="card-body">
      <?php if ($networkMasks): ?><div class="d-flex flex-wrap gap-2 mb-3" aria-label="<?= h($pia_lang['V4_IPv4_Masks']); ?>">
        <?php foreach ($networkMasks as $entry): ?><span class="badge text-bg-secondary"><?= h($entry['interface']); ?> · <?= h($pia_lang['V4_Mask']); ?>: <?= h($entry['mask']); ?></span><?php endforeach; ?>
      </div><?php endif; ?>
      <div class="table-responsive"><table class="table table-sm table-striped mb-0"><thead><tr><th><?= h($pia_lang['V4_Interface']); ?></th><th>IPv4</th><th>RX</th><th>TX</th></tr></thead><tbody><?php foreach ($networkInterfaces as $name => $interface): ?><tr><th scope="row"><?= h($name); ?></th><td><?php foreach ($interface['addresses'] as $address): ?><div><?= h($address); ?></div><?php endforeach; ?></td><td><?= h(number_format($interface['rx'] / 1048576, 2, ',', '.') . ' MB'); ?></td><td><?= h(number_format($interface['tx'] / 1048576, 2, ',', '.') . ' MB'); ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
  </section>
  <section class="card" aria-labelledby="services-heading"><div class="card-header"><h2 class="card-title" id="services-heading"><i class="bi bi-database-gear me-2" aria-hidden="true"></i><?= h($pia_lang['V4_Running_Services']); ?></h2></div><div class="card-body table-responsive pialert-sysinfo-scroll"><table class="table table-sm table-striped table-hover mb-0"><thead><tr><th><?= h($pia_lang['V4_Service_Name']); ?></th><th><?= h($pia_lang['V4_Service_Description']); ?></th></tr></thead><tbody><?php foreach ($runningServices as $service): ?><tr><td><?= h($service['name']); ?></td><td><?= h($service['description']); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
  <section class="card" aria-labelledby="usb-heading"><div class="card-header"><h2 class="card-title" id="usb-heading"><i class="bi bi-usb-symbol me-2" aria-hidden="true"></i><?= h($pia_lang['V4_USB_Devices']); ?></h2></div><div class="card-body table-responsive"><table class="table table-sm table-striped mb-0"><tbody><?php foreach ($usbDevices as $device): ?><tr><th scope="row"><?= h($device['bus']); ?></th><td><?= h($device['device']); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
</section>

<div id="systeminfo-actions" hidden
  data-reboot-title="<?= h($pia_lang['SysInfo_Reboot_noti_head'] ?? 'Reboot Pi.Alert after the next scan'); ?>"
  data-reboot-message="<?= h($pia_lang['SysInfo_Reboot_noti_text'] ?? 'The host will be rebooted after the next scan.'); ?>"
  data-shutdown-title="<?= h($pia_lang['SysInfo_Shutdown_noti_head'] ?? 'Shut down Pi.Alert after the next scan'); ?>"
  data-shutdown-message="<?= h($pia_lang['SysInfo_Shutdown_noti_text'] ?? 'The host will be shut down after the next scan.'); ?>"
  data-cancel="<?= h($pia_lang['Gen_Cancel'] ?? 'Cancel'); ?>"
  data-run="<?= h($pia_lang['Gen_Run'] ?? 'Run'); ?>"></div>
<?php pialert_v4_shell_end(array('js/systeminfo.js')); ?>

<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

session_start();

if ($_SESSION["login"] != 1) {
	header('Location: ./index.php');
	exit;
}

require __DIR__ . '/php/templates/header.php';

$cronFile = __DIR__ . '/php/server/usercron.log';
if (is_readable($cronFile)) {
    $prevVal = file_get_contents($cronFile);
} else {
    $prevVal = '';
}
$cleancron = array_filter(
    array_map('trim', explode("\n", $prevVal))
);
$stat['usercron'] = implode("\n", $cleancron);
// https://stackoverflow.com/a/19209082
$os_version = '';
// Raspbian
if ($os_version == '') {$os_version = exec('cat /etc/os-release | grep PRETTY_NAME');}
// Dietpi
if ($os_version == '') {$os_version = exec('uname -o');}
//$os_version_arr = explode("\n", trim($os_version));
$stat['os_version'] = str_replace('"', '', str_replace('PRETTY_NAME=', '', $os_version));
$stat['uptime'] = str_replace('up ', '', shell_exec("uptime -p"));
//cpu stat
$prevVal = shell_exec("cat /proc/cpuinfo | grep processor");
$prevArr = explode("\n", trim($prevVal));
$stat['cpu'] = sizeof($prevArr);
$cpu_result = shell_exec("cat /proc/cpuinfo | grep Model");
$stat['cpu_model'] = strstr($cpu_result, "\n", true);
$stat['cpu_model'] = str_replace(":", "", trim(str_replace("Model", "", $stat['cpu_model'])));
if ($stat['cpu_model'] == '') {
	$cpu_result = shell_exec("cat /proc/cpuinfo | grep model\ name");
	$stat['cpu_model'] = strstr($cpu_result, "\n", true);
	$stat['cpu_model'] = str_replace(":", "", trim(str_replace("model name", "", $stat['cpu_model'])));
}
if (file_exists('/sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq')) {
	// RaspbianOS
	$stat['cpu_frequ'] = exec('cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq') / 1000;
} elseif (is_numeric(str_replace(',', '.', exec('lscpu | grep "MHz" | awk \'{print $3}\'')))) {
	// Ubuntu Server, DietPi event. others
	$stat['cpu_frequ'] = round(exec('lscpu | grep "MHz" | awk \'{print $3}\''), 0);
} elseif (is_numeric(str_replace(',', '.', exec('lscpu | grep "max MHz" | awk \'{print $4}\'')))) {
	// RaspbianOS and event. others
	$stat['cpu_frequ'] = round(str_replace(',', '.', exec('lscpu | grep "max MHz" | awk \'{print $4}\'')), 0);
} else {
	// Fallback
	$stat['cpu_frequ'] = "unknown";
}
$kernel_arch = exec('dpkg --print-architecture');
//memory stat
$mem_result = shell_exec("cat /proc/meminfo | grep MemTotal");
$stat['mem_total'] = round(preg_replace("#[^0-9]+(?:\.[0-9]*)?#", "", $mem_result) / 1024 / 1024, 3);
$stat['mem_used'] = round(getMemUsage() * 100, 2);
//network stat
$network_result = shell_exec("cat /proc/net/dev | tail -n +3 | awk '{print $1}'");
$net_interfaces = explode("\n", trim($network_result));
$network_result = shell_exec("cat /proc/net/dev | tail -n +3 | awk '{print $2}'");
$net_interfaces_rx = explode("\n", trim($network_result));
$network_result = shell_exec("cat /proc/net/dev | tail -n +3 | awk '{print $10}'");
$net_interfaces_tx = explode("\n", trim($network_result));

// Retrieve IPv4 addresses without invoking the ip command. This is portable
// across LXC, VMs, and native hosts and avoids the lighttpd AF_NETLINK limit.
$interface_ipv4_addresses = array();
$interface_ipv4_masks = array();
if (function_exists("net_get_interfaces")) {
    $system_interfaces = @net_get_interfaces();
    if (is_array($system_interfaces)) {
        foreach ($system_interfaces as $interfaceName => $interfaceData) {
            foreach ($interfaceData["unicast"] ?? array() as $addressData) {
                if (($addressData["family"] ?? null) !== 2 || !isset($addressData["address"]) || filter_var($addressData["address"], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                    continue;
                }
                $interface_ipv4_addresses[$interfaceName][] = $addressData["address"];
                if (isset($addressData["netmask"]) && filter_var($addressData["netmask"], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                    $interface_ipv4_masks[$interfaceName][$addressData["netmask"]] = true;
                }
            }
        }
    }
}


// IPv4 subnet masks for the interfaces shown in the Network section.
// procfs is readable inside the lighttpd sandbox and does not require the
// AF_NETLINK socket used by the ip command and net_get_interfaces().
$interface_networks = array();
$known_interfaces = array();
foreach ($net_interfaces as $interface) {
    $interfaceName = trim(str_replace(":", "", $interface));
    if ($interfaceName !== "" && preg_match("/^[a-zA-Z0-9_.-]+$/", $interfaceName)) {
        $known_interfaces[$interfaceName] = true;
    }
}
$route_lines = @file("/proc/net/route", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach (array_slice(is_array($route_lines) ? $route_lines : array(), 1) as $routeLine) {
    $fields = preg_split("/\s+/", trim($routeLine));
    if (count($fields) < 8 || !isset($known_interfaces[$fields[0]]) || !preg_match("/^[0-9a-fA-F]{8}$/", $fields[1]) || !preg_match("/^[0-9a-fA-F]{8}$/", $fields[7]) || $fields[7] === "00000000") {
        continue;
    }

    // Linux stores IPv4 route addresses and masks in little-endian hexadecimal form.
    $networkValue = (int) hexdec(implode("", array_reverse(str_split($fields[1], 2))));
    $maskValue = (int) hexdec(implode("", array_reverse(str_split($fields[7], 2))));
    $prefixLength = substr_count(decbin($maskValue), "1");
    $interface_networks[] = array(
        "interface" => $fields[0],
        "network" => $networkValue,
        "mask" => $maskValue,
        "prefix" => $prefixLength,
    );
}
// When net_get_interfaces() is unavailable in the sandbox, identify local IPv4
// addresses from fib_trie and map them to the most specific interface route.
$fib_lines = @file("/proc/net/fib_trie", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$local_ipv4_addresses = array();
$candidateAddress = null;
foreach (is_array($fib_lines) ? $fib_lines : array() as $fibLine) {
    $trimmedLine = trim($fibLine);
    if (preg_match("/\|--\s+([0-9]+(?:\.[0-9]+){3})$/", $trimmedLine, $matches)) {
        $candidateAddress = $matches[1];
        continue;
    }
    if ($candidateAddress !== null && preg_match("/^\/32\s+host\s+LOCAL$/", $trimmedLine)) {
        $local_ipv4_addresses[$candidateAddress] = true;
        $candidateAddress = null;
    }
}
$fallback_interface_masks = array();
foreach (array_keys($local_ipv4_addresses) as $address) {
    $addressValue = (int) sprintf("%u", ip2long($address));
    $bestRoute = null;
    foreach ($interface_networks as $interfaceNetwork) {
        if (($addressValue & $interfaceNetwork["mask"]) !== ($interfaceNetwork["network"] & $interfaceNetwork["mask"])) {
            continue;
        }
        if ($bestRoute === null || $interfaceNetwork["prefix"] > $bestRoute["prefix"]) {
            $bestRoute = $interfaceNetwork;
        }
    }
    $interfaceName = $bestRoute["interface"] ?? (str_starts_with($address, "127.") && isset($known_interfaces["lo"]) ? "lo" : null);
    if ($interfaceName !== null && !in_array($address, $interface_ipv4_addresses[$interfaceName] ?? array(), true)) {
        $interface_ipv4_addresses[$interfaceName][] = $address;
    }
    if ($interfaceName !== null && $bestRoute !== null) {
        $fallback_interface_masks[$interfaceName][long2ip($bestRoute["mask"])] = true;
    } elseif ($interfaceName === "lo") {
        $fallback_interface_masks[$interfaceName]["255.0.0.0"] = true;
    }
}

// Prefer masks configured on the interface itself. Use only the route matching
// a local address as fallback when the lighttpd sandbox blocks net_get_interfaces().
$network_subnet_masks = array();
foreach (array_keys($known_interfaces) as $interfaceName) {
    if ($interfaceName === "lo") {
        continue;
    }
    $masks = !empty($interface_ipv4_masks[$interfaceName]) ? $interface_ipv4_masks[$interfaceName] : ($fallback_interface_masks[$interfaceName] ?? array());
    foreach (array_keys($masks) as $subnetMask) {
        if ($subnetMask === "255.255.255.255") {
            continue;
        }
        $network_subnet_masks[] = array(
            "interface" => $interfaceName,
            "mask" => $subnetMask,
        );
    }
}

// Storage usage data
$hdd_result = shell_exec("/usr/bin/df -P 2>/dev/null"); // -P: POSIX format, easier to parse
$lines = is_string($hdd_result) ? preg_split("/\r?\n/", trim($hdd_result), -1, PREG_SPLIT_NO_EMPTY) : array();
if (!empty($lines)) {
    array_shift($lines); // POSIX df header
}

// Initialize arrays
$hdd_devices = array();
$hdd_devices_total = array();
$hdd_devices_used = array();
$hdd_devices_free = array();
$hdd_devices_percent = array();
$hdd_devices_mount = array();

foreach ($lines as $line) {
    $fields = preg_split("/\s+/", trim($line));
    if (count($fields) < 6 || !is_numeric($fields[1]) || !is_numeric($fields[2]) || !is_numeric($fields[3]) || !preg_match("/^\d+%$/", $fields[4])) {
        continue;
    }

    $hdd_devices[] = $fields[0];
    $hdd_devices_total[] = $fields[1];
    $hdd_devices_used[] = $fields[2];
    $hdd_devices_free[] = $fields[3];
    $hdd_devices_percent[] = $fields[4];
    $hdd_devices_mount[] = implode(" ", array_slice($fields, 5));
}

// USB devices: sysfs remains readable inside the lighttpd sandbox,
// unlike the USB device nodes required by lsusb.
$usb_devices = array();
foreach (glob("/sys/bus/usb/devices/*") ?: array() as $usbPath) {
    $vendorFile = $usbPath . "/idVendor";
    $productFile = $usbPath . "/idProduct";
    if (!is_readable($vendorFile) || !is_readable($productFile)) {
        continue;
    }

    $vendorId = trim((string) file_get_contents($vendorFile));
    $productId = trim((string) file_get_contents($productFile));
    if (!preg_match("/^[0-9a-fA-F]{4}$/", $vendorId) || !preg_match("/^[0-9a-fA-F]{4}$/", $productId)) {
        continue;
    }

    $manufacturerFile = $usbPath . "/manufacturer";
    $productNameFile = $usbPath . "/product";
    $manufacturer = is_readable($manufacturerFile) ? trim((string) file_get_contents($manufacturerFile)) : "";
    $productName = is_readable($productNameFile) ? trim((string) file_get_contents($productNameFile)) : "";
    $deviceId = strtolower($vendorId) . ":" . strtolower($productId);
    $deviceName = trim($manufacturer . " " . $productName);
    $deviceDescription = $deviceName === "" ? $deviceId : $deviceName . " (" . $deviceId . ")";

    $busLabel = "USB " . basename($usbPath);
    $busNumberFile = $usbPath . "/busnum";
    $deviceNumberFile = $usbPath . "/devnum";
    $busNumber = is_readable($busNumberFile) ? trim((string) file_get_contents($busNumberFile)) : "";
    $deviceNumber = is_readable($deviceNumberFile) ? trim((string) file_get_contents($deviceNumberFile)) : "";
    if (ctype_digit($busNumber) && ctype_digit($deviceNumber)) {
        $busLabel = sprintf("Bus %03d Dev. %03d", (int) $busNumber, (int) $deviceNumber);
    }

    $usb_devices[] = array("bus" => $busLabel, "device" => $deviceDescription);
}
// count processes
$stat['process_count'] = shell_exec("ps -e --no-headers | wc -l");
?>

<!-- Page ------------------------------------------------------------------ -->
<div class="content-wrapper">

<!-- Content header--------------------------------------------------------- -->
    <section class="content-header">
    <?php require 'php/templates/notification.php';?>
      <h1 id="pageTitle">
         System Infomation
      </h1>
    </section>

    <!-- Main content ---------------------------------------------------------- -->
    <section class="content">
<?php
// Reboot Shutdown ----------------------------------------------------------
echo '
		<div class="row">
			<div class="col-sm-6" style="text-align: center; margin-bottom:20px;">
			  <div style="display: flex; justify-content: center;">
			    <a href="#" class="btn btn-danger" style="width:260px; display:flex; align-items:center; justify-content:center; gap:10px;">
			      <i class="fa-solid fa-power-off shutreboot-button-icon" id="Menu_Report_Envelope_Icon"></i>
			      <div class="shutreboot-button-text" onclick="askPialertShutdown()">
			        '.$pia_lang['SysInfo_Shutdown'].'
			      </div>
			    </a>
			  </div>
			</div>
			<div class="col-sm-6" style="text-align: center; margin-bottom:20px;">
			  <div style="display: flex; justify-content: center;">
			    <a href="#" class="btn btn-warning" style="width:260px; display:flex; align-items:center; justify-content:center; gap:10px;">
			      <i class="fa-solid fa-power-off shutreboot-button-icon" id="Menu_Report_Envelope_Icon"></i>
			      <div class="shutreboot-button-text" onclick="askPialertReboot()">
			        '.$pia_lang['SysInfo_Reboot'].'
			      </div>
			    </a>
			  </div>
			</div>
		</div>';

// Client ----------------------------------------------------------
echo '<div class="box bo
	x-solid">
        <div class="box-header"><h3 class="box-title sysinfo_headline"><i class="bi bi-globe"></i> This Client</h3></div>
        <div class="box-body">
					<div class="row">
					  <div class="col-sm-3 sysinfo_gerneral_a">User Agent</div>
					  <div class="col-sm-9 sysinfo_gerneral_b">' . $_SERVER['HTTP_USER_AGENT'] . '</div>
					</div>
					<div class="row">
					  <div class="col-sm-3 sysinfo_gerneral_a">Browser Resolution:</div>
					  <div class="col-sm-9 sysinfo_gerneral_b" id="resolution"></div>
					</div>
        </div>
      </div>';

echo '<script>
	var ratio = window.devicePixelRatio || 1;
	var w = window.innerWidth;
	var h = window.innerHeight;
	var rw = window.innerWidth * ratio;
	var rh = window.innerHeight * ratio;

	var resolutionDiv = document.getElementById("resolution");
	resolutionDiv.innerHTML = "Width: " + w + "px / Height: " + h + "px<br> " + "Width: " + rw + "px / Height: " + rh + "px (native)";
</script>';

// General ----------------------------------------------------------
if (($_SESSION['Scan_Satellite'] == True)) {
		//$_SESSION['local'] = "local";

		$uptime_search  = array('w ', 'd ', 'h ', 'm ');
        $uptime_replace = array(' weeks, ', ' days, ', ' hours, ', ' minutes ');

		global $satellite_badges_list;
    	$database = '../db/pialert.db';
	    $db = new SQLite3($database);
	    $sql_select = 'SELECT * FROM Satellites ORDER BY sat_name ASC';
	    $result = $db->query($sql_select);
	    if ($result) {
	        if ($result->numColumns() > 0) {
	        	$tab_id = 0;
	            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
	            	$tab_id++;
	                $tabs .=  '<li class=""><a href="#tab_'.$tab_id.'" data-toggle="tab" aria-expanded="false">'.$row['sat_name'].'</a></li>';

	                $hostdata = json_decode($row['sat_host_data'], true);

	                $satLastUpdate = $row['sat_lastupdate'] ?? null;
	                $spanClass = 'text-green';
					if ($satLastUpdate) {
					    $now = new DateTime();
					    $lastUpdate = DateTime::createFromFormat('Y-m-d H:i:s', $satLastUpdate);
					    
					    if ($lastUpdate) {
					        $diff = $now->getTimestamp() - $lastUpdate->getTimestamp();
					        $diffMinutes = abs($diff) / 60;
					        
					        if ($diffMinutes > 10) {
					            $spanClass = 'text-red';
					        }
					    }
					}

					if (is_bool($hostdata['satellite_proxymode'])) {
						if ($hostdata['satellite_proxymode'] == True) {$proxymode = "True";} else {$proxymode = "False";}
					} else {$proxymode = "Unknown";}

					if (!isset($hostdata['satellite_url'])) {$hostdata['satellite_url'] = "Unknown";}

	                $scan_time = explode(" ", $row['sat_lastupdate']);
	                $tab_content .= '<div class="tab-pane" id="tab_'.$tab_id.'">
											<div class="row">
											  <div class="col-sm-3 sysinfo_gerneral_a">Uptime</div>
											  <div class="col-sm-9 sysinfo_gerneral_b"><span class="'.htmlspecialchars($spanClass).'">' . str_replace($uptime_search, $uptime_replace, $hostdata['uptime']) . ' ('. $scan_time[0] . ' / '.substr($scan_time[1], 0, -3).')</span></div>
											</div>
											<div class="row">
											  <div class="col-sm-3 sysinfo_gerneral_a">Operating System</div>
											  <div class="col-sm-9 sysinfo_gerneral_b">' . $hostdata['os_version'] . '</div>
											</div>
											<div class="row">
											  <div class="col-sm-3 sysinfo_gerneral_a">Kernel Architecture:</div>
											  <div class="col-sm-9 sysinfo_gerneral_b">' . $hostdata['cpu_arch'] . '</div>
											</div>
											<div class="row">
											  <div class="col-sm-3 sysinfo_gerneral_a">CPU Name:</div>
											  <div class="col-sm-9 sysinfo_gerneral_b">' . $hostdata['cpu_name'] . '</div>
											</div>
											<div class="row">
											  <div class="col-sm-3 sysinfo_gerneral_a">CPU Cores:</div>
											  <div class="col-sm-9 sysinfo_gerneral_b">' . $hostdata['cpu_cores'] . ' @ ' . $hostdata['cpu_freq'] . '</div>
											</div>
											<div class="row">
											  <div class="col-sm-3 sysinfo_gerneral_a">Memory:</div>
											  <div class="col-sm-9 sysinfo_gerneral_b">' . round($hostdata['ram_total']/1048576, 2) . ' MB / ' . $hostdata['ram_used_percent'] . '% is used</div>
											</div>
											<div class="row">
											  <div class="col-sm-3 sysinfo_gerneral_a">Running Processes:</div>
											  <div class="col-sm-9 sysinfo_gerneral_b">' . $hostdata['proc_count'] . '</div>
											</div>
											<div class="row">
											  <div class="col-sm-3 sysinfo_gerneral_a">Timezone (System):</div>
											  <div class="col-sm-9 sysinfo_gerneral_b">"' . $hostdata['os_timezone'] . '"</div>
											</div>
											<div class="row">
											  <div class="col-sm-3 sysinfo_gerneral_a">Satellite Host:</div>
											  <div class="col-sm-9 sysinfo_gerneral_b">Name: ' . $hostdata['hostname'] . ' / IP: ' . $hostdata['satellite_ip'] . ' / MAC: <a href="./deviceDetails.php?mac=' . $hostdata['satellite_mac'] . '">' . $hostdata['satellite_mac'] . '</a></div>
											</div>
											<div class="row">
											  <div class="col-sm-3 sysinfo_gerneral_a">Proxy Mode:</div>
											  <div class="col-sm-9 sysinfo_gerneral_b">' . $proxymode . '</div>
											</div>
											<div class="row">
											  <div class="col-sm-3 sysinfo_gerneral_a">API Url:</div>
											  <div class="col-sm-9 sysinfo_gerneral_b">' . $hostdata['satellite_url'] . '</div>
											</div>
							            </div>';
	            }
	        }
	    }
	    $db->close();
}
echo '<div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
              <li class="pull-left header text-aqua" id="sys_info_gen_head"><i class="bi bi-info-circle"></i> General</li>
              <li class="active"><a href="#tab_0" data-toggle="tab" aria-expanded="true">Pi.Alert</a></li>
              '.$tabs.'
            </ul>
            <div class="tab-content">
              <div class="tab-pane active" id="tab_0">
				<div class="row">
				  <div class="col-sm-3 sysinfo_gerneral_a">Uptime</div>
				  <div class="col-sm-9 sysinfo_gerneral_b text-green">' . $stat['uptime'] . '</div>
				</div>
				<div class="row">
				  <div class="col-sm-3 sysinfo_gerneral_a">Operating System</div>
				  <div class="col-sm-9 sysinfo_gerneral_b">' . $stat['os_version'] . '</div>
				</div>
				<div class="row">
				  <div class="col-sm-3 sysinfo_gerneral_a">Kernel Architecture:</div>
				  <div class="col-sm-9 sysinfo_gerneral_b">' . $kernel_arch . '</div>
				</div>
				<div class="row">
				  <div class="col-sm-3 sysinfo_gerneral_a">CPU Name:</div>
				  <div class="col-sm-9 sysinfo_gerneral_b">' . $stat['cpu_model'] . '</div>
				</div>
				<div class="row">
				  <div class="col-sm-3 sysinfo_gerneral_a">CPU Cores:</div>
				  <div class="col-sm-9 sysinfo_gerneral_b">' . $stat['cpu'] . ' @ ' . $stat['cpu_frequ'] . ' MHz</div>
				</div>
				<div class="row">
				  <div class="col-sm-3 sysinfo_gerneral_a">Memory:</div>
				  <div class="col-sm-9 sysinfo_gerneral_b">' . $stat['mem_total'] . ' MB / ' . $stat['mem_used'] . '% is used</div>
				</div>
				<div class="row">
				  <div class="col-sm-3 sysinfo_gerneral_a">Running Processes:</div>
				  <div class="col-sm-9 sysinfo_gerneral_b">' . $stat['process_count'] . '</div>
				</div>
				<div class="row">
				  <div class="col-sm-3 sysinfo_gerneral_a">Timezone (PHP / System):</div>
				  <div class="col-sm-9 sysinfo_gerneral_b">"'. date_default_timezone_get() .'" / "'. get_local_system_tz() .'"</div>
				</div>
				<div class="row">
				  <div class="col-sm-3 sysinfo_gerneral_a">PHP Version:</div>
				  <div class="col-sm-9 sysinfo_gerneral_b">'. phpversion() .'</div>
				</div>
				<div class="row">
				  <div class="col-sm-3 sysinfo_gerneral_a">&nbsp;</div>
				  <div class="col-sm-9 sysinfo_gerneral_b"></div>
				</div>
				<div class="row">
				  <div class="col-sm-3 sysinfo_gerneral_a">&nbsp;</div>
				  <div class="col-sm-9 sysinfo_gerneral_b"></div>
				</div>
              </div>
              '.$tab_content.'
            </div>
          </div>';

// DB Info ----------------------------------------------------------
echo '<div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
              <li class="pull-left header text-aqua" id="sys_info_gen_head"><i class="bi bi-database"></i> Databases</li>
              <li class="active"><a href="#tab_db_a" data-toggle="tab" aria-expanded="true">Main</a></li>
              <li class=""><a href="#tab_db_b" data-toggle="tab" aria-expanded="true">Tools</a></li>
            </ul>
            <div class="tab-content">
              <div class="tab-pane active" id="tab_db_a">
				<div style="height: 250px; overflow-y: scroll; overflow-x: hidden;">';

				$DB_SOURCE = str_replace('front', 'db', getcwd()) . '/pialert.db';
				echo '<p>The directory of the Pi.Alert database is <b>' . $DB_SOURCE . '</b></p>';
				$db = new SQLite3('../db/pialert.db');
				$tablesQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name ASC");
				echo '<table class="table table-bordered table-hover table-striped dataTable no-footer" style="margin-bottom: 10px;">';
				echo '<thead>
						<tr role="row">
							<th class="sysinfo_services col-sm-4 col-xs-8" style="padding: 8px;">Table Name</th>
							<th class="sysinfo_services" style="padding: 8px;">Table Entries</th>
						</tr>
					  </thead>';
				while ($table = $tablesQuery->fetchArray()) {
				    $tableName = $table['name'];
				    $ignore_tables = ['Tools_Speedtest_History', 'Tools_Nmap_ManScan', 'sqlite_sequence', 'sqlite_stat1'];

				    if (in_array($tableName, $ignore_tables)) {
				    	continue;
				    }

				    $rowCountQuery = $db->query("SELECT COUNT(*) as count FROM $tableName");
				    $rowCount = $rowCountQuery->fetchArray()['count'];

				    echo '<tr>
				    	<td style="padding: 3px; padding-left: 10px;">' . $tableName . '</td>
				    	<td style="padding: 3px; padding-left: 10px;">' . $rowCount . '</td>
				    	</tr>';
				}
				echo '</table>';

				$db->close();
				echo '</div>
              </div>
              <div class="tab-pane" id="tab_db_b">
				<div style="height: 250px; overflow-y: scroll; overflow-x: hidden;">';

				$DB_SOURCE = str_replace('front', 'db', getcwd()) . '/pialert_tools.db';
				echo '<p>The directory of the Pi.Alert database is <b>' . $DB_SOURCE . '</b></p>';
				$db = new SQLite3('../db/pialert_tools.db');
				$tablesQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name ASC");
				echo '<table class="table table-bordered table-hover table-striped dataTable no-footer" style="margin-bottom: 10px;">';
				echo '<thead>
						<tr role="row">
							<th class="sysinfo_services col-sm-4 col-xs-8" style="padding: 8px;">Table Name</th>
							<th class="sysinfo_services" style="padding: 8px;">Table Entries</th>
						</tr>
					  </thead>';
				while ($table = $tablesQuery->fetchArray()) {
				    $tableName = $table['name'];
				    
				    if ($tableName == "sqlite_sequence" || $tableName == "sqlite_stat1") {
				    	continue;
				    }

				    $rowCountQuery = $db->query("SELECT COUNT(*) as count FROM $tableName");
				    $rowCount = $rowCountQuery->fetchArray()['count'];

				    echo '<tr>
				    	<td style="padding: 3px; padding-left: 10px;">' . $tableName . '</td>
				    	<td style="padding: 3px; padding-left: 10px;">' . $rowCount . '</td>
				    	</tr>';
				}
				echo '</table>';

				$db->close();
				echo '</div>
              </div>
            </div>
          </div>';

// User Crontab -----------------------------------------------------
echo '<div class="box box-solid">
            <div class="box-header">
              <h3 class="box-title sysinfo_headline"><i class="bi bi-list-task"></i> User Crontab</h3>
            </div>
            <div class="box-body">
            <pre style="background-color: transparent; border: none;">'.$stat['usercron'].'</pre>
            </div>
      </div>';

// Pi.Alert Crontab -----------------------------------------------------
echo '<div class="box box-solid">
            <div class="box-header">
              <h3 class="box-title sysinfo_headline"><i class="bi bi-list-task"></i> Pi.Alert Crons</h3>
            </div>
            <div class="box-body">
            <table class="table table-bordered table-hover table-striped dataTable no-footer" style="margin-bottom: 10px;">
			<thead>
				<tr role="row">
					<th class="sysinfo_services col-xs-4" style="padding: 8px;">Cron Name</th>
					<th class="sysinfo_services col-xs-4" style="padding: 8px;">Cron</th>
					<th class="sysinfo_services col-xs-4" style="padding: 8px;">Status</th>
				</tr>
	  		</thead>';
function convert_bool_to_status($status) {
	if ($status == True) {return "enabled";} else {return "disabled";}
}
echo '<tr>
		<td style="padding: 3px; padding-left: 10px;">Update Check</td>
		<td style="padding: 3px; padding-left: 10px;">'.$_SESSION['AUTO_UPDATE_CHECK_CRON'].'</td>
		<td style="padding: 3px; padding-left: 10px;">'.convert_bool_to_status($_SESSION['Auto_Update_Check']).'</td>
	  </tr>';
echo '<tr>
		<td style="padding: 3px; padding-left: 10px;">Backup</td>
		<td style="padding: 3px; padding-left: 10px;">'.$_SESSION['AUTO_DB_BACKUP_CRON'].'</td>
		<td style="padding: 3px; padding-left: 10px;">'.convert_bool_to_status($_SESSION['AUTO_DB_BACKUP']).'</td>
	  </tr>';
echo '<tr>
		<td style="padding: 3px; padding-left: 10px;">Speedtest</td>
		<td style="padding: 3px; padding-left: 10px;">'.$_SESSION['SPEEDTEST_TASK_CRON'].'</td>
		<td style="padding: 3px; padding-left: 10px;">'.convert_bool_to_status($_SESSION['SPEEDTEST_TASK_ACTIVE']).'</td>
	  </tr>';
echo '<tr>
		<td style="padding: 3px; padding-left: 10px;">Continuous notifications</td>
		<td style="padding: 3px; padding-left: 10px;">'.$_SESSION['REPORT_NEW_CONTINUOUS_CRON'].'</td>
		<td style="padding: 3px; padding-left: 10px;">'.convert_bool_to_status($_SESSION['REPORT_NEW_CONTINUOUS']).'</td>
	  </tr>';
echo '      </table>
            </div>
      </div>';

// Storage ----------------------------------------------------------
echo '<div class="box box-solid">
            <div class="box-header">
              <h3 class="box-title sysinfo_headline"><i class="bi bi-hdd"></i> Storage</h3>
            </div>
            <div class="box-body">';

$storage_lsblk = shell_exec("lsblk -io NAME,SIZE,TYPE,MOUNTPOINT,MODEL --list | tail -n +2 | awk '{print $1\"#\"$2\"#\"$3\"#\"$4\"#\"$5}'");
$storage_lsblk_line = explode("\n", $storage_lsblk);
$storage_lsblk_line = array_filter($storage_lsblk_line);

for ($x = 0; $x < sizeof($storage_lsblk_line); $x++) {
	$temp = array();
	$temp = explode("#", $storage_lsblk_line[$x]);
	$storage_lsblk_line[$x] = $temp;
}

for ($x = 0; $x < sizeof($storage_lsblk_line); $x++) {
	if (strtolower($storage_lsblk_line[$x][2]) != "loop") {
		echo '<div class="row">';
		if (preg_match('~[0-9]+~', $storage_lsblk_line[$x][0])) {
			echo '<div class="col-sm-4 sysinfo_gerneral_a">Mount point "' . $storage_lsblk_line[$x][3] . '"</div>';
		} else {
			echo '<div class="col-sm-4 sysinfo_gerneral_a">"' . str_replace('_', ' ', $storage_lsblk_line[$x][3]) . '"</div>';
		}
		echo '<div class="col-sm-3 sysinfo_gerneral_b">Device: /dev/' . $storage_lsblk_line[$x][0] . '</div>';
		echo '<div class="col-sm-2 sysinfo_gerneral_b">Size: ' . $storage_lsblk_line[$x][1] . '</div>';
		echo '<div class="col-sm-2 sysinfo_gerneral_b">Type: ' . $storage_lsblk_line[$x][2] . '</div>';
		echo '</div>';
	}
}
echo '      </div>
      </div>';

// Storage usage ----------------------------------------------------------
echo '<div class="box box-solid">
            <div class="box-header">
              <h3 class="box-title sysinfo_headline"><i class="bi bi-hdd"></i> Storage usage</h3>
            </div>
            <div class="box-body">';
for ($x = 0; $x < sizeof($hdd_devices); $x++) {
	if (stristr($hdd_devices[$x], '/dev/')) {
		if (!stristr($hdd_devices[$x], '/loop')) {
			if ($hdd_devices_total[$x] == 0 || $hdd_devices_total[$x] == '') {$temp_total = 0;} else { $temp_total = number_format(round(($hdd_devices_total[$x] / 1024 / 1024), 2), 2, ',', '.');}
			if ($hdd_devices_used[$x] == 0 || $hdd_devices_used[$x] == '') {$temp_used = 0;} else { $temp_used = number_format(round(($hdd_devices_used[$x] / 1024 / 1024), 2), 2, ',', '.');}
			if ($hdd_devices_free[$x] == 0 || $hdd_devices_free[$x] == '') {$temp_free = 0;} else { $temp_free = number_format(round(($hdd_devices_free[$x] / 1024 / 1024), 2), 2, ',', '.');}
			echo '<div class="row">';
			echo '<div class="col-sm-4 sysinfo_gerneral_a">Mount point "' . $hdd_devices_mount[$x] . '"</div>';
			echo '<div class="col-sm-2 sysinfo_gerneral_b">Total: ' . $temp_total . ' GB</div>';
			echo '<div class="col-sm-3 sysinfo_gerneral_b">Used: ' . $temp_used . ' GB (' . $hdd_devices_percent[$x] . ')</div>';
			echo '<div class="col-sm-2 sysinfo_gerneral_b">Free: ' . $temp_free . ' GB</div>';
			echo '</div>';
		}
	}
}
//echo '<br>' . $pia_lang['SysInfo_storage_note'];
echo '      </div>
      </div>';

// Network ----------------------------------------------------------
echo '<div class="box box-solid">
            <div class="box-header">
              <h3 class="box-title sysinfo_headline"><i class="bi bi-hdd-network"></i> Network</h3>
            </div>
            <div class="box-body">';

if (!empty($network_subnet_masks)) {
    echo '<div class="row"><div class="col-sm-12 sysinfo_network_a"><b>Subnet masks</b></div></div>';
    foreach ($network_subnet_masks as $subnet) {
        $subnetInterface = htmlspecialchars($subnet["interface"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
        $subnetMask = htmlspecialchars($subnet["mask"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
        echo '<div class="row"><div class="col-sm-2 sysinfo_network_a text-aqua">' . $subnetInterface . '</div><div class="col-sm-4 sysinfo_network_b">' . $subnetMask . '</div></div>';
    }
    echo '<hr style="margin: 8px 0; border-color: #bbb;">';
}

for ($x = 0; $x < sizeof($net_interfaces); $x++) {
    $interface_name = str_replace(":", "", $net_interfaces[$x]);
    $interface_addresses = $interface_ipv4_addresses[$interface_name] ?? array();
    $interface_ip = empty($interface_addresses) ? "--" : implode("<br>", array_map(function($address) {
        return htmlspecialchars($address, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    }, $interface_addresses));

    if ($net_interfaces_rx[$x] == 0) {$temp_rx = 0;} else { $temp_rx = number_format(round(($net_interfaces_rx[$x] / 1024 / 1024), 2), 2, ',', '.');}
	if ($net_interfaces_tx[$x] == 0) {$temp_tx = 0;} else { $temp_tx = number_format(round(($net_interfaces_tx[$x] / 1024 / 1024), 2), 2, ',', '.');}
	echo '<div class="row">';
	echo '<div class="col-sm-2 sysinfo_network_a">' . $interface_name . '</div>';
	echo '<div class="col-sm-2 sysinfo_network_b">' . $interface_ip . '</div>';
	echo '<div class="col-sm-3 sysinfo_network_b">RX: <div class="sysinfo_network_value">' . $temp_rx . ' MB</div></div>';
	echo '<div class="col-sm-3 sysinfo_network_b">TX: <div class="sysinfo_network_value">' . $temp_tx . ' MB</div></div>';
	echo '</div>';

}
echo '      </div>
      </div>';

// Services ----------------------------------------------------------
echo '<div class="box box-solid">
            <div class="box-header">
              <h3 class="box-title sysinfo_headline"><i class="bi bi-database-gear"></i> Services (running)</h3>
            </div>
            <div class="box-body">';
echo '<div style="height: 300px; overflow: scroll;">';
exec('systemctl --type=service --state=running', $running_services);
echo '<table class="table table-bordered table-hover table-striped dataTable no-footer" style="margin-bottom: 10px;">';
echo '<thead>
		<tr role="row">
			<th class="sysinfo_services" style="padding: 8px;">Service Name</th>
			<th class="sysinfo_services" style="padding: 8px;">Service Description</th>
		</tr>
	  </thead>';
for ($x = 0; $x < sizeof($running_services); $x++) {
	if (stristr($running_services[$x], '.service')) {
		$temp_services_arr = array_values(array_filter(explode(' ', trim($running_services[$x]))));
		$servives_name = $temp_services_arr[0];
		unset($temp_services_arr[0], $temp_services_arr[1], $temp_services_arr[2], $temp_services_arr[3]);
		$servives_description = implode(" ", $temp_services_arr);
		echo '<tr><td style="padding: 3px; padding-left: 10px;">' . substr($servives_name, 0, -8) . '</td><td style="padding: 3px; padding-left: 10px;">' . $servives_description . '</td></tr>';
	}
}
echo '</table>';
echo '</div>';
echo '      </div>
      </div>';

// USB ----------------------------------------------------------
echo '<div class="box box-solid">
            <div class="box-header">
               <h3 class="box-title sysinfo_headline"><i class="bi bi-usb-symbol"></i> USB Devices</h3>
            </div>
            <div class="box-body">';
echo '         <table class="table table-bordered table-hover table-striped dataTable no-footer" style="margin-bottom: 10px;">';

usort($usb_devices, function($left, $right) {
    return strcmp($left["bus"], $right["bus"]);
});
foreach ($usb_devices as $usb_device) {
    $usb_bus = htmlspecialchars(str_replace("Device", "Dev.", $usb_device["bus"]), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    $usb_name = htmlspecialchars($usb_device["device"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    echo '<tr><td style="padding: 3px; padding-left: 10px; width: 150px;"><b>' . $usb_bus . '</b></td><td style="padding: 3px; padding-left: 10px;">' . $usb_name . '</td></tr>';
}
echo '         </table>';
echo '      </div>
      </div>';
echo '<br>';

?>
    </section>
</div>

<!-- ----------------------------------------------------------------------- -->
<?php
require 'php/templates/footer.php';
?>

<script type="text/javascript">
// Pialert Reboot
function askPialertReboot() {
  showModalWarning('<?=$pia_lang['SysInfo_Reboot_noti_head'];?>', '<?=$pia_lang['SysInfo_Reboot_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Run'];?>', 'PialertReboot');
}
function PialertReboot() {
	$.get('php/server/commands.php?action=PialertReboot', function(msg) {showMessage (msg);});
}
// Pialert Shutdown
function askPialertShutdown() {
  showModalWarning('<?=$pia_lang['SysInfo_Shutdown_noti_head'];?>', '<?=$pia_lang['SysInfo_Shutdown_noti_text'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Run'];?>', 'PialertShutdown');
}
function PialertShutdown() {
	$.get('php/server/commands.php?action=PialertShutdown', function(msg) {showMessage (msg);});
}

</script>
<!-- ---------------------------------------------------------------------------
#  Pi.Alert
#  Open Source Network Guard / WIFI & LAN intrusion detector
#
#  network.php - Front module. network relationship
#-------------------------------------------------------------------------------
#  leiweibau 2024                                          GNU GPLv3
#--------------------------------------------------------------------------- -->

<?php
session_start();
error_reporting(0);

if ($_SESSION["login"] != 1) {
	header('Location: ./index.php');
	exit;
}

require 'php/templates/header.php';
require 'php/server/db.php';
require 'php/server/journal.php';

$DBFILE = '../db/pialert.db';
OpenDB();

?>

<div class="content-wrapper">

    <section class="content-header">
    <?php require 'php/templates/notification.php';?>
      <h1 id="pageTitle">
         <?=$pia_lang['Network_Title'];?>
         <a class="btn btn-xs btn-success servicelist_add_serv" href="./networkSettings.php" role="button"><i class="bi bi-plus-lg" style="font-size:1.5rem"></i></a>
      </h1>
    </section>

    <section class="content">

<?php

function unassigned_devices() {
	global $db;
	$func_sql = 'SELECT * FROM "Devices" WHERE ("dev_Infrastructure" = "" OR "dev_Infrastructure" IS NULL) AND "dev_Archived" = 0';
	$func_result = $db->query($func_sql); //->fetchArray(SQLITE3_ASSOC);
	while ($func_res = $func_result->fetchArray(SQLITE3_ASSOC)) {
		echo '<a href="./deviceDetails.php?mac=' . $func_res['dev_MAC'] . '"><div style="display: inline-block; padding: 5px 15px; font-weight: bold;">' . $func_res['dev_Name'] . '</div></a>';
	}
}

function get_downstream_devices($pia_func_down_devid) {
	global $db;

	//$manual_downstream_ports = array();
	$func_sql = 'SELECT * FROM "network_infrastructure" WHERE "device_id" = "' . $pia_func_down_devid . '"';
	$func_result = $db->query($func_sql); //->fetchArray(SQLITE3_ASSOC);
	while ($func_res = $func_result->fetchArray(SQLITE3_ASSOC)) {
		$temp_group_array = explode(';', $func_res['net_downstream_devices']);
	}
	$clean_group_array = array_filter($temp_group_array);
	unset($temp_group_array);
	for ($x = 0; $x < sizeof($clean_group_array); $x++) {
		$temp_port_array = explode(',', trim($clean_group_array[$x]));
		$downstream_devices[trim($temp_port_array[1])] = trim(strtolower($temp_port_array[0]));
	}
	return $downstream_devices;
}

function get_downstream_devices_b($pia_func_down_devid) {
	global $db;

	//$manual_downstream_ports = array();
	$func_sql = 'SELECT * FROM "network_infrastructure" WHERE "device_id" = "' . $pia_func_down_devid . '"';
	$func_result = $db->query($func_sql); //->fetchArray(SQLITE3_ASSOC);
	while ($func_res = $func_result->fetchArray(SQLITE3_ASSOC)) {
		$temp_group_array = explode(';', $func_res['net_downstream_devices']);
	}
	$downstream_devices = array_filter($temp_group_array);
	return $downstream_devices;
}

function get_downstream_from_mac($pia_func_down_mac) {
	global $db;

	$func_sql = 'SELECT * FROM "Devices" WHERE "dev_MAC" = "' . $pia_func_down_mac . '"';
	$func_result = $db->query($func_sql); //->fetchArray(SQLITE3_ASSOC);
	while ($func_res = $func_result->fetchArray(SQLITE3_ASSOC)) {
		return $func_res;
	}
}

function printNodeOnlineState($pia_func_node_state) {
	if ($pia_func_node_state == "online") {
		echo '<i class="fa fa-w fa-circle text-green-light fa-gradient-green"></i>&nbsp;';
	} elseif ($pia_func_node_state == "offline") {
		echo '<i class="fa fa-w fa-circle text-red fa-gradient-red"></i>&nbsp;';
	} elseif ($pia_func_node_state == "inactive") {
		echo '<i class="fa fa-w fa-circle text-gray"></i>&nbsp;';
	}
}

function getNodeOnlineState($pia_node_name) {
	global $db;
	$func_sql = 'SELECT * FROM "Devices" WHERE "dev_Name" = "' . $pia_node_name . '"';
	$func_result = $db->query($func_sql); //->fetchArray(SQLITE3_ASSOC);
	while ($func_res = $func_result->fetchArray(SQLITE3_ASSOC)) {
		if ($func_res['dev_PresentLastScan'] == 1) {$node_state = 'online';} else { $node_state = 'offline';}
	}
	if (!isset($node_state)) {$node_state = "offline";}
	return $node_state;
}

function getNodeOnlineState_by_mac($pia_node_mac) {
	global $db;
	$func_sql = 'SELECT * FROM "Devices" WHERE "dev_MAC" = "' . $pia_node_mac . '"';
	$func_result = $db->query($func_sql); //->fetchArray(SQLITE3_ASSOC);
	while ($func_res = $func_result->fetchArray(SQLITE3_ASSOC)) {
		if ($func_res['dev_PresentLastScan'] == 1) {$node_state = 'online';} else { $node_state = 'offline';}
	}
	if (!isset($node_state)) {$node_state = "offline";}
	return $node_state;
}

function getNodeClientsOnlineState($pia_node_id) {
	global $db;
	$func_sql = 'SELECT COUNT(*) as count FROM "Devices" WHERE "dev_PresentLastScan" = 1 AND "dev_Infrastructure" = "' . $pia_node_id . '"';
	$rows = $db->query($func_sql); //->fetchArray(SQLITE3_ASSOC);
	$row = $rows->fetchArray();
	$count_a = $row['count'];
	$count_b = 0;
	// Check the Devices of manual Port Config
	// supported Types
	$non_port_types = array("3_WLAN", "4_Powerline", "5_Hypervisor");
	if (in_array($row['net_device_typ'], $non_port_types)) {
		$sql = 'SELECT * FROM "network_infrastructure" WHERE "device_id" = "' . $pia_node_id . '"';
		$result = $db->query($sql);
		$row = $result->fetchArray(SQLITE3_ASSOC);

		if (strlen($row['net_downstream_devices']) > 16) {
			$customlist = explode(";", $row['net_downstream_devices']);
			for ($x=0;$x<sizeof($customlist);$x++) {
					if (getNodeOnlineState_by_mac($customlist[$x]) == "online") {
						$count_b++;
					}
			}
		}
	}
	$count = $count_a + $count_b;
	if ($count > 0) {$node_state = 'online';} else { $node_state = 'offline';}
	$state_data = array($node_state, $count);
	return $state_data;
}

function port_badge($status) {
	if ($status == "online") {echo '<span class="badge bg-green text-white" style="width: 60px;">Online</span>';}
	if ($status == "offline") {echo '<span class="badge bg-gray text-white" style="width: 60px;">Offline</span>';}
	if ($status == "dumb") {echo '<span class="badge bg-yellow text-white" style="width: 60px;">UM</span>';}
}

// Create the Tabs
function createnetworktab($pia_func_netdevid, $pia_func_netdevname, $pia_func_netdevtyp, $pia_func_netdevport, $activetab) {
	global $db;
	echo '<li class="' . $activetab . '">';
	echo '<a href="#' . $pia_func_netdevid . '" data-toggle="tab">';
	// Check Node name, if is was present last scan
	$nodestate = getNodeOnlineState($pia_func_netdevname);
	//echo $nodestate;
	if ($nodestate == "offline") {
		// if Node was offline, check if a connected Client was online and print "light" color depending on that status
		$temp_array = getNodeClientsOnlineState($pia_func_netdevid);
		if (($temp_array[0] == "offline") && (substr($pia_func_netdevtyp, 2) == 'WLAN')) {
			printNodeOnlineState('inactive');
		} else {
			printNodeOnlineState($temp_array[0]);
		}
	} else {
		// print "light" color
		printNodeOnlineState(getNodeOnlineState($pia_func_netdevname));
	}
	//echo getNodeClientsOnlineState($pia_func_netdevid);
	echo $pia_func_netdevname . ' / ';
	if (substr($pia_func_netdevtyp, 2) == 'WLAN') {
		// Tab icon depending on the pia_func_netdevty (first 2 chars "x_" removed)
		echo '<i class="bi bi-wifi network_tab_icon text-aqua" style="top: 1px;"></i>';
	} elseif (substr($pia_func_netdevtyp, 2) == 'Powerline') {
		echo '<i class="bi bi-plug-fill network_tab_icon text-aqua" style="top: 2px;"></i>';
	} elseif (substr($pia_func_netdevtyp, 2) == 'Router') {
		echo '<i class="bi bi-router-fill network_tab_icon text-aqua" style="top: 2px;"></i>';
	} elseif (substr($pia_func_netdevtyp, 2) == 'Switch') {
		echo '<i class="bi bi-ethernet network_tab_icon text-aqua" style="top: 2px;"></i>';
	} elseif (substr($pia_func_netdevtyp, 2) == 'Internet') {
		echo '<i class="bi bi-globe network_tab_icon text-aqua" style="top: 2px;"></i>';
	} elseif (substr($pia_func_netdevtyp, 2) == 'Hypervisor') {
		echo '<i class="bi bi-hdd-stack-fill network_tab_icon text-aqua" style="top: 2px;"></i>';
	} else {
		// No tab icon (first 2 chars "x_" removed)
		echo substr($pia_func_netdevtyp, 2);
	}

	// Enable the display of the complete Portcount
	//if ($pia_func_netdevport != "") {echo ' (' . $pia_func_netdevport . ')';}
	echo '</a></li>';
}

function get_all_devices_from_tables($pia_func_netdevid) {
	global $db;
	// Query detected Devices
	$func_sql1 = 'SELECT * FROM "Devices" WHERE "dev_Infrastructure" = "' . $pia_func_netdevid . '" AND "dev_Archived" = 0';
	$func_result1 = $db->query($func_sql1); //->fetchArray(SQLITE3_ASSOC);
	// Query dumb Devices
	$func_sql2 = 'SELECT * FROM "network_dumb_dev" WHERE "dev_Infrastructure" = "' . $pia_func_netdevid . '"';
	$func_result2 = $db->query($func_sql2); //->fetchArray(SQLITE3_ASSOC);

	while ($row1 = $func_result1->fetchArray(SQLITE3_ASSOC)) {
		$combinedResults[] = $row1;
	}
	while ($row2 = $func_result2->fetchArray(SQLITE3_ASSOC)) {
		$combinedResults[] = $row2;
	}
	return $combinedResults;
}

// Create the Tabspage
function createnetworktabcontent($pia_func_netdevid, $pia_func_netdevname, $pia_func_netdevtyp, $pia_func_netdevport, $activetab) {
	global $pia_lang;

	echo '<div class="tab-pane ' . $activetab . '" id="' . $pia_func_netdevid . '">
	      <h4>' . $pia_func_netdevname . '</h4><br>';

	$downstream_devices = get_downstream_devices($pia_func_netdevid);

	echo '<div class="box-body no-padding">
    <table class="table table-striped table-hover">
      <tbody><tr>
        <th style="width: 40px">Port</th>
        <th style="width: 75px">' . $pia_lang['Network_Table_State'] . '</th>
        <th>' . $pia_lang['Network_Table_Hostname'] . '</th>
        <th>' . $pia_lang['Network_Table_IP'] . '</th>
      </tr>';
	// Prepare Array for Devices with Port value
	// If no Port is set, the Port number is set to 1
	if ($pia_func_netdevport == "") {$pia_func_netdevport = 1;}
	// Create Array with specific length
	$network_device_portname = array();
	$network_device_portmac = array();
	$network_device_portip = array();
	$network_device_portstate = array();

	// make sql query for Network Hardware ID
	$combinedResults = get_all_devices_from_tables($pia_func_netdevid);

	foreach ($combinedResults as $func_res) {
		// Prepare Table with Port > enter values in the arrays
		if ($pia_func_netdevport > 1) {
			if (stristr($func_res['dev_Infrastructure_port'], ',') == '') {
				if ($network_device_portname[$func_res['dev_Infrastructure_port']] != '') {$network_device_portname[$func_res['dev_Infrastructure_port']] = $network_device_portname[$func_res['dev_Infrastructure_port']] . ',' . $func_res['dev_Name'];} else { $network_device_portname[$func_res['dev_Infrastructure_port']] = $func_res['dev_Name'];}
				if ($network_device_portmac[$func_res['dev_Infrastructure_port']] != '') {$network_device_portmac[$func_res['dev_Infrastructure_port']] = $network_device_portmac[$func_res['dev_Infrastructure_port']] . ',' . $func_res['dev_MAC'];} else { $network_device_portmac[$func_res['dev_Infrastructure_port']] = $func_res['dev_MAC'];}
				if ($network_device_portip[$func_res['dev_Infrastructure_port']] != '') {$network_device_portip[$func_res['dev_Infrastructure_port']] = $network_device_portip[$func_res['dev_Infrastructure_port']] . ',' . $func_res['dev_LastIP'];} else { $network_device_portip[$func_res['dev_Infrastructure_port']] = $func_res['dev_LastIP'];}
				if (isset($network_device_portstate[$func_res['dev_Infrastructure_port']])) {$network_device_portstate[$func_res['dev_Infrastructure_port']] = $network_device_portstate[$func_res['dev_Infrastructure_port']] . ',' . $func_res['dev_PresentLastScan'];} else { $network_device_portstate[$func_res['dev_Infrastructure_port']] = $func_res['dev_PresentLastScan'];}
			} else {
				$multiport = array();
				$multiport = explode(',', $func_res['dev_Infrastructure_port']);
				foreach ($multiport as $row) {
					$network_device_portname[trim($row)] = $func_res['dev_Name'];
					$network_device_portmac[trim($row)] = $func_res['dev_MAC'];
					$network_device_portip[trim($row)] = $func_res['dev_LastIP'];
					$network_device_portstate[trim($row)] = $func_res['dev_PresentLastScan'];
				}
				unset($multiport);
			}
		} else {
		// Prepare Table with Port > enter values in the arrays
			$network_device_portname[]=$func_res['dev_Name'];
			$network_device_portmac[]=$func_res['dev_MAC'];
			$network_device_portip[]=$func_res['dev_LastIP'];
			$network_device_portstate[]=$func_res['dev_PresentLastScan'];
		}
	}

	// Create table with Port
	if ($pia_func_netdevport > 1) {
		for ($x = 1; $x <= $pia_func_netdevport; $x++) {
			// Manual Entry Processing
			if (isset($downstream_devices[$x])) {
				$downstream_device_resolved = get_downstream_from_mac($downstream_devices[$x]);
				$network_device_portmac[$x] = $downstream_devices[$x];
				$network_device_portname[$x] = $downstream_device_resolved['dev_Name'];
				$network_device_portstate[$x] = $downstream_device_resolved['dev_PresentLastScan'];
				$network_device_portip[$x] = $downstream_device_resolved['dev_LastIP'];
			}
			echo '<tr>';
			echo '<td style="text-align: right; padding-right:16px;">' . $x . '</td>';
			// Check if multiple badges necessary
			if (stristr($network_device_portstate[$x], ',') == '') {
				// Set single online/offline badge
				echo '<td>';
				if ($network_device_portstate[$x] == "1") {port_badge('online');} elseif ($network_device_portstate[$x] === "dumb") {port_badge('dumb');} else {port_badge('offline');}
				echo '</td>';
			} else {
				// Set multiple online/offline badges
				$multistate = array();
				$multistate = explode(',', $network_device_portstate[$x]);
				echo '<td>';
				foreach ($multistate as $key => $value) {
					if ($value == "1") {port_badge('online');} elseif ($value === "dumb") {port_badge('dumb');} else {port_badge('offline');}
					echo '<br>';
				}
				echo '</td>';
				unset($multistate);
			}
			// Check if multiple Hostnames are set
			// print single hostname
			if (stristr($network_device_portmac[$x], ',') == '') {
				if ($network_device_portmac[$x] != "dumb") {
					// detectable Device
					echo '<td style="padding-left: 10px;"><a href="./deviceDetails.php?mac=' . $network_device_portmac[$x] . '"><b>' . $network_device_portname[$x] . '</b></a></td>';
				} else {
					// dumb Device
					echo '<td style="padding-left: 10px;"><a href="./networkSettings.php#hostedit"><b>' . $network_device_portname[$x] . '</b></a></td>';
				}
			} else {
				// print multiple hostnames with separate links
				$multimac = array();
				$multimac = explode(',', $network_device_portmac[$x]);
				$multiname = array();
				$multiname = explode(',', $network_device_portname[$x]);
				echo '<td style="padding-left: 10px;">';
				foreach ($multiname as $key => $value) {
					//echo '<a href="./deviceDetails.php?mac=' . $multimac[$key] . '"><b>' . $value . '</b></a><br>';
					if ($multimac[$key] != "dumb") {
						// detectable Device
						echo '<a href="./deviceDetails.php?mac=' . $multimac[$key] . '"><b>' . $value . '</b></a><br>';
					} else {
						// dumb Device
						echo '<a href="./networkSettings.php#hostedit"><b>' . $value . '</b></a><br>';
					}
				}
				echo '</td>';
				unset($multiname, $multimac);
			}
			// Check if multiple IP are set
			// print single IP
			if (stristr($network_device_portip[$x], ',') == '') {
				echo '<td style="padding-left: 10px;">' . $network_device_portip[$x] . '</td>';
			} else {
				// print multiple IPs
				$multiip = array();
				$multiip = explode(',', $network_device_portip[$x]);
				echo '<td style="padding-left: 10px;">';
				foreach ($multiip as $key => $value) {
					echo $value . '<br>';
				}
				echo '</td>';
				unset($multiip);
			}
			echo '</tr>';
		}
	} else {
		// Create table without Port
		if (substr($pia_func_netdevtyp, 2) == "WLAN") {$dev_port_icon = 'fa-wifi';}
		if (substr($pia_func_netdevtyp, 2) == "Powerline") {$dev_port_icon = 'fa-flash';}
		if (substr($pia_func_netdevtyp, 2) == "Hypervisor") {$dev_port_icon = 'fa-computer';}

		$manual_device_links = get_downstream_devices_b($pia_func_netdevid);

		if (isset($manual_device_links)) {
			for ($i = 0; $i < sizeof($manual_device_links); $i++){
				$manual_device_links_resolved = get_downstream_from_mac($manual_device_links[$i]);
				$network_device_portmac[] = $manual_device_links[$i];
				$network_device_portname[] = $manual_device_links_resolved['dev_Name'];
				$network_device_portstate[] = $manual_device_links_resolved['dev_PresentLastScan'];
				$network_device_portip[] = $manual_device_links_resolved['dev_LastIP'];
			}
		}
		natcasesort($network_device_portname);
		foreach($network_device_portname as $key => $network_device_portname_sort) {
			if ($network_device_portstate[$key] == 1) {$port_state = 'online';} else { $port_state = 'offline';}
			if ($func_res['dev_MAC'] != "dumb") {
				// detectable Device
				echo '<tr><td style="text-align: center;"><i class="fa ' . $dev_port_icon . '"></i></td><td>';
				port_badge($port_state);
				echo '</td><td style="padding-left: 10px;"><a href="./deviceDetails.php?mac=' . $network_device_portmac[$key] . '"><b>' . $network_device_portname_sort . '</b></a></td><td>' . $network_device_portip[$key] . '</td></tr>';
			} else {
				// dumb Devices
				echo '<tr><td style="text-align: center;"><i class="fa ' . $dev_port_icon . '"></i></td><td>';
				port_badge($port_state);
				echo '</td><td style="padding-left: 10px;"><a href="./networkSettings.php#hostedit"><b>' . $network_device_portname_sort . '</b></a></td><td>' . $network_device_portip[$key] . '</td></tr>';
			}	
		}
	}
	echo '        </tbody></table>
            </div>';
	echo '</div> ';
}

// #####################################
// ## Create Tabs
// #####################################
$sql = 'SELECT "device_id", "net_device_name", "net_device_typ", "net_device_port" FROM "network_infrastructure" ORDER BY "net_device_typ" ASC, "net_device_name" ASC';
$result = $db->query($sql); //->fetchArray(SQLITE3_ASSOC);
?>
      <div class="nav-tabs-custom" style="">
            <ul class="nav nav-tabs">
<?php
$i = 0;
while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
	if (!isset($res['device_id'])) {
		continue;
	}

	if ($i == 0) {$active = 'active';} else { $active = '';}
	createnetworktab($res['device_id'], $res['net_device_name'], $res['net_device_typ'], $res['net_device_port'], $active);
	$i++;
}
?>
            </ul>
			<div class="tab-content">
<?php
// #####################################
// ## Create Tab Content
// #####################################
$i = 0;
while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
	if (!isset($res['device_id'])) {
		continue;
	}

	if ($i == 0) {$active = 'active';} else { $active = '';}
	createnetworktabcontent($res['device_id'], $res['net_device_name'], $res['net_device_typ'], $res['net_device_port'], $active);
	$i++;
}
unset($i);
?>
              <!-- /.tab-pane -->
            </div>
            <!-- /.tab-content -->
  </div>

<div class="box box-default collapsed-box">
    <div class="box-header with-border" data-widget="collapse">
        <h3 class="box-title"><i class="fa"></i><?=$pia_lang['Network_UnassignedDevices'];?></h3>
          <div class="box-tools pull-right">
            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>
          </div>
    </div>
    <div class="box-body">
<?php
unassigned_devices();
?>
    </div>
    <!-- /.box-body -->
</div>

  <div style="width: 100%; height: 20px;"></div>
</section>

    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->

<!-- ----------------------------------------------------------------------- -->
<?php
require 'php/templates/footer.php';
?>
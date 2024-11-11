<?php
// Header - Delete Single WebGUI Reports
function useRegex($input) {
	$regex = '/[0-9]+-[0-9]+_.*\\.txt/i';
	return preg_match($regex, $input);
}

function get_local_system_tz() {
	$database = '../db/pialert.db';
    $db = new SQLite3($database);	
	$query = "SELECT par_Value FROM Parameters WHERE par_ID = 'Local_System_TZ'";
	$result = $db->query($query);

	$timezone = "unknown";
	if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
	    $timezone = $row['par_Value'];
	}
	return $timezone;
}
// Maintenance Page - Pause Arp Scan Section
function arpscanstatus() {
	global $pia_lang;
	if (!file_exists('../config/setting_stoppialert')) {
		unset($_SESSION['arpscan_timerstart']);
		$_SESSION['arpscan_result'] = '<span id="arpproccounter"></span> ' . $pia_lang['MT_arp_status_on'] . ' <div id="nextscancountdown" style="display: inline-block;"></div>';
		$_SESSION['arpscan_sidebarstate'] = 'Active';
		$_SESSION['arpscan_sidebarstate_light'] = 'green-light fa-gradient-green';
	} else {
		$_SESSION['arpscan_timerstart'] = date("H:i:s", filectime('../config/setting_stoppialert'));
		$_SESSION['arpscan_result'] = '<span style="color:red;">Pi.Alert ' . $pia_lang['MT_arp_status_off'] . '</span> <div id="nextscancountdown" style="display: none;"></div>';
		$_SESSION['arpscan_sidebarstate'] = 'Disabled&nbsp;&nbsp;&nbsp;(' . $_SESSION['arpscan_timerstart'] . ')';
		$_SESSION['arpscan_sidebarstate_light'] = 'red fa-gradient-red';
	}
}
// Sidebar - Systeminfo
function getTemperature() {
	if (file_exists('/sys/class/thermal/thermal_zone0/temp')) {
		$output = rtrim(file_get_contents('/sys/class/thermal/thermal_zone0/temp'));
	} elseif (file_exists('/sys/class/hwmon/hwmon0/temp1_input')) {
		$output = rtrim(file_get_contents('/sys/class/hwmon/hwmon0/temp1_input'));
	} else {
		$output = '';
	}
	if (is_numeric($output)) {
		// $output could be either 4-5 digits or 2-3, and we only divide by 1000 if it's 4-5 (ex. 39007 vs 39)
		$celsius = intval($output);
		// If celsius is greater than 1 degree and is in the 4-5 digit format
		if ($celsius > 1000) {
			// Use multiplication to get around the division-by-zero error
			$celsius *= 1e-3;
		}
		$limit = 60;
	} else {
		// Nothing can be colder than -273.15 degree Celsius (= 0 Kelvin).This is the minimum temperature possible
		$celsius = -273.16;
		// Set templimit to null if no tempsensor was found
		$limit = null;
	}
	return array($celsius, $limit);
}
// Sidebar - Systeminfo
function getMemUsage() {
	$data = explode("\n", file_get_contents('/proc/meminfo'));
	$meminfo = array();
	if (count($data) > 0) {
		foreach ($data as $line) {
			$expl = explode(':', $line);
			if (count($expl) == 2) {
				// remove " kB" from the end of the string and make it an integer
				$meminfo[$expl[0]] = intval(trim(substr($expl[1], 0, -3)));
			}
		}
		$memused = $meminfo['MemTotal'] - $meminfo['MemFree'] - $meminfo['Buffers'] - $meminfo['Cached'];
		$memusage = $memused / $meminfo['MemTotal'];
	} else {
		$memusage = -1;
	}
	return $memusage;
}
// Sidebar - Systeminfo
function format_MemUsage($memory_usage) {
	echo '<span><i class="fa fa-w fa-circle ';
	if ($memory_usage > 0.75 || $memory_usage < 0.0) {echo 'text-red fa-gradient-red';} else {echo 'text-green-light fa-gradient-green';}
	if ($memory_usage > 0.0) {echo '"></i> Memory usage:&nbsp;&nbsp;' . sprintf('%.1f', 100.0 * $memory_usage) . '&thinsp;%</span>';} else {echo '"></i> Memory usage:&nbsp;&nbsp; N/A</span>';}
}
// Sidebar - Systeminfo
function format_sysloadavg($loaddata) {
	$nproc = shell_exec('nproc');
	if (!is_numeric($nproc)) {
		$cpuinfo = file_get_contents('/proc/cpuinfo');
		preg_match_all('/^processor/m', $cpuinfo, $matches);
		$nproc = count($matches[0]);
	}
	echo '<span title="Detected ' . $nproc . ' cores"><i class="fa fa-w fa-circle ';
	if ($loaddata[0] > $nproc) {echo 'text-red fa-gradient-red';} else {echo 'text-green-light fa-gradient-green';}
	echo '"></i> Load:&nbsp;&nbsp;' . round($loaddata[0], 2) . '&nbsp;&nbsp;' . round($loaddata[1], 2) . '&nbsp;&nbsp;' . round($loaddata[2], 2) . '</span>';
}
// Sidebar - Systeminfo
function format_temperature($celsius, $temperaturelimit) {
	if ($celsius >= -273.15) {
		// Only show temp info if any data is available -->
		$tempcolor = 'text-vivid-blue';
		if (isset($temperaturelimit) && $celsius > $temperaturelimit) {$tempcolor = 'text-red fa-gradient-red';}
		echo '<span id="temperature">
		         <i class="fa fa-w fa-fire ' . $tempcolor . '" style="width: 1em !important"></i> Temp:&nbsp;<span id="rawtemp" hidden>' . $celsius . '</span><span id="tempdisplay"></span>
		      </span>';
	}
}
// Sidebar Menu - Web Services Menu Items
function toggle_webservices_menu($section) {
	global $pia_lang;
	if (($_SESSION['Scan_WebServices'] == True) && ($section == "Main")) {
		echo '<li class="';
		if (in_array(basename($_SERVER['SCRIPT_NAME']), array('services.php', 'serviceDetails.php'))) {echo 'active';}
		echo '"><a href="services.php">
                	<i class="fa fa-globe"></i>
                	<span>' . $pia_lang['NAV_Services'] . '</span>
		          	<span class="pull-right-container" style="margin-right:-5px">
		              <small class="label pull-right bg-yellow" id="header_services_count_warning"></small>
		              <small class="label pull-right bg-red" id="header_services_count_down"></small>
		              <small class="label pull-right bg-green" id="header_services_count_on"></small>
		            </span>
                </a></li>';
	}
}
// Sidebar Menu - ICPMScan Menu Items
function toggle_icmpscan_menu($section) {
	global $pia_lang;
	if (($_SESSION['ICMPScan'] == True) && ($section == "Main")) {
		echo '<li class="';
		if (in_array(basename($_SERVER['SCRIPT_NAME']), array('icmpmonitor.php', 'icmpmonitorDetails.php'))) {echo 'active';}
		echo '"><a href="icmpmonitor.php">
                    <i class="fa fa-magnifying-glass"></i>
                    <span>' . $pia_lang['NAV_ICMPScan'] . '</span>
					<span class="pull-right-container" style="margin-right:-5px">
						<small class="label pull-right bg-red" id="header_icmp_count_down"></small>
						<small class="label pull-right bg-green" id="header_icmp_count_on"></small>
					</span>
                </a></li>';
	}
}
// Sidebar Menu - Satellites Menu Items
function toggle_satellites_submenu() {
	if (($_SESSION['Scan_Satellite'] == True)) {
		// prepare SubHeadline on devices page
		$_SESSION['local'] = "local";
		global $satellite_badges_list;
    	$database = '../db/pialert.db';
	    $db = new SQLite3($database);
	    $sql_select = 'SELECT * FROM Satellites ORDER BY sat_name ASC';
	    $result = $db->query($sql_select);
	    if ($result) {
	        if ($result->numColumns() > 0) {
	            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
	                array_push($satellite_badges_list, $row['sat_token']);
	                // prepare SubHeadline on devices page
	                $_SESSION[$row['sat_token']] = $row['sat_name'];
	                // Create NavBar items
	                $dev_submenu .= '<li class="custom_filter">
	                	<a href="devices.php?scansource='.$row['sat_token'].'" style="font-size: 14px; height: 30px; line-height:30px;padding:0;padding-left:25px;">
	                		<i class="fa-solid fa-satellite" style="margin-right:5px;"></i>
	                		<span>'.$row['sat_name'].'</span>
	                		<span class="pull-right-container" style="margin-right:-5px">
				              <small class="label pull-right bg-yellow" id="header_'.$row['sat_token'].'_count_new"></small>
				              <small class="label pull-right bg-red" id="header_'.$row['sat_token'].'_count_down"></small>
				              <small class="label pull-right bg-green" id="header_'.$row['sat_token'].'_count_on"></small>
		            		</span>
		            </a></li>';

		            $pres_submenu .= '<li class="custom_filter">
	                	<a href="presence.php?scansource='.$row['sat_token'].'" style="font-size: 14px; height: 30px; line-height:30px;padding:0;padding-left:25px;">
	                		<i class="fa-solid fa-satellite" style="margin-right:5px;"></i>
	                		<span>'.$row['sat_name'].'</span>
	                		<span class="pull-right-container" style="margin-right:-5px">
				              <small class="label pull-right bg-gray" id="header_'.$row['sat_token'].'_presence"></small>
		            		</span></a>
	                	</li>';
	            }
	        return array($dev_submenu, $pres_submenu);
	        }
	    }
	    $db->close();
	}
}
function create_satellite_badges() {
	if ($_SESSION['Scan_Satellite'] == True) {
		global $satellite_badges_list;
		for ($x=0;$x<sizeof($satellite_badges_list);$x++) {
			echo "      getDevicesTotalsBadge('" . $satellite_badges_list[$x] . "');\n";
		}
	}
}
// Parse Config file
function get_config_parmeter($config_param) {
	$configContent = file_get_contents('../config/pialert.conf');
	$configContent = preg_replace('/^\s*#.*$/m', '', $configContent);
	$configArray = parse_ini_string($configContent);
	if (isset($configArray[$config_param])) {return $configArray[$config_param];} else {return False;}
}
// Set Session Vars
if (get_config_parmeter('ICMPSCAN_ACTIVE') == 1) {$_SESSION['ICMPScan'] = True;} else { $_SESSION['ICMPScan'] = False;}
if (get_config_parmeter('SATELLITES_ACTIVE') == 1) {$_SESSION['Scan_Satellite'] = True; $satellite_badges_list = array();} else { $_SESSION['Scan_Satellite'] = False;}
if (get_config_parmeter('SCAN_WEBSERVICES') == 1) {$_SESSION['Scan_WebServices'] = True;} else { $_SESSION['Scan_WebServices'] = False;}
if (get_config_parmeter('ARPSCAN_ACTIVE') == 1) {$_SESSION['Scan_MainScan'] = True;} else { $_SESSION['Scan_MainScan'] = False;}
if (get_config_parmeter('AUTO_UPDATE_CHECK') == 1) {$_SESSION['Auto_Update_Check'] = True;} else { $_SESSION['Auto_Update_Check'] = False;}
if (get_config_parmeter('AUTO_DB_BACKUP') == 1) {$_SESSION['AUTO_DB_BACKUP'] = True;} else { $_SESSION['AUTO_DB_BACKUP'] = False;}
if (get_config_parmeter('SPEEDTEST_TASK_ACTIVE') == 1) {$_SESSION['SPEEDTEST_TASK_ACTIVE'] = True;} else { $_SESSION['SPEEDTEST_TASK_ACTIVE'] = False;}
if (get_config_parmeter('SATELLITES_ACTIVE') == 1) {$_SESSION['SATELLITES_ACTIVE'] = True;} else { $_SESSION['SATELLITES_ACTIVE'] = False;}
$_SESSION['AUTO_UPDATE_CHECK_CRON'] = get_config_parmeter('AUTO_UPDATE_CHECK_CRON');
$_SESSION['AUTO_DB_BACKUP_CRON'] = get_config_parmeter('AUTO_DB_BACKUP_CRON');
$_SESSION['SPEEDTEST_TASK_CRON'] = get_config_parmeter('SPEEDTEST_TASK_CRON');
$_SESSION['REPORT_NEW_CONTINUOUS_CRON'] = get_config_parmeter('REPORT_NEW_CONTINUOUS_CRON');

// State for Toggle Buttons
function convert_state($state, $revert) {
	global $pia_lang;
	if ($revert == 1) {
		if ($state == 1) {return $pia_lang['Gen_off'];} else {return $pia_lang['Gen_on'];}
	} elseif ($revert == 0) {
		if ($state != 1) {return $pia_lang['Gen_off'];} else {return $pia_lang['Gen_on'];}
	}
}
function convert_state_action($state, $revert) {
	global $pia_lang;
	if ($revert == 1) {
		if ($state == 1) {return $pia_lang['Gen_deactivate'];} else {return $pia_lang['Gen_activate'];}
	} elseif ($revert == 0) {
		if ($state != 1) {return $pia_lang['Gen_deactivate'];} else {return $pia_lang['Gen_activate'];}
	}
}
// Top Navbar - Back button for details pages
function insert_back_button() {
	$pagename = basename($_SERVER['PHP_SELF']);
	if ($pagename == 'serviceDetails.php') {$backto = 'services.php';}
	if ($pagename == 'deviceDetails.php') {$backto = 'devices.php';}
	if ($pagename == 'icmpmonitorDetails.php') {$backto = 'icmpmonitor.php';}
	if (isset($backto)) {echo '<a id="navbar-back-button" href="./' . $backto . '" role="button"><i class="fa fa-chevron-left"></i></a>';}
}
// Theme Fix - Adjust Logo Color
function set_userimage($skinname) {
	if ($skinname == 'skin-black-light' || $skinname == 'skin-black'|| $skinname == 'leiweibau_light') {
		$_SESSION['UserLogo'] = 'pialertLogoBlack';
	} else {$_SESSION['UserLogo'] = 'pialertLogoWhite';}
}

// Sidebar Menu - Get DeviceList Filters and create session array to reduce sqlite3 queries
function get_devices_filter_list() {
	$database = '../db/pialert.db';
	$db = new SQLite3($database);
	$sql_select = 'SELECT * FROM Devices_table_filter ORDER BY reserve_a ASC, filtername ASC';
	$result = $db->query($sql_select);
	$_SESSION['Filter_Table'] = array();
	if ($result) {
		if ($result->numColumns() > 0) {
	        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
	        	array_push($_SESSION['Filter_Table'], $row);
	        }
		}
	} else {echo "";}
	$db->close();
	// Unset Var
	unset($row);
	show_group_filters();
	show_groupless_filters();
}
// Sidebar Menu - Show filter editor from array
function show_filter_editor() {
	global $pia_lang;
	$filter_table = $_SESSION['Filter_Table'];
	$i=0;
	$listsize = sizeof($filter_table);
	foreach ($filter_table as $row) {
		$i++;
		$spacer = '<div class="row"><div class="col-xs-12"><hr></div></div>';
		echo '<div class="row">';
    	echo '<div class="col-md-2 col-md-offset-1">
    			<div class="form-group" style="text-align: left;">
    				<label class="control-label">' . $pia_lang['Device_del_table_filtername'] . '</label>
    				<input class="form-control" id="txt_' . $row['id'] . '_ID" type="hidden" value="' . $row['id'] . '">
    				<input class="form-control" id="txt_' . $row['id'] . '_name" type="text" value="' . $row['filtername'] . '">
    			</div>
    		  </div>';
    	echo '<div class="col-md-2"><div class="form-group" style="text-align: left;"><label class="control-label">' . $pia_lang['Device_del_table_filterstring'] . '</label><input class="form-control" id="txt_' . $row['id'] . '_string" type="text" value="' . $row['filterstring'] . '"></div></div>';
    	echo '<div class="col-md-1"><div class="form-group" style="text-align: left;"><label class="control-label">' . $pia_lang['Device_del_table_filterindex'] . '</label><input class="form-control" id="txt_' . $row['id'] . '_index" type="text" value="' . $row['reserve_a'] . '"></div></div>';
    	echo '<div class="col-md-2"><div class="form-group" style="text-align: left;"><label class="control-label">' . $pia_lang['Device_del_table_filtercol'] . '</label><input class="form-control" id="txt_' . $row['id'] . '_column" type="text" value="' . $row['reserve_b'] . '"></div></div>';
    	echo '<div class="col-md-2"><div class="form-group" style="text-align: left;"><label class="control-label">' . $pia_lang['Device_del_table_filtergroup'] . '</label><input class="form-control" id="txt_' . $row['id'] . '_group" type="text" value="' . $row['reserve_c'] . '"></div></div>';
    	echo '<div class="col-md-1">
    			<div class="form-group" style="text-align: left;">
    				<button type="button" class="btn btn-link" id="btnSaveFilter_' . $row['id'] . '" onclick="SaveFilterID_' . $row['id'] . '(\'' . $row['filtername'] . '\',\'' . $row['id'] . '\')" ><i class="bi bi-floppy text-yellow" style="position: relative; font-size: 20px; top: 23px;"></i></button>
    			</div>
    		  </div>';
    	echo '</div>';
    	if ($i<$listsize) {echo $spacer;}
    }
}
// Sidebar Menu - Show filter editor from array
function create_filter_editor_js() {
	global $pia_lang;
	$filter_table = $_SESSION['Filter_Table'];
	foreach ($filter_table as $row) {
		echo '
function SaveFilterID_' . $row['id'] . '() {
	$.get(\'php/server/devices.php?action=SaveFilterID&\'
    + \'&filterid=\'      + $(\'#txt_' . $row['id'] . '_ID\').val()
    + \'&filtername=\'    + $(\'#txt_' . $row['id'] . '_name\').val()
    + \'&filterstring=\'  + $(\'#txt_' . $row['id'] . '_string\').val()
    + \'&filterindex=\'   + $(\'#txt_' . $row['id'] . '_index\').val()
    + \'&filtercolumn=\'  + $(\'#txt_' . $row['id'] . '_column\').val()
    + \'&filtergroup=\'   + $(\'#txt_' . $row['id'] . '_group\').val()
     , function(msg) {
     showMessage (msg);
   });
}';
    }
}
// Sidebar Menu - Show groupless filters in Sidebar from session array
function show_groupless_filters() {
	$filter_table = $_SESSION['Filter_Table'];
	foreach ($filter_table as $row) {
    	if ($row['filterstring'] == $_REQUEST['predefined_filter']) {$filterlist_icon = "fa-solid fa-circle";} else {$filterlist_icon = "fa-regular fa-circle";}
    	if ($row['reserve_c'] == "" || !isset($row['reserve_c'])) {
        	echo '<li class="custom_filter"><a href="devices.php?predefined_filter='.urlencode($row['filterstring']).'&filter_fields='.$row['reserve_b'].'" style="font-size: 14px; height: 30px; line-height:30px;padding:0;padding-left:25px;"><i class="'.$filterlist_icon.'" style="margin-right:5px;"></i>'. $row['filtername'] .'</a></li>';
    	}
    }
}
// Sidebar Menu - Show grouped filters in Sidebar from session array
function show_group_filters() {
	if (isset($_REQUEST['g'])) {$active_group = $_REQUEST['g'];}
	$filter_table = $_SESSION['Filter_Table'];
	$filter_groups = get_filter_group_list();
	for ($i = 0; $i < sizeof($filter_groups); $i++) {
		$temp_filter_group = $filter_groups[$i];
		if ($i == $active_group && isset($active_group)) {$group_state['menu'] = 'menu-open'; $group_state['list'] = 'block';} else {{$group_state['menu'] = ''; $group_state['list'] = 'none';}}
		echo '<li class="treeview '.$group_state['menu'].' custom_filter" style="height: auto;">
				<a href="#" style="font-size: 14px; height: 30px; line-height:30px;padding:0;padding-left:25px;">
	    			<i class="fa-solid fa-filter"></i>
	    			<span style="font-style: italic;">&nbsp;'.$temp_filter_group.'</span>
	    			<span class="pull-right-container">
	      				<i class="fa fa-angle-left pull-right"></i>
	    			</span>
	  			</a>
	  			<ul class="treeview-menu" style="display: '.$group_state['list'].';">';
		foreach ($filter_table as $row) {
	    	if ($row['reserve_c'] == $temp_filter_group) {
	    		if ($row['filterstring'] == $_REQUEST['predefined_filter']) {$filterlist_icon = "fa-solid fa-circle"; } else {$filterlist_icon = "fa-regular fa-circle"; }
	    		echo '<li><a href="devices.php?predefined_filter='.urlencode($row['filterstring']).'&filter_fields='.$row['reserve_b'].'&g='.$i.'" style="font-size: 14px; height: 30px; line-height:30px;padding:0;padding-left:22px;"><i class="'.$filterlist_icon.'" style="margin-right:5px;"></i>'. $row['filtername'] .'</a></li>';
	    	}
	    }
	    echo '</ul></li>';
	}
}
// Sidebar Menu - Get list of filter groups from session array
function get_filter_group_list() {
	$filter_table = $_SESSION['Filter_Table'];
	$filter_groups = array();
	foreach ($filter_table as $row) {
    	if ($row['reserve_c'] != "") {
    		array_push($filter_groups, $row['reserve_c']);
    	}
    }
    $filter_groups = array_unique($filter_groups);
    natsort($filter_groups);
    $filter_groups = array_values($filter_groups);
    return $filter_groups;
}
// Devicelist, ICMP Monitor - Enable Arp Histroy Graph
if (file_exists('../config/setting_noonlinehistorygraph')) {$ENABLED_HISTOY_GRAPH = False;} else { $ENABLED_HISTOY_GRAPH = True;}
// Theme - If Theme is used, hide Darkmode Button
$themefile = '../config/setting_theme*';
$theme_result = glob($themefile);
// Check if any matching files were found
if (!empty($theme_result)) {
	foreach ($theme_result as $file) {
		$themename_file = str_replace('setting_theme_', '', basename($file));
		$ENABLED_THEMEMODE = True;
		$ENABLED_DARKMODE = False;
		$skin_selected_head = '<link rel="stylesheet" href="lib/AdminLTE/dist/css/skins/skin-blue.min.css">';
		$skin_selected_body = '<body class="hold-transition skin-blue sidebar-mini" >';
		$theme_selected_head = '<link rel="stylesheet" href="css/themes/' . $themename_file . '/' . $themename_file . '.css">';
		set_userimage($themename_file);
	}
} else {
	// Darkmode
	if (file_exists('../config/setting_darkmode')) {$ENABLED_DARKMODE = True;} else { $ENABLED_DARKMODE = False;}
	// Use saved AdminLTE Skin
	foreach (glob("../config/setting_skin*") as $filename) {
		$skinname_file = str_replace('setting_', '', basename($filename));
		$skin_selected_head = '<link rel="stylesheet" href="lib/AdminLTE/dist/css/skins/' . $skinname_file . '.min.css">';
		$skin_selected_body = '<body class="hold-transition ' . $skinname_file . ' sidebar-mini" >';
		set_userimage($skinname_file);
	}
	// Use fallback AdminLTE Skin
	if (strlen($skin_selected_head) == 0) {
		$skin_selected_head = '<link rel="stylesheet" href="lib/AdminLTE/dist/css/skins/skin-blue.min.css">';
		$skin_selected_body = '<body class="hold-transition skin-blue sidebar-mini" >';
		set_userimage("skin-blue");
	}
}
// UI - Language
foreach (glob("../config/setting_language*") as $filename) {
	$pia_lang_selected = str_replace('setting_language_', '', basename($filename));
}
if (strlen($pia_lang_selected) == 0) {$pia_lang_selected = 'en_us';}
// UI - FavIcon
if (file_exists('../config/setting_favicon')) {
	$FRONTEND_FAVICON = file('../config/setting_favicon', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)[0];
} else {
	$FRONTEND_FAVICON = 'img/favicons/flat_blue_white.png';
}
// set ScanSource Defaults (Satellite Scans)
if ($_REQUEST['scansource']) {$SCANSOURCE=$_REQUEST['scansource'];} else {$SCANSOURCE='local';}

?>

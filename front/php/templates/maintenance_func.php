<?php
function check_error_reporting($tableinput) {
    $data = json_decode($tableinput, true);
    return isset($data['error_reporting']) ? $data['error_reporting'] : null;
}

function add_sat_metadata($tableinput) {
    $data = json_decode($tableinput, true);

    return [
        'satellite_proxymode' => isset($data['satellite_proxymode'])
            ? (is_bool($data['satellite_proxymode']) 
                ? ($data['satellite_proxymode'] ? 'True' : 'False') 
                : $data['satellite_proxymode']) 
            : 'unknown',

        'satellite_url' => $data['satellite_url'] ?? 'unknown',
    ];
}

function get_all_satellites_list() {
	global $pia_lang;

    $database = '../db/pialert.db';
    $db = new SQLite3($database);
    $sql_select = 'SELECT * FROM Satellites ORDER BY sat_name ASC';
    $result = $db->query($sql_select);
    if ($result) {
        if ($result->numColumns() > 0) {
        	$i = 0;
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            	if ($i!=0) {echo '<hr>';}
            	$sat_hostdata = check_error_reporting($row['sat_host_data']);
            	$sat_meta = add_sat_metadata($row['sat_host_data']);

            	if ($sat_hostdata === True) {$sat_version = $row['sat_remote_version'].' (<span class="text-red">R</span>)';} else {$sat_version = $row['sat_remote_version'];}
				show_all_satellites_list([
				    'rowid' => $row['sat_id'],
				    'name' => $row['sat_name'],
				    'token' => $row['sat_token'],
				    'password' => $row['sat_password'],
				    'last_transmit' => $row['sat_lastupdate'],
				    'version' => $sat_version,
				    'scan_arp' => $row['sat_conf_scan_arp'],
				    'scan_fritzbox' => $row['sat_conf_scan_fritzbox'],
				    'scan_mikrotik' => $row['sat_conf_scan_mikrotik'],
				    'scan_unifi' => $row['sat_conf_scan_unifi'],
				    'scan_openwrt' => $row['sat_conf_scan_openwrt'],
				    'scan_asuswrt' => $row['sat_conf_scan_asuswrt'],
				    'scan_pihole_net' => $row['sat_conf_scan_pihole_net'],
				    'scan_pihole_dhcp' => $row['sat_conf_scan_pihole_dhcp'],
				    'scan_pfsense' => $row['sat_conf_scan_pfsense'],
				    'satellite_proxymode' => $sat_meta['satellite_proxymode'],
				    'satellite_url' => $sat_meta['satellite_url'],
				]);
                $i++;
            }
        }
    }
    echo '<div id="modal_satellite_config" class="modal fade" tabindex="-1" role="dialog">
			  <div class="modal-dialog" role="document">
			    <div class="modal-content">
			      <div class="modal-header">
			        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			        <h4 class="modal-title">'.$pia_lang['MT_SET_SatEdit_Modal_head'].'</h4>
			      </div>
			      <div class="modal-body">
			      	<p>'.$pia_lang['MT_SET_SatEdit_Modal_info'].'</p>
			        <div class="form-group col-xs-12">
			            <div class="col-xs-3"><label>Proxy Mode:</label></div>
			            <div class="col-xs-9 text-left"><input type="checkbox" id="proxyMode" onchange="generateCommand()"></div>
			        </div>
			        <div class="form-group col-xs-12">
			            <div class="col-xs-3"><label for="urlInput">URL:</label></div>
				        <div class="col-xs-9"><input type="text" class="form-control" id="urlInput" placeholder="Enter URL" oninput="generateCommand()"></div>
				    </div>
			        <pre id="satellite_setup_command" style="white-space: pre-wrap; border:none;padding: 5px;"></pre>
			      </div>
			      <div class="modal-footer">
			        <button type="button" class="btn btn-default" data-dismiss="modal">'.$pia_lang['Gen_Close'].'</button>
			      </div>
			    </div>
			  </div>
			</div>';

    echo '<script>
			function InstallSatellite(satToken, satPassword) {
			    // Store the satellite name and row ID for later use
			    window.SatelliteConfigToken = satToken;
			    window.SatelliteConfigPassword = satPassword;
			    // Generate the command immediately when opening the modal
			    generateCommand();
			    // Open the modal
			    $(\'#modal_satellite_config\').modal(\'show\');
			  }

			  function generateCommand() {
			    var commandTemplate = \'bash -c "$(wget -qLO - https://github.com/leiweibau/Pi.Alert-Satellite/raw/main/install/pialert_satellite_install.sh)" -- ##NAME## ##PASSWORD## ##PROXY_MODE## ##URL##\';
			    var command = commandTemplate.replace(\'##NAME##\', window.SatelliteConfigToken).replace(\'##PASSWORD##\', window.SatelliteConfigPassword);
			    var proxyMode = document.getElementById(\'proxyMode\').checked ? \'True\' : \'False\';
			    command = command.replace(\'##PROXY_MODE##\', proxyMode);
			    var url = document.getElementById(\'urlInput\').value;
			    var quotedUrl = \'"\' + url + \'"\';
			    command = command.replace(\'##URL##\', quotedUrl);
			    document.getElementById(\'satellite_setup_command\').textContent = command;
			  }
			</script>';

    $db->close();
}
// Maintenance Page - Get Device List Columns
function read_DevListCol() {
	$file = '../config/setting_devicelist';
	if (file_exists($file)) {
		$get = file_get_contents($file, true);
		$output_array = json_decode($get, true);
	} else {
		$output_array = array('ConnectionType' => 0, 'Favorites' => 1, 'Group' => 1, 'Owner' => 1, 'Type' => 1, 'FirstSession' => 1, 'LastSession' => 1, 'LastIP' => 1, 'MACType' => 1, 'MACAddress' => 0, 'MACVendor' => 1, 'Location' => 0, 'WakeOnLAN' => 0);
	}
	return $output_array;
}
// Maintenance Page - Top Modal Block
function print_logviewer_modal_head($id, $title) {
	echo '<div class="modal fade" id="modal-logviewer-' . $id . '">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span></button>
                    <h4 class="modal-title">' . $title . '</h4>
                </div>
                <div class="modal-body main_logviwer_text_layout">
                    <div class="main_logviwer_log" style="max-height: 70vh;" id="modal_'.$id.'_content">';
}
// Maintenance Page - Bottom Modal Block
function print_logviewer_modal_foot() {
	global $pia_lang;
	echo '                <br></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">' . $pia_lang['Gen_Close'] . '</button></div>
            </div>
        </div>
    </div>';
}
// Maintenance Page - Satellite List
function show_all_satellites_list($satellite) {
    global $pia_lang;

    echo '      <div class="db_info_table_row">
                    <div class="col-xs-12 col-md-2 col-lg-2" style="padding: 5px;">
                        '.$pia_lang['MT_SET_SatCreate_FORM_Name'].': <br>
                        <input class="form-control col-xs-12" type="text" id="txtChangedSatelliteName_'.$satellite['rowid'].'" value="'.$satellite['name'].'">
                    </div>
                    <div class="col-xs-12 col-md-3 col-lg-3" style="padding: 5px;">
                        '.$pia_lang['MT_SET_SatEdit_FORM_Token'].': <br>
                        <input class="form-control col-xs-12" type="text" value="'.$satellite['token'].'" readonly>
                    </div>
                    <div class="col-xs-12 col-md-2 col-lg-3" style="padding: 5px;">
                        '.$pia_lang['MT_SET_SatEdit_FORM_Pass'].': <br>
                        <input class="form-control col-xs-12" type="text" value="'.$satellite['password'].'" readonly>
                    </div>
                    <div class="col-xs-6 col-md-2 col-lg-2" style="padding: 5px;">
                        '.$pia_lang['MT_SET_SatEdit_FORM_LastUpd'].': <br>
                        <input class="form-control col-xs-12" type="text" value="'.$satellite['last_transmit'].'" readonly>
                    </div>
                    <div class="col-xs-6 col-md-3 col-lg-2 text-center" style="padding: 5px;">
                        '.$pia_lang['MT_SET_SatEdit_FORM_Action'].': <br>
                        <button type="button" class="btn btn-link" id="btnInstallSatellite" onclick="InstallSatellite(\'' . $satellite['token'] . '\',\'' . $satellite['password'] . '\')" ><i class="bi bi-info-circle text-aqua satlist_action_btn_content"></i></button>
                        <button type="button" class="btn btn-link" id="btnSaveSatellite" onclick="SaveSatellite(\'' . $satellite['name'] . '\',\'' . $satellite['rowid'] . '\')" ><i class="bi bi-floppy text-yellow satlist_action_btn_content"></i></button>
                        <button type="button" class="btn btn-link" id="btnDeleteSatellite" onclick="DeleteSatellite(\'' . $satellite['name'] . '\',\'' . $satellite['rowid'] . '\')" ><i class="bi bi-trash text-red satlist_action_btn_content"></i></button>
                    </div>
                </div>';

    echo '      <div class="db_info_table_row">
                    <div class="col-xs-12 col-md-3 col-lg-2 text-muted">Version: '.$satellite['version'].'</div>
                    <div class="col-xs-12 col-md-9 col-lg-10 text-muted">
                        <div class="sat_config_list_a"><span class="sat_config_list_meth">arp Scan:</span><span class="sat_config_list_stat '.colorize_state($satellite['scan_arp'],1).'"> '.convert_state($satellite['scan_arp'],0).'</span></div>
                        <div class="sat_config_list_a"><span class="sat_config_list_meth">Fritz!Box:</span><span class="sat_config_list_stat '.colorize_state($satellite['scan_fritzbox'],1).'"> '.convert_state($satellite['scan_fritzbox'],0).'</span></div>
                        <div class="sat_config_list_a"><span class="sat_config_list_meth">Mikrotik:</span><span class="sat_config_list_stat '.colorize_state($satellite['scan_mikrotik'],1).'"> '.convert_state($satellite['scan_mikrotik'],0).'</span></div>
                        <div class="sat_config_list_a"><span class="sat_config_list_meth">UniFi:</span><span class="sat_config_list_stat '.colorize_state($satellite['scan_unifi'],1).'"> '.convert_state($satellite['scan_unifi'],0).'</span></div>
                        <div class="sat_config_list_a"><span class="sat_config_list_meth">OpenWRT:</span><span class="sat_config_list_stat '.colorize_state($satellite['scan_openwrt'],1).'"> '.convert_state($satellite['scan_openwrt'],0).'</span></div>
                        <div class="sat_config_list_a"><span class="sat_config_list_meth">AsusWRT:</span><span class="sat_config_list_stat '.colorize_state($satellite['scan_asuswrt'],1).'"> '.convert_state($satellite['scan_asuswrt'],0).'</span></div>
                        <div class="sat_config_list_a"><span class="sat_config_list_meth">Pi-hole:</span><span class="sat_config_list_stat '.colorize_state($satellite['scan_pihole_net'],1).'"> '.convert_state($satellite['scan_pihole_net'],0).'</span></div>
                        <div class="sat_config_list_a"><span class="sat_config_list_meth">Pi-hole DHCP:</span><span class="sat_config_list_stat '.colorize_state($satellite['scan_pihole_dhcp'],1).'"> '.convert_state($satellite['scan_pihole_dhcp'],0).'</span></div>
                        <div class="sat_config_list_a"><span class="sat_config_list_meth">pfSense:</span><span class="sat_config_list_stat '.colorize_state($satellite['scan_pfsense'],1).'"> '.convert_state($satellite['scan_pfsense'],0).'</span></div>
                    </div>
                </div>';
}
// Maintenance Page - Statusbox
function format_notifications($source_array) {
	$format_array_true = array();
	$format_array_false = array();
	$text_reference = array('WEBGUI', 'TELEGRAM', 'MAIL', 'PUSHSAFER', 'PUSHOVER', 'NTFY', 'DISCORD');
	$text_format = array('WebGUI', 'Telegram', 'Mail', 'Pushsafer', 'Pushover', 'NTFY', 'Discord');
	for ($x = 0; $x < sizeof($source_array); $x++) {
		$temp = explode("=", $source_array[$x]);
		$temp[0] = trim($temp[0]);
		$temp[1] = trim($temp[1]);
		if (strtolower($temp[1]) == "true") {
			$temp[0] = str_replace('REPORT_', '', $temp[0]);
			$temp[0] = str_replace('_WEBMON', '', $temp[0]);
			$key = array_search($temp[0], $text_reference);
			array_push($format_array_true, '<span style="color: green;">' . $text_format[$key] . '</span>');
		}
		if (strtolower($temp[1]) == "false") {
			$temp[0] = str_replace('REPORT_', '', $temp[0]);
			$temp[0] = str_replace('_WEBMON', '', $temp[0]);
			$key = array_search($temp[0], $text_reference);
			array_push($format_array_false, '<span style="color: red;">' . $text_format[$key] . '</span>');
		}
	}
	natsort($format_array_true);
	natsort($format_array_false);
	$output = implode(", ", $format_array_true) . ', ' . implode(", ", $format_array_false);
	echo $output;
}
?>

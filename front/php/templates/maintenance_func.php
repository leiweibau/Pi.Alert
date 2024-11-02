<?php
function extract_hostdata($tableinput) {
    $data = json_decode($tableinput, true);
    return isset($data['error_reporting']) ? $data['error_reporting'] : null;
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
            	$sat_hostdata = extract_hostdata($row['sat_host_data']);
            	if ($sat_hostdata === True) {$sat_version = $row['sat_remote_version'].' (<span class="text-red">R</span>)';} else {$sat_version = $row['sat_remote_version'];}
                show_all_satellites_list($row['sat_id'],$row['sat_name'],$row['sat_token'],$row['sat_password'],$row['sat_lastupdate'],$sat_version,$row['sat_conf_scan_arp'],$row['sat_conf_scan_fritzbox'],$row['sat_conf_scan_mikrotik'],$row['sat_conf_scan_unifi']);
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
// Maintenance Page - Aprscan read Timer
function read_arpscan_timer() {
	$file = '../config/setting_stoppialert';
	if (file_exists($file)) {
		$timer_arpscan = file_get_contents($file, true);
		if ($timer_arpscan == 10 || $timer_arpscan == 15 || $timer_arpscan == 30) {
			$timer_output = ' (' . $timer_arpscan . 'min)';
		}
		if ($timer_arpscan == 60 || $timer_arpscan == 120 || $timer_arpscan == 720 || $timer_arpscan == 1440) {
			$timer_arpscan = $timer_arpscan / 60;
			$timer_output = ' (' . $timer_arpscan . 'h)';
		}
		if ($timer_arpscan == 1051200) {
			$timer_output = ' (very long)';
		}
	}
	$timer_output = '<span style="color:red;">' . $timer_output . '</span>';
	echo $timer_output;
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
// Maintenance Page - Set preset checkboxes for Columnconfig
function set_column_checkboxes($table_config) {
	if ($table_config['ConnectionType'] == 1) {$col_checkbox['ConnectionType'] = "checked";}
	if ($table_config['Favorites'] == 1) {$col_checkbox['Favorites'] = "checked";}
	if ($table_config['Group'] == 1) {$col_checkbox['Group'] = "checked";}
	if ($table_config['Owner'] == 1) {$col_checkbox['Owner'] = "checked";}
	if ($table_config['Type'] == 1) {$col_checkbox['Type'] = "checked";}
	if ($table_config['FirstSession'] == 1) {$col_checkbox['FirstSession'] = "checked";}
	if ($table_config['LastSession'] == 1) {$col_checkbox['LastSession'] = "checked";}
	if ($table_config['LastIP'] == 1) {$col_checkbox['LastIP'] = "checked";}
	if ($table_config['MACType'] == 1) {$col_checkbox['MACType'] = "checked";}
	if ($table_config['MACAddress'] == 1) {$col_checkbox['MACAddress'] = "checked";}
	if ($table_config['MACVendor'] == 1) {$col_checkbox['MACVendor'] = "checked";}
	if ($table_config['Location'] == 1) {$col_checkbox['Location'] = "checked";}
	if ($table_config['WakeOnLAN'] == 1) {$col_checkbox['WakeOnLAN'] = "checked";}
	return $col_checkbox;
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
function show_all_satellites_list($sat_rowid, $sat_name, $sat_token, $sat_password, $sat_last_transmit, $sat_version, $scan_arp, $scan_fritzbox, $scan_mikrotik, $scan_unifi) {
	global $pia_lang;
	echo '      <div class="db_info_table_row">
                    <div class="col-xs-12 col-md-2" style="padding: 5px;">
                        '.$pia_lang['MT_SET_SatCreate_FORM_Name'].': <br>
                        <input class="form-control col-xs-12" type="text" id="txtChangedSatelliteName_'.$sat_rowid.'"value="'.$sat_name.'">
                    </div>
                    <div class="col-xs-12 col-md-3" style="padding: 5px;">
                        '.$pia_lang['MT_SET_SatEdit_FORM_Token'].': <br>
                        <input class="form-control col-xs-12" type="text" value="'.$sat_token.'" readonly>
                    </div>
                    <div class="col-xs-12 col-md-3" style="padding: 5px;">
                        '.$pia_lang['MT_SET_SatEdit_FORM_Pass'].': <br>
                        <input class="form-control col-xs-12" type="text" value="'.$sat_password.'" readonly>
                    </div>
                    <div class="col-xs-6 col-md-2" style="padding: 5px;">
                        '.$pia_lang['MT_SET_SatEdit_FORM_LastUpd'].': <br>
                        <input class="form-control col-xs-12" type="text" value="'.$sat_last_transmit.'" readonly>
                    </div>
                    <div class="col-xs-6 col-md-2 text-center" style="padding: 5px;">
                        '.$pia_lang['MT_SET_SatEdit_FORM_Action'].': <br>
                        <button type="button" class="btn btn-link" id="btnInstallSatellite" onclick="InstallSatellite(\'' . $sat_token . '\',\'' . $sat_password . '\')" ><i class="bi bi-info-circle text-aqua satlist_action_btn_content"></i></button>
                        <button type="button" class="btn btn-link" id="btnSaveSatellite" onclick="SaveSatellite(\'' . $sat_name . '\',\'' . $sat_rowid . '\')" ><i class="bi bi-floppy text-yellow satlist_action_btn_content"></i></button>
                        <button type="button" class="btn btn-link" id="btnDeleteSatellite" onclick="DeleteSatellite(\'' . $sat_name . '\',\'' . $sat_rowid . '\')" ><i class="bi bi-trash text-red satlist_action_btn_content"></i></button>
                    </div>
                </div>';
	echo '      <div class="db_info_table_row">
                    <div class="col-xs-12 col-md-2 text-muted">Version: '.$sat_version.'</div>
                    <div class="col-xs-12 col-md-2 text-muted">arp Scan: '.convert_state($scan_arp,0).'</div>
                    <div class="col-xs-12 col-md-2 text-muted">Fritzbox: '.convert_state($scan_fritzbox,0).'</div>
                    <div class="col-xs-12 col-md-2 text-muted">Mikrotik: '.convert_state($scan_mikrotik,0).'</div>
                    <div class="col-xs-12 col-md-2 text-muted">UniFi: '.convert_state($scan_unifi,0).'</div>
                </div>';
}
// Maintenance Page - Statusbox
function format_notifications($source_array) {
	$format_array_true = array();
	$format_array_false = array();
	$text_reference = array('WEBGUI', 'TELEGRAM', 'MAIL', 'PUSHSAFER', 'PUSHOVER', 'NTFY');
	$text_format = array('WebGUI', 'Telegram', 'Mail', 'Pushsafer', 'Pushover', 'NTFY');
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

<?php
session_start();
require 'timezone.php';
require 'db.php';
require 'journal.php';

OpenDB();

require 'language_switch.php';
require '../templates/language/' . $pia_lang_selected . '.php';

// Get Version from version.conf
$conf_file = '../../../config/version.conf';
$conf_data = parse_ini_file($conf_file);
//---------------------------------------------------------------------------------------------------------------------------------------
// Get Pi.Alert Release
$curl_handle = curl_init();
curl_setopt($curl_handle, CURLOPT_URL, 'https://api.github.com/repos/leiweibau/Pi.Alert/commits?path=tar%2Fpialert_latest.tar&page=1&per_page=1');
curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl_handle, CURLOPT_FRESH_CONNECT, TRUE);
curl_setopt($curl_handle, CURLOPT_USERAGENT, 'PHP');
$query = curl_exec($curl_handle);
curl_close($curl_handle);
// Generate JSON (Pi.Alert)
$pialert_update = json_decode($query, true);
// Check if json seems correct
if ($pialert_update['0']['commit']['author']['date'] != "") {$valid_update_notes = True;} else {$valid_update_notes = False;}
// Get Pi.Alert Version fro Github timestamp
if ($valid_update_notes) {
	$utc_ts = strtotime($pialert_update['0']['commit']['author']['date']);
	$offset = date("Z");
	$local_ts = $utc_ts + $offset;
	$local_pialert_time = date("d.m.Y, H:i", $utc_ts);
} else {
	$local_pialert_time = date("d.m.Y, H:i");
}
// Pi.Alert Version from config file
$pialert_cur_version = $conf_data['VERSION_DATE'];
// Get latest Release notes from Github
$updatenotes_array = explode("\n", $pialert_update['0']['commit']['message']);
$updatenotes_array = array_filter($updatenotes_array);
// DEBUG
// $pialert_cur_version = '2023-05-28';
$pialert_new_version = substr($updatenotes_array[0], -10);
//---------------------------------------------------------------------------------------------------------------------------------------
// Get MaxMind DB Release
if ($_SESSION['Scan_WebServices'] == True) {
	$curl_handle = curl_init();
	curl_setopt($curl_handle, CURLOPT_URL, 'https://api.github.com/repos/P3TERX/GeoLite.mmdb/releases/latest');
	curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
	curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_handle, CURLOPT_FRESH_CONNECT, TRUE);
	curl_setopt($curl_handle, CURLOPT_USERAGENT, 'PHP');
	$query = curl_exec($curl_handle);
	curl_close($curl_handle);
	// Generate JSON (GeoIP)
	$geolite_update = json_decode($query, true);
	// GeoIP Version from file system
	$geoliteDB_file = '../../../db/GeoLite2-Country.mmdb';
	if (file_exists($geoliteDB_file)) {
		$geolite_cur_version = date("Y.m.d", filemtime($geoliteDB_file));
		$geolite_cur_filesize = round((filesize($geoliteDB_file)/1048576),2);
	} else { $geolite_cur_version = "###";}
	// Get GeoIP Version from Tag Name
	$geolite_new_version = $geolite_update['name'];
	for ($x=0;$x<=2;$x++) {
		if ($geolite_update['assets'][$x]['name'] == "GeoLite2-Country.mmdb") {$geolite_update_filesize = round(($geolite_update['assets'][$x]['size']/1024/1024), 2);}
	}
	if ($geolite_update_filesize == "") {
		$curl_handle_fb = curl_init();
		curl_setopt($curl_handle_fb, CURLOPT_URL, 'https://api.github.com/repos/P3TERX/GeoLite.mmdb/contents/GeoLite2-Country.mmdb?ref=download');
		curl_setopt($curl_handle_fb, CURLOPT_CONNECTTIMEOUT, 2);
		curl_setopt($curl_handle_fb, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl_handle_fb, CURLOPT_FRESH_CONNECT, TRUE);
		curl_setopt($curl_handle_fb, CURLOPT_USERAGENT, 'PHP');
		$query_fb = curl_exec($curl_handle_fb);
		curl_close($curl_handle_fb);
		// Generate JSON (GeoIP)
		$geolite_update_fb = json_decode($query_fb, true);
		if ($geolite_update_fb['name'] == "GeoLite2-Country.mmdb" && $geolite_update_fb['size'] != "" && $geolite_update_fb['size'] > 0) {$geolite_update_filesize = round(($geolite_update_fb['size']/1024/1024), 2);}
	}
	// DEBUG
	// $geolite_cur_version = '2023-05-28';
	// prepare dates for comparison
	$temp_geolite_cur_version = str_replace(".", "-", $geolite_cur_version);
	$temp_geolite_new_version = str_replace(".", "-", $geolite_new_version);
}
//---------------------------------------------------------------------------------------------------------------------------------------
// Get Pi.Alert-Satellite Release
if ($_SESSION['SATELLITES_ACTIVE'] == True) {
	$curl_handle = curl_init();
	curl_setopt($curl_handle, CURLOPT_URL, 'https://api.github.com/repos/leiweibau/Pi.Alert-Satellite/commits?path=tar%2Fpialert_satellite_latest.tar&page=1&per_page=1');
	curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
	curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_handle, CURLOPT_FRESH_CONNECT, TRUE);
	curl_setopt($curl_handle, CURLOPT_USERAGENT, 'PHP');
	$query = curl_exec($curl_handle);
	curl_close($curl_handle);
	// Generate JSON (Pi.Alert)
	$satellite_update = json_decode($query, true);
	// Check if json seems correct
	if ($satellite_update['0']['commit']['author']['date'] != "") {$valid_sat_update_notes = True;} else {$valid_sat_update_notes = False;}

	// Get Pi.Alert-Satellite Version from Github timestamp
	if ($valid_sat_update_notes) {
		$utc_ts = strtotime($satellite_update['0']['commit']['author']['date']);
		$offset = date("Z");
		$local_ts = $utc_ts + $offset;
		$local_sat_time = date("d.m.Y, H:i", $utc_ts);
	} else {
		$local_sat_time = date("d.m.Y, H:i");
	}

	// Get all Versions from DB
	$satellite_cur_versions = array();
	$sql = 'SELECT sat_remote_version FROM Satellites';
	$result = $db->query($sql);
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		array_push($satellite_cur_versions, $row['sat_remote_version']);
	}
	// DEBUG
	// $satellite_cur_versions = array("2024-07-03","2024-07-02","2024-06-03");
	//$satellite_cur_versions[] = "2024-07-02";

	// Get latest Release notes from Github
	$updatenotes_sat_array = explode("\n", $satellite_update['0']['commit']['message']);
	$updatenotes_sat_array = array_filter($updatenotes_sat_array);

	$satellite_new_version = substr($updatenotes_sat_array[0], -10);
	$to_remove = array($satellite_new_version);
	$different_sat_versions = array_diff($satellite_cur_versions, $to_remove);
}
//---------------------------------------------------------------------------------------------------------------------------------------
// Print Update Box for GeoIP
if ($_SESSION['Scan_WebServices'] == True) {
	if (($temp_geolite_new_version > $temp_geolite_cur_version) && ($geolite_cur_version != "###")) {
	// DB present and github is newer as local
		echo '<div class="box">
	    		<div class="box-body">
					<h4 class="text-aqua" style="text-align: center;">' . $pia_lang['GeoLiteDB_Title'] . '</h4>
					<p class="updatechk_font_a">
					' . $pia_lang['GeoLiteDB_cur'] . ': 	<span class="text-green">	' . $geolite_cur_version . '</span> <span style="font-weight: normal;">('.$geolite_cur_filesize.' MB)</span><br>
					' . $pia_lang['GeoLiteDB_new'] . ': 	<span class="text-red">		' . $geolite_new_version . '</span> <span style="font-weight: normal;">('.$geolite_update_filesize.' MB)</span>
					</p>

	          <div class="row" style="margin-top: 30px;">
	            <style>
	                .downloader {
	                    border: 6px solid #f3f3f3; /* Light gray */
	                    border-top: 6px solid #3498db; /* Blue */
	                    border-radius: 50%;
	                    width: 32px;
	                    height: 32px;
	                    animation: spin 2s linear infinite;
	                    margin-left: 50px;
	                }

	                @keyframes spin {
	                    0% { transform: rotate(0deg); }
	                    100% { transform: rotate(360deg); }
	                }
	            </style>
	            <div class="col-sm-12" style="">
	              <div style="height: 60px;">
	                <div class="downloader" id="downloader" style="display: none;"></div>';
	    if ($geolite_update_filesize > 0) {
	        echo '<button class="btn btn-default" id="updateDB-button">' . $pia_lang['GeoLiteDB_button_upd'] . '</button>';
	    } else {
	    	echo '<span class="text-danger">Download not possible (Filsize ZERO)</span>';
	    }
	    echo ' </div>
	            </div>
	          </div>

				</div>
			  </div>';
		pialert_logging('a_060', $_SERVER['REMOTE_ADDR'], 'LogStr_0063', '', '');
	} elseif ($geolite_cur_version == "###") {
	// No DB present
		echo '<div class="box">
	    		<div class="box-body">
					<h4 class="text-aqua" style="text-align: center;">' . $pia_lang['GeoLiteDB_Title'] . '</h4>
					<p class="text-yellow updatechk_font_a">' . $pia_lang['GeoLiteDB_absent'] . '</p>
					<p>' . $pia_lang['GeoLiteDB_Installnotes'] . '</p>
				</div>
			  </div>';
		pialert_logging('a_060', $_SERVER['REMOTE_ADDR'], 'LogStr_0065', '', '');
	} else {
	// DB present an newer as github version
		echo '<div class="box">
	    		<div class="box-body">
					<h4 class="text-aqua" style="text-align: center;">' . $pia_lang['GeoLiteDB_Title'] . '</h4>
					<p class="text-green updatechk_font_a">' . $pia_lang['Updatecheck_U2D'] . '</p>
				</div>
			  </div>';
		pialert_logging('a_060', $_SERVER['REMOTE_ADDR'], 'LogStr_0064', '', '');
	}
}

// Print Update Box for Pi.Alert
if ($pialert_cur_version != $pialert_new_version && $valid_update_notes) {
// github version is not equal to local version (only a local dev version could be newer than github version)
	echo '<div class="box">
    		<div class="box-body">
				<h4 class="text-aqua" style="text-align: center;">' . $pia_lang['MT_Github_package_a'] . ' ' . $local_pialert_time . ' ' . $pia_lang['MT_Github_package_b'] . '</h4>
				<p class="updatechk_font_a">
				' . $pia_lang['Updatecheck_cur'] . ': 	<span class="text-green">	' . $pialert_cur_version . '</span><br>
				' . $pia_lang['Updatecheck_new'] . ': 	<span class="text-red">		' . $pialert_new_version . '</span>
				</p>
			</div>
		  </div>';
} elseif (!$valid_update_notes) {
// github not reachable or no json response
	echo '<div class="box">
    		<div class="box-body">
				<h4 class="text-aqua" style="text-align: center;"><span class="text-red">' . $pia_lang['Gen_error'] . '</span> ' . $local_pialert_time . ' ' . $pia_lang['MT_Github_package_b'] . '</h4>
				<p class="updatechk_font_a">
				' . $pia_lang['Updatecheck_cur'] . ': 	<span class="text-green">	' . $pialert_cur_version . '</span><br>
				' . $pia_lang['Updatecheck_new'] . ': 	<span class="text-red">		' . $pia_lang['Gen_error'] . '</span>
				</p>
			</div>
		  </div>';
	pialert_logging('a_060', $_SERVER['REMOTE_ADDR'], 'LogStr_0066', '', '');
}

// Print Update Box for Pi.Alert
if ($pialert_cur_version != $pialert_new_version && $valid_update_notes) {
	echo '<div class="box">
    <div class="box-body">
		<h4 class="text-aqua" style="text-align: center;">' . $pia_lang['Updatecheck_RN'] . '</h4><div>';
// Transform release notes
	foreach ($updatenotes_array as $row) {
		$row = str_replace("BREAKING CHANGES", "<span class=\"text-red\">BREAKING CHANGES</span>", $row);
		if (stristr($row, "Update Notes: ")) {
			echo '<span class="updatechk_font_a" style="text-decoration: underline;">' . $row . '</span><br>';
		} elseif (stristr($row, "New:")) {
			echo '<br><span class="updatechk_font_a">' . $row . '</span><br>';
		} elseif (stristr($row, "Fixed:")) {
			echo '<br><span class="updatechk_font_a">' . $row . '</span><br>';
		} elseif (stristr($row, "Updated:")) {
			echo '<br><span class="updatechk_font_a">' . $row . '</span><br>';
		} elseif (stristr($row, "Changed:")) {
			echo '<br><span class="updatechk_font_a">' . $row . '</span><br>';
		} elseif (stristr($row, "Note:")) {
			echo '<br><span class="updatechk_font_a">' . $row . '</span><br>';
		} elseif (stristr($row, "Removed:")) {
			echo '<br><span class="updatechk_font_a">' . $row . '</span><br>';
		} else {
			echo '<div style="display: list-item; margin-left : 2em;">' . str_replace('* ', '', $row) . '</div>';
		}
	}
	if (!file_exists("/opt/pialert")) {
		$updatecommand = 'bash -c &quot;$(wget -qLO - https://github.com/leiweibau/Pi.Alert/raw/main/install/pialert_update.sh)&quot;';
		$updateenv = '';
	} else {
		$updatecommand = 'bash -c &quot;$(wget -qLO - https://github.com/tteck/Proxmox/raw/main/ct/pialert.sh)&quot;';
		$updateenv = ' (LXC Container Env.)';
	}

	echo '<br><br>
			<lable for="bashupdatecommand" class="text-red"><i>Update command'.$updateenv.':</i></lable>
			<input id="bashupdatecommand" readonly value="'.$updatecommand.'" style="width:100%; overflow-x: scroll; border: none; background: transparent; margin: 0px; padding: 0px;">
		  <br><br>
		</div>
    <div class="box-footer">
        <a class="btn btn-default pull-left" href="https://leiweibau.net/archive/pialert/" target="_blank">Version History (leiweibau.net)</a>
    </div>
</div>';
	pialert_logging('a_060', $_SERVER['REMOTE_ADDR'], 'LogStr_0061', '', '');

}

if ($pialert_cur_version == $pialert_new_version) {
// github version is equal to local version
	echo '<div class="box">
    		<div class="box-body">
				<h4 class="text-aqua" style="text-align: center;">' . $pia_lang['Updatecheck_RN2'] . '</h4>
				<p class="text-green updatechk_font_a">' . $pia_lang['Updatecheck_U2D'] . '</p>
			</div>
		  </div>';
	pialert_logging('a_060', $_SERVER['REMOTE_ADDR'], 'LogStr_0062', '', '');
}
if ($_SESSION['SATELLITES_ACTIVE'] == True) {
	// Print Update Box for Pi.Alert Satellites
	if (sizeof($different_sat_versions) > 0 && $valid_sat_update_notes) {
	// github version is not equal to local version (only a local dev version could be newer than github version)
		echo '<div class="box">
	    		<div class="box-body">
					<h4 class="text-aqua" style="text-align: center;">' . $pia_lang['MT_Github_package_a'] . ' ' . $local_sat_time . ' ' . $pia_lang['MT_Github_package_b'] . '</h4>
					<p class="updatechk_font_a">
					' . $pia_lang['Updatecheck_Sat_cur'] . ': 	<span class="text-green">	' . implode(", ", $satellite_cur_versions) . '</span><br>
					' . $pia_lang['Updatecheck_Sat_new'] . ': 	<span class="text-red">		' . $satellite_new_version . '</span>
					</p>
				</div>
			  </div>';
	} elseif (!$valid_sat_update_notes) {
	// github not reachable or no json response
		echo '<div class="box">
	    		<div class="box-body">
					<h4 class="text-aqua" style="text-align: center;"><span class="text-red">' . $pia_lang['Gen_error'] . '</span> ' . $local_sat_time . ' ' . $pia_lang['MT_Github_package_b'] . '</h4>
					<p class="updatechk_font_a">
					' . $pia_lang['Updatecheck_Sat_cur'] . ': 	<span class="text-green">	' . implode(", ", $satellite_cur_versions) . '</span><br>
					' . $pia_lang['Updatecheck_Sat_new'] . ': 	<span class="text-red">		' . $pia_lang['Gen_error'] . '</span>
					</p>
				</div>
			  </div>';
		pialert_logging('a_060', $_SERVER['REMOTE_ADDR'], 'LogStr_0066', '', '');
	}

	// Print Update Box for Pi.Alert Satellites
	if (sizeof($different_sat_versions) > 0 && $valid_sat_update_notes) {
		echo '<div class="box">
	    <div class="box-body">
			<h4 class="text-aqua" style="text-align: center;">' . $pia_lang['Updatecheck_Sat_RN'] . '</h4><div>';
	// Transform release notes
		foreach ($updatenotes_sat_array as $row) {
			$row = str_replace("BREAKING CHANGES", "<span class=\"text-red\">BREAKING CHANGES</span>", $row);
			if (stristr($row, "Update Notes: ")) {
				echo '<span class="updatechk_font_a" style="text-decoration: underline;">' . $row . '</span><br>';
			} elseif (stristr($row, "New:")) {
				echo '<br><span class="updatechk_font_a">' . $row . '</span><br>';
			} elseif (stristr($row, "Fixed:")) {
				echo '<br><span class="updatechk_font_a">' . $row . '</span><br>';
			} elseif (stristr($row, "Updated:")) {
				echo '<br><span class="updatechk_font_a">' . $row . '</span><br>';
			} elseif (stristr($row, "Changed:")) {
				echo '<br><span class="updatechk_font_a">' . $row . '</span><br>';
			} elseif (stristr($row, "Note:")) {
				echo '<br><span class="updatechk_font_a">' . $row . '</span><br>';
			} elseif (stristr($row, "Removed:")) {
				echo '<br><span class="updatechk_font_a">' . $row . '</span><br>';
			} else {
				echo '<div style="display: list-item; margin-left : 2em;">' . str_replace('* ', '', $row) . '</div>';
			}
		}
		$updatecommand = 'bash -c &quot;$(wget -qLO - https://github.com/leiweibau/Pi.Alert-Satellite/raw/main/install/pialert_satellite_update.sh)&quot;';
		
		echo '<br><br>
				<lable for="bashupdatecommand" class="text-red"><i>Update command:</i></lable>
				<input id="bashupdatecommand" readonly value="'.$updatecommand.'" style="width:100%; overflow-x: scroll; border: none; background: transparent; margin: 0px; padding: 0px;">
			  <br><br>
			</div>
	    <div class="box-footer">
	        <a class="btn btn-default pull-left" href="https://leiweibau.net/archive/pialert/" target="_blank">Version History (leiweibau.net)</a>
	    </div>
	</div>';
		pialert_logging('a_060', $_SERVER['REMOTE_ADDR'], 'LogStr_0068', '', '');

	}

	if (sizeof($different_sat_versions) == 0) {
	// github version is equal to local version
		echo '<div class="box">
	    		<div class="box-body">
					<h4 class="text-aqua" style="text-align: center;">' . $pia_lang['Updatecheck_Sat_RN2'] . '</h4>
					<p class="text-green updatechk_font_a">' . $pia_lang['Updatecheck_U2D'] . '</p>
				</div>
			  </div>';
		pialert_logging('a_060', $_SERVER['REMOTE_ADDR'], 'LogStr_0069', '', '');
	}
}
echo '</div>';
echo '</div>';

echo '
<script>
$("#updateDB-button").on(\'click\', function() {
    var loader = $("#downloader");
    var downloadButton = $(this);
    // Hide the download button
    downloadButton.hide();
    // Display the loading animation
    loader.show();
    // Send an AJAX request to initiate the file download
    $.ajax({
        url: \'./php/server/services.php?action=updateGeoDB\',
        method: \'GET\',
        success: function(response) {
            console.log(\'Download complete!\');
        },
        // error: function() {
        //     console.error(\'Download error!\');
        // },
        complete: function() {
            // Show the download button again
            setTimeout(function () {
              location.reload(true);
            }, 1000);
        }
    });
});
</script>';

?>



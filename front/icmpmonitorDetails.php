<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . "/php/server/session.php";
pialert_start_session();

if ($_SESSION["login"] != 1) {
	header('Location: ./index.php');
	exit;
}

# Validate host
$request_host = isset($_GET['hostip']) && is_scalar($_GET['hostip']) ? (string) $_GET['hostip'] : '';
if (filter_var($request_host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || filter_var($request_host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
	$hostip = $request_host;
} else {
	header('Location: ./index.php');
	exit;
}

require 'php/server/db.php';
require 'php/templates/header.php';
require 'php/server/graph.php';
require 'php/server/journal.php';

# Init DB Connection
$db_file = '../db/pialert.db';
$db = new SQLite3($db_file);
$db->exec('PRAGMA journal_mode = wal;');

// -----------------------------------------------------------------------------------
function get_hostip_details($hostip) {
	global $db;

	$mon_res = db_execute_prepared($db, 'SELECT * FROM ICMP_Mon WHERE icmp_ip = :ip', array(':ip' => (string) $hostip));
	$row = $mon_res ? $mon_res->fetchArray() : false;
	return $row;
}

// -----------------------------------------------------------------------------------
function get_icmphost_events_table($icmp_ip, $icmpfilter) {
	global $db;
	
	$icmp_res = db_execute_prepared($db, 'SELECT rowid, * FROM ICMP_Mon WHERE icmp_ip = :ip', array(':ip' => (string) $icmp_ip));
	while ($rowa = $icmp_res->fetchArray(SQLITE3_ASSOC)) {
		$icmp_hostname = $rowa['icmp_hostname'];
	}

	$icmpeve_res = db_execute_prepared($db, 'SELECT * FROM ICMP_Mon_Connections WHERE icmpeve_ip = :ip ORDER BY rowid DESC LIMIT 2000', array(':ip' => (string) $icmp_ip));
	while ($row = $icmpeve_res->fetchArray()) {
		if ($icmp_hostname != "" && strlen($icmp_hostname) > 0) {$icmpeve_ip = $icmp_hostname;} else { $icmpeve_ip = $row['icmpeve_ip'];}
		echo '<tr>
              <td>' . h($icmpeve_ip) . '</td>
              <td>' . h($row['icmpeve_DateTime']) . '</td>
              <td>' . h($row['icmpeve_EventType']) . '</td>
          </tr>';
	}
}

// -----------------------------------------------------------------------------------
$icmpmonitorDetails = get_hostip_details($hostip);

if ($icmpmonitorDetails['icmp_PresentLastScan'] == 1) {
	$headstatus = 'Online';
	$headstatus_icon = 'fa fa-check text-green';
	$headstatus_color = 'text-green';} else {
	$headstatus = 'Offline';
	$headstatus_icon = 'fa fa-close text-gray';
	$headstatus_color = '';}

// Get Online Graph Arrays
// -----------------------------------------------------------------------------------
$graph_arrays = array();
$graph_arrays = prepare_graph_arrays_ICMPHost($hostip);
$Pia_Graph_ICMPHost_Time = $graph_arrays[0];
$Pia_Graph_ICMPHost_Up = $graph_arrays[1];
$Pia_Graph_ICMPHost_Down = $graph_arrays[2];

// get some stats
// -----------------------------------------------------------------------------------
function get_host_statistic($hostip) {
	global $db;

	$params = array(':ip' => (string) $hostip);
	$scalarQueries = array(
		'avg_rtt_all' => 'SELECT AVG(icmpeve_avgrtt) FROM ICMP_Mon_Events WHERE icmpeve_avgrtt != 99999 AND icmpeve_avgrtt != "" AND icmpeve_ip = :ip',
		'rtt_max_all' => 'SELECT MAX(icmpeve_avgrtt) FROM ICMP_Mon_Events WHERE icmpeve_avgrtt != 99999 AND icmpeve_avgrtt != "" AND icmpeve_ip = :ip',
		'rtt_min_all' => 'SELECT MIN(icmpeve_avgrtt) FROM ICMP_Mon_Events WHERE icmpeve_avgrtt != 99999 AND icmpeve_avgrtt != "" AND icmpeve_ip = :ip',
		'offline_all' => 'SELECT COUNT(*) FROM ICMP_Mon_Events WHERE icmpeve_Present = 0 AND icmpeve_ip = :ip',
		'online_all' => 'SELECT COUNT(*) FROM ICMP_Mon_Events WHERE icmpeve_Present = 1 AND icmpeve_ip = :ip',
	);
	$values = array();
	foreach ($scalarQueries as $key => $sql) {
		$result = db_execute_prepared($db, $sql, $params);
		$row = $result ? $result->fetchArray(SQLITE3_NUM) : array(0);
		$values[$key] = $row[0];
	}

	$statistic = array();
	$statistic['avg_rtt_all'] = round($values['avg_rtt_all'], 3) . ' ms';
	$statistic['rtt_max_all'] = '<i class="bi bi-speedometer2 flip-horizontal text-red"></i> ' . round($values['rtt_max_all'], 3) . ' ms';
	$statistic['rtt_min_all'] = '<i class="bi bi-speedometer2 text-green"></i> ' . round($values['rtt_min_all'], 3) . ' ms';
	$statistic['offline_all'] = (int) $values['offline_all'];
	$statistic['online_all'] = (int) $values['online_all'];
	$total = $statistic['online_all'] + $statistic['offline_all'];
	$onlinePercent = $statistic['online_all'] > 0 ? round(($statistic['online_all'] * 100 / $total), 2) : 0;
	$statistic['online_percent_all'] = $onlinePercent . ' %';
	$statistic['offline_percent_all'] = (100 - $onlinePercent) . ' %';

	$windows = array('24h' => 24 - (date('Z') / 3600), '1w' => 168 - (date('Z') / 3600));
	foreach ($windows as $label => $hours) {
		$result = db_execute_prepared($db, 'SELECT * FROM ICMP_Mon_Events
			WHERE icmpeve_ip = :ip AND datetime(icmpeve_DateTime) >= datetime("now", :offset)
			ORDER BY datetime(icmpeve_DateTime) DESC', array(':ip' => (string) $hostip, ':offset' => '-' . $hours . ' hours'));
		$offline = 0;
		$online = 0;
		$minimum = 99999;
		$maximum = 0;
		$average = 0;
		while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
			if ($row['icmpeve_avgrtt'] != '' && $row['icmpeve_avgrtt'] != '99999') {
				$online++;
				$maximum = max($maximum, $row['icmpeve_avgrtt']);
				$minimum = min($minimum, $row['icmpeve_avgrtt']);
				$average += $row['icmpeve_avgrtt'];
			} else {
				$offline++;
			}
		}
		$statistic['rtt_min_' . $label] = $minimum == 99999 ? 'n.a.' : '<i class="bi bi-speedometer2 text-green"></i> ' . round($minimum, 3) . ' ms';
		$statistic['rtt_max_' . $label] = $maximum == 0 ? 'n.a.' : '<i class="bi bi-speedometer2 flip-horizontal text-red"></i> ' . round($maximum, 3) . ' ms';
		$statistic['rtt_avg_' . $label] = $average > 0 ? round(($average / $online), 3) . ' ms' : 'n.a.';
		$statistic['online_' . $label] = $online;
		$statistic['offline_' . $label] = $offline;
		$total = $online + $offline;
		$onlinePercent = $online > 0 ? round(($online * 100 / $total), 2) : 0;
		$statistic['online_percent_' . $label] = $onlinePercent . ' %';
		$statistic['offline_percent_' . $label] = round(100 - $onlinePercent, 2) . ' %';
	}

	return $statistic;
}

?>

<!-- Page ------------------------------------------------------------------ -->
  <div class="content-wrapper">

    <section class="content-header">
      <?php require 'php/templates/notification.php';?>

      <h1 id="pageTitle">
        <?php echo h($icmpmonitorDetails['icmp_hostname']) . ' (' . h($hostip) . ')';?>
      </h1>
    </section>

    <section class="content">

     <div id="sticky-back-button" class="navbar navbar-default navbar-fixed-bottom" style="background-color: #000;">
      <a class="btn btn-lg btn-default btn-block" href="./icmpmonitor.php" role="button"><?=$pia_lang['Device_Table_nav_prev'];?></a>
    </div>

<!-- top small box --------------------------------------------------------- -->
      <div class="row">

        <div class="col-lg-3 col-sm-6 col-xs-6">
          <a href="#">
            <div class="small-box bg-aqua">
              <div class="inner" style="padding: 0px 10px;"> <h3 id="deviceStatus" class="<?=$headstatus_color?>" style="margin-left: 0em"><?=$headstatus?></h3>
                <p class="infobox_label"><?=$pia_lang['DevDetail_Shortcut_CurrentStatus'];?></p>
              </div>
              <div class="icon"> <i id="deviceStatusIcon" class="<?=$headstatus_icon?>"></i></div>
            </div>
          </a>
        </div>

        <div class="col-lg-3 col-sm-6 col-xs-6">
          <a href="#">
            <div class="small-box bg-yellow">
              <div class="inner" style="padding: 0px 10px;"> <h3 id="eventspresence"> -- </h3>
                <p class="infobox_label"><?=$pia_lang['DevDetail_Shortcut_curPresence'];?></p>
              </div>
              <div class="icon"> <i class="bi bi-check2-square text-green-40"></i> </div>
            </div>
          </a>
        </div>

        <div class="col-lg-3 col-sm-6 col-xs-6">
          <a href="#">
            <div  class="small-box bg-red">
              <div class="inner" style="padding: 0px 10px;"> <h3 id="eventsdown"> -- </h3>
                <p class="infobox_label"><?=$pia_lang['DevDetail_Shortcut_DownAlerts'];?></p>
              </div>
              <div class="icon"> <i class="mdi mdi-lan-disconnect text-red-40"></i> </div>
            </div>
          </a>
        </div>

      </div>
      <!-- /.row -->

<!-- tab control------------------------------------------------------------ -->
      <div class="row">
        <div class="col-lg-12 col-sm-12 col-xs-12">
          <div id="navDevice" class="nav-tabs-custom">
            <ul class="nav nav-tabs">
              <li class=""> <a id="tabDetails" href="#panDetails" data-toggle="tab"> <?=$pia_lang['DevDetail_Tab_Details'];?></a></li>
              <li class=""> <a id="tabNmap" href="#panNmap" data-toggle="tab"> <?=$pia_lang['DevDetail_Tab_Nmap'];?>     </a></li>
              <li class=""> <a id="tabEvents" href="#panEvents" data-toggle="tab"> <?=$pia_lang['DevDetail_Tab_Events'];?></a></li>
              <li class=""> <a id="tabGraph" href="#panGraph" data-toggle="tab"> <?=$pia_lang['WEBS_Tab_Graph'];?></a></li>
            </ul>

            <div class="tab-content" style="min-height: 480px;">

<!-- tab page 1 ------------------------------------------------------------ -->

              <div class="tab-pane" id="panDetails">

                <div class="row">
    <!-- column 1 -->
                  <div class="col-sm-6 col-xs-12">
                    <h4 class="bottom-border-aqua"><?=$pia_lang['DevDetail_MainInfo_Title'];?></h4>
                    <div class="box-body form-horizontal">

                      <!-- URL -->
                      <div class="form-group">
                        <label class="col-sm-3 control-label"><?=$pia_lang['ICMPMonitor_label_IP'];?></label>
                        <div class="col-sm-9">
                          <input class="form-control" id="txtIP" type="text" readonly value="<?=h($icmpmonitorDetails['icmp_ip'])?>">
                        </div>
                      </div>

                      <!-- Tags -->
                      <div class="form-group">
                        <label class="col-sm-3 control-label"><?=$pia_lang['ICMPMonitor_label_Hostname'];?></label>
                        <div class="col-sm-9">
                          <input class="form-control" id="txtHostname" type="text" value="<?=h($icmpmonitorDetails['icmp_hostname'])?>">
                        </div>
                      </div>

                      <!-- Owner -->
                      <div class="form-group">
                        <label class="col-sm-3 control-label"><?=$pia_lang['DevDetail_MainInfo_Owner'];?></label>
                        <div class="col-sm-7">
                          <div class="input-group">
                            <input class="form-control" id="txtOwner" type="text" value="<?=h($icmpmonitorDetails['icmp_owner'])?>">
                            <div class="input-group-btn">
                              <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                <span class="fa fa-caret-down"></span></button>
                              <ul id="dropdownOwner" class="dropdown-menu dropdown-menu-right">
                              </ul>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Type -->
                      <div class="form-group">
                        <label class="col-sm-3 control-label"><?=$pia_lang['DevDetail_MainInfo_Type'];?></label>
                        <div class="col-sm-7">
                          <div class="input-group">
                            <input class="form-control" id="txtDeviceType" type="text" value="<?=h($icmpmonitorDetails['icmp_type'])?>">
                            <div class="input-group-btn">
                              <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown" aria-expanded="false" >
                                <span class="fa fa-caret-down"></span></button>
                              <ul id="dropdownDeviceType" class="dropdown-menu dropdown-menu-right">
                                <li><a href="javascript:void(0)" onclick="setTextValue('txtDeviceType','Smartphone')"> Smartphone </a></li>
                                <li><a href="javascript:void(0)" onclick="setTextValue('txtDeviceType','Laptop')">     Laptop     </a></li>
                                <li><a href="javascript:void(0)" onclick="setTextValue('txtDeviceType','PC')">         PC         </a></li>
                                <li><a href="javascript:void(0)" onclick="setTextValue('txtDeviceType','Others')">     Others     </a></li>
                              </ul>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Vendor -->
                      <div class="form-group">
                        <label class="col-sm-3 control-label"><?=$pia_lang['DevDetail_MainInfo_Vendor'];?></label>
                        <div class="col-sm-7"><input class="form-control" id="txtVendor" type="text" value="<?=h($icmpmonitorDetails['icmp_vendor'])?>"></div>
                      </div>

                      <!-- Model -->
                      <div class="form-group">
                        <label class="col-sm-3 control-label"><?=$pia_lang['DevDetail_MainInfo_Model'];?></label>
                        <div class="col-sm-7"><input class="form-control" id="txtModel" type="text" value="<?=h($icmpmonitorDetails['icmp_model'])?>"></div>
                      </div>

                      <!-- Serialnumber -->
                      <div class="form-group">
                        <label class="col-sm-3 control-label"><?=$pia_lang['DevDetail_MainInfo_Serialnumber'];?></label>
                        <div class="col-sm-7"><input class="form-control" id="txtSerialnumber" type="text" value="<?=h($icmpmonitorDetails['icmp_serial'])?>"></div>
                      </div>

                      <!-- Group -->
                      <div class="form-group">
                        <label class="col-sm-3 control-label"><?=$pia_lang['DevDetail_MainInfo_Group'];?></label>
                        <div class="col-sm-7">
                          <div class="input-group">
                            <input class="form-control" id="txtGroup" type="text" value="<?=h($icmpmonitorDetails['icmp_group'])?>">
                            <div class="input-group-btn">
                              <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                <span class="fa fa-caret-down"></span></button>
                              <ul id="dropdownGroup" class="dropdown-menu dropdown-menu-right">
                                <li><a href="javascript:void(0)" onclick="setTextValue('txtGroup','Always On')"> Always On </a></li>
                                <li><a href="javascript:void(0)" onclick="setTextValue('txtGroup','Friends')">   Friends   </a></li>
                                <li><a href="javascript:void(0)" onclick="setTextValue('txtGroup','Personal')">  Personal  </a></li>
                                <li class="divider"></li>
                                <li><a href="javascript:void(0)" onclick="setTextValue('txtGroup','Others')">    Others    </a></li>
                              </ul>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Location -->
                      <div class="form-group">
                        <label class="col-sm-3 control-label"><?=$pia_lang['DevDetail_MainInfo_Location'];?></label>
                        <div class="col-sm-7">
                          <div class="input-group">
                            <input class="form-control" id="txtLocation" type="text" value="<?=h($icmpmonitorDetails['icmp_location'])?>">
                            <div class="input-group-btn">
                              <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                <span class="fa fa-caret-down"></span></button>
                              <ul id="dropdownLocation" class="dropdown-menu dropdown-menu-right">
                                <li><a href="javascript:void(0)" onclick="setTextValue('txtLocation','Bathroom')">    Bathroom</a></li>
                                <li><a href="javascript:void(0)" onclick="setTextValue('txtLocation','Bedroom')">     Bedroom</a></li>
                                <li><a href="javascript:void(0)" onclick="setTextValue('txtLocation','Hall')">        Hall</a></li>
                                <li><a href="javascript:void(0)" onclick="setTextValue('txtLocation','Kitchen')">     Kitchen</a></li>
                                <li><a href="javascript:void(0)" onclick="setTextValue('txtLocation','Living room')"> Living room</a></li>
                                <li class="divider"></li>
                                <li><a href="javascript:void(0)" onclick="setTextValue('txtLocation','Others')">      Others</a></li>
                              </ul>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Notes -->
                      <div class="form-group">
                        <label class="col-sm-3 control-label"><?=$pia_lang['WEBS_label_Notes'];?></label>
                        <div class="col-sm-9">
                          <input class="form-control" id="txtNotes" type="text" value="<?=h($icmpmonitorDetails['icmp_Notes'])?>">
                        </div>
                      </div>

                    </div>
                  </div>

    <!-- column 2 -->
                  <div class="col-sm-6 col-xs-12" style="margin-bottom: 50px;">
                    <h4 class="bottom-border-aqua"><?=$pia_lang['DevDetail_EveandAl_Title'];?></h4>
                    <div class="box-body form-horizontal">

                      <!-- Last Scan -->
                      <div class="form-group">
                        <label class="col-sm-4 control-label"><?=$pia_lang['WEBS_label_ScanTime'];?></label>
                        <div class="col-sm-8">
                          <input class="form-control" id="txtLastScan" type="text" readonly value="<?=h($icmpmonitorDetails['icmp_LastScan'])?>">
                        </div>
                      </div>

                      <!-- Last HTTP Status -->
                      <div class="form-group">
                        <label class="col-sm-4 control-label"><?=$pia_lang['ICMPMonitor_label_RTT'];?></label>
                        <div class="col-sm-8">
                          <input class="form-control" id="txtavgrtt" type="text" readonly value="<?=h($icmpmonitorDetails['icmp_avgrtt'])?>">
                        </div>
                      </div>

                      <!-- Scan Validation -->
                      <div class="form-group">
                        <label class="col-sm-4 control-label"><?=$pia_lang['DevDetail_EveandAl_ScanValid'];?></label>
                        <div class="col-sm-8"><input class="form-control" id="txtScanValidation" type="text" value="<?=h($icmpmonitorDetails['icmp_Scan_Validation'])?>"></div>
                      </div>

                      <div class="form-group">
                        <label class="col-xs-4 control-label"><?=$pia_lang['Device_TableHead_Favorite'];?></label>
                        <div class="col-xs-4" style="padding-top:6px;">
                          <input class="checkbox orange" id="chkFavorit" <?php if ($icmpmonitorDetails['icmp_Favorite'] == 1) {echo 'checked';}?> type="checkbox">
                        </div>
                      </div>

                      <div class="form-group">
                        <label class="col-xs-4 control-label"><?=$pia_lang['DevDetail_MainInfo_MQTTDevice']?></label>
                        <div class="col-xs-4" style="padding-top:6px;">
                        	<input class="checkbox purple hidden" id="chkMQTTDevice" <?php if ($icmpmonitorDetails['icmp_MQTTDevice'] == 1) {echo 'checked';}?> type="checkbox"></div>
                      </div>

                      <div class="form-group">
                        <label class="col-xs-4 control-label"><?=$pia_lang['DevDetail_EveandAl_Archived'];?></label>
                        <div class="col-xs-4" style="padding-top:6px;">
                          <input class="checkbox blue" id="chkArchived" <?php if ($icmpmonitorDetails['icmp_Archived'] == 1) {echo 'checked';}?> type="checkbox">
                        </div>
                      </div>


                      <!-- Alert events -->
                      <div class="form-group">
                        <label class="col-xs-4 control-label"><?=$pia_lang['WEBS_label_AlertEvents'];?></label>
                        <div class="col-xs-4" style="padding-top:6px;">
                          <input class="checkbox blue" id="chkAlertEvents" <?php if ($icmpmonitorDetails['icmp_AlertEvents'] == 1) {echo 'checked';}?> type="checkbox">
                        </div>
                      </div>

                      <!-- Alert Down -->
                      <div class="form-group">
                        <label class="col-xs-4 control-label"><?=$pia_lang['WEBS_label_AlertDown'];?></label>
                        <div class="col-xs-4" style="padding-top:6px;">
                          <input class="checkbox red" id="chkAlertDown" <?php if ($icmpmonitorDetails['icmp_AlertDown'] == 1) {echo 'checked';}?> type="checkbox">
                        </div>
                      </div>

                    </div>
                  </div>

                  <!-- Buttons -->
                  <div class="col-xs-12">
                    <div class="pull-right">
                        <button type="button" class="btn btn-danger servicedet_button_space"  id="btnDelete"   onclick="deleteICMPHost()"> <?=$pia_lang['Gen_Delete'];?> </button>
                        <button type="button" class="btn btn-default servicedet_button_space" id="btnRestore" onclick="restoreOrCloseICMPHost()"> <?=$pia_lang['Gen_Close'];?> </button>
                        <button type="button" disabled class="btn btn-primary servicedet_button_space" id="btnSave" onclick="setICMPHostData()"> <?=$pia_lang['Gen_Save'];?> </button>
                    </div>
                  </div>

                </div>
              </div>

<!-- tab page 5 ------------------------------------------------------------ -->
              <div class="tab-pane fade" id="panNmap">

                <h4 class="">Nmap Scans</h4>
                <div style="width:100%; text-align: center;">
                  <button type="button" id="manualnmap_fast" class="btn btn-primary pa-btn" style="margin-bottom: 20px; margin-left: 10px; margin-right: 10px;" onclick="manualnmapscan(document.getElementById('txtIP').value, 'fast')">Loading...</button>
                  <button type="button" id="manualnmap_normal" class="btn btn-primary pa-btn" style="margin-bottom: 20px; margin-left: 10px; margin-right: 10px;" onclick="manualnmapscan(document.getElementById('txtIP').value, 'normal')">Loading...</button>

                </div>

                <div id="scanoutput" style="margin-top: 30px;"></div>

                  <script>
                  function manualnmapscan(targetip, mode) {
                    $( "#scanoutput" ).empty();
                    $.ajax({
                      method: "POST",
                      url: "./php/server/nmap_scan.php",
                      data: { scan: targetip, mode: mode },
                      beforeSend: function() { $('#scanoutput').addClass("ajax_scripts_loading"); },
                      complete: function() { $('#scanoutput').removeClass("ajax_scripts_loading"); },
                      success: function(data, textStatus) {
                          $("#scanoutput").html(data);
                      }
                    })
                  }
                  </script>

              </div>

<!-- Events ------------------------------------------------------------ -->
              <div class="tab-pane fade table-responsive" id="panEvents">

                <!-- Datatable Events -->
                <h3 class="text-aqua" style="display: inline-block;font-size: 18px; margin: 0; line-height: 1; margin-bottom: 15px;"><?=$pia_lang['WEBS_EVE_Shortcut_All']?></h3>
                <table id="tableEvents" class="table table-bordered table-hover table-striped ">
                  <thead>
                    <tr>
                      <!-- <th>Service URL</th> -->
                      <th><?=$pia_lang['WEBS_tablehead_TargetIP'];?></th>
                      <th><?=$pia_lang['WEBS_tablehead_ScanTime'];?></th>
                      <th>Event Type</th>
                    </tr>
                  </thead>
                  <tbody>
<?php
# Create Event table
get_icmphost_events_table($hostip, $icmpfilter);
?>
                  </tbody>
                </table>
              </div>

<!-- Graph ------------------------------------------------------------ -->
              <div class="tab-pane fade table-responsive" id="panGraph" style="height:100%;">
                <h4 class="text-aqua" style="font-size: 18px; margin: 0; line-height: 1; margin-bottom: 20px;"><?=$pia_lang['WEBS_Chart_a'];?> <span class="maxlogage-interval">24</span> <?=$pia_lang['WEBS_Chart_b'];?></h4>
                <div class="col-md-12">
                  <div class="chart" style="height: 150px;">
                    <script src="lib/AdminLTE/bower_components/chart.js/chart.js"></script>
                    <canvas id="ServiceChart"></canvas>
                  </div>
                </div>
                <script src="js/graph_online_history.js"></script>
                <script>
                  var pia_js_online_history_time = [<?php pia_graph_devices_data($Pia_Graph_ICMPHost_Time);?>];
                  var pia_js_online_history_online = [<?php pia_graph_devices_data($Pia_Graph_ICMPHost_Up);?>];
                  var pia_js_online_history_offline = [<?php pia_graph_devices_data($Pia_Graph_ICMPHost_Down);?>];
                  graph_icmphost_history(pia_js_online_history_time, pia_js_online_history_offline, pia_js_online_history_online);
                </script>

                <div class="col-md-12 bottom-border-aqua" style="margin-top: 30px; opacity: 0.7"></div>
<?php
# Get Statistic
$statistic = get_host_statistic($hostip);
?>
                <div class="col-md-12">

                  <div class="row" style="margin-top: 10px;">
                    <div class="col-sm-12" style="font-weight: 600;"><?=$pia_lang['WEBS_Stats_Time'];?>:</div>
                  </div>
                  <div class="row" style="margin-top: 10px;">
                    <div class="col-sm-2" style="font-weight: 600;">24h</div>
                    <div class="col-sm-2"><span class="text-aqua">&Oslash;</span> <?=$statistic['rtt_avg_24h'];?></div>
                    <div class="col-sm-2"><?=$statistic['rtt_min_24h'];?></div>
                    <div class="col-sm-2"><?=$statistic['rtt_max_24h'];?></div>
                  </div>
                  <div class="row" style="margin-top: 10px;">
                    <div class="col-sm-2" style="font-weight: 600;">7d</div>
                    <div class="col-sm-2"><span class="text-aqua">&Oslash;</span> <?=$statistic['rtt_avg_1w'];?></div>
                    <div class="col-sm-2"><?=$statistic['rtt_min_1w'];?></div>
                    <div class="col-sm-2"><?=$statistic['rtt_max_1w'];?></div>
                  </div>
                  <div class="row" style="margin-top: 10px;">
                    <div class="col-sm-2" style="font-weight: 600;">All</div>
                    <div class="col-sm-2"><span class="text-aqua">&Oslash;</span> <?=$statistic['avg_rtt_all'];?></div>
                    <div class="col-sm-2"><?=$statistic['rtt_min_all'];?></div>
                    <div class="col-sm-2"><?=$statistic['rtt_max_all'];?></div>
                    <div class="col-sm-4">&nbsp;</div>
                  </div>
                </div>

                <div class="col-md-12 bottom-border-aqua" style="margin-top: 10px; opacity: 0.7"></div>

                <div class="col-md-12">
                  <div class="row" style="margin-top: 10px;">
                    <div class="col-sm-12" style="font-weight: 600;"><?=$pia_lang['ICMPMonitor_Availability'];?></div>
                  </div>
                  <div class="row" style="margin-top: 10px;">
                    <div class="col-sm-2" style="font-weight: 600;">24h</div>
                    <div class="col-sm-2"><span class="text-green"><?=$pia_lang['ICMPMonitor_Shortcut_Online'];?></span> <?=$statistic['online_percent_24h'];?></div>
                    <div class="col-sm-2"><span class="text-red"><?=$pia_lang['ICMPMonitor_Shortcut_Offline'];?></span> <?=$statistic['offline_percent_24h'];?></div>
                  </div>
                  <div class="row" style="margin-top: 10px;">
                    <div class="col-sm-2" style="font-weight: 600;">7d</div>
                    <div class="col-sm-2"><span class="text-green"><?=$pia_lang['ICMPMonitor_Shortcut_Online'];?></span> <?=$statistic['online_percent_1w'];?></div>
                    <div class="col-sm-2"><span class="text-red"><?=$pia_lang['ICMPMonitor_Shortcut_Offline'];?></span> <?=$statistic['offline_percent_1w'];?></div>
                  </div>
                  <div class="row" style="margin-top: 10px;">
                    <div class="col-sm-2" style="font-weight: 600;">All</div>
                    <div class="col-sm-2"><span class="text-green"><?=$pia_lang['ICMPMonitor_Shortcut_Online'];?></span> <?=$statistic['online_percent_all'];?></div>
                    <div class="col-sm-2"><span class="text-red"><?=$pia_lang['ICMPMonitor_Shortcut_Offline'];?></span> <?=$statistic['offline_percent_all'];?></div>
                  </div>
                </div>

              </div>

            </div>
            <!-- /.tab-content -->
          </div>
          <!-- /.nav-tabs-custom -->

          <!-- </div> -->
        </div>
        <!-- /.col -->
      </div>

    </section>

  </div>

<!-- ----------------------------------------------------------------------- -->
<?php
require 'php/templates/footer.php';
?>

<!-- iCkeck -->
<link rel="stylesheet" href="lib/AdminLTE/plugins/iCheck/all.css">
<script src="lib/AdminLTE/plugins/iCheck/icheck.min.js"></script>

<!-- Datatable -->
<link rel="stylesheet" href="lib/AdminLTE/bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css">
<script src="lib/AdminLTE/bower_components/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="lib/AdminLTE/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js"></script>

<!-- fullCalendar -->
<link rel="stylesheet" href="lib/AdminLTE/bower_components/fullcalendar/dist/fullcalendar.min.css">
<link rel="stylesheet" href="lib/AdminLTE/bower_components/fullcalendar/dist/fullcalendar.print.min.css" media="print">
<script src="lib/AdminLTE/bower_components/moment/moment.js"></script>
<script src="lib/AdminLTE/bower_components/fullcalendar/dist/fullcalendar.min.js"></script>
<script src="lib/AdminLTE/bower_components/fullcalendar/dist/locale-all.js"></script>

<!-- Dark-Mode Patch -->
<?php
if ($ENABLED_DARKMODE === True) {
	echo '<link rel="stylesheet" href="css/dark-patch-cal.css">';
}
?>

<!-- page script ----------------------------------------------------------- -->
<script>

  var url                 = '';
  var devicesList         = [];
  var pos                 = -1;
  var parPeriod           = 'Front_icmpmonitorDetails_Period';
  var parTab              = 'Front_icmpmonitorDetails_Tab';
  var parEventsRows       = 'Front_icmpmonitorDetails_Events_Rows';
  var period              = '1 month';
  var tab                 = 'tabDetails'
  var icmpDetailsDirty    = false;
  var icmpInitialValues   = {};
  const icmpCloseLabel    = <?=json_encode($pia_lang['Gen_Close'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);?>;
  const icmpCancelLabel   = <?=json_encode($pia_lang['DevDetail_button_Reset'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);?>;
  //var eventsRows          = 25;

  // Read parameters & Initialize components
  main();

// -----------------------------------------------------------------------------
function main () {
  hostip = '<?=$hostip;?>'
  initializeTabs();
  initializeiCheck();
  captureICMPFormState();
  deactivateICMPSaveRestoreData();
  getEventsTotalsforICMPHost();
  initializeDatatable();
  initializeCombos();
	initToolsSection();
<?php
if (isset($_GET['icmpfilter'])) {
	echo "$('.nav-tabs a[id=tabEvents]').tab('show');";
}
?>

}
// -----------------------------------------------------------------------------
function initializeTabs () {
  // Activate panel
  var activeTab = getCookie("icmpTab");
  // If there is an active tab in the cookie, activate it
  if (activeTab != "") {
    $('.nav-tabs a[href="' + activeTab + '"]').tab('show');
  } else {
    activeTab = "#panDetails";
    $('.nav-tabs a[href="' + activeTab + '"]').tab('show');
  }

  // Save the selected tab in a cookie
  $('.nav-tabs a').on('shown.bs.tab', function(event) {
    var selectedTab = $(event.target).attr("href");
    setCookie("icmpTab", selectedTab, 30);
  });
  //$('.nav-tabs a[id='+ tab +']').tab('show');
}
// -----------------------------------------------------------------------------
function initializeiCheck () {
   // Blue
   $('input[type="checkbox"].blue').iCheck({
     checkboxClass: 'icheckbox_flat-blue',
     radioClass:    'iradio_flat-blue',
     increaseArea:  '20%'
   });
  // Orange
  $('input[type="checkbox"].orange').iCheck({
    checkboxClass: 'icheckbox_flat-orange',
    radioClass:    'iradio_flat-orange',
    increaseArea:  '20%'
  });
  // Red
  $('input[type="checkbox"].red').iCheck({
    checkboxClass: 'icheckbox_flat-red',
    radioClass:    'iradio_flat-red',
    increaseArea:  '20%'
  });
  // Purple
  $('input[type="checkbox"].purple').iCheck({
    checkboxClass: 'icheckbox_flat-purple',
    radioClass:    'iradio_flat-purple',
    increaseArea:  '20%'
  });
}
// -----------------------------------------------------------------------------
function getEventsTotalsforICMPHost() {
  // stop timer
  // stopTimerRefreshData();

  // get totals and put in boxes
  const hostIp = <?=json_encode($icmpmonitorDetails['icmp_ip'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);?>;
  $.get('php/server/icmpmonitor.php?action=getEventsTotalsforICMP&hostip=' + encodeURIComponent(hostIp), function(data) {
    var totalsEvents = JSON.parse(data);

    $('#eventspresence').html   (totalsEvents[0].toLocaleString() + ' h.');
    $('#eventsdown').html       (totalsEvents[1].toLocaleString());
  });
    // Timer for refresh data
    //newTimerRefreshData(getEventsTotals);
}
// -----------------------------------------------------------------------------
function initializeDatatable () {
  $('#tableEvents').DataTable({
    'paging'       : true,
    'lengthChange' : true,
    'lengthMenu'   : [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, 'All']],
    //'bLengthChange': false,
    'searching'    : true,
    'ordering'     : true,
    'info'         : true,
    'autoWidth'    : false,
    'pageLength'   : 10,
    'order'        : [[1, 'desc']],
    'columns': [
        { "data": 0 },
        { "data": 1 },
        { "data": 2 }
      ],

    'columnDefs'  : [
      {targets: '_all', render: $.fn.dataTable.render.text()},
      {className: 'text-left', targets: [1,2] },
      {targets: [2],
        'createdCell': function (td, cellData, rowData, row, col) {
          if (cellData == 99999){
            setCellText(td, "TimeOut");
          } else {
            setCellText(td, cellData);
          }
      } },
    ],

    // Processing
    'processing'  : true,
    'language'    : {
      processing: '<table><td width="130px" align="middle">Loading...</td><td><i class="ion ion-ios-sync fa-spin fa-2x fa-fw"></td></table>',
      emptyTable: 'No data',
      "lengthMenu": "<?=$pia_lang['EVE_Tablelenght'];?>",
      "search":     "<?=$pia_lang['EVE_Searchbox'];?>: ",
      "paginate": {
          "next":       "<?=$pia_lang['EVE_Table_nav_next'];?>",
          "previous":   "<?=$pia_lang['EVE_Table_nav_prev'];?>"
      },
      "info":           "<?=$pia_lang['EVE_Table_info'];?>",
    },
  });
};

// -----------------------------------------------------------------------------
function setICMPHostData(refreshCallback='') {
  if (hostip == '') {
    return;
  }

  // update data to server
  pialertPost('php/server/icmpmonitor.php?action=setICMPHostData'
    + '&icmp_ip='         + $('#txtIP').val()
    + '&icmp_hostname='   + $('#txtHostname').val()
    + '&icmp_type='       + $('#txtDeviceType').val()
    + '&icmp_group='      + $('#txtGroup').val()
    + '&icmp_location='   + $('#txtLocation').val()
    + '&icmp_owner='      + $('#txtOwner').val()
    + '&icmp_notes='      + $('#txtNotes').val()
    + '&icmp_scanvalid='  + $('#txtScanValidation').val()
    + '&icmp_vendor='     + $('#txtVendor').val()
    + '&icmp_model='      + $('#txtModel').val()
    + '&icmp_serial='     + $('#txtSerialnumber').val()
    + '&mqttdevice='      + ($('#chkMQTTDevice')[0].checked * 1)
    + '&favorit='         + ($('#chkFavorit')[0].checked * 1)
    + '&archived='        + ($('#chkArchived')[0].checked * 1)
    + '&alertdown='       + ($('#chkAlertDown')[0].checked * 1)
    + '&alertevents='     + ($('#chkAlertEvents')[0].checked * 1)
    , function(msg) {

    // deactivate button
    captureICMPFormState();
    deactivateICMPSaveRestoreData();
    showMessage (msg);
    // Callback fuction
    if (typeof refreshCallback == 'function') {
      refreshCallback();
    }
  });

  // refresh Sidebar
  setTimeout(function(){
      updateTotals();
  }, 1000);

}

// -----------------------------------------------------------------------------
function askdeleteICMPHost () {
  if (hostip == '') {
    return;
  }

  // Ask delete device
  showModalWarning ('<?=$pia_lang['WEBS_button_Delete_label'];?>', '<?=$pia_lang['WEBS_button_Delete_Warning'];?>',
    '<?=$pia_lang['Gen_Cancel'];?>', '<?=$pia_lang['Gen_Delete'];?>', 'deleteICMPHost');
}

// -----------------------------------------------------------------------------
function deleteICMPHost () {
  if (hostip == '') {
    return;
  }

  // Delete device
  pialertPost('php/server/icmpmonitor.php?action=deleteICMPHost&icmp_ip='+ hostip, function(msg) {
    showMessage (msg);
  });

  // Deactivate controls
  $('#panDetails :input').attr('disabled', true);
}

// -----------------------------------------------------------------------------
function setTextValue (textElement, textValue) {
  $('#'+textElement).val (textValue);
  activateICMPSaveRestoreData();
}

$(document).on('input change', '#panDetails input:not([readonly]), #panDetails textarea, #panDetails select', function() {
  activateICMPSaveRestoreData();
});

$('#panDetails input[type="checkbox"]').on('ifToggled', function() {
  activateICMPSaveRestoreData();
});

function activateICMPSaveRestoreData () {
  icmpDetailsDirty = true;
  $('#btnRestore').text(icmpCancelLabel);
  $('#btnSave').removeAttr('disabled');
}

function deactivateICMPSaveRestoreData () {
  icmpDetailsDirty = false;
  $('#btnRestore').text(icmpCloseLabel);
  $('#btnSave').attr('disabled', '');
}

function captureICMPFormState () {
  icmpInitialValues = {};
  $('#panDetails input, #panDetails textarea, #panDetails select').each(function() {
    if (!this.id || this.type === 'button' || this.type === 'submit') return;
    icmpInitialValues[this.id] = this.type === 'checkbox' ? this.checked : $(this).val();
  });
}

function restoreICMPFormState () {
  Object.keys(icmpInitialValues).forEach(function(id) {
    const element = document.getElementById(id);
    if (!element) return;
    if (element.type === 'checkbox') {
      $(element).iCheck(icmpInitialValues[id] ? 'check' : 'uncheck');
    } else {
      $(element).val(icmpInitialValues[id]);
    }
  });
  deactivateICMPSaveRestoreData();
}

function restoreOrCloseICMPHost () {
  if (icmpDetailsDirty) {
    restoreICMPFormState();
    return;
  }
  window.location.href = './icmpmonitor.php';
}

// Get Cookie (Tab state)
function getCookie(cookieName) {
  var name = cookieName + "=";
  var decodedCookie = decodeURIComponent(document.cookie);
  var cookieArray = decodedCookie.split(';');

  for (var i = 0; i < cookieArray.length; i++) {
    var cookie = cookieArray[i];

    while (cookie.charAt(0) == ' ') {
      cookie = cookie.substring(1);
    }

    if (cookie.indexOf(name) == 0) {
      return cookie.substring(name.length, cookie.length);
    }
  }

  return "";
}

// -----------------------------------------------------------------------------
// Set Cookie (Tab state)
function setCookie(cookieName, cookieValue, expirationDays) {
  var date = new Date();
  date.setTime(date.getTime() + (expirationDays * 24 * 60 * 60 * 1000));
  var expires = "expires=" + date.toUTCString();
  document.cookie = cookieName + "=" + cookieValue + ";" + expires + ";path=/";
}

// -----------------------------------------------------------------------------
function initializeCombos () {
  // Initialize combos with queries
  initializeCombo ( $('#dropdownOwner')[0],                      'getOwners',       'txtOwner');
  initializeCombo ( $('#dropdownDeviceType')[0],                 'getDeviceTypes',  'txtDeviceType');
  initializeCombo ( $('#dropdownGroup')[0],                      'getGroups',       'txtGroup');
  initializeCombo ( $('#dropdownLocation')[0],                   'getLocations',    'txtLocation');

  // Initialize static combos
  //initializeComboSkipRepeated ();
}

function initializeComboSkipRepeated () {
  // find dropdown menu element
  HTMLelement = $('#dropdownSkipRepeated')[0];
  HTMLelement.innerHTML = ''

  // for each item
  skipRepeatedItems.forEach(function (item, index) {
    // add dropdown item
    HTMLelement.innerHTML += ' <li><a href="javascript:void(0)" ' +
      'onclick="setTextValue(\'txtSkipRepeated\',\'' + item + '\');">'+
      item +'</a></li>';
  });
}

function initializeCombo (HTMLelement, queryAction, txtDataField) {
  $.get('php/server/devices.php?action=' + encodeURIComponent(queryAction), function(data) {
    const listData = JSON.parse(data);
    let order = 1;

    while (HTMLelement.firstChild) {
      HTMLelement.removeChild(HTMLelement.firstChild);
    }

    listData.forEach(function(item) {
      if (order != item.order) {
        const divider = document.createElement('li');
        divider.className = 'divider';
        HTMLelement.appendChild(divider);
        order = item.order;
      }

      const value = item.id !== undefined && item.id !== null && item.id !== '' ? item.id : item.name;
      const label = queryAction === 'getNetworkNodes'
        ? String(item.name ?? '') + ' [' + String(value ?? '') + ']'
        : String(item.name ?? '');
      const listItem = document.createElement('li');
      const link = document.createElement('a');

      link.href = '#';
      link.textContent = label;
      link.addEventListener('click', function(event) {
        event.preventDefault();
        setTextValue(txtDataField, String(value ?? ''));
      });

      listItem.appendChild(link);
      HTMLelement.appendChild(listItem);
    });
  });
}

function showmanualnmapscan(targetip) {
  $( "#scanoutput" ).empty();
  $.ajax({
    method: "POST",
    url: "./php/server/nmap_scan.php",
    timeout: 60000,
    data: { scan: targetip, mode: "view" },
    success: function(data, textStatus) {
        $("#scanoutput").html(data);
    }
  })
}

function initToolsSection () {
setTimeout(function(){
   document.getElementById('manualnmap_fast').textContent='<?=$pia_lang['DevDetail_Tools_nmap_buttonFast'];?> (' + document.getElementById('txtIP').value +')';
   document.getElementById('manualnmap_normal').textContent='<?=$pia_lang['DevDetail_Tools_nmap_buttonDefault'];?> (' + document.getElementById('txtIP').value +')';
   showmanualnmapscan(document.getElementById('txtIP').value);
}, 1000);
}
</script>

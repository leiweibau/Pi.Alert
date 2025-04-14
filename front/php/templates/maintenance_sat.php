<?php
if ($_SESSION['SATELLITES_ACTIVE'] == True) {
    echo '<div class="tab-pane '.$pia_tab_satellites.'" id="tab_satellites">
            <div class="db_info_table">
                <div class="db_info_table_row">
                    <h4 class="bottom-border-aqua">'.$pia_lang['MT_SET_SatCreate_head'].'</h4>
                </div>
                <div class="db_info_table_row">
                    <div class="col-xs-10 col-md-4 col-lg-3" style="padding: 5px;">
                        '.$pia_lang['MT_SET_SatCreate_FORM_Name'].': <br>
                        <input class="form-control col-xs-12" type="text" id="txtNewSatelliteName" placeholder="'.$pia_lang['MT_SET_SatCreate_FORM_Name_PH'].'">
                    </div>
                    <div class="col-xs-2 col-md-1 col-lg-1 text-right">
                        <button type="button" class="btn btn-link" id="btnCreateNewSatellite" onclick="askCreateNewSatellite()"><i class="bi bi-floppy text-green" style="position: relative; font-size: 20px; top: 23px;"></i></button>
                    </div>
                    <div class="col-xs-12 col-md-6 col-lg-6 text-center">
                        <a href="./download/proxymodeconfig.php" target="blank" type="button" class="btn btn-warning" id="btnProxyModeConfig" style="position: relative; top: 27px; margin-bottom:30px;">'.$pia_lang['MT_SET_SatExport_BTM'].'</a>
                    </div>
                </div>
                <div class="db_info_table_row">
                    <h4 class="bottom-border-aqua">'.$pia_lang['MT_SET_SatEdit_head'].'</h4>
                </div>';
    get_all_satellites_list();
    echo '  </div>
          </div>';
}
?>
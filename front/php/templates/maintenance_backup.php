        <div class="tab-pane <?=$pia_tab_backup;?>" id="tab_BackupRestore">
            <div class="row">
                <div class="col-xs-12">
                    <h4 class="bottom-border-aqua"><?=$pia_lang['MT_Tools_Tab_Subheadline_j'];?></h4>
                </div>
            </div>

            <div class="row">
                <div class="col-xs-12 col-md-6">
                    <div class="db_info_table">

                        <div class="db_info_table_row">
                            <div class="db_tools_table_cell_a" style="">
                                <button type="button" class="btn btn-default dbtools-button" id="btnPiaBackupConfigFile" onclick="BackupConfigFile('yes')"><?=$pia_lang['MT_Tool_ConfBackup'];?></button>
                            </div>
                            <div class="db_tools_table_cell_b"><?=$pia_lang['MT_Tool_ConfBackup_text'];?></div>
                        </div>
                        <div class="db_info_table_row">
                            <div class="db_tools_table_cell_a" style="">
                                <button type="button" class="btn btn-default dbtools-button" id="btnBackupDBtoArchive" onclick="askBackupDBtoArchive()"><?=$pia_lang['MT_Tool_backup'];?></button>
                            </div>
                            <div class="db_tools_table_cell_b"><?=$pia_lang['MT_Tool_backup_text'];?></div>
                        </div>
                        <div class="db_info_table_row">
                            <div class="db_tools_table_cell_a" style="">
<?php
if (!$block_restore_button_db) {
    echo '<button type="button" class="btn btn-default dbtools-button" id="btnRestoreDBfromArchive" onclick="askRestoreDBfromArchive()">' . $pia_lang['MT_Tool_restore'] . '<br>' . $LATEST_BACKUP_DATE . '</button>';
} else {
    echo '<button type="button" class="btn btn-default dbtools-button disabled" id="btnRestoreDBfromArchive">' . $pia_lang['MT_Tool_restore'] . '<br>' . $LATEST_BACKUP_DATE . '</button>';
}
?>
                            </div>
                            <div class="db_tools_table_cell_b"><?=$pia_lang['MT_Tool_restore_text'];?></div>
                        </div>

                    </div>
                </div>
                <div class="col-xs-12 col-md-6">
                    <div class="db_info_table">

                        <div class="db_info_table_row">
                            <div class="db_tools_table_cell_a" style="">
                                <button type="button" class="btn btn-default dbtools-button" id="btnPurgeDBBackups" onclick="askPurgeDBBackups()"><?=$pia_lang['MT_Tool_purgebackup'];?></button>
                            </div>
                            <div class="db_tools_table_cell_b"><?=$pia_lang['MT_Tool_purgebackup_text'];?></div>
                        </div>
                        <div class="db_info_table_row">
                            <div class="db_tools_table_cell_a" style="">
                                <button type="button" class="btn btn-default dbtools-button" id="btnBackupDBtoCSV" onclick="askBackupDBtoCSV()"><?=$pia_lang['MT_Tool_backupcsv'];?></button>
                            </div>
                            <div class="db_tools_table_cell_b"><?=$pia_lang['MT_Tool_backupcsv_text'];?></div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="row" style="margin-top: 20px;">
                <div class="col-xs-12">
                    <h4 class="bottom-border-aqua">Downloads</h4>
                </div>
            </div>

            <div class="row" style="margin-top: 10px; margin-bottom: 20px;">
                <div class="col-xs-12">
 <?php
if (!$block_restore_button_db) {
	echo '<div class="col-md-3 col-xs-12 p-3" style="margin-bottom:15px;"><a class="btn btn-default col-xs-12" href="./download/database.php" role="button">' . $pia_lang['MT_Tool_latestdb_download'] . '</a></div>';}
if (file_exists('../db/pialertcsv.zip')) {
    $csvdate = date("Y.m.d", filemtime("../db/pialertcsv.zip"));
	echo '<div class="col-md-3 col-xs-12 p-3" style="margin-bottom:15px;"><a class="btn btn-default col-xs-12" href="./download/databasecsv.php" role="button">' . $pia_lang['MT_Tool_CSVExport_download'] . ' ('. $csvdate .')</a></div>';}
?>
                    <div class="col-md-3 col-xs-12 p-3" style="margin-bottom:15px;"><a class="btn btn-default col-xs-12" href="./download/config.php" role="button"><?=$pia_lang['MT_Tool_latestconf_download']?></a></div>
                    <div class="col-md-3 col-xs-12 p-3" style="margin-bottom:15px;"><a class="btn btn-default col-xs-12" href="./download/uisettings.php" role="button"><?=$pia_lang['MT_Tool_uisettings_download']?></a></div>
                </div>
            </div>

        </div>
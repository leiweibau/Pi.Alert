        <div class="tab-pane <?=$pia_tab_backup;?>" id="tab_BackupRestore">
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
 <?php
echo '<div class="row">';
if (!$block_restore_button_db) {
	echo '<div class="col-md-4" style="text-align: center;">
			<a class="btn btn-default" href="./download/database.php" role="button" style="margin-top: 20px; margin-bottom: 20px;">' . $pia_lang['MT_Tool_latestdb_download'] . '</a>
			</div>';}
if (file_exists('../db/pialertcsv.zip')) {
	echo '<div class="col-md-4" style="text-align: center;">
			<a class="btn btn-default" href="./download/databasecsv.php" role="button" style="margin-top: 20px; margin-bottom: 20px;">' . $pia_lang['MT_Tool_CSVExport_download'] . '</a>
			</div>';}
echo '<div class="col-md-4" style="text-align: center;">
			<a class="btn btn-default" href="./download/config.php" role="button" style="margin-top: 20px; margin-bottom: 20px;">' . $pia_lang['MT_Tool_latestconf_download'] . '</a>
			</div>';
echo '</div>';
?>
        </div>
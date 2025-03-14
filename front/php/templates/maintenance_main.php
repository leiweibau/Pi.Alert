        <div class="tab-pane <?=$pia_tab_tool;?>" id="tab_DBTools">
            <div class="db_info_table">
                <div class="db_info_table_row">
                    <div class="db_tools_table_cell_a">
                        <button type="button" class="btn btn-default dbtools-button" id="btnDeleteMAC" onclick="askDeleteAllDevices()"><?=$pia_lang['MT_Tool_del_alldev'];?></button>
                    </div>
                    <div class="db_tools_table_cell_b"><?=$pia_lang['MT_Tool_del_alldev_text'];?></div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_tools_table_cell_a">
                        <button type="button" class="btn btn-default dbtools-button" id="btnDeleteUnknown" onclick="askDeleteUnknown()"><?=$pia_lang['MT_Tool_del_unknowndev'];?></button>
                    </div>
                    <div class="db_tools_table_cell_b"><?=$pia_lang['MT_Tool_del_unknowndev_text'];?></div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_tools_table_cell_a">
                        <button type="button" class="btn btn-default dbtools-button" id="btnDeleteAllEvents" onclick="askDeleteEvents()"><?=$pia_lang['MT_Tool_del_allevents'];?></button>
                    </div>
                    <div class="db_tools_table_cell_b"><?=$pia_lang['MT_Tool_del_allevents_text'];?></div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_tools_table_cell_a">
                        <button type="button" class="btn btn-default dbtools-button" id="btnDeleteAllEvents" onclick="askresetVoidedEvents()"><?=$pia_lang['MT_Tool_reset_voided'];?></button>
                    </div>
                    <div class="db_tools_table_cell_b"><?=$pia_lang['MT_Tool_reset_voided_text'];?></div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_tools_table_cell_a">
                        <button type="button" class="btn btn-default dbtools-button" id="btnDeleteActHistory" onclick="askDeleteActHistory()"><?=$pia_lang['MT_Tool_del_ActHistory'];?></button>
                    </div>
                    <div class="db_tools_table_cell_b"><?=$pia_lang['MT_Tool_del_ActHistory_text'];?></div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_tools_table_cell_a">
                        <button type="button" class="btn btn-default dbtools-button" id="btnDeleteSpeedtests" onclick="askDeleteSpeedtestResults()"><?=$pia_lang['MT_Tool_del_speedtest'];?></button>
                    </div>
                    <div class="db_tools_table_cell_b"><?=$pia_lang['MT_Tool_del_speedtest_text'];?></div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_tools_table_cell_a">
                        <button type="button" class="btn btn-default dbtools-button" id="btnDeleteNmapScans" onclick="askDeleteNmapScansResults()"><?=$pia_lang['MT_Tool_del_nmapscans'];?></button>
                    </div>
                    <div class="db_tools_table_cell_b"><?=$pia_lang['MT_Tool_del_nmapscans_text'];?></div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_tools_table_cell_a">
                        <button type="button" class="btn btn-default dbtools-button" id="btnDeleteInactiveHosts" onclick="askDeleteInactiveHosts()"><?=$pia_lang['MT_Tool_del_Inactive_Hosts'];?></button>
                    </div>
                    <div class="db_tools_table_cell_b"><?=$pia_lang['MT_Tool_del_Inactive_Hosts_text'];?> <a href="#" data-toggle="modal" data-target="#modal-logviewer-inactivehosts"><i class="bi bi-info-circle text-aqua" style=""></i></a></div>
                </div>
                <div class="db_info_table_row">
                    <div class="db_tools_table_cell_a">
                        <button type="button" class="btn btn-default dbtools-button" id="btnDeleteWebServices" onclick="askDeleteAllWebServices()"><?=$pia_lang['MT_Tool_del_allserv'];?></button>
                    </div>
                    <div class="db_tools_table_cell_b"><?=$pia_lang['MT_Tool_del_allserv_text'];?></div>
                </div>
            </div>
        </div>

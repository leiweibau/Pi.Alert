        <div class="tab-pane <?=$pia_tab_tool;?>" id="tab_DBTools">
            <div class="row">
                <div class="col-xs-12 col-md-6">
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

                    </div>
                </div>
                <div class="col-xs-12 col-md-6">
                    <div class="db_info_table">
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
            </div>

            <div class="row">
                <div class="col-xs-12">
                    <h4 class="bottom-border-aqua">Einträge verwalten</h4>
                </div>
            </div>

            <div class="row">
                <div class="col-xs-12 col-md-3">
                    <label>Column</label><br>
                    <div class="input-group dropup">
                        <input class="form-control" id="txtMTTableColumn" type="text" readonly>
                            <div class="input-group-btn">
                                <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown" aria-expanded="false" ><span class="fa fa-caret-down"></span></button>
                                <ul id="dropdownMTTableColumn" class="dropdown-menu dropdown-menu-right">
                                    <li><a href="javascript:void(0)" onclick="setTextValue('txtMTTableColumn','Group'); handleMTSelection('Group');">         Group     </a></li>
                                    <li><a href="javascript:void(0)" onclick="setTextValue('txtMTTableColumn','Owner'); handleMTSelection('Owner');">         Owner     </a></li>
                                    <li><a href="javascript:void(0)" onclick="setTextValue('txtMTTableColumn','Type'); handleMTSelection('Type');">           Type      </a></li>
                                    <li><a href="javascript:void(0)" onclick="setTextValue('txtMTTableColumn','Location'); handleMTSelection('Location');">   Location  </a></li>
                                </ul>
                            </div>
                    </div>
                </div>
                <div class="col-xs-12 col-md-3">
                    <label>Column Content</label><br>
                    <div class="input-group dropup">
                        <input class="form-control" id="txtMTColumnContent" type="text" readonly>
                            <div class="input-group-btn">
                                <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown" aria-expanded="false" ><span class="fa fa-caret-down"></span></button>
                                <ul id="dropdownMTColumnContent" class="dropdown-menu dropdown-menu-right"></ul>
                            </div>
                    </div>
                </div>
                <div class="col-xs-12 col-md-3">
                    <label>New Content</label><br>
                    <input class="form-control" type="text" placeholder="New Value" id="txtMTNewColumnContent">
                </div>
                <div class="col-xs-12 col-md-3">
                    <label>Actions</label><br>
                        <button type="button" class="btn btn-link" id="" onclick="askMTUpdateColumnContent()" ><i class="bi bi-floppy text-yellow satlist_action_btn_content"></i></button>
                        <button type="button" class="btn btn-link" id="" onclick="askMTXDeletColumnContent()" ><i class="bi bi-trash text-red satlist_action_btn_content"></i></button>
                </div>
            </div>
        </div>

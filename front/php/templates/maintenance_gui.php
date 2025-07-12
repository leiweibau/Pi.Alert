        <div class="tab-pane <?=$pia_tab_gui;?>" id="tab_GUI">
			<table class="table_settings">
                <tr><td colspan="2"><h4 class="bottom-border-aqua"><?=$pia_lang['MT_Tools_Tab_Subheadline_a'];?></h4></td></tr>
                <tr class="table_settings">
                    <td class="db_info_table_cell" colspan="2" style="text-align: justify;"><?=$pia_lang['MT_Tools_Tab_Settings_Intro'];?></td>
                </tr>
                <tr class="table_settings_row">
                    <td class="db_info_table_cell" colspan="2" style="padding-bottom: 20px;">
                        <div style="display: flex; justify-content: center; flex-wrap: wrap;">
<!-- Language Selection --------------------------------------------------- -->
                            <div class="settings_button_wrapper">
                                <div class="settings_button_box">
                                    <div class="form-group" style="width:160px; margin-bottom:5px;">
                                      <div class="input-group">
                                        <input class="form-control" id="txtLangSelection" type="text" value="<?=$pia_lang['MT_lang_selector_empty'];?>" readonly >
                                        <div class="input-group-btn">
                                          <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown" aria-expanded="false" id="dropdownButtonLangSelection">
                                            <span class="fa fa-caret-down"></span></button>
                                          <ul id="dropdownLangSelection" class="dropdown-menu dropdown-menu-right">
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtLangSelection','en_us');"><?=$pia_lang['MT_lang_en_us'];?></a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtLangSelection','de_de');"><?=$pia_lang['MT_lang_de_de'];?></a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtLangSelection','fr_fr');"><?=$pia_lang['MT_lang_fr_fr'];?></a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtLangSelection','es_es');"><?=$pia_lang['MT_lang_es_es'];?></a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtLangSelection','it_it');"><?=$pia_lang['MT_lang_it_it'];?></a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtLangSelection','pl_pl');"><?=$pia_lang['MT_lang_pl_pl'];?></a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtLangSelection','nl_nl');"><?=$pia_lang['MT_lang_nl_nl'];?></a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtLangSelection','dk_da');"><?=$pia_lang['MT_lang_dk_da'];?></a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtLangSelection','cz_cs');"><?=$pia_lang['MT_lang_cz_cs'];?></a></li>
                                          </ul>
                                        </div>
                                      </div>
                                    </div>
                                    <button type="button" class="btn btn-default" style="margin-top:0px; width:160px;" id="btnSaveLangSelection" onclick="setPiAlertLanguage()" ><?=$pia_lang['MT_lang_selector_apply'];?> </button>
                                </div>
                            </div>
<!-- Theme Selection ------------------------------------------------------ -->
                            <div class="settings_button_wrapper">
                                <div class="settings_button_box">
                                    <div class="form-group" style="width:160px; margin-bottom:5px;">
                                      <div class="input-group">
                                        <input class="form-control" id="txtSkinSelection" type="text" value="<?=$pia_lang['MT_themeselector_empty'];?>" readonly >
                                        <div class="input-group-btn">
                                          <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown" aria-expanded="false" id="dropdownButtonSkinSelection">
                                            <span class="fa fa-caret-down"></span></button>
                                          <ul id="dropdownSkinSelection" class="dropdown-menu dropdown-menu-right">
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtSkinSelection','leiweibau_dark');">Theme leiweibau-dark</a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtSkinSelection','leiweibau_light');">Theme leiweibau-light</a></li>
                                            <li class="divider"></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtSkinSelection','skin-black-light');">Skin Black-light</a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtSkinSelection','skin-black');">Skin Black</a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtSkinSelection','skin-blue-light');">Skin Blue-light</a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtSkinSelection','skin-blue');">Skin Blue</a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtSkinSelection','skin-green-light');">Skin Green-light</a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtSkinSelection','skin-green');">Skin Green</a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtSkinSelection','skin-purple-light');">Skin Purple-light</a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtSkinSelection','skin-purple');">Skin Purple</a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtSkinSelection','skin-red-light');">Skin Red-light</a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtSkinSelection','skin-red');">Skin Red</a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtSkinSelection','skin-yellow-light');">Skin Yellow-light</a></li>
                                            <li><a href="javascript:void(0)" onclick="setTextValue('txtSkinSelection','skin-yellow');">Skin Yellow</a></li>
                                          </ul>
                                        </div>
                                      </div>
                                    </div>
                                    <button type="button" class="btn btn-default" style="margin-top:0px; width:160px;" id="btnSaveSkinSelection" onclick="setPiAlertTheme()" ><?=$pia_lang['MT_themeselector_apply'];?> </button>
                                </div>
                            </div>
<!-- Toggle DarkMode ------------------------------------------------------ -->
                            <div class="settings_button_wrapper" id="Darkmode_button_container">
                                <div class="settings_button_box">
                                	<?php $state = convert_state_action($ENABLED_DARKMODE, 1);?>
                                    <button type="button" class="btn btn-default dbtools-button" id="btnEnableDarkmode" onclick="askEnableDarkmode()"><span class="<?= ($ENABLED_DARKMODE == 0) ? 'text-red' : 'text-green' ?>"><?=$pia_lang['MT_Tool_darkmode'] . '</span><br>' . $state;?></button>
                                </div>
                            </div>
<!-- Toggle History Graph ------------------------------------------------- -->
                            <div class="settings_button_wrapper">
                                <div class="settings_button_box">
                                	<?php $state = convert_state_action($ENABLED_HISTOY_GRAPH, 1);?>
                                    <button type="button" class="btn btn-default dbtools-button" id="btnEnableOnlineHistoryGraph" onclick="askEnableOnlineHistoryGraph()"><span class="<?= ($ENABLED_HISTOY_GRAPH == 0) ? 'text-red' : 'text-green' ?>"><?=$pia_lang['MT_Tool_onlinehistorygraph'] . '</span><br>' . $state;?></button>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr><td colspan="2"><h4 class="bottom-border-aqua"><?=$pia_lang['MT_Tools_Tab_Subheadline_e'];?></h4></td></tr>
<!-- FavIcon -------------------------------------------------------------- -->
                <tr class="table_settings">
                    <td class="db_info_table_cell" colspan="2" style="text-align: justify;"><?=$pia_lang['MT_Tools_Tab_Subheadline_e_Intro'];?> (<a href="https://github.com/leiweibau/Pi.Alert/blob/main/docs/ICONS.md" target="_blank">View on Github</a>)</td>
                </tr>
                <tr><td class="db_info_table_cell" colspan="2" style="padding: 10px;">
					<div class="row">
        				<div class="col-md-10">
	                      <div class="form-group">
	                        <label class="col-xs-3 col-md-2" style="margin-top: 5px;">FavIcon</label>
	                        <div class="col-xs-9 col-md-10">
	                          <div class="input-group" style="margin-bottom: 20px;">
	                            <input class="form-control" id="txtFavIconURL" type="text" value="<?=$FRONTEND_FAVICON?>">
	                            <div class="input-group-btn">
	                              <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown" aria-expanded="false" >
	                                <span class="fa fa-caret-down"></span></button>
	                              <ul id="dropdownFavIcons" class="dropdown-menu dropdown-menu-right">
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','redglass_w_local')">    <?=$pia_lang['FavIcon_color_red'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','redflat_w_local')">     <?=$pia_lang['FavIcon_color_red'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','redglass_b_local')">    <?=$pia_lang['FavIcon_color_red'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','redflat_b_local')">     <?=$pia_lang['FavIcon_color_red'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>

	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','blueglass_w_local')">   <?=$pia_lang['FavIcon_color_blue'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','blueflat_w_local')">    <?=$pia_lang['FavIcon_color_blue'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','blueglass_b_local')">   <?=$pia_lang['FavIcon_color_blue'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','blueflat_b_local')">    <?=$pia_lang['FavIcon_color_blue'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>

	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','greenglass_w_local')">  <?=$pia_lang['FavIcon_color_green'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','greenflat_w_local')">   <?=$pia_lang['FavIcon_color_green'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','greenglass_b_local')">  <?=$pia_lang['FavIcon_color_green'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','greenflat_b_local')">   <?=$pia_lang['FavIcon_color_green'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>

	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','yellowglass_w_local')"> <?=$pia_lang['FavIcon_color_yellow'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','yellowflat_w_local')">  <?=$pia_lang['FavIcon_color_yellow'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','yellowglass_b_local')"> <?=$pia_lang['FavIcon_color_yellow'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','yellowflat_b_local')">  <?=$pia_lang['FavIcon_color_yellow'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>

	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','purpleglass_w_local')"> <?=$pia_lang['FavIcon_color_purple'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','purpleflat_w_local')">  <?=$pia_lang['FavIcon_color_purple'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','purpleglass_b_local')"> <?=$pia_lang['FavIcon_color_purple'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','purpleflat_b_local')">  <?=$pia_lang['FavIcon_color_purple'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>

	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','blackglass_w_local')">  <?=$pia_lang['FavIcon_color_black'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','blackflat_w_local')">   <?=$pia_lang['FavIcon_color_black'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','whiteglass_b_local')">  <?=$pia_lang['FavIcon_color_white'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','whiteflat_b_local')">   <?=$pia_lang['FavIcon_color_white'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_local'];?>)</a></li>

	                                <li class="divider"></li>

	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','redglass_w_remote')">    <?=$pia_lang['FavIcon_color_red'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','redflat_w_remote')">     <?=$pia_lang['FavIcon_color_red'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','redglass_b_remote')">    <?=$pia_lang['FavIcon_color_red'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','redflat_b_remote')">     <?=$pia_lang['FavIcon_color_red'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>

	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','blueglass_w_remote')">   <?=$pia_lang['FavIcon_color_blue'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','blueflat_w_remote')">    <?=$pia_lang['FavIcon_color_blue'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','blueglass_b_remote')">   <?=$pia_lang['FavIcon_color_blue'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','blueflat_b_remote')">    <?=$pia_lang['FavIcon_color_blue'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>

	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','greenglass_w_remote')">  <?=$pia_lang['FavIcon_color_green'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','greenflat_w_remote')">   <?=$pia_lang['FavIcon_color_green'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','greenglass_b_remote')">  <?=$pia_lang['FavIcon_color_green'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','greenflat_b_remote')">   <?=$pia_lang['FavIcon_color_green'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>

	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','yellowglass_w_remote')"> <?=$pia_lang['FavIcon_color_yellow'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','yellowflat_w_remote')">  <?=$pia_lang['FavIcon_color_yellow'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','yellowglass_b_remote')"> <?=$pia_lang['FavIcon_color_yellow'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','yellowflat_b_remote')">  <?=$pia_lang['FavIcon_color_yellow'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>

	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','purpleglass_w_remote')"> <?=$pia_lang['FavIcon_color_purple'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','purpleflat_w_remote')">  <?=$pia_lang['FavIcon_color_purple'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','purpleglass_b_remote')"> <?=$pia_lang['FavIcon_color_purple'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','purpleflat_b_remote')">  <?=$pia_lang['FavIcon_color_purple'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>

	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','blackglass_w_remote')">  <?=$pia_lang['FavIcon_color_black'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','blackflat_w_remote')">   <?=$pia_lang['FavIcon_color_black'];?>, <?=$pia_lang['FavIcon_logo_white']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','whiteglass_b_remote')">  <?=$pia_lang['FavIcon_color_white'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_glass'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                                <li><a href="javascript:void(0)" onclick="setTextValue('txtFavIconURL','whiteflat_b_remote')">   <?=$pia_lang['FavIcon_color_white'];?>, <?=$pia_lang['FavIcon_logo_black']?>, <?=$pia_lang['FavIcon_mode_flat'];?> (<?=$pia_lang['FavIcon_remote'];?>)</a></li>
	                              </ul>
	                            </div>
	                          </div>
	                        </div>
	                      </div>
        				</div>
        				<div class="col-md-2 text-center">
        					 <img src="<?=$FRONTEND_FAVICON?>" alt="FavIcon Preview" width="50" height="50" style="margin-bottom: 20px;">
        				</div>
     				</div>
					<div class="row">
        				<div class="col-md-12 text-center">
        					<button type="button" class="btn btn-default" style="width:160px; margin-bottom: 20px;" id="btnSaveFavIconSelection" onclick="setFavIconURL()" ><?=$pia_lang['MT_themeselector_apply'];?> </button>
        				</div>
        			</div>
                    </td>
                </tr>
<!-- Pi-hole -------------------------------------------------------------- -->
                <tr><td colspan="2"><h4 class="bottom-border-aqua"><?=$pia_lang['MT_Tools_Tab_Subheadline_h'];?></h4></td></tr>
                <tr class="table_settings">
                    <td class="db_info_table_cell" colspan="2" style="text-align: justify;"><?=$pia_lang['MT_Tools_Tab_Subheadline_h_Intro'];?></td>
                </tr>
                <tr><td class="db_info_table_cell" colspan="2" style="padding: 10px;">
                    <div class="row">
                        <div class="col-md-10">
                          <div class="form-group">
                            <label class="col-xs-3 col-md-2" style="margin-top: 5px;"><i class="mdi mdi-pi-hole" style="font-size: 20px;"></i> URL</label>
                            <div class="col-xs-9 col-md-10">
                                <input class="form-control" id="txtPiholeURL" type="text" value="<?=$FRONTEND_PHBUTTON;?>" style="margin-bottom: 36px;">
                            </div>
                          </div>
                        </div>
                        <div class="col-md-2 text-center"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 text-center">
                            <button type="button" class="btn btn-default" style="width:160px; margin-bottom: 20px;" id="btnSavePiholeSelection" onclick="setPiholeURL()" ><?=$pia_lang['MT_themeselector_apply'];?> </button>
                        </div>
                    </div>

                    </td>
                </tr>
<!-- Column Config -------------------------------------------------------- -->
                <tr><td colspan="2"><h4 class="bottom-border-aqua"><?=$pia_lang['MT_Tools_Tab_Subheadline_b'];?></h4></td></tr>
                <tr>
                    <td colspan="2" style="text-align: center;">
                        <?php $table_config = read_DevListCol();?>
                        <div class="form-group">
                            <div class="table_settings_col_box">
                              <input class="checkbox blue" id="chkConnectionType" type="checkbox" <?= $table_config['ConnectionType'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_TableHead_ConnectionType'];?></label>
                            </div>
                            <div class="table_settings_col_box">
                              <input class="checkbox blue" id="chkOwner" type="checkbox" <?= $table_config['Owner'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_TableHead_Owner'];?></label>
                            </div>
                            <div class="table_settings_col_box">
                              <input class="checkbox blue" id="chkType" type="checkbox" <?= $table_config['Type'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_TableHead_Type'];?></label>
                            </div>
                            <div class="table_settings_col_box">
                              <input class="checkbox blue" id="chkFavorite" type="checkbox" <?= $table_config['Favorites'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_TableHead_Favorite'];?></label>
                            </div>
                            <div class="table_settings_col_box">
                              <input class="checkbox blue" id="chkGroup" type="checkbox" <?= $table_config['Group'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_TableHead_Group'];?></label>
                            </div>
                            <div class="table_settings_col_box">
                              <input class="checkbox blue" id="chkLocation" type="checkbox" <?= $table_config['Location'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_TableHead_Location'];?></label>
                            </div>
                            <div class="table_settings_col_box">
                              <input class="checkbox blue" id="chkfirstSess" type="checkbox" <?= $table_config['FirstSession'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_TableHead_FirstSession'];?></label>
                            </div>
                            <div class="table_settings_col_box">
                              <input class="checkbox blue" id="chklastSess" type="checkbox" <?= $table_config['LastSession'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_TableHead_LastSession'];?></label>
                            </div>
                            <div class="table_settings_col_box">
                              <input class="checkbox blue" id="chklastIP" type="checkbox" <?= $table_config['LastIP'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_TableHead_LastIP'];?></label>
                            </div>
                            <div class="table_settings_col_box">
                              <input class="checkbox blue" id="chkMACtype" type="checkbox" <?= $table_config['MACType'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_TableHead_MAC'];?></label>
                            </div>
                            <div class="table_settings_col_box">
                              <input class="checkbox blue" id="chkMACaddress" type="checkbox" <?= $table_config['MACAddress'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_TableHead_MAC'];?>-Address</label>
                            </div>
                            <div class="table_settings_col_box">
                              <input class="checkbox blue" id="chkMACVendor" type="checkbox" <?= $table_config['MACVendor'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5">Vendor</label>
                            </div>
                            <div class="table_settings_col_box">
                              <input class="checkbox blue" id="chkWakeOnLAN" type="checkbox" <?= $table_config['WakeOnLAN'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_TableHead_WakeOnLAN'];?> (WakeOnLAN)</label>
                            </div>
                            <br>
                            <button type="button" class="btn btn-default" style="margin-top:10px; width:160px;" id="btnSaveDeviceListCol" onclick="askDeviceListCol()" ><?=$pia_lang['Gen_Save'];?></button>
                        </div>
                    </td>
                </tr>
<!-- Header Config -------------------------------------------------------- -->
               <tr><td colspan="2"><h4 class="bottom-border-aqua"><?=$pia_lang['MT_Tools_Tab_Subheadline_i'];?></h4></td></tr>
                <tr>
                    <td colspan="2" style="text-align: center;">
                        <?php $header_config = read_HeaderConfig();?>
                        <div class="form-group">
                            <p style="text-align: left; font-size: 16px;"><?=$pia_lang['NAV_Devices'];?></p>
                            <div class="table_settings_col_box bg-aqua">
                              <input class="checkbox blue" id="chk_dev_all" type="checkbox" <?= $header_config['devices']['all'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_AllDevices'];?></label>
                            </div>
                            <div class="table_settings_col_box bg-green">
                              <input class="checkbox blue" id="chk_dev_con" type="checkbox" <?= $header_config['devices']['con'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_Connected'];?></label>
                            </div>
                            <div class="table_settings_col_box bg-yellow">
                              <input class="checkbox blue" id="chk_dev_fav" type="checkbox" <?= $header_config['devices']['fav'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_Favorites'];?></label>
                            </div>
                            <div class="table_settings_col_box bg-yellow">
                              <input class="checkbox blue" id="chk_dev_new" type="checkbox" <?= $header_config['devices']['new'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_NewDevices'];?></label>
                            </div>
                            <div class="table_settings_col_box bg-red">
                              <input class="checkbox blue" id="chk_dev_dnw" type="checkbox" <?= $header_config['devices']['dnw'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_DownAlerts'];?></label>
                            </div>
                            <div class="table_settings_col_box bg-gray">
                              <input class="checkbox blue" id="chk_dev_arc" type="checkbox" <?= $header_config['devices']['arc'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_Archived'];?></label>
                            </div>
                            <div style="display: block; height: 20px;"></div>
                            <p style="text-align: left; font-size: 16px;"><?=$pia_lang['NAV_ICMPScan'];?></p>
                            <div class="table_settings_col_box bg-aqua">
                              <input class="checkbox blue" id="chk_icmp_all" type="checkbox" <?= $header_config['icmp']['all'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_AllDevices'];?></label>
                            </div>
                            <div class="table_settings_col_box bg-green">
                              <input class="checkbox blue" id="chk_icmp_con" type="checkbox" <?= $header_config['icmp']['con'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_Connected'];?></label>
                            </div>
                            <div class="table_settings_col_box bg-yellow">
                              <input class="checkbox blue" id="chk_icmp_fav" type="checkbox" <?= $header_config['icmp']['fav'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_Favorites'];?></label>
                            </div>
                            <div class="table_settings_col_box bg-red">
                              <input class="checkbox blue" id="chk_icmp_dnw" type="checkbox" <?= $header_config['icmp']['dnw'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_DownAlerts'];?></label>
                            </div>
                            <div class="table_settings_col_box bg-gray">
                              <input class="checkbox blue" id="chk_icmp_arc" type="checkbox" <?= $header_config['icmp']['arc'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_Archived'];?></label>
                            </div>
                            <div style="display: block; height: 20px;"></div>
                            <p style="text-align: left; font-size: 16px;"><?=$pia_lang['NAV_Presence'];?></p>
                            <div class="table_settings_col_box bg-aqua">
                              <input class="checkbox blue" id="chk_pres_all" type="checkbox" <?= $header_config['presence']['all'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_AllDevices'];?></label>
                            </div>
                            <div class="table_settings_col_box bg-green">
                              <input class="checkbox blue" id="chk_pres_con" type="checkbox" <?= $header_config['presence']['con'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_Connected'];?></label>
                            </div>
                            <div class="table_settings_col_box bg-yellow">
                              <input class="checkbox blue" id="chk_pres_fav" type="checkbox" <?= $header_config['presence']['fav'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_Favorites'];?></label>
                            </div>
                            <div class="table_settings_col_box bg-yellow">
                              <input class="checkbox blue" id="chk_pres_new" type="checkbox" <?= $header_config['presence']['new'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_NewDevices'];?></label>
                            </div>
                            <div class="table_settings_col_box bg-red">
                              <input class="checkbox blue" id="chk_pres_dnw" type="checkbox" <?= $header_config['presence']['dnw'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_DownAlerts'];?></label>
                            </div>
                            <div class="table_settings_col_box bg-gray">
                              <input class="checkbox blue" id="chk_pres_arc" type="checkbox" <?= $header_config['presence']['arc'] == 1 ? 'checked' : ''; ?>>
                              <label class="control-label mgleft-5"><?=$pia_lang['Device_Shortcut_Archived'];?></label>
                            </div>
                            <div style="display: block; height: 20px;"></div>
                            <button type="button" class="btn btn-default" style="margin-top:10px; width:160px;" id="btnSaveDeviceListCol" onclick="askListHeaderConfig()" ><?=$pia_lang['Gen_Save'];?></button>
                        </div>
                    </td>
                </tr>

                <tr><td colspan="2"><h4 class="bottom-border-aqua"><?=$pia_lang['MT_Tools_Tab_Subheadline_f'];?></h4></td></tr>
                <tr>
                    <td colspan="2" style="text-align: center;">
                        <?php show_filter_editor();?>
                    </td>
                </tr>
            </table>
        </div>
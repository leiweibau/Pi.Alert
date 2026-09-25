<section id="devices-page" aria-label="<?= h($title); ?>">
  <div class="row g-3 mb-4">
  <?php foreach (array(
      array('all','devicesAll',$labels['all'],'primary','fa-solid fa-laptop'), array('connected','devicesConnected',$labels['connected'],'success','mdi mdi-lan-connect'),
      array('favorites','devicesFavorites',$labels['favorites'],'warning','fa-solid fa-star'), array('new','devicesNew',$labels['new'],'warning','fa-solid fa-plus'),
      array('down','devicesDown',$labels['down'],'danger','mdi mdi-lan-disconnect'), array('archived','devicesArchived',$labels['archived'],'secondary','fa-solid fa-eye-slash')
  ) as [$status,$id,$label,$tone,$icon]):
    $widgetKey = array('all'=>'all','connected'=>'con','favorites'=>'fav','new'=>'new','down'=>'dnw','archived'=>'arc')[$status];
    if (!$headerWidgets[$widgetKey]) continue; ?>
    <div class="<?= h($headerWidgetColumnClass); ?>"><button type="button" class="small-box text-bg-<?= h($tone); ?> pialert-device-filter w-100 border-0 text-start" data-device-status="<?= h($status); ?>" aria-pressed="<?= $status === 'all' ? 'true' : 'false'; ?>"><div class="inner"><h2 id="<?= h($id); ?>" class="mb-1">--</h2><p class="mb-0"><?= h($label); ?></p></div><i class="small-box-icon <?= h($icon); ?>" aria-hidden="true"></i></button></div>
  <?php endforeach; ?>
  </div>

  <?php if ($historyEnabled): ?><section class="card mb-4"><div class="card-header"><h2 class="card-title"><?= h($L('Device_Shortcut_OnlineChart_a','Online history ') . '12 ' . $L('Device_Shortcut_OnlineChart_b','hours')); ?></h2></div><div class="card-body"><div class="pialert-history-chart"><canvas id="OnlineChart"></canvas></div></div></section><?php endif; ?>

  <section id="tableDevicesBox" class="card card-primary card-outline" aria-labelledby="tableDevicesTitle">
    <div class="card-header d-flex align-items-center gap-2"><h2 id="tableDevicesTitle" class="card-title me-auto"><?= h($labels['all']); ?></h2><a href="<?= h(pialert_v4_route('ui_settings')); ?>" class="btn btn-sm btn-outline-secondary" aria-label="<?= h($pia_lang['V4_Configure_Table_Columns']); ?>" title="<?= h($pia_lang['V4_Configure_Table_Columns']); ?>"><i class="fa-solid fa-table-columns" aria-hidden="true"></i></a>
      <?php if ($predefined_filter === ''): ?><a href="devices.php?mod=bulkedit&amp;scansource=<?= rawurlencode($SCANSOURCE); ?>" class="btn btn-sm btn-outline-warning" aria-label="<?= h($L('Device_bulkEditor_mode','Bulk editor')); ?>"><i class="fa-solid fa-pencil"></i></a><button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modal-set-predefined-filter" aria-label="<?= h($L('Device_predef_table_filter','Create filter')); ?>"><i class="fa-solid fa-filter"></i></button><?php else: ?><button type="button" class="btn btn-sm btn-outline-danger" id="deleteDeviceFilter"><i class="fa-solid fa-filter-circle-xmark"></i><span class="visually-hidden"><?= h($labels['filterDeleteTitle']); ?></span></button><?php endif; ?>
    </div>
    <div class="card-body"><div class="table-responsive pa-device-table"><table id="tableDevices" class="table table-bordered table-hover table-striped align-middle w-100"><thead><tr>
    <?php foreach ($deviceColumns as $columnId => $column):
      $label = $column['label'] === null ? $column['fallback'] : $L($column['label'], $column['fallback']);
      $isFavorite = $columnId === 'Favorites';
      $heading = $isFavorite ? $L('Device_TableHead_Favorite_Symbol', '⭐️') : $label;
    ?><th<?= $isFavorite ? ' aria-label="' . h($label) . '" title="' . h($label) . '"' : ''; ?>><?= h(pialert_v4_ui_plain_label($heading)); ?></th><?php endforeach; ?>
    </tr></thead></table><div id="deviceCards" class="pa-device-cards" role="list"></div></div></div>
  </section>

  <div class="modal fade" id="modal-set-predefined-filter" tabindex="-1" aria-labelledby="device-filter-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title fs-5" id="device-filter-title"><?= h($L('Device_predef_table_filter','Create filter')); ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h($pia_lang['Gen_Close']); ?>"></button></div>
    <div class="modal-body"><div class="row g-3"><div class="col-md-4"><label class="form-label" for="txtFilterName"><?= h($L('Device_del_table_filtername','Filter name')); ?></label><input class="form-control" id="txtFilterName"></div><div class="col-md-4"><label class="form-label" for="txtFilterString"><?= h($L('Device_del_table_filterstring','Filter string')); ?></label><input class="form-control" id="txtFilterString"></div><div class="col-md-4"><label class="form-label" for="txtFilterGroup"><?= h($L('Device_del_table_filtergroup','Group')); ?></label><input class="form-control" id="txtFilterGroup"></div></div>
      <fieldset class="mt-3"><legend class="fs-6"><?= h($L('Device_del_table_columns','Excluded columns')); ?></legend><div class="row g-2">
      <?php foreach (array('Name'=>$L('Device_TableHead_Name','Name'),'Owner'=>$L('Device_TableHead_Owner','Owner'),'Group'=>$L('Device_TableHead_Group','Group'),'Location'=>$L('Device_TableHead_Location','Location'),'Type'=>$L('Device_TableHead_Type','Type'),'IP'=>$L('Device_TableHead_LastIP','IP'),'Mac'=>$L('Device_TableHead_MACaddress','MAC'),'Vendor'=>$L('DevDetail_MainInfo_Vendor','Vendor'),'ConnectionType'=>$L('Device_TableHead_ConnectionType','Connection type')) as $key=>$label): ?><div class="col-6 col-md-4"><div class="form-check"><input class="form-check-input blue" id="chkFilter<?= h($key); ?>" type="checkbox"><label class="form-check-label" for="chkFilter<?= h($key); ?>"><?= h(pialert_v4_ui_plain_label($label)); ?></label></div></div><?php endforeach; ?>
      </div></fieldset>
    </div><div class="modal-footer"><button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal"><?= h($L('Gen_Close','Close')); ?></button><button type="button" class="btn btn-primary" id="btnFilterSave"><?= h($L('Gen_Save','Save')); ?></button></div>
  </div></div></div>
</section>

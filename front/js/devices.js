(function (window, document, $) {
  'use strict';

  var configElement = document.getElementById('devices-page-config');
  if (!configElement || !$) return;
  var config = JSON.parse(configElement.textContent || '{}');
  var labels = config.labels || {};
  var refreshTimer = null;
  var deviceStatus = 'all';
  var table = null;
  var actionMap = {};
  var historyChart = null;
  var historyResizeObserver = null;
  var preferenceTimer = null;
  var lastSavedPreferences = JSON.stringify({ length: config.pageLength, order: config.order });

  function text (value) { return String(value == null ? '' : value); }
  function setCookie (name, value) { document.cookie = name + '=' + value + '; SameSite=Strict; path=/'; }
  function savePreferences (patch) {
    return window.pialertPost('php/server/v4_ui_settings.php', { action: 'save', patch: JSON.stringify(patch) })
      .fail(function (xhr) { lastSavedPreferences = ''; console.error('Device table preferences could not be saved', xhr.status); });
  }
  function tablePreferences () {
    return { length: table.page.len(), order: table.order().map(function (part) { return [part[0], part[1]]; }) };
  }
  function persistTablePreferences (keepalive) {
    if (!table) return;
    var current = tablePreferences();
    var signature = JSON.stringify(current);
    if (signature === lastSavedPreferences) return;
    var order = current.order.map(function (part) { return [config.columnIds[part[0]], part[1]]; });
    var patch = { 'devices.page_length': current.length, 'devices.order': order };
    lastSavedPreferences = signature;
    if (keepalive) {
      var body = new URLSearchParams({ action: 'save', patch: JSON.stringify(patch) });
      var csrf = document.querySelector('meta[name="csrf-token"]');
      window.fetch('php/server/v4_ui_settings.php', { method: 'POST', body: body, credentials: 'same-origin', keepalive: true, headers: { 'X-CSRF-Token': csrf ? csrf.content : '' } })
        .catch(function () { console.error('Device table preferences could not be saved'); });
    } else savePreferences(patch);
  }
  function scheduleTablePreferences () {
    window.clearTimeout(preferenceTimer);
    preferenceTimer = window.setTimeout(function () {
      preferenceTimer = null;
      persistTablePreferences(false);
    }, 300);
  }
  function makeLink (href, value, className) {
    var link = document.createElement('a');
    link.href = href; link.textContent = text(value);
    if (className) link.className = className;
    return link;
  }
  function endpoint (action, extra) {
    var params = new URLSearchParams(Object.assign({ action: action }, extra || {}));
    return 'php/server/devices.php?' + params.toString();
  }

  function statusInfo (status) {
    var states = {
      Down: ['danger', window.pialertV4Text('V4_Down')], NewON: ['pialert-status-new-online', window.pialertV4Text('V4_New')], NewOFF: ['pialert-status-new-offline', window.pialertV4Text('V4_New')], OnlineV: ['success', window.pialertV4Text('V4_Online_Verified')],
      'On-line': ['success', window.pialertV4Text('V4_Online')], 'Off-line': ['secondary', window.pialertV4Text('V4_Offline')], Archived: ['secondary', labels.archived]
    };
    return states[status] || ['info', ''];
  }

  function appendMobileField (container, label, value) {
    var row = document.createElement('div');
    row.className = 'pa-device-card-field';
    var term = document.createElement('span'); term.className = 'pa-device-card-label'; term.textContent = label;
    var data = document.createElement('span'); data.textContent = text(value) || '—';
    row.append(term, data); container.append(row);
  }

  function makeDeleteButton (rowData, forCard) {
    var button=document.createElement('button'); button.type='button'; button.className='btn btn-sm btn-outline-danger delete-device'+(forCard?' pa-device-card-action':'');
    button.dataset.deviceMac=text(rowData[11]); button.setAttribute('aria-label',labels.deleteDeviceTitle+': '+text(rowData[0])); button.title=button.getAttribute('aria-label');
    var icon=document.createElement('i'); icon.className='fa-solid fa-trash'; icon.setAttribute('aria-hidden','true'); button.append(icon);
    return button;
  }

  function renderMobileCards (api) {
    var cards = document.getElementById('deviceCards');
    if (!cards || !['glas', 'piano', 'console'].includes(document.documentElement.getAttribute('data-pialert-theme'))) return;
    var tableNode = api.table().node();
    if (tableNode.parentNode && cards.previousElementSibling !== tableNode) tableNode.insertAdjacentElement('afterend', cards);
    cards.replaceChildren();
    var rows = api.rows({ search: 'applied', order: 'applied', page: 'current' }).data().toArray();
    if (!rows.length) {
      var empty = document.createElement('p'); empty.className = 'pa-device-cards-empty'; empty.textContent = labels.empty || window.pialertV4Text('V4_No_Data'); cards.append(empty); return;
    }
    rows.forEach(function (rowData) {
      var mac = text(rowData[11]);
      var href = 'deviceDetails.php?mac=' + encodeURIComponent(mac);
      var state = statusInfo(rowData[13]);
      var card = document.createElement('article'); card.className = 'pa-device-card'; card.setAttribute('role', 'listitem');
      var icon = document.createElement('i'); icon.className = 'fa-solid fa-laptop pa-device-card-icon'; icon.setAttribute('aria-hidden', 'true');
      var content = document.createElement('div'); content.className = 'pa-device-card-content';
      var name = makeLink(href, text(rowData[0]) || window.pialertV4Text('V4_Unknown_Name'), 'pa-device-card-name'); content.append(name);
      appendMobileField(content, labels.type || window.pialertV4Text('V4_Type'), rowData[3]);
      appendMobileField(content, labels.lastIp || window.pialertV4Text('V4_Last_IP'), rowData[9]);
      appendMobileField(content, labels.mac || window.pialertV4Text('V4_MAC_Address'), mac);
      var status = makeLink(href, state[1] || text(rowData[13]), 'badge pa-device-card-status pialert-device-status-link ' + (state[0].startsWith('pialert-status-') ? state[0] : 'text-bg-' + state[0]));
      status.setAttribute('aria-label', (labels.status || window.pialertV4Text('V4_Status')) + ': ' + (state[1] || text(rowData[13])));
      var details = makeLink(href, '', 'pa-device-card-details');
      details.setAttribute('aria-label', (labels.details || window.pialertV4Text('V4_Details')) + ': ' + (text(rowData[0]) || mac));
      var chevron = document.createElement('i'); chevron.className = 'fa-solid fa-chevron-right'; chevron.setAttribute('aria-hidden', 'true'); details.append(chevron);
      card.append(icon, content, status, details);
      if (!(config.hiddenColumns || []).includes(19)) { card.classList.add('has-actions'); var actionArea=document.createElement('div'); actionArea.className='pa-device-card-actions'; window.pialertEntityActions.append(actionArea,actionMap[mac],makeDeleteButton(rowData,true)); card.append(actionArea); }
      cards.append(card);
    });
  }

  function initializeTable (rows, order) {
    table = $('#tableDevices').DataTable({
      paging: true, lengthChange: true, lengthMenu: [[10,25,50,100,500,-1],[10,25,50,100,500,labels.lengthAll || 'All']],
      searching: true, search: { search: config.predefinedFilter || '' }, ordering: true, info: true, autoWidth: false,
      pageLength: Number.isInteger(rows) ? rows : 10, order: Array.isArray(order) ? order : [[3,'desc'],[0,'asc']],
      ajax: {url:endpoint('getDevicesList', { scansource: config.scanSource, status: deviceStatus }),dataSrc:function(response){actionMap=response && response.actions && typeof response.actions==='object' ? response.actions : {};return Array.isArray(response.data) ? response.data : [];}},
      columnDefs: [
        { targets: '_all', render: $.fn.dataTable.render.text() },
        { visible: false, targets: config.hiddenColumns || [14,15,16,18] },
        { className: 'text-center', targets: [4,9,10,11,13,17,19] },
        { className: 'pialert-device-timestamp', width: '7rem', targets: [7,8] },
        { width: '30px', targets: [10] }, { width: '0px', targets: [13] }, { width: '3rem', targets: [17] }, { width: '10rem', targets: [19] },
        { orderData: [14], targets: [9] }, { targets: config.filterFields || [], searchable: false },
        { targets: [0], createdCell: function (td, cellData, rowData) {
          var colors = { Down:'var(--bs-danger)',NewON:'var(--bs-warning)',NewOFF:'var(--bs-warning)',OnlineV:'var(--bs-success)','On-line':'var(--bs-success)' };
          td.replaceChildren(makeLink('deviceDetails.php?mac=' + encodeURIComponent(text(rowData[11])), cellData, text(rowData[11]).indexOf('Internet') === 0 ? 'text-danger' : ''));
          td.style.borderLeft = colors[rowData[13]] ? '2px solid ' + colors[rowData[13]] : '';
        } },
        { targets: [4], createdCell: function (td, cellData) { td.replaceChildren(); if (cellData == 1) { var icon=document.createElement('i'); icon.className='fa-solid fa-star text-warning'; td.append(icon); } } },
        { targets: [9], createdCell: function (td, cellData, rowData) { td.textContent=(rowData[18]===true||rowData[18]===1?'* ':'')+text(cellData)+(rowData[18]===true||rowData[18]===1?' *':''); td.classList.toggle('nmap-queued-ip',rowData[18]===true||rowData[18]===1); } },
        { targets: [10], createdCell: function (td, cellData) { td.replaceChildren(); if (cellData == 1) { var icon=document.createElement('i'); icon.className='fa-solid fa-shuffle text-warning'; icon.title=window.pialertV4Text('V4_Random_MAC'); td.append(icon); } } },
        { targets: [11], createdCell: function (td, cellData) { var value=text(cellData); td.textContent=value.indexOf('Internet')===0&&value.length>20?value.slice(0,20)+'…':value; } },
        { targets: [13], createdCell: function (td, _cellData, rowData) { var state=statusInfo(rowData[13]); var tone=state[0].startsWith('pialert-status-')?state[0]:'text-bg-'+state[0]; var link=makeLink('deviceDetails.php?mac='+encodeURIComponent(text(rowData[11])),state[1],'badge pialert-device-status-link '+tone); td.replaceChildren(link); } },
        { targets: [17], data: null, orderable: false, searchable: false, createdCell: function (td, _cellData, rowData) {
          td.replaceChildren();
          if (['Mini PC','Server','Laptop','NAS','PC','Hypervisor','VM Guest'].indexOf(text(rowData[3]).trim())===-1 || text(rowData[11]).startsWith('Internet')) return;
          var button=document.createElement('button'); button.type='button'; button.className='btn btn-sm btn-outline-danger pialert-device-wol';
          button.setAttribute('aria-label', labels.wolTitle + ': ' + text(rowData[0])); button.title=button.getAttribute('aria-label');
          var icon=document.createElement('i'); icon.className='fa-solid fa-power-off'; icon.setAttribute('aria-hidden','true'); button.append(icon);
          button.addEventListener('click',function(){askWakeOnLan(text(rowData[11]),text(rowData[9]),text(rowData[0]));}); td.append(button);
        } },
        { targets: [19], data: null, orderable: false, searchable: false, createdCell: function (td, _cellData, rowData) {
          window.pialertEntityActions.append(td,actionMap[text(rowData[11])],makeDeleteButton(rowData, false));
        } }
      ],
      processing: true,
      drawCallback: function () { renderMobileCards(this.api()); window.pialertEntityActions.verifyIcons(); },
      language: window.pialertV4DataTableLanguage({ processing:window.pialertV4Text('V4_Loading'),emptyTable:window.pialertV4Text('V4_No_Data'),lengthMenu:labels.lengthMenu,search:(labels.search||window.pialertV4Text('V4_Table_Search').replace(/:$/, ''))+': ',paginate:{next:labels.next,previous:labels.previous},info:labels.info })
    });
    $('#tableDevices').on('length.dt',function(){scheduleTablePreferences();});
    $('#tableDevices').on('order.dt',function(){scheduleTablePreferences();saveVisibleRows();});
    $('#tableDevices').on('search.dt',saveVisibleRows);
    $('#devices-page').on('click','.delete-device',function(){
      var mac=this.dataset.deviceMac;
      if(!mac)return;
      window.showModalWarning(labels.deleteDeviceTitle,labels.deleteDeviceWarning,labels.cancel,labels.delete,function(){
        window.pialertPost(endpoint('deleteDevice'),{mac:mac},function(message){window.showMessage(message);table.ajax.reload(null,false);getDevicesTotals();})
          .fail(function(xhr){window.showMessage(xhr.responseText||xhr.statusText||window.pialertV4Text('V4_Request_Failed'));});
      });
    });
    document.querySelectorAll('.pialert-device-filter').forEach(function(button){button.addEventListener('click',function(){getDevicesList(button.dataset.deviceStatus);});});
    getDevicesTotals();
  }

  function saveVisibleRows () { if (table) setCookie('devicesList',JSON.stringify(table.column(16,{search:'applied'}).data().toArray())); }
  function getDevicesTotals () {
    window.clearTimeout(refreshTimer);
    $.get(endpoint('getDevicesTotals',{scansource:config.scanSource}),function(data){
      var totals=typeof data==='string'?JSON.parse(data):data; ['devicesAll','devicesConnected','devicesFavorites','devicesNew','devicesDown','devicesArchived'].forEach(function(id,index){var node=document.getElementById(id);if(node)node.textContent=Number(totals[index]||0).toLocaleString();});
      refreshTimer=window.setTimeout(getDevicesTotals,60000);
    });
  }
  function getDevicesList (status) {
    deviceStatus=status; var tones={all:'primary',connected:'success',favorites:'warning',new:'warning',down:'danger',archived:'secondary'};
    var title=document.getElementById('tableDevicesTitle'); title.textContent=labels[status]||labels.devices; var box=document.getElementById('tableDevicesBox');
    box.className='card card-'+(tones[status]||'secondary')+' card-outline';
    document.querySelectorAll('.pialert-device-filter').forEach(function(button){button.setAttribute('aria-pressed',String(button.dataset.deviceStatus===status));});
    table.ajax.url(endpoint('getDevicesList',{scansource:config.scanSource,status:status})).load();
  }
  window.addEventListener('pageshow',function(event){if(event.persisted && table)table.ajax.reload(null,false);});

  function askWakeOnLan (mac, ip, name) { window._deviceWake={mac:mac,ip:ip}; window.showModalWarning(labels.wolTitle+' (<span class="text-danger">'+text(name)+'</span>)',labels.wolText,labels.cancel,labels.run,'wakeonlan'); }
  window.wakeonlan=function(){var value=window._deviceWake||{};window.pialertPost(endpoint('wakeonlan'),{mac:value.mac,ip:value.ip},window.showMessage);};
  function filterChanged (response, successPrefix) {
    window.showMessage(response);
    if (String(response || '').trim().startsWith(String(successPrefix || '').trim())) {
      window.setTimeout(function(){window.location.assign('devices.php');}, 1200);
    }
  }
  window.DeleteDeviceFilter=function(){window.pialertPost(endpoint('DeleteDeviceFilter'),{filterid:config.filterId,filterstring:config.predefinedFilter},function(response){filterChanged(response,labels.filterDeletedPrefix);});};
  window.BulkDeletion=function(){var hosts=[];document.querySelectorAll('.hostselection:checked').forEach(function(input){hosts.push(input.dataset.hostId);});var payload=new URLSearchParams();hosts.forEach(function(host){payload.append('hosts[]',host);});window.pialertPost(endpoint('BulkDeletion'),payload,function(message){window.showMessage(message);});};

  function initializeList () {
    initializeTable(config.pageLength, config.order);
    var saveFilter=document.getElementById('btnFilterSave'); if(saveFilter)saveFilter.addEventListener('click',function(){
      window.pialertPost(endpoint('SetDeviceFilter'),{filtername:$('#txtFilterName').val(),filterstring:$('#txtFilterString').val(),filtergroup:$('#txtFilterGroup').val(),fname:+$('#chkFilterName')[0].checked,fowner:+$('#chkFilterOwner')[0].checked,fgroup:+$('#chkFilterGroup')[0].checked,flocation:+$('#chkFilterLocation')[0].checked,ftype:+$('#chkFilterType')[0].checked,fip:+$('#chkFilterIP')[0].checked,fmac:+$('#chkFilterMac')[0].checked,fvendor:+$('#chkFilterVendor')[0].checked,fconnectiont:+$('#chkFilterConnectionType')[0].checked},function(response){filterChanged(response,labels.filterSavedPrefix);});
    });
    var deleteFilter=document.getElementById('deleteDeviceFilter'); if(deleteFilter)deleteFilter.addEventListener('click',function(){window.showModalWarning(labels.filterDeleteTitle,labels.filterDeleteText,labels.cancel,labels.delete,'DeleteDeviceFilter');});
    var modal=document.getElementById('modal-set-predefined-filter');if(modal)modal.addEventListener('shown.bs.modal',function(){var field=document.getElementById('txtFilterString');if(field)field.value=table ? table.search() : '';});
    var chartCanvas = document.getElementById('OnlineChart');
    if (window.Chart && chartCanvas) {
      var theme = document.documentElement.getAttribute('data-pialert-theme');
      var themed = theme === 'glas' || theme === 'piano' || theme === 'console';
      var chartText = themed ? (theme === 'console' ? '#9cf7a8' : (theme === 'piano' ? '#d7dcdf' : '#d8e7f3')) : undefined;
      var chartGrid = themed ? (theme === 'console' ? 'rgba(74,222,128,.14)' : (theme === 'piano' ? 'rgba(255,255,255,.12)' : 'rgba(183,217,242,.14)')) : undefined;
      var chartColors = theme === 'console' ? ['#48e57c', '#f05d65', '#82958a'] : (themed ? ['rgba(40,203,131,.9)', 'rgba(250,82,102,.92)', 'rgba(154,177,199,.78)'] : ['rgba(25,135,84,.65)', 'rgba(220,53,69,.65)', 'rgba(108,117,125,.65)']);
      historyChart = new window.Chart(chartCanvas,{type:'bar',data:{labels:config.history.time,datasets:[{label:window.pialertV4Text('V4_Online'),data:config.history.online,backgroundColor:chartColors[0]},{label:window.pialertV4Text('V4_Offline_Down'),data:config.history.down,backgroundColor:chartColors[1]},{label:window.pialertV4Text('V4_Archived'),data:config.history.archived,backgroundColor:chartColors[2]}]},options:{maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{color:chartText,usePointStyle:true,pointStyle:'rectRounded'}},tooltip:{mode:'index',intersect:false}},scales:{x:{stacked:true,grid:{color:chartGrid},ticks:{color:chartText,maxRotation:0,autoSkip:true,maxTicksLimit:10}},y:{stacked:true,beginAtZero:true,grid:{color:chartGrid},ticks:{color:chartText,stepSize:1,precision:0}}}}});
      if (window.ResizeObserver) {
        historyResizeObserver = new window.ResizeObserver(function (entries) {
          var width = entries[0] ? entries[0].contentRect.width : 0;
          var limit = width < 520 ? 5 : (width < 900 ? 7 : 10);
          if (historyChart.options.scales.x.ticks.maxTicksLimit !== limit) {
            historyChart.options.scales.x.ticks.maxTicksLimit = limit;
            historyChart.resize(); historyChart.update('none');
          }
        });
        historyResizeObserver.observe(chartCanvas.parentElement);
      }
    }
  }

  function initializeBulk () {
    document.querySelectorAll('.bulk-enable').forEach(function(input){input.addEventListener('change',function(){var target=document.getElementById(input.dataset.bulkTarget);target.disabled=!input.checked;if(!input.checked){target.checked=false;if(target.type!=='checkbox')target.value='';}});});
    document.querySelectorAll('.bulk-visibility').forEach(function(button){button.addEventListener('click',function(){var items=document.querySelectorAll('.'+button.dataset.bulkClass);var hide=Array.prototype.some.call(items,function(item){return !item.hidden;});items.forEach(function(item){item.hidden=hide;});});});
    var onlyNew=false;document.getElementById('bulkOnlyNew').addEventListener('click',function(event){onlyNew=!onlyNew;document.querySelectorAll('.bulked_dev_box').forEach(function(item){item.hidden=onlyNew&&!item.classList.contains('bulked_new_dev');});event.currentTarget.textContent=onlyNew?labels.allDevices:labels.newDevices;});
    document.getElementById('deviceSearch').addEventListener('input',function(event){var search=event.target.value.trim().toLowerCase();document.querySelectorAll('.bulked_dev_box').forEach(function(item){item.hidden=search!==''&&item.textContent.toLowerCase().indexOf(search)===-1;});});
    var allSelected=false;document.getElementById('bulked_checkall').addEventListener('click',function(event){allSelected=!allSelected;document.querySelectorAll('.hostselection').forEach(function(input){input.checked=allSelected;});event.currentTarget.textContent=allSelected?labels.selectNone:labels.selectAll;});
    document.getElementById('bulkVisibleToggle').addEventListener('click',function(event){var visible=Array.prototype.filter.call(document.querySelectorAll('.bulked_dev_box'),function(item){return !item.hidden;});var shouldCheck=visible.some(function(item){return !item.querySelector('.hostselection').checked;});visible.forEach(function(item){item.querySelector('.hostselection').checked=shouldCheck;});event.currentTarget.textContent=shouldCheck?labels.selectVisibleNone:labels.selectVisible;});
    document.getElementById('btnBulkDeletion').addEventListener('click',function(){window.showModalWarning(labels.bulkDeleteTitle,labels.bulkDeleteText,labels.cancel,labels.delete,'BulkDeletion');});
  }

  if (document.getElementById('devices-page')) initializeList();
  if (document.getElementById('devices-bulk-page')) initializeBulk();
  window.addEventListener('pagehide',function(){window.clearTimeout(refreshTimer);if(historyResizeObserver)historyResizeObserver.disconnect();if(preferenceTimer){window.clearTimeout(preferenceTimer);persistTablePreferences(true);}});
})(window, document, window.jQuery);

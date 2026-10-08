(function (window, document, $) {
  'use strict';

  var configElement = document.getElementById('devices-page-config');
  if (!configElement || !$) return;
  var config = JSON.parse(configElement.textContent || '{}');
  var labels = config.labels || {};
  var logical = window.pialertLogicalDevices;
  var logicalLabels = config.logicalLabels || {};
  var grouped = config.viewMode === 'devices';
  var queryState = new URLSearchParams(window.location.search);
  var currentSearch = queryState.has('list_search') ? queryState.get('list_search') : config.predefinedFilter || '';
  var logicalRows = new Map();
  var openLogicalId = null;
  var modalTrigger = null;
  var requestVersion = 0;
  var restoringPage = true;
  var initialPage = Math.max(0, Math.min(100000, Number(queryState.get('list_page')) || 0));
  var memberModal = document.getElementById('logical-members-modal');
  var wakeConfirmationPending = false;
  var pluralRules;
  var pluralLocale = (document.documentElement.lang || 'en').replace('_', '-');
  pluralLocale = ({'cz-cs':'cs','dk-da':'da','se-sv':'sv','ua-uk':'uk'})[pluralLocale.toLowerCase()] || pluralLocale;
  try { pluralRules = new Intl.PluralRules(pluralLocale); } catch (_) { pluralRules = new Intl.PluralRules('en'); }
  function L(key, values) { return text(logicalLabels[key]).replace(/\{(\w+)\}/g, function (_, name) { return text((values || {})[name]); }); }
  function countLabel(key, count, values) { var form = pluralRules.select(count); return L(key + (form === 'one' ? 'One' : form === 'few' && logicalLabels[key + 'Few'] ? 'Few' : 'Many'), Object.assign({count: count}, values || {})); }
  function returnUrl() {
    var params = new URLSearchParams(window.location.search);
    params.delete('mod'); params.set('scansource', config.scanSource);
    params.set('list_search', currentSearch); params.set('list_status', deviceStatus);
    if (table) { params.set('list_page', table.page()); params.set('list_order', JSON.stringify(table.order())); params.set('list_length', table.page.len()); }
    return 'devices.php?' + params.toString();
  }
  function detailsUrl(member, id) {
    return 'deviceDetails.php?' + new URLSearchParams({mac: text(member.dev_MAC), logical_id: text(id), return_url: returnUrl()}).toString();
  }
  function groupButton(row, label, title) {
    var button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-link p-0 pialert-logical-open';
    button.dataset.logicalId = row._logical.id; button.textContent = text(label);
    if (title) { button.title = title; button.classList.add('pialert-logical-tooltip'); button.setAttribute('aria-label', text(label) + ': ' + title); }
    button.addEventListener('click', function () { openMembers(row._logical.id, button); });
    return button;
  }
  function disposeTooltips() {
    document.querySelectorAll('#tableDevices .pialert-logical-tooltip').forEach(function (node) {
      var tooltip = window.bootstrap.Tooltip.getInstance(node); if (tooltip) tooltip.dispose();
    });
  }
  function initializeTooltips() {
    document.querySelectorAll('#tableDevices .pialert-logical-tooltip').forEach(function (node) {
      window.bootstrap.Tooltip.getOrCreateInstance(node, {trigger: node.tagName === 'BUTTON' ? 'hover focus' : 'hover focus click', container: 'body'});
    });
  }
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') document.querySelectorAll('#tableDevices .pialert-logical-tooltip').forEach(function (node) { var tooltip = window.bootstrap.Tooltip.getInstance(node); if (tooltip) tooltip.hide(); });
  });
  function badge(status, label) {
    var state = statusInfo(status), node = document.createElement('span');
    node.className = 'badge pialert-device-status-link ' + (state[0].startsWith('pialert-status-') ? state[0] : 'text-bg-' + state[0]);
    node.textContent = label == null ? state[1] : label;
    return node;
  }
  function renderGroupName(td, row) {
    var state = row._logical;
    td.classList.toggle('pialert-device-partial', row[13] === 'Partial');
    td.replaceChildren(groupButton(row, row[0]));
    var hints = document.createElement('div'); hints.className = 'pialert-logical-hints small';
    function hint(value) { var span = document.createElement('span'); span.textContent = value; hints.append(span); }
    if ((currentSearch !== '' || deviceStatus !== 'all') && state.matches.length) hint(countLabel('LD_Matches', state.members.length, {count: state.matches.length, total: state.members.length}));
    if (state.ownMatch) hint(L('LD_OwnMatch'));
    if (state.archived) hint(countLabel('LD_Archived', state.archived));
    if (hints.childNodes.length) td.append(hints);
  }
  function renderMembers() {
    var row = logicalRows.get(text(openLogicalId));
    if (!row) { if (memberModal && memberModal.classList.contains('show')) window.bootstrap.Modal.getOrCreateInstance(memberModal).hide(); return; }
    var state = row._logical, body = document.getElementById('logical-members-body');
    var wolColumn = config.columnIds.indexOf('WakeOnLAN');
    var showWol = table && wolColumn !== -1 && table.column(wolColumn).visible();
    document.getElementById('logical-members-wol').hidden = !showWol;
    document.getElementById('logical-members-title').textContent = L('LD_MembersTitle', {name: row[0]});
    body.replaceChildren();
    state.members.forEach(function (member) {
      var tr = document.createElement('tr'), nameCell = document.createElement('td'), ipCell = document.createElement('td'), statusCell = document.createElement('td');
      nameCell.append(makeLink(detailsUrl(member, state.id), member.dev_Name));
      var mac = document.createElement('div'); mac.className = 'small'; mac.textContent = text(member.dev_MAC);
      var source = document.createElement('span'); source.className = 'pialert-member-source'; source.textContent = ' (' + text(member.scan_source_name || member.dev_ScanSource) + ')'; mac.append(source); nameCell.append(mac);
      var matched = (currentSearch !== '' || deviceStatus !== 'all') && state.matches.indexOf(text(member.dev_MAC)) !== -1;
      if (matched) { tr.className = 'pialert-logical-member-match'; var marker = document.createElement('span'); marker.className = 'small pialert-member-match-label'; marker.textContent = L('LD_MatchedMember'); nameCell.append(marker); }
      ipCell.textContent = text(member.dev_LastIP);
      var archived = Number(member.dev_Archived) === 1;
      statusCell.className = 'text-center';
      statusCell.append(badge(archived ? 'Archived' : member.dev_Status));
      tr.append(nameCell, ipCell, statusCell);
      if (showWol) {
        var wolCell = document.createElement('td'); wolCell.className = 'text-center pialert-device-wol-cell';
        var wolButton = makeWakeOnLanButton(member.dev_DeviceType, member.dev_MAC, member.dev_LastIP, member.dev_Name);
        if (wolButton) wolCell.append(wolButton);
        tr.append(wolCell);
      }
      body.append(tr);
    });
    var own = document.getElementById('logical-members-own-match'); own.hidden = !state.ownMatch; own.textContent = L('LD_OwnMatch');
  }
  function openMembers(id, trigger) {
    openLogicalId = id; modalTrigger = trigger; renderMembers();
    window.bootstrap.Modal.getOrCreateInstance(memberModal).show();
  }
  if (memberModal) memberModal.addEventListener('hidden.bs.modal', function () {
    var closedId = openLogicalId; openLogicalId = null;
    if (modalTrigger && !modalTrigger.isConnected) modalTrigger = Array.from(document.querySelectorAll('.pialert-logical-open')).find(function (button) { return text(button.dataset.logicalId) === text(closedId) && button.textContent === modalTrigger.textContent; }) || modalTrigger;
    if (modalTrigger && modalTrigger.isConnected) modalTrigger.focus();
    else { var fallback = document.getElementById('device-table-search'); if (!fallback) fallback = document.querySelector('#tableDevices_wrapper .dt-search input'); if (fallback) fallback.focus(); }
  });
  function passesSearch(_search, row) { return logical.filter(row, config.scanSource, deviceStatus, currentSearch, config.filterFields || []); }
  function showLoadError(failed) { var error = document.getElementById('logical-load-error'); if (error) { error.hidden = !failed; error.textContent = L('LD_LoadError'); } }
  function loadTableData(_request, callback) {
    var version = ++requestVersion;
    var requests = [$.getJSON(endpoint('getDevicesList', {scansource: config.scanSource, status: deviceStatus})), $.getJSON(endpoint('getDevicesTotals', {scansource: config.scanSource}))];
    if (grouped) requests.push($.getJSON('php/server/logical_devices.php?action=list'));
    Promise.all(requests).then(function (results) {
      if (version !== requestVersion) return;
      if (grouped && (!results[2] || !results[2].ok || !Array.isArray(results[2].devices))) throw new Error('Incomplete logical device response');
      var response = results[0], groups = grouped ? results[2].devices : [];
      actionMap = response && response.actions && typeof response.actions === 'object' ? response.actions : {};
      var data = Array.isArray(response.data) ? response.data : [];
      data.forEach(function (row) {
        row._assignment = (response.assignments || {})[text(row[11])] || null;
        row[20] = row._assignment ? text(row._assignment.name) : '';
      });
      if (grouped) data = logical.rows(data, groups, config.scanSource, deviceStatus);
      logicalRows.clear(); groups.forEach(function (group) { logicalRows.set(text(group.id), logical.aggregate(group)); }); data.forEach(function (row) { if (row._logical) logicalRows.set(text(row._logical.id), row); });
      showLoadError(false);
      var totals = grouped ? logical.totals(results[1], groups, config.scanSource) : results[1];
      ['devicesAll','devicesConnected','devicesFavorites','devicesNew','devicesDown','devicesArchived'].forEach(function(id,index){var node=document.getElementById(id);if(node)node.textContent=Number(totals[index]||0).toLocaleString();});
      callback({data: data});
      if (restoringPage && table) { restoringPage = false; if (initialPage) table.page(Math.min(initialPage, Math.max(0, table.page.info().pages - 1))).draw('page'); }
      if (openLogicalId !== null) renderMembers();
    }).catch(function () { if (version === requestVersion) { showLoadError(true); callback({data: []}); } }).finally(function () {
      if (version !== requestVersion) return;
      window.clearTimeout(refreshTimer); refreshTimer = window.setTimeout(function () { table.ajax.reload(null, false); }, 60000);
    });
  }
  var refreshTimer = null;
  var deviceStatus = ['all','connected','favorites','new','down','archived'].indexOf(queryState.get('list_status')) !== -1 ? queryState.get('list_status') : 'all';
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
      Partial: ['pialert-status-partial', L('LD_Partial')], 'On-line': ['success', window.pialertV4Text('V4_Online')], 'Off-line': ['secondary', window.pialertV4Text('V4_Offline')], Archived: ['secondary', labels.archived]
    };
    return states[status] || ['info', ''];
  }

  function makeDeleteButton (rowData) {
    var button=document.createElement('button'); button.type='button'; button.className='btn btn-sm btn-outline-danger delete-device';
    button.dataset.deviceMac=text(rowData[11]); button.setAttribute('aria-label',labels.deleteDeviceTitle+': '+text(rowData[0])); button.title=button.getAttribute('aria-label');
    var icon=document.createElement('i'); icon.className='fa-solid fa-trash'; icon.setAttribute('aria-hidden','true'); button.append(icon);
    return button;
  }

  function makeWakeOnLanButton (deviceType, mac, ip, name) {
    if (['Mini PC','Server','Laptop','NAS','PC','Hypervisor','VM Guest'].indexOf(text(deviceType).trim()) === -1 || text(mac).startsWith('Internet')) return null;
    var button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-sm btn-outline-danger pialert-device-wol';
    button.setAttribute('aria-label', labels.wolTitle + ': ' + text(name)); button.title = button.getAttribute('aria-label');
    var icon = document.createElement('i'); icon.className = 'fa-solid fa-power-off'; icon.setAttribute('aria-hidden','true'); button.append(icon);
    button.addEventListener('click', function () { askWakeOnLan(text(mac), text(ip), text(name)); });
    return button;
  }

  function initializeTable (rows, order) {
    try {
      var restored = JSON.parse(queryState.get('list_order'));
      if (Array.isArray(restored) && restored.length > 0 && restored.length <= 3 && restored.every(function (part) { return Array.isArray(part) && part.length === 2 && Number.isInteger(part[0]) && part[0] >= 0 && part[0] < 21 && [17,19].indexOf(part[0]) === -1 && ['asc','desc'].indexOf(part[1]) !== -1; })) order = restored;
    } catch (_) {}
    var restoredLength = Number(queryState.get('list_length'));
    if ([-1,10,25,50,100,500].indexOf(restoredLength) !== -1) rows = restoredLength;
    $.fn.dataTable.ext.type.order['pialert-address-asc'] = function (a,b) { return logical.compareAddress(a,b,1); };
    $.fn.dataTable.ext.type.order['pialert-address-desc'] = function (a,b) { return logical.compareAddress(a,b,-1); };
    // DataTables 3 finishes its built-in string search before firing search.dt.
    // Replace only this table's grouped result indexes at that boundary. Keeping
    // its normal search API/input preserves saved filters and external callers.
    $('#tableDevices').on('search.dt.logical', function (_event, settings) {
      var api = new $.fn.dataTable.Api(settings);
      currentSearch = text(api.search());
      if (!grouped) return;
      settings.display = settings.displayMaster.filter(function (index) {
        var entry = settings.data[index];
        return entry && passesSearch('', entry.data);
      });
    });
    table = $('#tableDevices').DataTable({
      paging: true, lengthChange: true, lengthMenu: [[10,25,50,100,500,-1],[10,25,50,100,500,labels.lengthAll || 'All']],
      searching: true, search: { search: currentSearch }, ordering: true, info: true, autoWidth: true,
      pageLength: Number.isInteger(rows) ? rows : 10, order: Array.isArray(order) ? order : [[3,'desc'],[0,'asc']],
      ajax: loadTableData,
      columnDefs: [
        { targets: '_all', render: function(value, type, row, meta) {
          if (type === 'sort' && (meta.col === 9 || meta.col === 11)) return logical.addressSortKey(row, meta.col);
          return $.fn.dataTable.render.text().display(value);
        } },
        { visible: false, targets: config.hiddenColumns || [14,15,16,18] },
        { className: 'pialert-device-name', targets: [0] },
        { className: 'pialert-device-favorite', targets: [4] },
        { className: 'text-center', targets: [4,9,10,11,13] },
        { className: 'pialert-device-last-ip', targets: [9] },
        { className: 'pialert-device-mac', targets: [11] },
        { className: 'pialert-device-status', targets: [13] },
        { className: 'text-center pialert-device-wol-cell', targets: [17] },
        { className: 'text-center pialert-device-actions-cell', targets: [19] },
        { className: 'pialert-device-timestamp', width: '7rem', targets: [7,8] },
        { width: '30px', targets: [10] }, { width: '52px', targets: [17] }, { width: '10rem', targets: [19] },
        { type: 'pialert-address', targets: [9,11] },
        { targets: [20], searchable: false, defaultContent: '', createdCell: function (td, _value, row) {
          td.replaceChildren();
          if (!row._assignment || row._logical) return;
          var link = makeLink('deviceAssignments.php?' + new URLSearchParams({logical_id: row._assignment.id, return_url: returnUrl()}).toString(), row._assignment.name);
          link.addEventListener('click', function () { link.href = 'deviceAssignments.php?' + new URLSearchParams({logical_id: row._assignment.id, return_url: returnUrl()}).toString(); });
          td.append(link);
        } }, { targets: config.filterFields || [], searchable: false },
        { targets: [0], createdCell: function (td, cellData, rowData) {
          if (rowData._logical) { renderGroupName(td, rowData); return; }
          var colors = { Down:'var(--bs-danger)',NewON:'var(--bs-warning)',NewOFF:'var(--bs-warning)',OnlineV:'var(--bs-success)','On-line':'var(--bs-success)' };
          td.replaceChildren(makeLink('deviceDetails.php?mac=' + encodeURIComponent(text(rowData[11])), cellData, text(rowData[11]).indexOf('Internet') === 0 ? 'text-danger' : ''));
          td.style.borderLeft = colors[rowData[13]] ? '2px solid ' + colors[rowData[13]] : '';
        } },
        { targets: [4], createdCell: function (td, cellData) { td.replaceChildren(); if (cellData == 1) { var icon=document.createElement('i'); icon.className='fa-solid fa-star text-warning'; td.append(icon); } } },
        { targets: [9], createdCell: function (td, cellData, rowData) { if (rowData._logical) { td.replaceChildren(groupButton(rowData, countLabel('LD_IPCount', cellData), L('LD_IPHelp'))); return; } td.textContent=(rowData[18]===true||rowData[18]===1?'* ':'')+text(cellData)+(rowData[18]===true||rowData[18]===1?' *':''); td.classList.toggle('nmap-queued-ip',rowData[18]===true||rowData[18]===1); } },
        { targets: [10], createdCell: function (td, cellData) { td.replaceChildren(); if (cellData == 1) { var icon=document.createElement('i'); icon.className='fa-solid fa-shuffle text-warning'; icon.title=window.pialertV4Text('V4_Random_MAC'); td.append(icon); } } },
        { targets: [11], createdCell: function (td, cellData, rowData) { if (rowData._logical) { td.replaceChildren(groupButton(rowData, countLabel('LD_MACCount', cellData))); return; } var value=text(cellData); td.textContent=value.indexOf('Internet')===0&&value.length>20?value.slice(0,20)+'…':value; } },
        { targets: [13], createdCell: function (td, _cellData, rowData) {
          if (rowData._logical) {
            var aggregate = rowData._logical, statusBadge = badge(rowData[13]);
            if (rowData[13] === 'On-line') statusBadge.textContent += ' · ' + aggregate.online + '/' + aggregate.active;
            if (rowData[13] !== 'Archived') { statusBadge.classList.add('pialert-logical-tooltip'); statusBadge.title = countLabel('LD_OnlineCount', aggregate.active, {online: aggregate.online, total: aggregate.active}); statusBadge.tabIndex = 0; statusBadge.setAttribute('aria-label', statusBadge.textContent + ': ' + statusBadge.title); }
            td.replaceChildren(statusBadge); return;
          }
          var state=statusInfo(rowData[13]); var tone=state[0].startsWith('pialert-status-')?state[0]:'text-bg-'+state[0]; var link=makeLink('deviceDetails.php?mac='+encodeURIComponent(text(rowData[11])),state[1],'badge pialert-device-status-link '+tone); td.replaceChildren(link); } },
        { targets: [17], data: null, orderable: false, searchable: false, createdCell: function (td, _cellData, rowData) {
          td.replaceChildren();
          if (rowData._logical) return;
          var button = makeWakeOnLanButton(rowData[3], rowData[11], rowData[9], rowData[0]);
          if (button) td.append(button);
        } },
        { targets: [19], data: null, orderable: false, searchable: false, createdCell: function (td, _cellData, rowData) {
          if (rowData._logical) { td.replaceChildren(); return; }
          window.pialertEntityActions.append(td,actionMap[text(rowData[11])],makeDeleteButton(rowData));
        } }
      ],
      processing: true,
      preDrawCallback: disposeTooltips,
      rowCallback: function (tr, row) { if (row._logical) { var td = tr.querySelector('.pialert-device-name'); if (td) renderGroupName(td, row); } },
      drawCallback: function () { window.pialertEntityActions.verifyIcons(); initializeTooltips(); saveVisibleRows(); },
      language: window.pialertV4DataTableLanguage({ processing:window.pialertV4Text('V4_Loading'),emptyTable:window.pialertV4Text('V4_No_Data'),lengthMenu:labels.lengthMenu,search:(labels.search||window.pialertV4Text('V4_Table_Search').replace(/:$/, ''))+': ',paginate:{next:labels.next,previous:labels.previous},info:labels.info })
    });
    $('#tableDevices').on('length.dt',function(){scheduleTablePreferences();});
    $('#tableDevices').on('order.dt',function(){scheduleTablePreferences();saveVisibleRows();});
    $('#tableDevices').on('search.dt',saveVisibleRows);
    $('#devices-page').on('click','.delete-device',function(){
      var mac=this.dataset.deviceMac;
      if(!mac)return;
      window.showModalWarning(labels.deleteDeviceTitle,labels.deleteDeviceWarning,labels.cancel,labels.delete,function(){
        window.pialertPost(endpoint('deleteDevice'),{mac:mac},function(message){window.showMessage(message);table.ajax.reload(null,false);})
          .fail(function(xhr){window.showMessage(xhr.responseText||xhr.statusText||window.pialertV4Text('V4_Request_Failed'));});
      });
    });
    document.querySelectorAll('.pialert-device-filter').forEach(function(button){button.addEventListener('click',function(){getDevicesList(button.dataset.deviceStatus);});});
    updateStatusUI();
  }

  function saveVisibleRows () { if (table) setCookie('devicesList',JSON.stringify(table.rows({search:'applied'}).data().toArray().filter(function(row){return !row._logical;}).map(function(row){return row[16];}))); }
  function updateStatusUI () {
    var status = deviceStatus, tones={all:'primary',connected:'success',favorites:'warning',new:'warning',down:'danger',archived:'secondary'};
    var title=document.getElementById('tableDevicesTitle'); title.textContent=labels[status]||labels.devices; var box=document.getElementById('tableDevicesBox');
    box.className='card card-'+(tones[status]||'secondary')+' card-outline';
    document.querySelectorAll('.pialert-device-filter').forEach(function(button){button.setAttribute('aria-pressed',String(button.dataset.deviceStatus===status));});
  }
  function getDevicesList (status) {
    deviceStatus=status; updateStatusUI(); table.ajax.reload();
  }
  window.addEventListener('pageshow',function(event){if(event.persisted && table)table.ajax.reload(null,false);});

  function askWakeOnLan (mac, ip, name) {
    if (wakeConfirmationPending) return;
    wakeConfirmationPending = true;
    window._deviceWake = {mac:mac,ip:ip};
    var restoreMembers = memberModal && memberModal.classList.contains('show');
    var id = openLogicalId, trigger = modalTrigger;
    function confirmWake() {
      var confirmation = window.showModalWarning(labels.wolTitle+' (<span class="text-danger">'+text(name)+'</span>)',labels.wolText,labels.cancel,labels.run,'wakeonlan');
      confirmation.addEventListener('hidden.bs.modal', function () {
        wakeConfirmationPending = false;
        if (restoreMembers && logicalRows.has(text(id))) openMembers(id, trigger);
      }, {once:true});
    }
    if (restoreMembers) {
      memberModal.addEventListener('hidden.bs.modal', confirmWake, {once:true});
      var dialog = window.bootstrap.Modal.getOrCreateInstance(memberModal);
      if (dialog._isTransitioning) memberModal.addEventListener('shown.bs.modal', function () { dialog.hide(); }, {once:true});
      else dialog.hide();
    } else confirmWake();
  }
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
    var management = document.getElementById('device-assignments-link');
    if (management) management.addEventListener('click', function () { management.href = 'deviceAssignments.php?' + new URLSearchParams({return_url: returnUrl()}).toString(); });
    var saveFilter=document.getElementById('btnFilterSave'); if(saveFilter)saveFilter.addEventListener('click',function(){
      window.pialertPost(endpoint('SetDeviceFilter'),{filtername:$('#txtFilterName').val(),filterstring:$('#txtFilterString').val(),filtergroup:$('#txtFilterGroup').val(),fname:+$('#chkFilterName')[0].checked,fowner:+$('#chkFilterOwner')[0].checked,fgroup:+$('#chkFilterGroup')[0].checked,flocation:+$('#chkFilterLocation')[0].checked,ftype:+$('#chkFilterType')[0].checked,fip:+$('#chkFilterIP')[0].checked,fmac:+$('#chkFilterMac')[0].checked,fvendor:+$('#chkFilterVendor')[0].checked,fconnectiont:+$('#chkFilterConnectionType')[0].checked},function(response){filterChanged(response,labels.filterSavedPrefix);});
    });
    var deleteFilter=document.getElementById('deleteDeviceFilter'); if(deleteFilter)deleteFilter.addEventListener('click',function(){window.showModalWarning(labels.filterDeleteTitle,labels.filterDeleteText,labels.cancel,labels.delete,'DeleteDeviceFilter');});
    var modal=document.getElementById('modal-set-predefined-filter');if(modal)modal.addEventListener('shown.bs.modal',function(){var field=document.getElementById('txtFilterString');if(field)field.value=table ? text(table.search()) : currentSearch;});
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
  window.addEventListener('pagehide',function(){disposeTooltips();window.clearTimeout(refreshTimer);if(historyResizeObserver)historyResizeObserver.disconnect();if(preferenceTimer){window.clearTimeout(preferenceTimer);persistTablePreferences(true);}});
})(window, document, window.jQuery);

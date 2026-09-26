(function (window, document, $) {
  'use strict';
  var root = document.getElementById('device-details-page');
  if (!root || !$) return;
  var config = JSON.parse(document.getElementById('device-details-config').textContent);
  var labels = config.labels;
  var mac = config.mac;
  var preferenceKeys = {
    period:'Front_Details_Period', tab:'Front_Details_Tab', sessionsRows:'Front_Details_Sessions_Rows',
    eventsRows:'Front_Details_Events_Rows', eventsHide:'Front_Details_Events_Hide', speedtestRows:'Front_Details_Speedtest_Rows'
  };
  var preferences = {period:'1 month',tab:'tabDetails',sessionsRows:10,eventsRows:10,eventsHide:true,speedtestRows:10};
  var period = preferences.period;
  var deviceList = [];
  var position = -1;
  var dirty = false;
  var loaded = false;
  var deviceLoadGeneration = 0;
  var tables = {};
  var speedChart = null;
  var detailTimer = null;
  var nmapRequest = null;
  var queueRequest = null;
  var nmapGeneration = 0;
  var queueWasPending = false;
  var nmapBusy = false;
  var unloading = false;
  var calendarLayoutTimer = null;
  var calendar = null;
  var calendarRequest = null;
  var calendarGeneration = 0;

  function field(id) { return document.getElementById(id); }
  function value(id) { return field(id).value; }
  function check(id) { return field(id).checked ? 1 : 0; }
  function endpoint(name, action, data) {
    var url = new URL('php/server/' + name + '.php', window.location.href);
    url.searchParams.set('action', action);
    Object.keys(data || {}).forEach(function (key) { url.searchParams.set(key, data[key]); });
    return url.pathname + url.search;
  }
  function notify(message) { if (window.showMessage) window.showMessage(message); }
  function post(url, data, callback) {
    var request = window.pialertPost(url, data || {}, callback);
    request.fail(function (xhr) { notify(xhr.responseText || xhr.statusText || window.pialertV4Text('V4_Request_Failed')); });
    return request;
  }
  function readPreferences() {
    var allowedLengths = [10,25,50,100,500,-1];
    return Promise.all(Object.keys(preferenceKeys).map(function (name) {
      return Promise.resolve($.getJSON(endpoint('parameters','get',{parameter:preferenceKeys[name]}))).then(function (saved) {
        if (saved == null) return;
        if (name === 'period') {
          if (Array.from(field('period').options).some(function (option) { return option.value === saved; })) preferences.period = saved;
        } else if (name === 'tab') {
          if (field(saved) && field(saved).matches('#deviceDetailsTabs [data-bs-toggle="tab"]')) preferences.tab = saved;
        } else if (name === 'eventsHide') {
          if (saved === true || saved === false || saved === 'true' || saved === 'false') preferences.eventsHide = saved === true || saved === 'true';
        } else {
          var length = Number(saved);
          if (Number.isInteger(length) && allowedLengths.indexOf(length) !== -1) preferences[name] = length;
        }
      },function () { /* Keep the page defaults if a saved preference cannot be read. */ });
    }));
  }
  function writePreference(name,next) {
    preferences[name] = next;
    post('php/server/parameters.php',{action:'set',parameter:preferenceKeys[name],value:String(next)},function (response) {
      if (String(response).trim() !== 'OK') notify(response);
    });
  }
  function readCookie(name) {
    var item = document.cookie.split('; ').find(function (part) { return part.indexOf(name + '=') === 0; });
    return item ? decodeURIComponent(item.slice(name.length + 1)) : '';
  }
  function setDirty(next) {
    dirty = next;
    field('btnSave').disabled = !next;
    field('btnRestore').textContent = next ? labels.reset : labels.close;
  }
  function setValue(id, next) { field(id).value = next == null ? '' : String(next); }
  function setCheck(id, next) { field(id).checked = Number(next) === 1; }
  function listPosition(row) {
    position = deviceList.findIndex(function (item) { return String(item) === String(row.rowid); });
    if (position < 0) { deviceList = [row.rowid]; position = 0; }
    field('txtRecord').textContent = (position + 1) + ' / ' + deviceList.length;
    field('btnPrevious').disabled = position <= 0;
    field('btnNext').disabled = position >= deviceList.length - 1;
  }
  function updateData(row) {
    if (!row || !row.dev_MAC) {
      loaded = false;
      if (window.pialertEntityActionsEditor) window.pialertEntityActionsEditor.setTarget('device', '');
      field('pageTitle').textContent = labels.notFound + ': ' + mac;
      field('txtRecord').textContent = '0 / 0';
      field('btnPrevious').disabled = true;
      field('btnNext').disabled = true;
      root.querySelectorAll('#panDetails input, #panDetails textarea, #panDetails select, #panDetails button').forEach(function (control) { control.disabled = true; });
      return;
    }
    loaded = true;
    mac = String(row.dev_MAC);
    if (window.pialertEntityActionsEditor) window.pialertEntityActionsEditor.setTarget('device', mac);
    var address = new URL(window.location.href);
    address.searchParams.set('mac', mac);
    history.replaceState(null, '', address.pathname + address.search);
    var name = String(row.dev_Name || mac);
    var owner = String(row.dev_Owner || '');
    field('pageTitle').textContent = owner && name.indexOf(owner) === -1 ? name + ' (' + owner + ')' : name;
    field('deviceStatus').textContent = String(row.dev_Status || '???').replace('-', '');
    field('deviceSessions').textContent = Number(row.dev_Sessions || 0).toLocaleString();
    field('deviceDownAlerts').textContent = Number(row.dev_DownAlerts || 0).toLocaleString();
    field('deviceEvents').textContent = Math.max(0, Number(row.dev_PresenceHours || 0)).toLocaleString() + ' h.';
    var values = {
      txtMAC:'dev_MAC', txtName:'dev_Name', txtOwner:'dev_Owner', txtDeviceType:'dev_DeviceType', txtVendor:'dev_Vendor',
      txtModel:'dev_Model', txtSerialnumber:'dev_Serialnumber', txtGroup:'dev_Group', txtLocation:'dev_Location',
      txtComments:'dev_Comments', txtStatus:'dev_Status', txtFirstConnection:'dev_FirstConnection',
      txtLastConnection:'dev_LastConnection', txtLastIP:'dev_LastIP',
      txtNetworkNodeMac:'dev_Network_Node_MAC', txtNetworkPort:'dev_Network_Node_port',
      txtConnectionType:'dev_ConnectionType', txtLinkSpeed:'dev_LinkSpeed', txtScanValidation:'dev_Scan_Validation'
    };
    Object.keys(values).forEach(function (id) { setValue(id, row[values[id]]); });
    setValue('txtStatus', String(row.dev_Status || '').replace('-', ''));
    setValue('txtScanCycle', row.dev_ScanCycle == null ? '1' : row.dev_ScanCycle);
    setValue('txtSkipRepeated', row.dev_SkipRepeated == null ? '0' : row.dev_SkipRepeated);
    var checks = {chkFavorite:'dev_Favorite',chkStaticIP:'dev_StaticIP',chkMQTTDevice:'dev_MQTTDevice',
      chkAlertEvents:'dev_AlertEvents',chkAlertDown:'dev_AlertDeviceDown',chkNewDevice:'dev_NewDevice',
      chkArchived:'dev_Archived',chkShowPresence:'dev_PresencePage'};
    Object.keys(checks).forEach(function (id) { setCheck(id, row[checks[id]]); });
    field('iconRandomMAC').classList.toggle('text-warning', Number(row.dev_RandomMAC) === 1);
    field('iconRandomMAC').classList.toggle('text-secondary', Number(row.dev_RandomMAC) !== 1);
    var local = row.dev_ScanSource === 'local';
    field('tabNmap').parentElement.hidden = !local;
    if (!local && field('tabNmap').classList.contains('active')) window.bootstrap.Tab.getOrCreateInstance(field('tabDetails')).show();
    var back = new URL(config.back, window.location.href);
    if (!local && row.dev_ScanSource) back.searchParams.set('scansource', row.dev_ScanSource);
    field('deviceDetailsBack').href = back.pathname + back.search;
    listPosition(row);
    setDirty(false);
    updateTools();
  }
  function loadDevice(first) {
    if (!mac) return;
    var requestGeneration = ++deviceLoadGeneration;
    $.getJSON(endpoint('devices','getDeviceData',{mac:mac,period:period})).done(function (row) {
      if (requestGeneration !== deviceLoadGeneration) return;
      updateData(row);
      if (first || loaded) loadActivity();
    }).fail(function (xhr) { if (requestGeneration === deviceLoadGeneration) notify(xhr.responseText || window.pialertV4Text('V4_Device_Load_Failed')); });
  }
  function loadActivity() {
    if (!loaded) return;
    tables.sessions.ajax.reload(null, false);
    tables.events.ajax.reload(null, false);
    if (calendar) calendar.refetchEvents();
  }
  function tableLanguage() { return window.pialertV4DataTableLanguage({emptyTable:window.pialertV4Text('V4_No_Data'),lengthMenu:labels.lengthMenu,search:labels.search + ': ',paginate:{next:labels.next,previous:labels.previous},info:labels.info}); }
  function initializeTables() {
    var base = {paging:true,lengthChange:true,lengthMenu:[[10,25,50,100,500,-1],[10,25,50,100,500,'All']],
      searching:true,ordering:true,info:true,autoWidth:false,pageLength:10,processing:true,
      columnDefs:[{targets:'_all',render:$.fn.dataTable.render.text()}],language:tableLanguage()};
    tables.sessions = $('#tableSessions').DataTable($.extend(true,{},base,{
      pageLength:preferences.sessionsRows,order:[[0,'desc'],[1,'desc']],columnDefs:[{targets:'_all',render:$.fn.dataTable.render.text()},{targets:0,visible:false}],
      ajax:function (_request, done) {
        if (!loaded) { done({data:[]}); return; }
        $.getJSON(endpoint('events','getDeviceSessions',{mac:mac,period:period})).done(function (response) { done({data:Array.isArray(response.data) ? response.data : []}); }).fail(function () { done({data:[]}); });
      }
    }));
    tables.events = $('#tableEvents').DataTable($.extend(true,{},base,{
      pageLength:preferences.eventsRows,order:[[0,'desc']],ajax:function (_request, done) {
        if (!loaded) { done({data:[]}); return; }
        $.getJSON(endpoint('events','getDeviceEvents',{mac:mac,period:period,hideConnections:String(field('chkHideConnectionEvents').checked)})).done(function (response) { done({data:Array.isArray(response.data) ? response.data : []}); }).fail(function () { done({data:[]}); });
      }
    }));
    if (field('tableSpeedtest')) {
      tables.speedtest = $('#tableSpeedtest').DataTable($.extend(true,{},base,{pageLength:preferences.speedtestRows,order:[[0,'desc']]}));
      $('#tableSpeedtest').on('draw.dt', updateSpeedChart);
      $('#tableSpeedtest').on('length.dt',function (_event,_settings,length) { writePreference('speedtestRows',length); });
      updateSpeedChart();
    }
    $('#tableSessions').on('length.dt',function (_event,_settings,length) {
      writePreference('sessionsRows',length);
      if (tables.events.page.len() !== length) tables.events.page.len(length).draw();
    });
    $('#tableEvents').on('length.dt',function (_event,_settings,length) {
      writePreference('eventsRows',length);
      if (tables.sessions.page.len() !== length) tables.sessions.page.len(length).draw();
    });
  }
  function initializeCalendar() {
    if (!window.FullCalendar) return;
    var narrow = window.matchMedia('(max-width: 767px)').matches;
    calendar = new window.FullCalendar.Calendar(field('calendar'), {editable:false,selectable:false,eventStartEditable:false,eventDurationEditable:false,
      initialView:narrow ? 'timeGridDay' : 'timeGridMonth',height:'auto',firstDay:1,allDaySlot:false,timeZone:'local',
      slotDuration:'02:00:00',slotLabelInterval:'04:00:00',slotLabelFormat:{hour:'2-digit',minute:'2-digit',hour12:false},eventTimeFormat:{hour:'2-digit',minute:'2-digit',hour12:false},locale:config.calendarLocale,
      headerToolbar:{left:'prev,next today',center:'title',right:narrow ? 'timeGridDay' : 'timeGridMonth,timeGridWeek,timeGridDay'},
      views:{timeGridMonth:{type:'timeGrid',duration:{months:1},buttonText:labels.calendarMonth,dayHeaderFormat:{day:'numeric'}},
        timeGridWeek:{buttonText:labels.calendarWeek},timeGridDay:{buttonText:labels.calendarDay,slotDuration:'01:00:00'}},
      events:function(fetchInfo,success,failure) {
        if (!loaded) { success([]); return; }
        var generation=++calendarGeneration;
        if (calendarRequest && calendarRequest.readyState!==4) calendarRequest.abort();
        var settled=false;
        function finish(callback,value){if(settled)return;settled=true;callback(value);}
        calendarRequest=$.ajax({url:endpoint('events','getDevicePresence',{mac:mac,start:fetchInfo.startStr,end:fetchInfo.endStr}),dataType:'json',cache:false})
          .done(function(response){
            if(generation!==calendarGeneration){finish(success,[]);return;}
            if(response===''){finish(success,[]);return;}
            if(!Array.isArray(response)){var error=new Error('Unexpected calendar response');if(window.console)window.console.error(error.message);notify(window.pialertV4Text('V4_Request_Failed'));finish(failure,error);return;}
            finish(success,response);
          }).fail(function(xhr,requestStatus){
            if(requestStatus==='abort'||generation!==calendarGeneration){finish(success,[]);return;}
            var error=new Error(xhr.status?'HTTP '+xhr.status+' '+xhr.statusText:window.pialertV4Text('V4_Request_Failed'));if(window.console)window.console.error('Calendar request:',error.message);notify(window.pialertV4Text('V4_Request_Failed'));finish(failure,error);
          });
      },eventDidMount:function(argument){
        var tooltip=argument.event.extendedProps.tooltip;
        if(tooltip) argument.el.setAttribute('title',String(tooltip).replace(/\r\n?/g,'\n'));
      }
    });
    calendar.render();
  }
  function refreshCalendarLayout() {
    if (!calendar || !field('panPresence').classList.contains('active')) return;
    var render = function () {
      if (!unloading && field('panPresence').classList.contains('active')) {
        calendar.updateSize();
      }
    };
    window.requestAnimationFrame(function () { window.requestAnimationFrame(render); });
    window.clearTimeout(calendarLayoutTimer);
    calendarLayoutTimer = window.setTimeout(render,250);
  }
  function updateSpeedChart() {
    if (!field('SpeedtestChart') || !window.Chart) return;
    var rows = tables.speedtest ? tables.speedtest.rows({page:'current',search:'applied',order:'applied'}).data().toArray() : config.speedtestRows;
    var labels = rows.map(function (row) { return String(row[0] || ''); });
    var ping = rows.map(function (row) { return Number.parseFloat(row[3]) || 0; });
    var down = rows.map(function (row) { return Number.parseFloat(row[4]) || 0; });
    var up = rows.map(function (row) { return Number.parseFloat(row[5]) || 0; });
    if (speedChart) speedChart.destroy();
    speedChart = new window.Chart(field('SpeedtestChart'),{type:'line',data:{labels:labels,datasets:[
      {label:window.pialertV4Text('V4_Ping')+' (ms)',data:ping,borderColor:'#167ac4',yAxisID:'ping'},
      {label:window.pialertV4Text('V4_Download')+' (Mbps)',data:down,borderColor:'#00a659',yAxisID:'speed'},
      {label:window.pialertV4Text('V4_Upload')+' (Mbps)',data:up,borderColor:'#b9002b',yAxisID:'speed'}
    ]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},scales:{ping:{type:'linear',position:'left',beginAtZero:true},speed:{type:'linear',position:'right',beginAtZero:true,grid:{drawOnChartArea:false}}}}});
  }
  function refreshSpeedtest() {
    if (!config.internet) return;
    $.getJSON(endpoint('devices','getSpeedtestResults')).done(function (response) {
      config.speedtestRows = Array.isArray(response.rows) ? response.rows : [];
      if (tables.speedtest) tables.speedtest.clear().rows.add(config.speedtestRows).order([0,'desc']).draw();
      else updateSpeedChart();
    });
  }
  function fillSuggestions() {
    root.querySelectorAll('.device-detail-options[data-suggestion-action]').forEach(function (menu) {
      $.getJSON(endpoint('devices',menu.dataset.suggestionAction)).done(function (items) {
        menu.replaceChildren();
        if (!Array.isArray(items)) return;
        var previousOrder = null;
        items.forEach(function (item) {
          var value = String(item.id !== undefined && item.id !== null && item.id !== '' ? item.id : item.name || '');
          if (!value) return;
          var order = item.order === undefined ? null : String(item.order);
          if (previousOrder !== null && order !== previousOrder) {
            var separator = document.createElement('li');
            var line = document.createElement('hr'); line.className = 'dropdown-divider';
            separator.appendChild(line); menu.appendChild(separator);
          }
          previousOrder = order;
          var entry = document.createElement('li');
          var button = document.createElement('button'); button.type = 'button'; button.className = 'dropdown-item';
          button.dataset.value = value;
          button.textContent = menu.dataset.suggestionAction === 'getNetworkNodes'
            ? String(item.name || '') + ' [' + value + ']'
            : String(item.name || value);
          button.addEventListener('click',function () {
            var target = field(menu.dataset.suggestionTarget);
            if (!target) return;
            target.value = value;
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.focus();
          });
          entry.appendChild(button); menu.appendChild(entry);
        });
      });
    });
  }
  function saveDevice(callback) {
    if (!loaded || !dirty) { if (callback) callback(); return; }
    var data = {action:'setDeviceData',mac:mac,name:value('txtName'),owner:value('txtOwner'),type:value('txtDeviceType'),
      vendor:value('txtVendor'),model:value('txtModel'),serialnumber:value('txtSerialnumber'),favorite:check('chkFavorite'),
      showpresence:check('chkShowPresence'),group:value('txtGroup'),location:value('txtLocation'),comments:value('txtComments'),
      networknode:value('txtNetworkNodeMac'),networknodeport:value('txtNetworkPort'),connectiontype:value('txtConnectionType'),
      linkspeed:value('txtLinkSpeed'),staticIP:check('chkStaticIP'),mqttdevice:check('chkMQTTDevice'),scancycle:value('txtScanCycle'),
      alertevents:check('chkAlertEvents'),alertdown:check('chkAlertDown'),skiprepeated:value('txtSkipRepeated'),
      scanvalid:value('txtScanValidation'),newdevice:check('chkNewDevice'),archived:check('chkArchived')};
    post('php/server/devices.php',data,function (message) {
      notify(message);
      if (String(message).trim() !== String(labels.saved).trim()) return;
      setDirty(false);
      fillSuggestions();
      if (callback) callback();
    });
  }
  function navigate(delta, actionHandled) {
    var editor = window.pialertEntityActionsEditor;
    if (!actionHandled && editor && editor.dirty()) {
      editor.navigationChoice().then(function (choice) {
        if (choice === 'discard') navigate(delta, true);
        else if (choice === 'save') editor.save().then(function (ok) { if (ok) navigate(delta, true); });
      });
      return;
    }
    if (dirty) { saveDevice(function () { navigate(delta, true); }); return; }
    var next = position + delta;
    if (next < 0 || next >= deviceList.length) return;
    mac = String(deviceList[next]);
    loaded = false;
    if (editor) editor.setTarget('device', '');
    loadDevice(true);
  }
  function ask(title, message, callback) { window.showModalWarning(title,message,labels.cancel,labels.delete,callback); }
  function deleteEvents() { post(endpoint('devices','deleteDeviceEvents'),{mac:mac},function (message) { notify(message); loadDevice(false); }); }
  function deleteDevice() { post(endpoint('devices','deleteDevice'),{mac:mac},function (message) { notify(message); window.location.assign(field('deviceDetailsBack').href); }); }
  function wake() { post(endpoint('devices','wakeonlan'),{mac:mac,ip:value('txtLastIP')},notify); }
  function safeOutput(markup) {
    var output = field('scanoutput');
    output.replaceChildren();
    var source = new DOMParser().parseFromString(String(markup || ''),'text/html');
    var allowed = ['DIV','SPAN','STRONG','B','EM','I','BR','TABLE','THEAD','TBODY','TR','TH','TD','P','PRE','H4','SMALL','UL','LI','A'];
    function copy(node,parent) {
      if (node.nodeType === Node.TEXT_NODE) { parent.appendChild(document.createTextNode(node.nodeValue || '')); return; }
      if (node.nodeType !== Node.ELEMENT_NODE) return;
      if (['SCRIPT','STYLE','IFRAME','OBJECT','EMBED','SVG','FORM'].indexOf(node.tagName) >= 0) return;
      if (allowed.indexOf(node.tagName) < 0) { Array.from(node.childNodes).forEach(function (child) { copy(child,parent); }); return; }
      var element;
      if (node.tagName === 'A') {
        var target = node.getAttribute('data-target') || '';
        var link = node.getAttribute('href') || '';
        if (node.classList.contains('nmap-reload') && /^[0-9a-fA-F:.]+$/.test(target)) {
          element = document.createElement('button');
          element.type = 'button'; element.className = 'btn btn-sm btn-outline-secondary';
          element.addEventListener('click',function () { scan('view',target); });
        } else if (link.startsWith('./download/hostnmapresultscvs.php?')) {
          var url = new URL(link,window.location.href);
          if (url.origin !== window.location.origin || !url.pathname.endsWith('/download/hostnmapresultscvs.php')) return;
          element = document.createElement('a'); element.href = url.pathname + url.search;
        } else return;
      } else element = document.createElement(node.tagName.toLowerCase());
      if (node.className && typeof node.className === 'string') {
        element.className += ' ' + node.className.split(/\s+/).filter(function (part) { return /^[A-Za-z0-9_-]+$/.test(part); }).join(' ');
      }
      Array.from(node.childNodes).forEach(function (child) { copy(child,element); });
      parent.appendChild(element);
    }
    Array.from(source.body.childNodes).forEach(function (node) { copy(node,output); });
  }
  function toolPost(path,data,callback) {
    field('scanoutput').textContent = window.pialertV4Text('V4_Loading');
    var request = post(path,data,function (response) { callback(response); });
    return request;
  }
  function nmapState(message,tone) {
    var status = field('nmapstatus');
    status.textContent = message || '';
    status.dataset.tone = tone || '';
  }
  function nmapButtonsBusy(busy) {
    nmapBusy = busy;
    field('manualnmap_fast').disabled = busy;
    field('manualnmap_normal').disabled = busy;
    field('manualnmap_detail').disabled = busy || queueWasPending;
  }
  function scan(mode,target) {
    var ip = target || value('txtLastIP');
    if (!ip || ip === '--') return;
    if (nmapBusy && mode !== 'view') return;
    if (nmapRequest && nmapRequest.readyState !== 4) nmapRequest.abort();
    var generation = ++nmapGeneration;
    var requestedMac = mac;
    nmapButtonsBusy(mode !== 'view');
    nmapState(labels.nmapLoading);
    if (mode === 'detail') {
      nmapRequest = window.pialertPost('php/server/nmap_scan.php',{scan:ip,mac:mac,mode:'detail'},function (response) {
        if (generation !== nmapGeneration || requestedMac !== mac || ip !== value('txtLastIP')) return;
        try { var result = typeof response === 'string' ? JSON.parse(response) : response; queueWasPending = !!result.pending; nmapState(result.pending ? labels.nmapQueued : labels.nmapError,result.pending ? '' : 'error'); }
        catch (_error) { nmapState(labels.nmapError,'error'); }
        nmapButtonsBusy(false);
        queueStatus();
      }).fail(function (_xhr,status) { if (status !== 'abort' && generation === nmapGeneration) { nmapButtonsBusy(false); nmapState(labels.nmapError,'error'); } });
      return;
    }
    nmapRequest = window.pialertPost('php/server/nmap_scan.php',{scan:ip,mode:mode},function (response) {
      if (generation !== nmapGeneration || requestedMac !== mac || ip !== value('txtLastIP')) return;
      window.pialertNmapResults.render(response,field('scanoutput'),ip,function () { scan('view'); });
      nmapState(''); nmapButtonsBusy(false);
    }).fail(function (_xhr,status) { if (status !== 'abort' && generation === nmapGeneration) { nmapButtonsBusy(false); nmapState(labels.nmapRequestError,'error'); } });
  }
  function queueStatus() {
    window.clearTimeout(detailTimer);
    if (!loaded || !mac || field('tabNmap').parentElement.hidden) return;
    if (queueRequest && queueRequest.readyState !== 4) queueRequest.abort();
    var requestedMac = mac;
    var requestedIp = value('txtLastIP');
    queueRequest = window.pialertPost('php/server/nmap_scan.php',{mode:'detail_status',mac:mac},function (response) {
      if (requestedMac !== mac || requestedIp !== value('txtLastIP')) return;
      var result;
      try { result = typeof response === 'string' ? JSON.parse(response) : response; } catch (_error) { result = {}; }
      var completed = queueWasPending && !result.pending;
      queueWasPending = !!result.pending;
      field('manualnmap_detail').disabled = nmapBusy || queueWasPending;
      field('manualnmap_detail').textContent = result.pending ? labels.nmapPending : labels.nmapDetail + ' (' + value('txtLastIP') + ')';
      if (result.pending) {
        if (!nmapBusy) nmapState(labels.nmapQueued);
        detailTimer = window.setTimeout(queueStatus,5000);
      }
      if (completed) scan('view');
    }).fail(function (_xhr,status) {
      if (status === 'abort' || requestedMac !== mac || requestedIp !== value('txtLastIP')) return;
      nmapState(labels.nmapRequestError,'error');
      if (queueWasPending) detailTimer = window.setTimeout(queueStatus,5000);
    });
  }
  function updateTools() {
    ++nmapGeneration;
    window.clearTimeout(detailTimer);
    if (nmapRequest && nmapRequest.readyState !== 4) nmapRequest.abort();
    if (queueRequest && queueRequest.readyState !== 4) queueRequest.abort();
    queueWasPending = false; nmapButtonsBusy(false); nmapState(''); field('scanoutput').replaceChildren();
    var ip = value('txtLastIP');
    field('manualnmap_fast').textContent = labels.nmapFast + ' (' + ip + ')';
    field('manualnmap_normal').textContent = labels.nmapNormal + ' (' + ip + ')';
    if (field('btnwakeonlan')) field('btnwakeonlan').textContent = labels.wol + ' ' + ip;
    if (!field('tabNmap').parentElement.hidden) { scan('view'); queueStatus(); }
    fillIgnoreOptions();
  }
  function ignore(kind,part) {
    var action = kind === 'MAC' ? 'BlockDeviceMAC' : 'BlockDeviceIP';
    window.showModalWarning(labels.ignore + ' ' + kind,part + labels.ignoreText,labels.cancel,labels.run,function () {
      post(endpoint('files',action),kind === 'MAC' ? {mac:part} : {ip:part},notify);
    });
  }
  function fillIgnoreOptions() {
    [['MAC','ignoreMACOptions',value('txtMAC').split(':')],['IP','ignoreIPOptions',value('txtLastIP').split('.')]].forEach(function (entry) {
      var menu = field(entry[1]); menu.replaceChildren();
      var expected = entry[0] === 'MAC' ? 6 : 4;
      if (entry[2].length !== expected) return;
      entry[2].forEach(function (_part,index) {
        var value = entry[2].slice(0,index + 1).join(entry[0] === 'MAC' ? ':' : '.');
        var item = document.createElement('li'); var button = document.createElement('button');
        button.type = 'button'; button.className = 'dropdown-item'; button.textContent = value;
        button.addEventListener('click',function () { ignore(entry[0],value); }); item.appendChild(button); menu.appendChild(item);
      });
    });
  }
  function initializeTabs() {
    var saved = preferences.tab;
    if (!field(saved)) saved = 'tabDetails';
    root.querySelectorAll('#deviceDetailsTabs [data-bs-toggle="tab"]').forEach(function (button) {
      button.addEventListener('shown.bs.tab',function () {
        if (preferences.tab !== button.id) writePreference('tab',button.id);
        if (button.id === 'tabPresence') refreshCalendarLayout();
        if (button.id === 'tabNmap') queueStatus(); else window.clearTimeout(detailTimer);
        if (button.id === 'tabSpeedtest') refreshSpeedtest();
        if (button.id === 'tabSessions' && tables.sessions) tables.sessions.columns.adjust();
        if (button.id === 'tabEvents' && tables.events) tables.events.columns.adjust();
      });
    });
    window.bootstrap.Tab.getOrCreateInstance(field(saved)).show();
    if (saved === 'tabPresence') refreshCalendarLayout();
  }
  function init() {
    try { var cookieList = JSON.parse(readCookie('devicesList')); if (Array.isArray(cookieList)) deviceList = cookieList; } catch (_error) { deviceList = []; }
    readPreferences().then(function () {
      period = preferences.period;
      field('period').value = period;
      field('chkHideConnectionEvents').checked = preferences.eventsHide;
      initializeTables(); initializeCalendar(); initializeTabs(); fillSuggestions(); loadDevice(true);
      if (config.internet && !tables.speedtest) updateSpeedChart();
    });
    field('period').addEventListener('change',function () { period = this.value; writePreference('period',period); loadDevice(false); });
    root.querySelectorAll('.device-summary').forEach(function (button) { button.addEventListener('click',function () { window.bootstrap.Tab.getOrCreateInstance(field(button.dataset.openTab)).show(); }); });
    field('panDetails').addEventListener('input',function (event) { if (loaded && !event.target.readOnly) setDirty(true); });
    field('panDetails').addEventListener('change',function (event) { if (loaded && !event.target.readOnly) setDirty(true); });
    field('btnSave').addEventListener('click',function () { saveDevice(); });
    field('btnRestore').addEventListener('click',function () { if (dirty) loadDevice(false); else window.location.assign(field('deviceDetailsBack').href); });
    field('btnPrevious').addEventListener('click',function () { navigate(-1); });
    field('btnNext').addEventListener('click',function () { navigate(1); });
    field('btnDeleteEvents').addEventListener('click',function () { ask(labels.deleteEventsTitle,labels.deleteEventsWarning,deleteEvents); });
    field('btnDelete').addEventListener('click',function () { ask(labels.deleteTitle,labels.deleteWarning,deleteDevice); });
    field('chkHideConnectionEvents').addEventListener('change',function () { writePreference('eventsHide',this.checked); tables.events.ajax.reload(); });
    field('manualnmap_fast').addEventListener('click',function () { scan('fast'); });
    field('manualnmap_normal').addEventListener('click',function () { scan('normal'); });
    field('manualnmap_detail').addEventListener('click',function () { scan('detail'); });
    if (field('btnwakeonlan')) field('btnwakeonlan').addEventListener('click',function () { window.showModalWarning(labels.wolTitle,labels.wolText,labels.cancel,labels.run,wake); });
    if (field('speedtestcli')) field('speedtestcli').addEventListener('click',function () { toolPost('php/server/speedtestcli.php',{},function (response) { safeOutput(response); refreshSpeedtest(); }); });
    if (field('speedtestcli_ookla') && !config.speedtestInstalled) field('speedtestcli_ookla').addEventListener('click',function () { toolPost('php/server/speedtest_ookla.php',{mod:'get'},function (response) { safeOutput(response); var reload=document.createElement('button'); reload.type='button'; reload.className='btn btn-outline-primary mt-2'; reload.textContent=window.pialertV4Text('V4_Reload_Page'); reload.addEventListener('click',function () { window.location.reload(); }); field('scanoutput').appendChild(reload); }); });
    window.addEventListener('beforeunload',function (event) { if ((dirty || (window.pialertEntityActionsEditor && window.pialertEntityActionsEditor.dirty())) && !unloading) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('pagehide',function () { unloading = true; ++nmapGeneration; ++calendarGeneration; window.clearTimeout(detailTimer); window.clearTimeout(calendarLayoutTimer); if (nmapRequest) nmapRequest.abort(); if (queueRequest) queueRequest.abort(); if (calendarRequest) calendarRequest.abort(); Object.keys(tables).forEach(function (key) { tables[key].destroy(); }); if (speedChart) speedChart.destroy(); if (calendar) calendar.destroy(); });
  }
  init();
})(window, document, window.jQuery);

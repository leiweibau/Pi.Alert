(function (window, document, $) {
  'use strict';
  var configNode = document.getElementById('icmpmonitor-page-config');
  if (!configNode || !$) return;
  var config = JSON.parse(configNode.textContent || '{}');
  var labels = config.labels || {};
  var table = null;
  var actionMap = {};
  var preferenceTimer = null;
  var lastSavedPreferences = JSON.stringify({ length: config.pageLength, order: config.order });
  var status = 'all';
  var pendingDelete = '';
  var modalNode = document.getElementById('icmp-host-modal');
  var modal = modalNode && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(modalNode) : null;
  function text (value) { return String(value == null ? '' : value); }
  function setCookie (name, value) { document.cookie = name + '=' + encodeURIComponent(value) + ';max-age=2592000;SameSite=Strict;path=/'; }
  function endpoint (action, extra) { return 'php/server/icmpmonitor.php?' + new URLSearchParams(Object.assign({ action: action }, extra || {})).toString(); }
  function detailUrl (ip) { return 'icmpmonitorDetails.php?hostip=' + encodeURIComponent(text(ip)); }
  function link (href, value, className) { var node=document.createElement('a');node.href=href;node.textContent=text(value);if(className)node.className=className;return node; }
  function persistTablePreferences (keepalive) {
    if (!table) return;
    var current = { length: table.page.len(), order: table.order().map(function (part) { return [part[0], part[1]]; }) };
    var signature = JSON.stringify(current);
    if (signature === lastSavedPreferences) return;
    var order = current.order.map(function (part) { return [config.columnIds[part[0]], part[1]]; });
    var patch = { 'icmp.page_length': current.length, 'icmp.order': order };
    lastSavedPreferences = signature;
    if (keepalive) {
      var body = new URLSearchParams({ action: 'save', patch: JSON.stringify(patch) });
      var csrf = document.querySelector('meta[name="csrf-token"]');
      window.fetch('php/server/v4_ui_settings.php', { method: 'POST', body: body, credentials: 'same-origin', keepalive: true, headers: { 'X-CSRF-Token': csrf ? csrf.content : '' } })
        .catch(function () { console.error('ICMP table preferences could not be saved'); });
    } else {
      window.pialertPost('php/server/v4_ui_settings.php', { action: 'save', patch: JSON.stringify(patch) })
        .fail(function (xhr) { lastSavedPreferences = ''; console.error('ICMP table preferences could not be saved', xhr.status); });
    }
  }
  function scheduleTablePreferences () {
    window.clearTimeout(preferenceTimer);
    preferenceTimer = window.setTimeout(function () { preferenceTimer = null; persistTablePreferences(false); }, 300);
  }
  function statusInfo (value) { return {Down:['danger',window.pialertV4Text('V4_Down')],OnlineV:['success',window.pialertV4Text('V4_Online_Verified')],Online:['success',window.pialertV4Text('V4_Online')],Offline:['secondary',window.pialertV4Text('V4_Offline')]}[value] || ['info','']; }
  function initializeChecks () {
    if (!$.fn || typeof $.fn.iCheck !== 'function') return;
    [['blue','icheckbox_flat-blue'],['orange','icheckbox_flat-orange'],['red','icheckbox_flat-red']].forEach(function(item){$('input[type="checkbox"].'+item[0]).iCheck({checkboxClass:item[1],increaseArea:'20%'});});
  }
  function initializeTable () {
    table=$('#tableDevices').DataTable({paging:true,lengthChange:true,lengthMenu:[[10,25,50,100,500,-1],[10,25,50,100,500,labels.lengthAll||'All']],searching:true,ordering:true,info:true,autoWidth:false,pageLength:config.pageLength,order:config.order,ajax:{url:endpoint('getDevicesList',{status:status}),dataSrc:function(response){actionMap=response && response.actions && typeof response.actions==='object' ? response.actions : {};return Array.isArray(response.data) ? response.data : [];}},columnDefs:[
      {targets:'_all',render:$.fn.dataTable.render.text()},{visible:false,targets:config.hiddenColumns},{className:'text-center',targets:[1,2,3,4,5,9]},{targets:[0],createdCell:function(td,cell,row){td.replaceChildren(link(detailUrl(row[1]),cell));td.style.borderLeft={Down:'2px solid var(--bs-danger)',OnlineV:'2px solid var(--bs-success)',Online:'2px solid var(--bs-success)'}[row[7]]||'';}},
      {targets:[2],createdCell:function(td,cell){td.replaceChildren();if(cell==1){var icon=document.createElement('i');icon.className='fa-solid fa-star text-warning';icon.setAttribute('aria-label',window.pialertV4Text('V4_Favorite_Host'));td.append(icon);}}},
      {targets:[3],createdCell:function(td,cell){td.textContent=cell==99999?window.pialertV4Text('V4_Timeout'):text(cell)+' ms';}},
      {targets:[5],createdCell:function(td,_cell,row){var state=statusInfo(row[7]);td.replaceChildren(link(detailUrl(row[1]),state[1],'badge text-bg-'+state[0]));}},
      {targets:[9],data:null,orderable:false,searchable:false,createdCell:function(td,_cell,row){td.className='text-center icmp-actions';var del=document.createElement('button');del.type='button';del.className='btn btn-sm btn-outline-danger delete-icmp-host';del.dataset.hostIp=text(row[1]);del.setAttribute('aria-label',labels.delete+': '+text(row[0]||row[1]));del.title=del.getAttribute('aria-label');var trash=document.createElement('i');trash.className='fa-solid fa-trash';trash.setAttribute('aria-hidden','true');del.append(trash);window.pialertEntityActions.append(td,actionMap[text(row[1])],del);}}
    ],processing:true,drawCallback:function(){window.pialertEntityActions.verifyIcons();},language:window.pialertV4DataTableLanguage({processing:window.pialertV4Text('V4_Loading'),emptyTable:window.pialertV4Text('V4_No_Data'),lengthMenu:labels.lengthMenu,search:(labels.search||window.pialertV4Text('V4_Table_Search').replace(/:$/, ''))+': ',paginate:{next:labels.next,previous:labels.previous},info:labels.info})});
    $('#tableDevices').on('length.dt order.dt',scheduleTablePreferences);
    $('#tableDevices').on('draw.dt search.dt order.dt',saveVisibleRows);
    $('#tableDevices tbody').on('click','.delete-icmp-host',function(){pendingDelete=this.dataset.hostIp||'';window.showModalWarning(labels.deleteTitle,labels.deleteText,labels.cancel,labels.delete,'deleteICMPHost');});
    document.querySelectorAll('.icmp-filter').forEach(function(button){button.addEventListener('click',function(){getDevicesList(button.dataset.icmpStatus);});});
    getTotals(); getDevicesList('all');
  }
  function saveVisibleRows () {
    if (!table) return;
    var hosts = table.rows({ search: 'applied', order: 'applied' }).data().toArray().map(function (row) { return text(row[1]); }).filter(Boolean);
    setCookie('icmpHostsList', JSON.stringify(hosts));
  }
  function getDevicesList (nextStatus) {
    status=nextStatus;var tones={all:'primary',connected:'success',favorites:'warning',down:'danger',archived:'secondary'};var box=document.getElementById('tableDevicesBox');box.className='card card-'+(tones[status]||'secondary')+' card-outline';document.getElementById('tableDevicesTitle').textContent=labels[status]||labels.hosts;document.querySelectorAll('.icmp-filter').forEach(function(button){button.setAttribute('aria-pressed',String(button.dataset.icmpStatus===status));});if(table)table.ajax.url(endpoint('getDevicesList',{status:status})).load();
  }
  window.addEventListener('pageshow',function(event){if(event.persisted && table)table.ajax.reload(null,false);});
  function getTotals () { $.get(endpoint('getICMPHostTotals'),function(data){var totals=typeof data==='string'?JSON.parse(data):data;['devicesAll','devicesDown','devicesConnected','devicesFavorites','devicesArchived'].forEach(function(id,index){var node=document.getElementById(id);if(node)node.textContent=Number(totals[index]||0).toLocaleString();});}); }
  function insertHost (event) { event.preventDefault();var form=document.getElementById('icmp-host-form');if(!form.reportValidity())return;var button=document.getElementById('btnInsert');button.disabled=true;window.pialertPost(endpoint('insertNewICMPHost'),{icmp_ip:document.getElementById('icmphost_ip').value.trim(),icmp_hostname:document.getElementById('icmphost_name').value.trim(),icmp_fav:+document.getElementById('insFavorite').checked,alertdown:+document.getElementById('insAlertDown').checked,alertevents:+document.getElementById('insAlertEvents').checked},function(message){if(modal)modal.hide();window.showMessage(message);form.reset();if(table)table.ajax.reload(null,false);getTotals();}).always(function(){button.disabled=false;}); }
  window.deleteICMPHost=function(){if(!pendingDelete)return;window.pialertPost(endpoint('deleteICMPHost'),{icmp_ip:pendingDelete},function(message){window.showMessage(message);pendingDelete='';if(table)table.ajax.reload(null,false);getTotals();});};
  window.insertNewICMPHost=function(){document.getElementById('icmp-host-form').requestSubmit();};
  function initializeList () {
    initializeChecks();document.getElementById('icmp-host-form').addEventListener('submit',insertHost);
    initializeTable();
    if(window.Chart&&document.getElementById('OnlineChart'))new window.Chart(document.getElementById('OnlineChart'),{type:'bar',data:{labels:config.history.time,datasets:[{label:window.pialertV4Text('V4_Online'),data:config.history.online,backgroundColor:'rgba(25,135,84,.65)'},{label:window.pialertV4Text('V4_Offline_Down'),data:config.history.down,backgroundColor:'rgba(220,53,69,.65)'},{label:window.pialertV4Text('V4_Archived'),data:config.history.archived,backgroundColor:'rgba(108,117,125,.65)'}]},options:{maintainAspectRatio:false,plugins:{legend:{position:'bottom'},tooltip:{mode:'index',intersect:false}},scales:{x:{stacked:true},y:{stacked:true,beginAtZero:true,ticks:{stepSize:1,precision:0}}}}});
  }
  function initializeBulk () {
    document.querySelectorAll('.bulk-enable').forEach(function(input){input.addEventListener('change',function(){var target=document.getElementById(input.dataset.bulkTarget);target.disabled=!input.checked;if(!input.checked){target.checked=false;if(target.type!=='checkbox')target.value='';}});});
    document.getElementById('icmpHostSearch').addEventListener('input',function(event){var term=event.target.value.trim().toLowerCase();document.querySelectorAll('.icmp-bulk-host').forEach(function(host){host.hidden=term!==''&&host.textContent.toLowerCase().indexOf(term)===-1;});});
    var selected=false;document.getElementById('icmpSelectAll').addEventListener('click',function(event){selected=!selected;document.querySelectorAll('.hostselection').forEach(function(input){input.checked=selected;});event.currentTarget.textContent=selected?labels.selectNone:labels.selectAll;});
    document.getElementById('btnBulkDeletion').addEventListener('click',function(){window.showModalWarning(labels.bulkDeleteTitle,labels.bulkDeleteText,labels.cancel,labels.delete,'BulkDeletion');});
  }
  window.BulkDeletion=function(){var payload=new URLSearchParams();document.querySelectorAll('.hostselection:checked').forEach(function(input){payload.append('hosts[]',input.dataset.hostId);});window.pialertPost(endpoint('BulkDeletion'),payload,function(message){window.showMessage(message);});};
  if(document.getElementById('icmpmonitor-page'))initializeList();
  if(document.getElementById('icmpmonitor-bulk-page'))initializeBulk();
  window.addEventListener('pagehide',function(){if(preferenceTimer){window.clearTimeout(preferenceTimer);persistTablePreferences(true);}});
})(window, document, window.jQuery);

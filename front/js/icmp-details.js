(function (window, document, $) {
  'use strict';
  var root = document.getElementById('icmp-details-page');
  if (!root || !$) return;
  var table = null;
  var chart = null;
  var fields = ['txtHostname', 'txtOwner', 'txtDeviceType', 'txtVendor', 'txtModel', 'txtSerialnumber', 'txtGroup', 'txtLocation', 'txtNotes', 'txtScanValidation'];
  var checks = ['chkFavorit', 'chkMQTTDevice', 'chkArchived', 'chkAlertEvents', 'chkAlertDown'];
  var initialState = '';
  var nmapRequest = null;
  var nmapGeneration = 0;
  var nmapBusy = false;
  var hostList = [];
  var hostPosition = -1;
  var navigationBusy = false;
  var unloading = false;

  function byId (id) { return document.getElementById(id); }
  function readCookie (name) {
    var item = document.cookie.split('; ').find(function (part) { return part.indexOf(name + '=') === 0; });
    return item ? decodeURIComponent(item.slice(name.length + 1)) : '';
  }
  function state () {
    return JSON.stringify(fields.map(function (id) { return byId(id).value; }).concat(checks.map(function (id) { return byId(id).checked; })));
  }
  function updateDirty () {
    var dirty = state() !== initialState;
    byId('btnSave').disabled = !dirty;
    byId('btnRestore').textContent = dirty ? root.dataset.reset : root.dataset.close;
  }
  function restoreInitialState () {
    var values;
    try { values = JSON.parse(initialState); } catch (_error) { return; }
    fields.forEach(function (id, index) { byId(id).value = values[index]; });
    checks.forEach(function (id, index) { byId(id).checked = values[fields.length + index]; });
    updateDirty();
  }
  function notify (message) {
    // Legacy write endpoints append a navigation meta tag to successful responses.
    var clean = String(message == null ? '' : message).replace(/<meta\s+[^>]*http-equiv\s*=\s*['"]?refresh['"]?[^>]*>/gi, '').trim();
    window.showMessage(clean);
  }
  function initializeTabs () {
    var stored = document.cookie.split('; ').find(function (item) { return item.indexOf('icmpTab=') === 0; });
    var target = root.dataset.filterEvents === '1' ? '#panEvents' : stored ? decodeURIComponent(stored.slice(8)) : '#panDetails';
    var trigger = Array.from(document.querySelectorAll('#icmpDetailsTabs [data-bs-target]')).find(function (button) { return button.dataset.bsTarget === target; });
    if (trigger && window.bootstrap) window.bootstrap.Tab.getOrCreateInstance(trigger).show();
    document.querySelectorAll('#icmpDetailsTabs [data-bs-toggle="tab"]').forEach(function (button) {
      button.addEventListener('shown.bs.tab', function () {
        document.cookie = 'icmpTab=' + encodeURIComponent(button.dataset.bsTarget) + ';max-age=2592000;path=/;SameSite=Strict';
        if (button.id === 'tabGraph' && chart) chart.resize();
        if (button.id === 'tabEvents' && table) table.columns.adjust();
      });
    });
  }
  function initializeTable () {
    if (!$.fn || typeof $.fn.DataTable !== 'function') return;
    table = $('#tableEvents').DataTable({
      paging: true, lengthChange: true, lengthMenu: [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, 'All']],
      searching: true, ordering: true, info: true, autoWidth: false, pageLength: 10, order: [[1, 'desc']],
      columns: [{ data: 0 }, { data: 1 }, { data: 2 }],
      columnDefs: [{ targets: '_all', render: $.fn.dataTable.render.text() }],
      language: window.pialertV4DataTableLanguage({ emptyTable: window.pialertV4Text('V4_No_Data'), lengthMenu: root.dataset.lengthMenu, search: root.dataset.search + ': ', paginate: { next: root.dataset.next, previous: root.dataset.previous }, info: root.dataset.info })
    });
  }
  function initializeChart () {
    if (!window.Chart) return;
    var data = JSON.parse(byId('icmp-detail-chart-data').textContent);
    if (!data.time.length) {
      byId('ServiceChart').hidden = true;
      return;
    }
    chart = new window.Chart(byId('ServiceChart').getContext('2d'), {
      type: 'bar', data: { labels: data.time, datasets: [
        { label: window.pialertV4Text('V4_Online'), data: data.online, backgroundColor: 'rgba(25,135,84,.7)' },
        { label: window.pialertV4Text('V4_Offline_Down'), data: data.down, backgroundColor: 'rgba(220,53,69,.7)' }
      ] },
      options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index' } }, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1, precision: 0 } } } }
    });
  }
  function getTotals () {
    $.get(root.dataset.endpoint + '?action=getEventsTotalsforICMP&hostip=' + encodeURIComponent(root.dataset.hostIp), function (response) {
      var totals;
      try { totals = typeof response === 'string' ? JSON.parse(response) : response; } catch (_error) { return; }
      if (!Array.isArray(totals)) return;
      byId('eventspresence').textContent = Number(totals[0] || 0).toLocaleString() + ' h.';
      byId('eventsdown').textContent = Number(totals[1] || 0).toLocaleString();
    });
  }
  function save (callback) {
    if (!root.dataset.hostIp) return;
    var button = byId('btnSave');
    button.disabled = true;
    return window.pialertPost(root.dataset.endpoint, {
      action: 'setICMPHostData', icmp_ip: root.dataset.hostIp,
      icmp_hostname: byId('txtHostname').value, icmp_type: byId('txtDeviceType').value,
      icmp_group: byId('txtGroup').value, icmp_location: byId('txtLocation').value,
      icmp_owner: byId('txtOwner').value, icmp_notes: byId('txtNotes').value,
      icmp_scanvalid: byId('txtScanValidation').value, icmp_vendor: byId('txtVendor').value,
      icmp_model: byId('txtModel').value, icmp_serial: byId('txtSerialnumber').value,
      mqttdevice: +byId('chkMQTTDevice').checked, favorit: +byId('chkFavorit').checked,
      archived: +byId('chkArchived').checked, alertdown: +byId('chkAlertDown').checked,
      alertevents: +byId('chkAlertEvents').checked
    }, function (message) {
      initialState = state();
      notify(message);
      if (typeof window.updateTotals === 'function') window.updateTotals();
      if (typeof callback === 'function') callback();
    }).always(updateDirty);
  }
  function updateNavigation () {
    byId('txtRecord').textContent = hostPosition >= 0 ? (hostPosition + 1) + ' / ' + hostList.length : '0 / 0';
    byId('btnPrevious').disabled = navigationBusy || hostPosition <= 0;
    byId('btnNext').disabled = navigationBusy || hostPosition < 0 || hostPosition >= hostList.length - 1;
  }
  function initializeNavigation () {
    var fallback = [];
    try { fallback = JSON.parse(byId('icmp-host-navigation-data').textContent); } catch (_error) { fallback = []; }
    try {
      var stored = JSON.parse(readCookie('icmpHostsList'));
      if (Array.isArray(stored) && stored.some(function (host) { return String(host) === root.dataset.hostIp; })) hostList = stored;
    } catch (_error) { hostList = []; }
    if (!hostList.length) hostList = Array.isArray(fallback) ? fallback : [];
    hostList = hostList.map(String).filter(function (host, index, list) { return host !== '' && list.indexOf(host) === index; });
    hostPosition = hostList.findIndex(function (host) { return host === root.dataset.hostIp; });
    if (hostPosition < 0) { hostList = [root.dataset.hostIp]; hostPosition = 0; }
    updateNavigation();
  }
  function navigate (delta, actionHandled) {
    if (navigationBusy) return;
    var editor = window.pialertEntityActionsEditor;
    if (!actionHandled && editor && editor.dirty()) {
      editor.navigationChoice().then(function (choice) {
        if (choice === 'discard') navigate(delta, true);
        else if (choice === 'save') editor.save().then(function (ok) { if (ok) navigate(delta, true); });
      });
      return;
    }
    var next = hostPosition + delta;
    if (next < 0 || next >= hostList.length) return;
    var openHost = function () {
      var target = new URL(window.location.href);
      target.searchParams.set('hostip', hostList[next]);
      unloading = true;
      window.location.assign(target.pathname + target.search + target.hash);
    };
    if (state() !== initialState) {
      navigationBusy = true;
      updateNavigation();
      var request = save(openHost);
      if (request && typeof request.fail === 'function') request.fail(function () { navigationBusy = false; updateNavigation(); });
    } else openHost();
  }
  function removeHost () {
    window.pialertPost(root.dataset.endpoint, { action: 'deleteICMPHost', icmp_ip: root.dataset.hostIp }, function (message) {
      notify(message);
      window.location.assign('icmpmonitor.php');
    });
  }
  function initializeSuggestions () {
    [['txtOwner', 'getOwners'], ['txtDeviceType', 'getDeviceTypes'], ['txtGroup', 'getGroups'], ['txtLocation', 'getLocations']].forEach(function (pair) {
      $.get('php/server/devices.php?action=' + pair[1], function (response) {
        var values;
        try { values = typeof response === 'string' ? JSON.parse(response) : response; } catch (_error) { return; }
        if (!Array.isArray(values)) return;
        var list = byId('suggest-' + pair[0]);
        values.forEach(function (item) {
          var value = item && item.id != null && item.id !== '' ? item.id : item && item.name;
          if (value == null) return;
          var option = document.createElement('option');
          option.value = String(value);
          list.appendChild(option);
        });
      });
    });
  }
  function nmap (mode) {
    var output = byId('scanoutput');
    if (nmapBusy && mode !== 'view') return;
    if (nmapRequest && nmapRequest.readyState !== 4) nmapRequest.abort();
    var generation = ++nmapGeneration;
    var status = byId('nmapstatus');
    status.textContent = root.dataset.nmapLoading;
    status.dataset.tone = '';
    nmapBusy = mode !== 'view';
    byId('manualnmap_fast').disabled = nmapBusy;
    byId('manualnmap_normal').disabled = nmapBusy;
    nmapRequest = window.pialertPost('php/server/nmap_scan.php', { scan: root.dataset.hostIp, mode: mode }, function (markup) {
      if (generation !== nmapGeneration) return;
      window.pialertNmapResults.render(markup,output,root.dataset.hostIp,function () { nmap('view'); });
      status.textContent = '';
      nmapBusy = false;
      byId('manualnmap_fast').disabled = false;
      byId('manualnmap_normal').disabled = false;
    }).fail(function (_xhr,error) {
      if (error === 'abort' || generation !== nmapGeneration) return;
      status.textContent = root.dataset.nmapError;
      status.dataset.tone = 'error';
      nmapBusy = false;
      byId('manualnmap_fast').disabled = false;
      byId('manualnmap_normal').disabled = false;
    });
    return nmapRequest;
  }

  fields.concat(checks).forEach(function (id) {
    byId(id).addEventListener('input', updateDirty);
    byId(id).addEventListener('change', updateDirty);
  });
  initialState = state();
  initializeNavigation();
  byId('btnSave').addEventListener('click', function () { save(); });
  byId('btnRestore').addEventListener('click', function () {
    if (state() !== initialState) restoreInitialState();
    else window.location.assign(root.dataset.backUrl);
  });
  byId('btnDelete').addEventListener('click', function () {
    window.showModalWarning(root.dataset.deleteTitle, root.dataset.deleteMessage, root.dataset.cancel, root.dataset.delete, removeHost);
  });
  byId('btnPrevious').addEventListener('click', function () { navigate(-1); });
  byId('btnNext').addEventListener('click', function () { navigate(1); });
  byId('manualnmap_fast').textContent = root.dataset.fast + ' (' + root.dataset.hostIp + ')';
  byId('manualnmap_normal').textContent = root.dataset.normal + ' (' + root.dataset.hostIp + ')';
  byId('manualnmap_fast').addEventListener('click', function () { nmap('fast'); });
  byId('manualnmap_normal').addEventListener('click', function () { nmap('normal'); });
  if (window.pialertEntityActionsEditor) window.pialertEntityActionsEditor.setTarget('icmp', root.dataset.hostIp);
  initializeTabs(); initializeTable(); initializeChart(); initializeSuggestions(); getTotals(); nmap('view');
  window.addEventListener('beforeunload', function (event) { if ((state() !== initialState || (window.pialertEntityActionsEditor && window.pialertEntityActionsEditor.dirty())) && !unloading) { event.preventDefault(); event.returnValue = ''; } });
  window.addEventListener('pagehide', function () { unloading = true; ++nmapGeneration; if (nmapRequest) nmapRequest.abort(); if (table) table.destroy(); if (chart) chart.destroy(); });
})(window, document, window.jQuery);

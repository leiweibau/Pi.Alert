(function (window, document, $) {
  'use strict';
  var root = document.getElementById('service-details-page');
  if (!root) return;
  var table = null;
  var chart = null;

  function field (id) { return document.getElementById(id); }
  function boolValue (id) { return field(id).checked ? 1 : 0; }

  function initializeTabs () {
    var selector = document.cookie.split('; ').find(function (entry) { return entry.indexOf('serviceTab=') === 0; });
    var target = selector ? decodeURIComponent(selector.slice('serviceTab='.length)) : '#panDetails';
    if (root.dataset.filter !== 'all') target = '#panEvents';
    var trigger = document.querySelector('[data-bs-target="' + target + '"]');
    if (trigger && window.bootstrap) window.bootstrap.Tab.getOrCreateInstance(trigger).show();
    document.querySelectorAll('#serviceDetailsTabs [data-bs-toggle="tab"]').forEach(function (button) {
      button.addEventListener('shown.bs.tab', function () {
        document.cookie = 'serviceTab=' + encodeURIComponent(button.dataset.bsTarget) + ';max-age=2592000;path=/;SameSite=Strict';
        if (button.id === 'tabGraph' && chart) chart.resize();
      });
    });
  }

  function initializeTable () {
    if (!$.fn || typeof $.fn.DataTable !== 'function') return;
    table = $('#tableEvents').DataTable({
      paging: true, lengthChange: true,
      lengthMenu: [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, 'All']],
      searching: true, ordering: true, info: true, autoWidth: false, pageLength: 10,
      order: [[1, 'desc']], columns: [{ data: 0 }, { data: 1 }, { data: 2 }, { data: 3 }, { data: 4 }],
      columnDefs: [{ targets: '_all', render: $.fn.dataTable.render.text() }, { className: 'text-center', targets: [1, 2, 3, 4] }],
      language: window.pialertV4DataTableLanguage({ emptyTable: window.pialertV4Text('V4_No_Data'), lengthMenu: root.dataset.lengthMenu, search: root.dataset.search + ': ', paginate: { next: root.dataset.next, previous: root.dataset.previous }, info: root.dataset.info })
    });
  }

  function initializeChart () {
    if (!window.Chart) return;
    var data = JSON.parse(field('service-chart-data').textContent);
    chart = new window.Chart(field('ServiceChart').getContext('2d'), {
      type: 'bar', data: { labels: data.time, datasets: [
        { label: '2xx', data: data['2xx'], borderColor: 'rgb(0,166,89)', backgroundColor: 'rgba(0,166,89,.6)' },
        { label: '3xx', data: data['3xx'], borderColor: 'rgb(242,156,18)', backgroundColor: 'rgba(242,156,18,.7)' },
        { label: '4xx', data: data['4xx'], borderColor: 'rgb(242,156,18)', backgroundColor: 'rgba(242,156,18,.7)' },
        { label: '5xx', data: data['5xx'], borderColor: 'rgb(254,76,0)', backgroundColor: 'rgba(254,76,0,.7)' },
        { label: window.pialertV4Text('V4_Down'), data: data.down, borderColor: 'rgb(189,43,26)', backgroundColor: 'rgba(189,43,26,.7)' }
      ] }, options: { maintainAspectRatio: false, plugins: { legend: { labels: { color: '#888' } }, tooltip: { mode: 'index' } }, scales: { x: { stacked: true, ticks: { color: '#888' }, grid: { display: false } }, y: { stacked: true, ticks: { display: false }, grid: { display: false } } } }
    });
    window.serviceHistoryChart = chart;
  }

  function getTotals () {
    $.get(root.dataset.endpoint + '?action=getEventsTotalsforService&url=' + encodeURIComponent(root.dataset.serviceUrl), function (response) {
      var totals;
      try { totals = typeof response === 'string' ? JSON.parse(response) : response; } catch (_error) { return; }
      if (!Array.isArray(totals)) return;
      ['eventsAll', 'events2xx', 'events3xx', 'events4xx', 'events5xx', 'eventsDown'].forEach(function (id, index) {
        if (field(id)) field(id).textContent = Number(totals[index] || 0).toLocaleString();
      });
    });
  }

  function setServiceData (refreshCallback) {
    if (!root.dataset.serviceUrl) return;
    return window.pialertPost(root.dataset.endpoint, { action: 'setServiceData', url: field('txtURL').value, tags: field('txtTags').value, mac: field('txtMAC').value, alertdown: boolValue('chkAlertDown'), alertup: boolValue('chkAlertUp'), alertevents: boolValue('chkAlertEvents') }, function (message) {
      window.showMessage(message);
      if (typeof refreshCallback === 'function') refreshCallback();
    });
  }

  function askDeleteService () {
    if (!root.dataset.serviceUrl) return;
    window.showModalWarning(root.dataset.deleteTitle, root.dataset.deleteMessage, root.dataset.cancel, root.dataset.delete, deleteService);
  }

  function deleteService () {
    if (!root.dataset.serviceUrl) return;
    window.pialertPost(root.dataset.endpoint + '?action=deleteService&url=' + encodeURIComponent(root.dataset.serviceUrl), function (message) { window.showMessage(message); });
    document.querySelectorAll('#panDetails input, #panDetails button').forEach(function (control) { control.disabled = true; });
  }

  function geoAction (action) {
    var button = field(action === 'downloadGeoDB' ? 'downloadDB-button' : 'deleteDB-button');
    if (button) button.disabled = true;
    if (field('downloader')) field('downloader').hidden = false;
    return window.pialertPost(root.dataset.endpoint, { action: action }, function () { window.location.reload(); });
  }

  field('btnSave').addEventListener('click', function () { setServiceData(); });
  field('btnRestore').addEventListener('click', function () { window.location.reload(); });
  field('btnDelete').addEventListener('click', askDeleteService);
  if (field('downloadDB-button')) field('downloadDB-button').addEventListener('click', function () { geoAction('downloadGeoDB'); });
  if (field('deleteDB-button')) field('deleteDB-button').addEventListener('click', function () { geoAction('deleteGeoDB'); });
  window.setServiceData = setServiceData;
  window.askDeleteService = askDeleteService;
  window.deleteService = deleteService;
  initializeTabs(); initializeTable(); initializeChart(); getTotals();
  window.addEventListener('pagehide', function () { if (table) table.destroy(); if (chart) chart.destroy(); });
})(window, document, window.jQuery);

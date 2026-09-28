(function (window, document, $) {
  'use strict';
  var root = document.getElementById('dashboard-page');
  if (!root || !$) return;
  var configNode = document.getElementById('dashboard-page-config');
  var config = configNode ? JSON.parse(configNode.textContent || '{}') : {};
  var labels = config.labels || {};
  var charts = {};
  var eventsTable = null;
  var refreshTimer = null;
  var countdownTimer = null;
  var nextRefreshAt = 0;
  var zoomCookieName = 'pialert_dashboard_zoom';
  var zoomLevel = readZoomLevel();
  var logDates = [];
  var logIndex = -1;
  var currentLog = '';
  var logModal = window.bootstrap.Modal.getOrCreateInstance(document.getElementById('logModal'));
  var reportModal = window.bootstrap.Modal.getOrCreateInstance(document.getElementById('reportModal'));
  var centerText = {
    id: 'dashboardCenterText',
    beforeDraw: function (chart, _args, options) {
      if (!options || !options.top) return;
      var area = chart.chartArea;
      var ctx = chart.ctx;
      var x = (area.left + area.right) / 2;
      var y = (area.top + area.bottom) / 2;
      ctx.save();
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillStyle = getComputedStyle(document.body).color;
      ctx.font = 'bold 22px system-ui'; ctx.fillText(options.top, x, y - 9);
      ctx.font = '13px system-ui'; ctx.fillText(options.bottom || '', x, y + 13);
      ctx.restore();
    }
  };

  function byId (id) { return document.getElementById(id); }
  function replaceChart (name, canvas, definition) {
    if (!window.Chart || !canvas) return;
    if (charts[name]) charts[name].destroy();
    charts[name] = new window.Chart(canvas, definition);
  }
  function api (action, data, callback) {
    $.ajax({ url: 'php/server/dashboard.php', type: 'GET', dataType: 'json', data: Object.assign({ action: action }, data || {}) }).done(callback);
  }
  function donut (id, values, labels, colors, caption) {
    var total = values.reduce(function (sum, value) { return sum + Number(value || 0); }, 0);
    replaceChart(id, byId(id), {
      type: 'doughnut',
      data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12 } },
        dashboardCenterText: { top: total.toLocaleString(), bottom: caption }
      } },
      plugins: [centerText]
    });
  }
  function loadDeviceStatus () {
    api('getLocalDeviceStatus', {}, function (data) {
      if (!data || data.online === undefined) return;
      donut('devicesDonut', [data.online, data.offline, data.archived], [labels.online, labels.offline, labels.archived], ['#2ecc71', '#e74c3c', '#95a5a6'], labels.devices);
    });
    api('getIcmpDeviceStatus', {}, function (data) {
      if (!data || data.online === undefined) return;
      donut('devicesDonutIcmp', [data.online, data.offline, data.archived], [labels.online, labels.offline, labels.archived], ['#2ecc71', '#e74c3c', '#95a5a6'], labels.icmpDevices);
    });
    api('getServiceStatusSummary', {}, function (data) {
      if (!data || !Array.isArray(data.labels) || !Array.isArray(data.data)) return;
      var colors = { Offline: '#e74c3c', '1xx': '#3498db', '2xx': '#2ecc71', '3xx': '#f1c40f', '4xx': '#e67e22', '5xx': '#ff4c3c', Other: '#7f8c8d' };
      donut('servicesStatusDonut', data.data, data.labels.map(function (label) { return label === 'Offline' ? labels.offline : (label === 'Other' ? labels.otherStatus : label); }), data.labels.map(function (label) { return colors[label] || colors.Other; }), labels.services);
    });
  }
  function loadSpeedtest (days) {
    document.querySelectorAll('.dashboard-speedtest-range').forEach(function (button) {
      var active = Number(button.dataset.days) === days;
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed', String(active));
    });
    api('getSpeedtestHistory', { days: days }, function (data) {
      if (!data || !Array.isArray(data.labels)) return;
      replaceChart('speedtest', byId('speedtestChart'), {
        type: 'line', data: { labels: data.labels, datasets: [
          { label: labels.ping + ' (ms)', data: data.ping, borderColor: '#3498db', backgroundColor: '#3498db', fill: false, borderWidth: 1, pointRadius: 2, tension: .2 },
          { label: labels.download + ' (Mbps)', data: data.down, borderColor: '#2ecc71', backgroundColor: '#2ecc71', fill: false, borderWidth: 1, pointRadius: 2, tension: .2 },
          { label: labels.upload + ' (Mbps)', data: data.up, borderColor: '#e74c3c', backgroundColor: '#e74c3c', fill: false, borderWidth: 1, pointRadius: 2, tension: .2 }
        ] },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            x: { ticks: { callback: function (value) {
              var label = this.getLabelForValue(value);
              if (!label) return '';
              var parts = String(label).split(' ');
              if (parts.length < 2) return label;
              var date = parts[0].split('-');
              return date.length === 3 ? [date[1] + '.' + date[2] + '.', parts[1].slice(0, 5)] : label;
            } } },
            y: { beginAtZero: true, ticks: { maxTicksLimit: 4 } }
          }
        }
      });
    });
  }
  function history (source) {
    var id = 'historyChart_' + source;
    var canvas = byId(id);
    if (!canvas) {
      var wrapper = document.createElement('section');
      wrapper.className = 'mb-3';
      var heading = document.createElement('h3');
      heading.className = 'h6';
      heading.textContent = config.historyTitle + ': ' + (source === 'main_scan' ? labels.mainScan : labels.icmpScan);
      var container = document.createElement('div');
      container.className = 'dashboard-history-chart';
      canvas = document.createElement('canvas'); canvas.id = id;
      container.appendChild(canvas); wrapper.append(heading, container); byId('historyChartsContainer').appendChild(wrapper);
    }
    api('getDeviceHistoryChart', { source: source }, function (data) {
      if (!data || !Array.isArray(data.labels)) return;
      replaceChart(id, canvas, {
        type: 'bar', data: data,
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } }, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1, precision: 0 } } } }
      });
    });
  }
  function reportCounts () {
    api('getReportsCount', {}, function (data) {
      if (!data) return;
      byId('reportsCount').textContent = Number(data.reports || 0).toLocaleString();
      byId('reportsArchiveCount').textContent = Number(data.archive || 0).toLocaleString();
    });
  }
  function reportName (filename) { return filename.replace(/^[0-9]{8}-[0-9]{6}_/, '').replace(/\.txt$/i, ''); }
  function showReport (filename) {
    byId('reportModalTitle').textContent = filename;
    byId('reportModalContent').textContent = labels.loading;
    reportModal.show();
    $.get('php/server/dashboard.php', { action: 'getReportContent', file: filename }, function (data) { byId('reportModalContent').textContent = String(data); }).fail(function () { byId('reportModalContent').textContent = labels.reportError; });
  }
  function latestReports () {
    api('getLatestReports', {}, function (data) {
      var container = byId('latestReports');
      container.replaceChildren();
      if (!Array.isArray(data) || !data.length) {
        var empty = document.createElement('em'); empty.textContent = labels.noReports; container.appendChild(empty); return;
      }
      var list = document.createElement('ul'); list.className = 'list-unstyled mb-0';
      data.forEach(function (item) {
        var filename = String(item.name == null ? '' : item.name);
        var row = document.createElement('li');
        var link = document.createElement('a'); link.href = '#'; link.textContent = reportName(filename);
        link.addEventListener('click', function (event) { event.preventDefault(); showReport(filename); });
        var time = document.createElement('small'); time.className = 'text-body-secondary'; time.textContent = String(item.time == null ? '' : item.time);
        row.append(link, time); list.appendChild(row);
      });
      container.appendChild(list);
    });
  }
  function eventLink (cell, label, row) {
    cell.replaceChildren();
    var link = document.createElement('a');
    var name = String(label == null ? '' : label);
    if (row[13]) {
      link.textContent = name;
    } else {
      // The ICMP event endpoint already includes this suffix in dev_name.
      link.appendChild(document.createTextNode(name.replace(/ \*\*$/, '') + ' '));
      var marker = document.createElement('strong');
      marker.className = 'text-warning';
      marker.textContent = '**';
      link.appendChild(marker);
    }
    link.href = row[13] ? 'deviceDetails.php?mac=' + encodeURIComponent(String(row[13])) : 'icmpmonitorDetails.php?hostip=' + encodeURIComponent(String(row[9] == null ? '' : row[9]));
    cell.appendChild(link);
  }
  function initializeEvents () {
    if (!$.fn || typeof $.fn.DataTable !== 'function') return;
    eventsTable = $('#tableEvents').DataTable({
      ajax: { url: 'php/server/events.php', data: { action: 'getEvents', type: 'all', period: '1 day' }, dataSrc: 'data', cache: false },
      paging: false, searching: false, info: false, lengthChange: false, ordering: true,
      order: [[0, 'desc'], [3, 'desc'], [5, 'desc']], scrollY: '330', scrollX: true, scrollCollapse: true, autoWidth: true, pageLength: 50,
      columnDefs: [{ targets: '_all', render: $.fn.dataTable.render.text() }, { visible: false, targets: [0, 2, 5, 6, 7, 8, 10, 11] }, { targets: [1], createdCell: eventLink }],
      processing: true, language: window.pialertV4DataTableLanguage({ emptyTable: labels.noData })
    });
  }
  function loadLogDates () {
    currentLog = byId('logfileSelect').value;
    logDates = []; logIndex = -1;
    var select = byId('dateSelect');
    select.replaceChildren(new Option(currentLog ? labels.loading : labels.selectDate, ''));
    if (!currentLog) return;
    api('getLogfileDatesAsJson', { logfile: currentLog }, function (data) {
      logDates = Array.isArray(data) ? data.map(String) : [];
      select.replaceChildren(new Option(labels.selectDate, ''));
      logDates.forEach(function (date) { select.appendChild(new Option(date, date)); });
    });
  }
  function updateLogButtons () {
    document.querySelector('.btn-prev').disabled = logIndex <= 0;
    document.querySelector('.btn-next').disabled = logIndex >= logDates.length - 1;
  }
  function loadLog (date) {
    byId('logContent').textContent = labels.loadingLog;
    $.get('php/server/dashboard.php', { action: 'getLogfileContent', logfile: currentLog, date: date }, function (data) {
      byId('logModalTitle').textContent = currentLog + ' – ' + date;
      byId('logContent').textContent = String(data);
      updateLogButtons();
    }).fail(function () { byId('logContent').textContent = labels.logError; });
  }
  function showLog () {
    var date = byId('dateSelect').value;
    if (!currentLog || !date) return;
    logIndex = logDates.indexOf(date);
    if (logIndex < 0) return;
    loadLog(date); logModal.show();
  }
  function navigateLog (direction) {
    var index = logIndex + direction;
    if (index < 0 || index >= logDates.length) return;
    logIndex = index;
    byId('dateSelect').value = logDates[index];
    loadLog(logDates[index]);
  }
  function refresh () {
    if (document.hidden) return;
    loadDeviceStatus(); reportCounts(); latestReports(); history('main_scan'); history('icmp_scan');
    if (eventsTable) eventsTable.ajax.reload(null, false);
  }
  function updateCountdown () {
    var target = byId('dashboardRefreshCountdownValue');
    if (target) target.textContent = String(Math.max(0, Math.ceil((nextRefreshAt - Date.now()) / 1000)));
  }
  function stopPolling () {
    if (refreshTimer) window.clearInterval(refreshTimer);
    if (countdownTimer) window.clearInterval(countdownTimer);
    refreshTimer = null;
    countdownTimer = null;
  }
  function startPolling () {
    stopPolling();
    if (document.hidden) return;
    nextRefreshAt = Date.now() + 120000;
    updateCountdown();
    refreshTimer = window.setInterval(function () { refresh(); nextRefreshAt = Date.now() + 120000; updateCountdown(); }, 120000);
    countdownTimer = window.setInterval(updateCountdown, 1000);
  }
  function validZoomLevel (value) {
    return Number.isInteger(value) && value >= 50 && value <= 150 && value % 10 === 0;
  }
  function readZoomLevel () {
    var prefix = zoomCookieName + '=';
    var cookie = document.cookie.split(';').map(function (part) { return part.trim(); })
      .find(function (part) { return part.indexOf(prefix) === 0; });
    if (!cookie) return 100;
    var value = Number(cookie.slice(prefix.length));
    return validZoomLevel(value) ? value : 100;
  }
  function saveZoomLevel () {
    var cookie = zoomCookieName + '=' + zoomLevel + '; Max-Age=31536000; Path=' +
      window.location.pathname + '; SameSite=Lax';
    document.cookie = cookie + (window.location.protocol === 'https:' ? '; Secure' : '');
  }
  function applyZoom () {
    // Zoom the dashboard content, not AdminLTE's viewport-sized app shell.
    // The shell is capped at 100vw and would otherwise leave unused space.
    root.style.zoom = zoomLevel + '%';
    root.style.width = '100%';
    byId('zoom-percent').textContent = zoomLevel + '%';
    if (eventsTable) eventsTable.columns.adjust();
  }
  function zoom (action) {
    if (action === 'reset') zoomLevel = 100;
    else zoomLevel = Math.max(50, Math.min(150, zoomLevel + (action === 'in' ? 10 : -10)));
    saveZoomLevel();
    applyZoom();
  }
  document.querySelectorAll('.dashboard-speedtest-range').forEach(function (button) { button.addEventListener('click', function () { loadSpeedtest(Number(button.dataset.days)); }); });
  byId('logfileSelect').addEventListener('change', loadLogDates);
  byId('showLog').addEventListener('click', showLog);
  document.querySelectorAll('[data-log-direction]').forEach(function (button) { button.addEventListener('click', function () { navigateLog(Number(button.dataset.logDirection)); }); });
  document.querySelectorAll('[data-dashboard-zoom]').forEach(function (button) { button.addEventListener('click', function () { zoom(button.dataset.dashboardZoom); }); });
  applyZoom(); initializeEvents(); loadSpeedtest(7); refresh(); startPolling();
  document.addEventListener('visibilitychange', function () { if (document.hidden) stopPolling(); else { refresh(); startPolling(); } });
  window.addEventListener('pagehide', function () {
    stopPolling();
    if (eventsTable) eventsTable.destroy();
    Object.values(charts).forEach(function (chart) { chart.destroy(); });
  });
})(window, document, window.jQuery);

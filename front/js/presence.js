(function (window, document, $) {
  'use strict';
  var node = document.getElementById('presence-page-config');
  var root = document.getElementById('presence-page');
  if (!node || !root || !$) return;
  var config = JSON.parse(node.textContent || '{}');
  var labels = config.labels || {};
  var scanSource = String(config.scanSource || 'local');
  var status = 'all';
  var chart = null;
  var timer = null;
  var calendar = $('#calendar');

  function endpoint (script, action, extra) {
    return 'php/server/' + script + '.php?' + new URLSearchParams(Object.assign({ action: action, scansource: scanSource }, extra || {})).toString();
  }
  function detailUrl (mac) { return 'deviceDetails.php?mac=' + encodeURIComponent(String(mac == null ? '' : mac)); }
  function refreshTotals () {
    if (document.hidden) return;
    $.get(endpoint('devices', 'getDevicesTotals'), function (response) {
      var totals;
      try { totals = typeof response === 'string' ? JSON.parse(response) : response; } catch (_error) { return; }
      if (!Array.isArray(totals)) return;
      ['devicesAll', 'devicesConnected', 'devicesFavorites', 'devicesNew', 'devicesDown', 'devicesHidden'].forEach(function (id, index) {
        var target = document.getElementById(id);
        if (target) target.textContent = Number(totals[index] || 0).toLocaleString();
      });
    });
  }
  function selectStatus (next) {
    status = next;
    var tones = { all: 'primary', connected: 'success', favorites: 'warning', new: 'warning', down: 'danger', archived: 'secondary' };
    var card = document.getElementById('tableDevicesBox');
    card.className = 'card card-' + (tones[status] || 'secondary') + ' card-outline';
    document.getElementById('tableDevicesTitle').textContent = labels[status] || labels.all || window.pialertV4Text('V4_Shortcut_Devices');
    document.querySelectorAll('.presence-filter').forEach(function (button) { button.setAttribute('aria-pressed', String(button.dataset.presenceStatus === status)); });
    calendar.fullCalendar('option', 'resources', endpoint('devices', 'getDevicesListCalendar', { status: status }));
    calendar.fullCalendar('refetchResources');
    calendar.fullCalendar('removeEventSources');
    calendar.fullCalendar('addEventSource', { url: endpoint('events', 'getEventsCalendar') });
  }
  function initializeCalendar () {
    if (typeof $.fn.fullCalendar !== 'function') return;
    calendar.fullCalendar({
      header: { left: 'prev,next today', center: 'title', right: 'timelineMonth,timelineWeek,timelineDay' },
      defaultView: 'timelineMonth', height: 'auto', firstDay: 1, allDaySlot: false, timeFormat: 'H:mm',
      resourceLabelText: labels.resource || 'Devices', resourceAreaWidth: '160px', slotWidth: '1px', resourceOrder: '-favorite,title',
      locale: labels.locale || 'en', schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
      views: {
        timelineYear: { type: 'timeline', duration: { year: 1 }, buttonText: labels.year || 'Year', slotLabelFormat: 'MMM', slotDuration: { minutes: 44641 } },
        timelineQuarter: { type: 'timeline', duration: { month: 3 }, buttonText: labels.quarter || 'Quarter', slotLabelFormat: 'MMM', slotDuration: { minutes: 44641 } },
        timelineMonth: { type: 'timeline', duration: { month: 1 }, buttonText: labels.month || 'Month', slotLabelFormat: 'D', slotDuration: '24:00:01' },
        timelineWeek: { type: 'timeline', duration: { week: 1 }, buttonText: labels.week || 'Week', slotLabelFormat: 'D', slotDuration: '24:00:01' },
        timelineDay: { type: 'timeline', duration: { day: 1 }, buttonText: labels.day || 'Day', slotLabelFormat: 'H', slotDuration: '00:30:00' }
      },
      dayRender: function (date, cell) {
        var view = calendar.fullCalendar('getView').name;
        if (view === 'timelineYear') { cell.removeClass('fc-sat fc-sun'); return; }
        if (date.day() === 0) cell.addClass('fc-sun');
        if (date.day() === 6) cell.addClass('fc-sat');
        if (date.format('YYYY-MM-DD') === window.moment().format('YYYY-MM-DD')) cell.addClass('fc-today');
        if (view === 'timelineDay') {
          cell.removeClass('fc-sat fc-sun fc-today');
          if (date.format('YYYY-MM-DD HH') === window.moment().format('YYYY-MM-DD HH')) cell.addClass('fc-today');
        }
      },
      resourceRender: function (resource, labelTds) {
        var cell = labelTds.find('span.fc-cell-text').get(0);
        if (!cell) return;
        var link = document.createElement('a');
        link.href = detailUrl(resource.id);
        link.textContent = String(resource.title == null ? '' : resource.title);
        cell.replaceChildren(link);
      },
      eventRender: function (event, element) {
        if (event.tooltip && window.bootstrap && window.bootstrap.Tooltip) {
          element.attr('title', String(event.tooltip));
          window.bootstrap.Tooltip.getOrCreateInstance(element[0], { container: 'body', placement: 'bottom' });
        }
      },
      loading: function (busy) { document.getElementById('loading').hidden = !busy; }
    });
    selectStatus('all');
  }
  function initializeChart () {
    var canvas = document.getElementById('OnlineChart');
    if (!canvas || !window.Chart) return;
    var history = config.history || {};
    chart = new window.Chart(canvas, {
      type: 'bar', data: { labels: history.time || [], datasets: [
        { label: window.pialertV4Text('V4_Online'), data: history.online || [], backgroundColor: 'rgba(25,135,84,.65)' },
        { label: window.pialertV4Text('V4_Offline_Down'), data: history.down || [], backgroundColor: 'rgba(220,53,69,.65)' },
        { label: window.pialertV4Text('V4_Archived'), data: history.archived || [], backgroundColor: 'rgba(108,117,125,.65)' }
      ] },
      options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } }, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1, precision: 0 } } } }
    });
  }
  document.querySelectorAll('.presence-filter').forEach(function (button) {
    button.addEventListener('click', function () { selectStatus(button.dataset.presenceStatus); });
  });
  initializeChart(); initializeCalendar(); refreshTotals();
  timer = window.setInterval(refreshTotals, 120000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) refreshTotals(); });
  window.addEventListener('pagehide', function () { if (timer) window.clearInterval(timer); if (chart) chart.destroy(); if (typeof $.fn.fullCalendar === 'function') calendar.fullCalendar('destroy'); });
})(window, document, window.jQuery);

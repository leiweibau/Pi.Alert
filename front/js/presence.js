(function (window, document, $) {
  'use strict';
  var node = document.getElementById('presence-page-config');
  var root = document.getElementById('presence-page');
  if (!node || !root || !$ || !window.FullCalendar) return;
  var config = JSON.parse(node.textContent || '{}');
  var labels = config.labels || {};
  var scanSource = String(config.scanSource || 'local');
  var status = 'all';
  var chart = null;
  var timer = null;
  var calendar = null;
  var pendingLoads = 0;
  var requests = { resources: null, events: null };
  var generations = { resources: 0, events: 0 };

  function endpoint (script, action, extra) {
    return 'php/server/' + script + '.php?' + new URLSearchParams(Object.assign({ action: action, scansource: scanSource }, extra || {})).toString();
  }
  function detailUrl (mac) { return 'deviceDetails.php?mac=' + encodeURIComponent(String(mac == null ? '' : mac)); }
  function setLoading (delta) {
    pendingLoads = Math.max(0, pendingLoads + delta);
    var loading = document.getElementById('loading');
    if (loading) loading.hidden = pendingLoads === 0;
  }
  function reportCalendarError (message) {
    var text = window.pialertV4Text('V4_Request_Failed');
    if (window.console && message) window.console.error('Calendar request:', message);
    if (window.showMessage) window.showMessage(text);
  }
  function normalizeArray (response, map) {
    if (response === '') return [];
    if (!Array.isArray(response)) throw new Error('Unexpected calendar response');
    return map ? response.map(map) : response;
  }
  function fetchCalendarData (kind, url, success, failure, map) {
    var generation = ++generations[kind];
    if (requests[kind] && requests[kind].readyState !== 4) requests[kind].abort();
    var settled = false;
    function finish (callback, value) {
      if (settled) return;
      settled = true;
      setLoading(-1);
      callback(value);
    }
    setLoading(1);
    requests[kind] = $.ajax({ url: url, dataType: 'json', cache: false })
      .done(function (response) {
        if (generation !== generations[kind]) { finish(success, []); return; }
        try { finish(success, normalizeArray(response, map)); }
        catch (error) { reportCalendarError(error.message); finish(failure, error); }
      })
      .fail(function (xhr, requestStatus) {
        if (requestStatus === 'abort' || generation !== generations[kind]) { finish(success, []); return; }
        var error = new Error(xhr.status ? 'HTTP ' + xhr.status + ' ' + xhr.statusText : window.pialertV4Text('V4_Request_Failed'));
        reportCalendarError(error.message);
        finish(failure, error);
      });
  }
  function resourceSource (_fetchInfo, success, failure) {
    fetchCalendarData('resources', endpoint('devices', 'getDevicesListCalendar', { status: status }), success, failure, function (resource) {
      var deviceId = String(resource.id == null ? '' : resource.id);
      return Object.assign({}, resource, { id: deviceId.toLowerCase(), deviceId: deviceId, favorite: Number(resource.favorite || 0) });
    });
  }
  function eventSource (fetchInfo, success, failure) {
    fetchCalendarData('events', endpoint('events', 'getEventsCalendar', { start: fetchInfo.startStr, end: fetchInfo.endStr }), success, failure, function (event) {
      var normalized = Object.assign({}, event, { resourceId: String(event.resourceId == null ? '' : event.resourceId).toLowerCase() });
      if (normalized.className != null && normalized.classNames == null) normalized.classNames = Array.isArray(normalized.className) ? normalized.className : String(normalized.className).split(/\s+/);
      delete normalized.className;
      return normalized;
    });
  }
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
  function updateFilterUi () {
    var tones = { all: 'primary', connected: 'success', favorites: 'warning', new: 'warning', down: 'danger', archived: 'secondary' };
    var card = document.getElementById('tableDevicesBox');
    card.className = 'card card-' + (tones[status] || 'secondary') + ' card-outline';
    document.getElementById('tableDevicesTitle').textContent = labels[status] || labels.all || window.pialertV4Text('V4_Shortcut_Devices');
    document.querySelectorAll('.presence-filter').forEach(function (button) { button.setAttribute('aria-pressed', String(button.dataset.presenceStatus === status)); });
  }
  function selectStatus (next) {
    status = next;
    updateFilterUi();
    if (calendar) { calendar.refetchResources(); calendar.refetchEvents(); }
  }
  function slotClasses (argument) {
    var view = argument.view.type;
    var now = new Date();
    var date = argument.date;
    var classes = [];
    if (view !== 'resourceTimelineYear' && view !== 'resourceTimelineDay') {
      if (date.getDay() === 0) classes.push('fc-pialert-sunday');
      if (date.getDay() === 6) classes.push('fc-pialert-saturday');
      if (date.toDateString() === now.toDateString()) classes.push('fc-pialert-today');
    }
    if (view === 'resourceTimelineDay' && date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth() && date.getDate() === now.getDate() && date.getHours() === now.getHours()) classes.push('fc-pialert-current-hour');
    return classes;
  }
  function initializeCalendar () {
    calendar = new window.FullCalendar.Calendar(document.getElementById('calendar'), {
      headerToolbar: { left: 'prev,next today', center: 'title', right: 'resourceTimelineMonth,resourceTimelineWeek,resourceTimelineDay' },
      initialView: 'resourceTimelineMonth', height: 'auto', firstDay: 1, timeZone: 'local',
      eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false, omitZeroMinute: false },
      eventMinWidth: 1,
      resourceAreaHeaderContent: labels.resource || 'Devices', resourceAreaWidth: '180px', resourceOrder: '-favorite,title',
      locale: labels.locale || 'en', schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
      editable: false, selectable: false, eventStartEditable: false, eventDurationEditable: false, eventResourceEditable: false,
      resources: resourceSource, events: eventSource,
      views: {
        resourceTimelineYear: { type: 'resourceTimeline', duration: { years: 1 }, buttonText: labels.year || 'Year', slotDuration: { months: 1 }, slotLabelFormat: { month: 'short' }, slotMinWidth: 42 },
        resourceTimelineQuarter: { type: 'resourceTimeline', duration: { months: 3 }, buttonText: labels.quarter || 'Quarter', slotDuration: { months: 1 }, slotLabelFormat: { month: 'short' }, slotMinWidth: 64 },
        resourceTimelineMonth: { type: 'resourceTimeline', duration: { months: 1 }, buttonText: labels.month || 'Month', slotDuration: { hours: 24 }, slotLabelInterval: { hours: 24 }, slotLabelFormat: { day: 'numeric' }, slotMinWidth: 24 },
        resourceTimelineWeek: { type: 'resourceTimeline', duration: { weeks: 1 }, buttonText: labels.week || 'Week', slotDuration: { hours: 24 }, slotLabelInterval: { hours: 24 }, slotLabelFormat: { weekday: 'short', day: 'numeric' }, slotMinWidth: 54 },
        resourceTimelineDay: { type: 'resourceTimeline', duration: { days: 1 }, buttonText: labels.day || 'Day', slotDuration: '00:30:00', slotLabelInterval: '01:00:00', slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false }, slotMinWidth: 34 }
      },
      slotLaneClassNames: slotClasses,
      slotLabelClassNames: slotClasses,
      resourceLabelContent: function (argument) {
        var link = document.createElement('a');
        link.href = detailUrl(argument.resource.extendedProps.deviceId || argument.resource.id);
        link.textContent = String(argument.resource.title == null ? '' : argument.resource.title);
        return { domNodes: [link] };
      },
      eventDidMount: function (argument) {
        var tooltip = argument.event.extendedProps.tooltip;
        if (tooltip && window.bootstrap && window.bootstrap.Tooltip) {
          argument.el.setAttribute('title', String(tooltip).replace(/\r\n?/g, '\n'));
          window.bootstrap.Tooltip.getOrCreateInstance(argument.el, { container: 'body', placement: 'bottom', customClass: 'pialert-calendar-tooltip' });
        }
      },
      eventWillUnmount: function (argument) {
        if (window.bootstrap && window.bootstrap.Tooltip) {
          var tooltip = window.bootstrap.Tooltip.getInstance(argument.el);
          if (tooltip) tooltip.dispose();
        }
      }
    });
    calendar.render();
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
  updateFilterUi(); initializeChart(); initializeCalendar(); refreshTotals();
  timer = window.setInterval(refreshTotals, 120000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) refreshTotals(); });
  window.addEventListener('pagehide', function () {
    if (timer) window.clearInterval(timer);
    Object.keys(requests).forEach(function (kind) { ++generations[kind]; if (requests[kind]) requests[kind].abort(); });
    if (chart) chart.destroy();
    if (calendar) calendar.destroy();
  });
})(window, document, window.jQuery);

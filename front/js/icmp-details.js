(function (window, document, $) {
  'use strict';
  var root = document.getElementById('icmp-details-page');
  if (!root || !$) return;
  var table = null;
  var timeline = null;
  var timelineRequest = null;
  var timelineGeneration = 0;
  var timelineLayoutTimer = null;
  var timelineTimer = null;
  var timelineDurationMinutes = null;
  var calendar = null;
  var calendarRequest = null;
  var calendarGeneration = 0;
  var calendarLayoutTimer = null;
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
        if (button.id === 'tabGraph') { updateTimelineRange(); refreshTimelineLayout(); }
        if (button.id === 'tabEvents' && table) table.columns.adjust();
        if (button.id === 'tabPresence') refreshCalendarLayout();
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
  function timelineWindow () {
    var end = new Date();
    end.setSeconds(0, 0);
    var start = new Date(end.getTime() - 24 * 60 * 60 * 1000);
    // FullCalendar advances in local wall time, so account for daylight saving changes.
    return {start: start, durationMinutes: 1440 - (end.getTimezoneOffset() - start.getTimezoneOffset())};
  }
  function updateTimelineRange () {
    if (timeline && byId('panGraph').classList.contains('active') && !document.hidden) {
      var range = timelineWindow();
      if (range.durationMinutes !== timelineDurationMinutes) {
        timelineDurationMinutes = range.durationMinutes;
        timeline.setOption('duration', {minutes: timelineDurationMinutes});
      }
      timeline.gotoDate(range.start);
    }
  }
  function refreshTimelineLayout () {
    if (!timeline || !byId('panGraph').classList.contains('active')) return;
    var render = function () {
      if (!unloading && byId('panGraph').classList.contains('active')) timeline.updateSize();
    };
    window.requestAnimationFrame(function () { window.requestAnimationFrame(render); });
    window.clearTimeout(timelineLayoutTimer);
    timelineLayoutTimer = window.setTimeout(render, 250);
  }
  function initializeTimeline () {
    if (!window.FullCalendar) return;
    var config = JSON.parse(byId('icmp-calendar-config').textContent);
    var range = timelineWindow();
    timelineDurationMinutes = range.durationMinutes;
    timeline = new window.FullCalendar.Calendar(byId('icmp-timeline'), {
      initialView: 'timeline', duration: {minutes: timelineDurationMinutes}, dateAlignment: 'minute', initialDate: range.start, height: 'auto', timeZone: 'local',
      locale: config.locale, schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
      headerToolbar: false, slotDuration: '01:00:00', slotLabelInterval: '02:00:00', scrollTime: '24:00:00',
      slotLabelFormat: {hour: '2-digit', minute: '2-digit', hour12: false}, slotMinWidth: 42, eventMinWidth: 1,
      editable: false, selectable: false, eventStartEditable: false, eventDurationEditable: false,
      events: function (fetchInfo, success, failure) {
        var generation = ++timelineGeneration;
        if (timelineRequest && timelineRequest.readyState !== 4) timelineRequest.abort();
        var settled = false;
        function finish (callback, value) { if (!settled) { settled = true; callback(value); } }
        timelineRequest = $.ajax({
          url: root.dataset.endpoint, dataType: 'json', cache: false,
          data: {action: 'getICMPTimeline', hostip: root.dataset.hostIp, start: fetchInfo.startStr, end: fetchInfo.endStr}
        }).done(function (response) {
          if (generation !== timelineGeneration) { finish(success, []); return; }
          if (!Array.isArray(response)) {
            var error = new Error('Unexpected timeline response');
            if (window.console) window.console.error(error.message);
            notify(window.pialertV4Text('V4_Request_Failed'));
            finish(failure, error);
            return;
          }
          finish(success, response);
        }).fail(function (xhr, requestStatus) {
          if (requestStatus === 'abort' || generation !== timelineGeneration) { finish(success, []); return; }
          var error = new Error(xhr.status ? 'HTTP ' + xhr.status + ' ' + xhr.statusText : window.pialertV4Text('V4_Request_Failed'));
          if (window.console) window.console.error('ICMP timeline request:', error.message);
          notify(window.pialertV4Text('V4_Request_Failed'));
          finish(failure, error);
        });
      },
      eventDidMount: function (argument) {
        var tooltip = argument.event.extendedProps.tooltip;
        if (!tooltip) return;
        argument.el.setAttribute('aria-label', String(tooltip).replace(/\s*\n\s*/g, ', '));
        argument.el.setAttribute('title', String(tooltip).replace(/\r\n?/g, '\n'));
        if (window.bootstrap && window.bootstrap.Tooltip) {
          window.bootstrap.Tooltip.getOrCreateInstance(argument.el, {container: 'body', placement: 'bottom', customClass: 'pialert-calendar-tooltip'});
        }
      },
      eventWillUnmount: function (argument) {
        if (window.bootstrap && window.bootstrap.Tooltip) {
          var tooltip = window.bootstrap.Tooltip.getInstance(argument.el);
          if (tooltip) tooltip.dispose();
        }
      }
    });
    timeline.render();
    refreshTimelineLayout();
    timelineTimer = window.setInterval(updateTimelineRange, 60000);
  }
  function initializeCalendar () {
    if (!window.FullCalendar) return;
    var config = JSON.parse(byId('icmp-calendar-config').textContent);
    var narrow = window.matchMedia('(max-width: 767px)').matches;
    calendar = new window.FullCalendar.Calendar(byId('icmp-calendar'), {
      editable: false, selectable: false, eventStartEditable: false, eventDurationEditable: false,
      initialView: narrow ? 'timeGridDay' : 'timeGridMonth', height: 'auto', firstDay: 1,
      allDaySlot: false, timeZone: 'local', slotDuration: '02:00:00', slotLabelInterval: '04:00:00',
      slotLabelFormat: {hour: '2-digit', minute: '2-digit', hour12: false},
      eventTimeFormat: {hour: '2-digit', minute: '2-digit', hour12: false}, locale: config.locale,
      schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
      headerToolbar: {left: 'prev,next today', center: 'title', right: narrow ? 'timeGridDay' : 'timeGridMonth,timeGridWeek,timeGridDay'},
      views: {
        timeGridMonth: {type: 'timeGrid', duration: {months: 1}, buttonText: config.month, dayHeaderFormat: {day: 'numeric'}},
        timeGridWeek: {buttonText: config.week},
        timeGridDay: {buttonText: config.day, slotDuration: '01:00:00'}
      },
      events: function (fetchInfo, success, failure) {
        var generation = ++calendarGeneration;
        if (calendarRequest && calendarRequest.readyState !== 4) calendarRequest.abort();
        var settled = false;
        function finish (callback, value) { if (!settled) { settled = true; callback(value); } }
        calendarRequest = $.ajax({
          url: root.dataset.endpoint, dataType: 'json', cache: false,
          data: {action: 'getICMPPresence', hostip: root.dataset.hostIp, start: fetchInfo.startStr, end: fetchInfo.endStr}
        }).done(function (response) {
          if (generation !== calendarGeneration) { finish(success, []); return; }
          if (!Array.isArray(response)) {
            var error = new Error('Unexpected calendar response');
            if (window.console) window.console.error(error.message);
            notify(window.pialertV4Text('V4_Request_Failed'));
            finish(failure, error);
            return;
          }
          finish(success, response);
        }).fail(function (xhr, requestStatus) {
          if (requestStatus === 'abort' || generation !== calendarGeneration) { finish(success, []); return; }
          var error = new Error(xhr.status ? 'HTTP ' + xhr.status + ' ' + xhr.statusText : window.pialertV4Text('V4_Request_Failed'));
          if (window.console) window.console.error('ICMP calendar request:', error.message);
          notify(window.pialertV4Text('V4_Request_Failed'));
          finish(failure, error);
        });
      },
      eventDidMount: function (argument) {
        var tooltip = argument.event.extendedProps.tooltip;
        if (tooltip) argument.el.setAttribute('title', String(tooltip).replace(/\r\n?/g, '\n'));
      }
    });
    calendar.render();
    refreshCalendarLayout();
  }
  function refreshCalendarLayout () {
    if (!calendar || !byId('panPresence').classList.contains('active')) return;
    var render = function () {
      if (!unloading && byId('panPresence').classList.contains('active')) calendar.updateSize();
    };
    window.requestAnimationFrame(function () { window.requestAnimationFrame(render); });
    window.clearTimeout(calendarLayoutTimer);
    calendarLayoutTimer = window.setTimeout(render, 250);
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
  initializeTabs(); initializeTable(); initializeTimeline(); initializeCalendar(); initializeSuggestions(); getTotals(); nmap('view');
  window.addEventListener('beforeunload', function (event) { if ((state() !== initialState || (window.pialertEntityActionsEditor && window.pialertEntityActionsEditor.dirty())) && !unloading) { event.preventDefault(); event.returnValue = ''; } });
  window.addEventListener('pagehide', function () { unloading = true; ++nmapGeneration; ++calendarGeneration; ++timelineGeneration; window.clearTimeout(calendarLayoutTimer); window.clearTimeout(timelineLayoutTimer); window.clearInterval(timelineTimer); if (nmapRequest) nmapRequest.abort(); if (calendarRequest) calendarRequest.abort(); if (timelineRequest) timelineRequest.abort(); if (table) table.destroy(); if (timeline) timeline.destroy(); if (calendar) calendar.destroy(); });
})(window, document, window.jQuery);

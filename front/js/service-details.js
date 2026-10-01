(function (window, document, $) {
  'use strict';
  var root = document.getElementById('service-details-page');
  if (!root) return;
  var table = null;
  var timeline = null;
  var timelineRequest = null;
  var timelineGeneration = 0;
  var timelineLayoutTimer = null;
  var timelineTimer = null;
  var timelineDurationMinutes = null;
  var timelineStart = null;
  var timelinePeriod = '24h';
  var timelineRenderedPeriod = null;
  var unloading = false;

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
        if (button.id === 'tabGraph') { refreshTimelineData(); refreshTimelineLayout(); }
      });
    });
  }

  function initializeTable () {
    if (!$.fn || typeof $.fn.DataTable !== 'function') return;
    table = $('#tableEvents').DataTable({
      paging: true, lengthChange: true,
      lengthMenu: [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, 'All']],
      searching: true, ordering: true, info: true, autoWidth: true, pageLength: 10,
      order: [[0, 'desc']], columns: [{ data: 0 }, { data: 1 }, { data: 2 }, { data: 3 }],
      columnDefs: [{ targets: '_all', render: $.fn.dataTable.render.text() }, { className: 'text-center', targets: [0, 1, 2, 3] }],
      language: window.pialertV4DataTableLanguage({ emptyTable: window.pialertV4Text('V4_No_Data'), lengthMenu: root.dataset.lengthMenu, search: root.dataset.search + ': ', paginate: { next: root.dataset.next, previous: root.dataset.previous }, info: root.dataset.info })
    });
  }

  function refreshTimelineLayout () {
    if (!timeline || !field('panGraph').classList.contains('active')) return;
    var render = function () {
      if (!unloading && field('panGraph').classList.contains('active')) timeline.updateSize();
    };
    window.requestAnimationFrame(function () { window.requestAnimationFrame(render); });
    window.clearTimeout(timelineLayoutTimer);
    timelineLayoutTimer = window.setTimeout(render, 250);
  }

  function applyTimelineData (snapshot) {
    var config = JSON.parse(field('service-timeline-config').textContent);
    ['2xx', '3xx', '4xx', '5xx', 'down'].forEach(function (status) {
      var count = document.querySelector('[data-service-status-count="' + status + '"]');
      if (count) count.textContent = Number(snapshot.counts[status] || 0).toLocaleString();
    });
    if (!snapshot.start || !snapshot.end) return;
    var start = new Date(snapshot.start);
    var end = new Date(snapshot.end);
    var duration = Math.max(60, Math.round((end - start) / 60000 - (end.getTimezoneOffset() - start.getTimezoneOffset())));
    var formatter = new Intl.DateTimeFormat(config.locale, {year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'});
    field('service-timeline-range').textContent = formatter.format(start) + ' – ' + formatter.format(end);
    field('service-timeline-empty').hidden = snapshot.events.length !== 0;
    var events = snapshot.events.length ? snapshot.events : [{
      title: config.noData, start: snapshot.start, end: snapshot.end,
      backgroundColor: 'var(--bs-tertiary-bg)', borderColor: 'var(--bs-border-color)', textColor: 'var(--bs-secondary-color)',
      classNames: ['service-timeline-placeholder'], tooltip: config.noData
    }];
    if (timeline && timelineRenderedPeriod !== timelinePeriod) {
      timeline.destroy();
      timeline = null;
    }
    if (timeline) {
      if (duration !== timelineDurationMinutes) {
        timelineDurationMinutes = duration;
        timeline.setOption('duration', {minutes: duration});
      }
      if (snapshot.start !== timelineStart) {
        timelineStart = snapshot.start;
        timeline.gotoDate(start);
      }
      timeline.removeAllEventSources();
      timeline.addEventSource(events);
      refreshTimelineLayout();
      return;
    }
    timelineStart = snapshot.start;
    timelineDurationMinutes = duration;
    timelineRenderedPeriod = timelinePeriod;
    var week = timelinePeriod === '7d';
    timeline = new window.FullCalendar.Calendar(field('service-timeline'), {
      initialView: 'timeline', duration: {minutes: duration}, dateAlignment: 'minute', initialDate: start,
      height: 'auto', timeZone: 'local', locale: config.locale,
      schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
      navLinks: false,
      headerToolbar: false, slotDuration: week ? '06:00:00' : '01:00:00', slotLabelInterval: week ? '12:00:00' : '02:00:00', scrollTime: week ? '00:00:00' : '24:00:00',
      slotLabelFormat: week ? [{weekday: 'short', day: '2-digit', month: '2-digit'}, {hour: '2-digit', minute: '2-digit', hour12: false}] : {hour: '2-digit', minute: '2-digit', hour12: false}, slotMinWidth: 42, eventMinWidth: 1,
      editable: false, selectable: false, eventStartEditable: false, eventDurationEditable: false,
      events: events,
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
    window.serviceTimeline = timeline;
    refreshTimelineLayout();
  }

  function refreshTimelineData () {
    if (!window.FullCalendar || unloading || document.hidden) return;
    var generation = ++timelineGeneration;
    if (timelineRequest && timelineRequest.readyState !== 4) timelineRequest.abort();
    timelineRequest = $.ajax({
      url: root.dataset.endpoint, dataType: 'json', cache: false,
      data: {action: 'getServiceTimeline', url: root.dataset.serviceUrl, period: timelinePeriod}
    }).done(function (response) {
      if (generation !== timelineGeneration) return;
      if (!response || !Array.isArray(response.events) || !response.counts) {
        window.showMessage(window.pialertV4Text('V4_Request_Failed'));
        return;
      }
      applyTimelineData(response);
    }).fail(function (xhr, requestStatus) {
      if (requestStatus === 'abort' || generation !== timelineGeneration) return;
      if (window.console) window.console.error('Service timeline request:', xhr.status, xhr.statusText);
      window.showMessage(window.pialertV4Text('V4_Request_Failed'));
    });
  }

  function initializeTimeline () {
    refreshTimelineData();
    timelineTimer = window.setInterval(function () {
      if (field('panGraph').classList.contains('active')) refreshTimelineData();
    }, 60000);
  }

  function selectTimelinePeriod (period) {
    if (period === timelinePeriod) return;
    timelinePeriod = period;
    document.querySelectorAll('[data-service-timeline-period]').forEach(function (button) {
      var selected = button.dataset.serviceTimelinePeriod === period;
      button.classList.toggle('active', selected);
      button.classList.toggle('btn-primary', selected);
      button.classList.toggle('btn-outline-primary', !selected);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
    refreshTimelineData();
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
  document.querySelectorAll('[data-service-timeline-period]').forEach(function (button) {
    button.addEventListener('click', function () { selectTimelinePeriod(button.dataset.serviceTimelinePeriod); });
  });
  window.setServiceData = setServiceData;
  window.askDeleteService = askDeleteService;
  window.deleteService = deleteService;
  initializeTabs(); initializeTable(); initializeTimeline(); getTotals();
  window.addEventListener('pagehide', function () {
    unloading = true;
    if (timelineTimer) window.clearInterval(timelineTimer);
    if (timelineLayoutTimer) window.clearTimeout(timelineLayoutTimer);
    if (timelineRequest && timelineRequest.readyState !== 4) timelineRequest.abort();
    if (table) table.destroy();
    if (timeline) timeline.destroy();
  });
})(window, document, window.jQuery);

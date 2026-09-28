(function (window, document, $) {
  'use strict';

  var PARAM_PERIOD = 'Front_Events_Period';
  var PARAM_ROWS = 'Front_Events_Rows';
  var VALID_PERIODS = ['1 day', '7 days', '1 month', '1 year', '100 years'];
  var VALID_TYPES = ['all', 'sessions', 'missing', 'voided', 'new', 'down'];
  var state = { type: 'all', period: '1 month', rows: 50, table: null, refreshTimer: null };
  var root = document.getElementById('devices-events-page');

  function parseJson (data) {
    if (typeof data !== 'string') return data;
    try { return JSON.parse(data); } catch (_error) { return null; }
  }

  function readParameter (name) {
    return $.get('php/server/parameters.php?action=get&parameter=' + encodeURIComponent(name));
  }

  function saveParameter (name, value) {
    if (typeof window.pialertPost !== 'function') return null;
    var request = window.pialertPost('php/server/parameters.php', {
      action: 'set', parameter: name, value: value
    });
    request.done(function (data) {
      if (data !== 'OK' && window.console) window.console.warn('Pi.Alert could not save ' + name);
    });
    return request;
  }

  function replaceCellWithText (td, value) {
    td.textContent = String(value == null ? '' : value);
  }

  function replaceCellWithLink (td, href, value, warningSuffix) {
    var link = document.createElement('a');
    var text = String(value == null ? '' : value);
    link.href = href;
    if (warningSuffix && text.endsWith(warningSuffix)) {
      link.appendChild(document.createTextNode(text.slice(0, -warningSuffix.length)));
      var warning = document.createElement('strong');
      warning.className = 'text-warning';
      warning.textContent = warningSuffix;
      link.appendChild(warning);
    } else {
      link.textContent = text;
    }
    td.replaceChildren(link);
  }

  function initializeTable () {
    state.table = $('#tableEvents').DataTable({
      paging: true,
      lengthChange: true,
      lengthMenu: [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, 'All']],
      searching: true,
      ordering: true,
      info: true,
      autoWidth: true,
      order: [[0, 'desc'], [3, 'desc'], [5, 'desc']],
      pageLength: state.rows,
      columnDefs: [
        { targets: '_all', render: $.fn.dataTable.render.text() },
        { visible: false, targets: [0, 5, 6, 7, 8, 10] },
        { orderData: [8], targets: 7 },
        { orderData: [10], targets: 9 },
        { targets: 1, createdCell: function (td, cellData, rowData) {
          if (rowData[13]) {
            replaceCellWithLink(td, 'deviceDetails.php?mac=' + encodeURIComponent(String(rowData[13])), cellData, '');
          } else {
            replaceCellWithLink(td, 'icmpmonitorDetails.php?hostip=' + encodeURIComponent(String(rowData[9] == null ? '' : rowData[9])), cellData, '**');
          }
        } },
        { targets: [3, 4, 5, 6, 7], createdCell: replaceCellWithText }
      ],
      processing: true,
      language: window.pialertV4DataTableLanguage({
        processing: window.pialertV4Text('V4_Loading'),
        emptyTable: window.pialertV4Text('V4_No_Data'),
        lengthMenu: root.dataset.lengthMenu,
        search: root.dataset.search + ': ',
        paginate: { next: root.dataset.next, previous: root.dataset.previous },
        info: root.dataset.info
      })
    });
    $('#tableEvents').on('length.dt.pialertEvents', function (_event, _settings, length) {
      state.rows = length;
      saveParameter(PARAM_ROWS, length);
    });
  }

  function updateSelectedFilter (type) {
    document.querySelectorAll('.pialert-event-filter').forEach(function (button) {
      var selected = button.dataset.eventType === type;
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
  }

  function showEvents (type) {
    if (VALID_TYPES.indexOf(type) === -1 || !state.table) type = 'all';
    state.type = type;
    var sessionColumns = type === 'sessions' || type === 'missing';
    var tone = type === 'sessions' ? 'success' : (type === 'down' ? 'danger' : (type === 'all' ? 'primary' : 'warning'));
    var box = document.getElementById('tableEventsBox');
    var title = document.getElementById('tableEventsTitle');
    box.className = 'card card-' + tone + ' card-outline';
    title.textContent = root.dataset['title' + type.charAt(0).toUpperCase() + type.slice(1)] || root.dataset.titleEvents;
    updateSelectedFilter(type);

    state.table.column(3).visible(!sessionColumns, false);
    state.table.column(4).visible(!sessionColumns, false);
    state.table.column(5).visible(sessionColumns, false);
    state.table.column(6).visible(sessionColumns, false);
    state.table.column(7).visible(sessionColumns, false);
    state.table.clear().draw(false);
    state.table.order([[0, 'desc'], [3, 'desc'], [5, 'desc']]);
    state.table.ajax.url('php/server/events.php?action=getEvents&type=' + encodeURIComponent(type) + '&period=' + encodeURIComponent(state.period)).load();
  }

  function getEventsTotals () {
    window.clearTimeout(state.refreshTimer);
    $.get('php/server/events.php?action=getEventsTotals&period=' + encodeURIComponent(state.period), function (data) {
      var totals = parseJson(data);
      if (!Array.isArray(totals) || totals.length < 6) return;
      ['eventsAll', 'eventsSessions', 'eventsMissing', 'eventsVoided', 'eventsNewDevices', 'eventsDown'].forEach(function (id, index) {
        var target = document.getElementById(id);
        var value = Number(totals[index]);
        if (target) target.textContent = Number.isFinite(value) ? value.toLocaleString() : '0';
      });
      state.refreshTimer = window.setTimeout(getEventsTotals, 60000);
    });
  }

  function periodChanged () {
    var select = document.getElementById('period');
    state.period = VALID_PERIODS.indexOf(select.value) !== -1 ? select.value : '1 month';
    saveParameter(PARAM_PERIOD, state.period);
    getEventsTotals();
    showEvents(state.type);
  }

  function bindControls () {
    document.getElementById('period').addEventListener('change', periodChanged);
    document.querySelectorAll('.pialert-event-filter').forEach(function (button) {
      button.addEventListener('click', function () { showEvents(button.dataset.eventType); });
    });
  }

  function init () {
    if (!root || !$.fn || typeof $.fn.DataTable !== 'function') return;
    $.when(readParameter(PARAM_PERIOD), readParameter(PARAM_ROWS)).always(function (periodResponse, rowsResponse) {
      var periodData = parseJson(Array.isArray(periodResponse) ? periodResponse[0] : periodResponse);
      var rowsData = parseJson(Array.isArray(rowsResponse) ? rowsResponse[0] : rowsResponse);
      if (VALID_PERIODS.indexOf(periodData) !== -1) state.period = periodData;
      if (Number.isInteger(rowsData)) state.rows = rowsData;
      document.getElementById('period').value = state.period;
      initializeTable();
      bindControls();
      getEventsTotals();
      showEvents(state.type);
    });
  }

  window.getEvents = showEvents;
  window.getEventsTotals = getEventsTotals;
  window.periodChanged = periodChanged;
  $(init);
  window.addEventListener('pagehide', function () { window.clearTimeout(state.refreshTimer); });
})(window, document, window.jQuery);

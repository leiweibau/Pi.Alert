/* -----------------------------------------------------------------------------
 * Pi.Alert - AdminLTE 4 shell runtime
 *
 * Read-only status polling and shell state. Safe to initialize repeatedly.
 * -------------------------------------------------------------------------- */
(function (window, document) {
  'use strict';

  // The reboot and shutdown waiting pages are standalone PHP pages. Keep the current
  // server-selected theme available to them while the application is offline.
  function storageRead (key) {
    try { return window.localStorage.getItem(key); } catch (_) { return null; }
  }

  function storageWrite (key, value) {
    try { window.localStorage.setItem(key, value); } catch (_) { /* Current tab remains usable. */ }
  }

  function storageRemove (key) {
    try { window.localStorage.removeItem(key); } catch (_) { /* Current tab remains usable. */ }
  }

  storageWrite('pialert-ui-theme', document.documentElement.getAttribute('data-pialert-theme') || 'standard');
  storageWrite('pialert-ui-mode', document.documentElement.getAttribute('data-bs-theme') || 'light');

  var TOTALS_INTERVAL_MS = 30000;
  var REPORT_INTERVAL_MS = 15000;
  var AUTO_RELOAD_MS = 120000;
  var REPORT_TITLE_PATTERN = /\([\d.,\s\u00a0]+\)$/;
  var state = {
    initialized: false,
    totalsInterval: null,
    reportInterval: null,
    clockTimeout: null,
    reloadTimeout: null,
    serverStartTime: 0,
    clientStartTime: 0,
    autoReloadCheckbox: null,
    requests: Object.create(null)
  };

  function getJQuery () {
    if (!window.jQuery || typeof window.jQuery.get !== 'function') {
      throw new Error('Pi.Alert v4 shell runtime requires jQuery');
    }
    return window.jQuery;
  }

  function element (id) {
    return document.getElementById(id);
  }

  function setText (id, value) {
    var target = element(id);
    if (target) {
      target.textContent = String(value == null ? '' : value);
    }
  }

  function displayCount (value) {
    var count = Number(value);
    return Number.isFinite(count) && count > 0 ? count.toLocaleString() : '';
  }

  function parseArrayResponse (data, label) {
    try {
      var parsed = typeof data === 'string' ? JSON.parse(data) : data;
      if (!Array.isArray(parsed)) {
        throw new TypeError('response is not an array');
      }
      return parsed;
    } catch (error) {
      if (window.console && typeof window.console.warn === 'function') {
        window.console.warn('Pi.Alert v4 ignored malformed ' + label + ' response', error);
      }
      return null;
    }
  }

  function read (key, url, success) {
    var $ = getJQuery();
    if (state.requests[key]) {
      return state.requests[key];
    }
    var request = $.get(url, success);
    state.requests[key] = request;
    if (request && typeof request.always === 'function') {
      request.always(function () { delete state.requests[key]; });
    } else {
      delete state.requests[key];
    }
    return request;
  }

  function getDevicesTotalsBadge (scanSource) {
    var source = String(scanSource || 'local');
    return read('devices:' + source, 'php/server/devices.php?action=getDevicesTotals&scansource=' + encodeURIComponent(source), function (data) {
      var totals = parseArrayResponse(data, 'device totals');
      if (!totals) return;
      setText('header_' + source + '_count_on', displayCount(totals[1]));
      setText('header_' + source + '_count_new', displayCount(totals[3]));
      setText('header_' + source + '_count_down', displayCount(totals[4]));
      var total = Number(totals[0]);
      var present = Number(totals[6]);
      setText('header_' + source + '_presence', Number.isFinite(total) && total > 0
        ? total.toLocaleString() + '/' + (total - (Number.isFinite(present) ? present : 0)).toLocaleString()
        : '');
    });
  }

  function getICMPTotalsBadge () {
    return read('icmp', 'php/server/icmpmonitor.php?action=getICMPHostTotals', function (data) {
      var totals = parseArrayResponse(data, 'ICMP totals');
      if (!totals) return;
      setText('header_icmp_count_on', displayCount(totals[2]));
      setText('header_icmp_count_down', displayCount(totals[1]));
    });
  }

  function getServicesTotalsBadge () {
    return read('services', 'php/server/services.php?action=getServiceMonTotals', function (data) {
      var totals = parseArrayResponse(data, 'service totals');
      if (!totals) return;
      setText('header_services_count_on', displayCount(totals[2]));
      setText('header_services_count_down', displayCount(totals[1]));
      setText('header_services_count_warning', displayCount(totals[3]));
    });
  }

  function getUpdateStatus () {
    return read('updates', 'php/server/files.php?action=GetUpdateStatus', function (data) {
      var totals = parseArrayResponse(data, 'update status');
      if (!totals) return;
      var count = Number(totals[0]);
      setText('header_updatecheck_notification', Number.isFinite(count) ? count.toLocaleString() : '');
    });
  }

  function setDefaultPageTitle () {
    if (!REPORT_TITLE_PATTERN.test(document.title)) {
      document.title += ' (0)';
    }
  }

  function getReportTotalsBadge () {
    return read('reports', 'php/server/files.php?action=getReportTotals', function (data) {
      var totals = parseArrayResponse(data, 'report totals');
      if (!totals) return;
      var count = Number(totals[0]);
      if (!Number.isFinite(count)) count = 0;
      setText('Menu_Report_Counter_Badge', displayCount(count));
      var icon = element('Menu_Report_Envelope_Icon');
      if (icon) {
        icon.classList.toggle('text-danger', count > 0);
        icon.classList.toggle('text-red', count > 0);
      }
      var localized = count.toLocaleString();
      document.title = REPORT_TITLE_PATTERN.test(document.title)
        ? document.title.replace(REPORT_TITLE_PATTERN, '(' + localized + ')')
        : document.title + ' (' + localized + ')';
    });
  }

  function scanSources () {
    var sources = ['local'];
    Array.prototype.forEach.call(document.querySelectorAll('[id^="header_"][id$="_count_on"]'), function (node) {
      var match = /^header_(.+)_count_on$/.exec(node.id);
      if (match && ['icmp', 'services'].indexOf(match[1]) === -1 && sources.indexOf(match[1]) === -1) {
        sources.push(match[1]);
      }
    });
    return sources;
  }

  function updateTotals () {
    scanSources().forEach(getDevicesTotalsBadge);
    getICMPTotalsBadge();
    getServicesTotalsBadge();
    getUpdateStatus();
  }

  function formatTwoDigits (value) {
    return String(value).padStart(2, '0');
  }

  function showPiAlertServerTime () {
    if (!state.serverStartTime) return;
    var serverTime = new Date(state.serverStartTime + (Date.now() - state.clientStartTime));
    setText('PIA_Servertime_place', '- ' + formatTwoDigits(serverTime.getHours()) + ':' + formatTwoDigits(serverTime.getMinutes()));

    var countdownMinutes = 4 - (serverTime.getMinutes() % 5);
    var countdownSeconds = 60 - serverTime.getSeconds();
    if (countdownSeconds === 60) {
      countdownSeconds = 0;
      countdownMinutes += 1;
    }
    setText('nextscancountdown', window.pialertV4Text('V4_Next_Scan_In') + ': ' + formatTwoDigits(countdownMinutes) + ':' + formatTwoDigits(countdownSeconds));
    window.clearTimeout(state.clockTimeout);
    state.clockTimeout = window.setTimeout(showPiAlertServerTime, 1000);
  }

  function getPiAlertServerTime () {
    return read('server-time', 'php/server/files.php?action=GetServerTime', function (data) {
      var values = String(data).split(',').map(Number);
      if (values.length < 6 || values.some(function (value) { return !Number.isFinite(value); })) {
        if (window.console && typeof window.console.warn === 'function') {
          window.console.warn('Pi.Alert v4 ignored malformed server-time response');
        }
        return;
      }
      state.serverStartTime = new Date(values[0], values[1] - 1, values[2], values[3], values[4], values[5]).getTime();
      state.clientStartTime = Date.now();
      showPiAlertServerTime();
    });
  }

  function reloadPage () {
    window.clearTimeout(state.reloadTimeout);
    state.reloadTimeout = window.setTimeout(function () { window.location.reload(); }, AUTO_RELOAD_MS);
  }

  function handleCheckboxChange () {
    var checkbox = state.autoReloadCheckbox || element('autoReloadCheckbox');
    if (!checkbox) return;
    if (checkbox.checked) {
      reloadPage();
      storageWrite('autoReloadChecked', 'true');
    } else {
      window.clearTimeout(state.reloadTimeout);
      state.reloadTimeout = null;
      storageRemove('autoReloadChecked');
    }
  }

  function initAutoReload () {
    var checkbox = element('autoReloadCheckbox');
    if (!checkbox) return;
    state.autoReloadCheckbox = checkbox;
    checkbox.addEventListener('change', handleCheckboxChange);
    if (storageRead('autoReloadChecked') === 'true') {
      checkbox.checked = true;
      reloadPage();
    }
  }

  function initTemperature () {
    var raw = element('rawtemp');
    var output = element('tempdisplay');
    if (!raw || !output) return;
    var unit = storageRead('tempunit') || 'C';
    var selector = element('tempunit-selector');

    function render (nextUnit) {
      var temperature = Number.parseFloat(raw.textContent);
      if (!Number.isFinite(temperature)) return;
      unit = nextUnit || 'C';
      storageWrite('tempunit', unit);
      if (unit === 'K') output.textContent = (temperature + 273.15).toFixed(1) + '\u00a0K';
      else if (unit === 'F') output.textContent = ((temperature * 9) / 5 + 32).toFixed(1) + '\u00a0\u00b0F';
      else output.textContent = temperature.toFixed(1) + '\u00a0\u00b0C';
    }

    render(unit);
    if (selector) {
      selector.value = unit;
      selector.addEventListener('change', function () { render(selector.value); }, { signal: state.temperatureController.signal });
    }
  }

  function initTheme () {
    var root = document.documentElement;
    var declaredMode = root.getAttribute('data-pialert-color-mode') || root.getAttribute('data-bs-theme');
    if (declaredMode === 'dark' || declaredMode === 'light') {
      root.setAttribute('data-bs-theme', declaredMode);
    }
    var skin = document.body ? document.body.getAttribute('data-pialert-skin') : '';
    if (skin) {
      root.setAttribute('data-pialert-skin', skin);
    }
  }

  function toggleSystemInfoBox () {
    var info = element('sidebar_systeminfobox');
    if (info) info.classList.toggle('collapse');
    Array.prototype.forEach.call(document.querySelectorAll('.custom_filter'), function (filter) {
      filter.classList.toggle('d-none');
    });
  }

  function abortRequests () {
    Object.keys(state.requests).forEach(function (key) {
      var request = state.requests[key];
      if (request && typeof request.abort === 'function') request.abort();
      delete state.requests[key];
    });
  }

  function destroy () {
    window.clearInterval(state.totalsInterval);
    window.clearInterval(state.reportInterval);
    window.clearTimeout(state.clockTimeout);
    window.clearTimeout(state.reloadTimeout);
    state.totalsInterval = null;
    state.reportInterval = null;
    state.clockTimeout = null;
    state.reloadTimeout = null;
    state.serverStartTime = 0;
    if (state.autoReloadCheckbox) {
      state.autoReloadCheckbox.removeEventListener('change', handleCheckboxChange);
      state.autoReloadCheckbox = null;
    }
    if (state.temperatureController) state.temperatureController.abort();
    abortRequests();
    state.initialized = false;
  }

  function init () {
    if (state.initialized) destroy();
    state.temperatureController = new AbortController();
    try {
      initTheme();
      setDefaultPageTitle();
      initTemperature();
      initAutoReload();
      getReportTotalsBadge();
      getPiAlertServerTime();
      updateTotals();
      state.totalsInterval = window.setInterval(updateTotals, TOTALS_INTERVAL_MS);
      state.reportInterval = window.setInterval(getReportTotalsBadge, REPORT_INTERVAL_MS);
      state.initialized = true;
    } catch (error) {
      destroy();
      throw error;
    }
    return api;
  }

  var api = { init: init, destroy: destroy, updateTotals: updateTotals, state: state };
  window.PiAlertV4Shell = api;
  window.getDevicesTotalsBadge = getDevicesTotalsBadge;
  window.getICMPTotalsBadge = getICMPTotalsBadge;
  window.getServicesTotalsBadge = getServicesTotalsBadge;
  window.GetUpdateStatus = getUpdateStatus;
  window.getReportTotalsBadge = getReportTotalsBadge;
  window.GetPiAlertServerTime = getPiAlertServerTime;
  window.ShowPiAlertServerTime = showPiAlertServerTime;
  window.updateTotals = updateTotals;
  window.reloadPage = reloadPage;
  window.handleCheckboxChange = handleCheckboxChange;
  window.initCPUtemp = initTemperature;
  window.setDefaultPageTitle = setDefaultPageTitle;
  window.toggle_systeminfobox = toggleSystemInfoBox;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})(window, document);

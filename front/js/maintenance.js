(function (window, document, $) {
  'use strict';

  var root = document.getElementById('v4-maintenance-page');
  if (!root || !$) return;

  function element(id) { return document.getElementById(id); }
  function cleanMessage(value) {
    var text = String(value == null ? '' : value).replace(/<meta\b[^>]*>/gi, '').trim();
    return text || root.dataset.done;
  }
  function post(url, data, refresh) {
    var request = window.pialertPost(url, data || {}, function (response) {
      window.showMessage(cleanMessage(response));
      if (refresh !== false && !/\berror\b/i.test(String(response))) {
        window.setTimeout(function () { window.location.reload(); }, 1300);
      }
    });
    request.fail(function (xhr) {
      window.showMessage(cleanMessage(xhr.responseText || root.dataset.requestError));
    });
    return request;
  }
  function confirmAction(title, message, action) {
    window.showModalWarning(title, message, root.dataset.cancel, root.dataset.confirm, action);
  }
  function toUrl(url, values) {
    var target = new URL(url, window.location.href);
    Object.keys(values).forEach(function (key) { target.searchParams.set(key, String(values[key])); });
    return target.pathname + target.search;
  }

  root.addEventListener('click', function (event) {
    var action = event.target.closest('[data-mt-action]');
    if (action && root.contains(action)) {
      confirmAction(action.dataset.mtTitle || action.textContent.trim(), action.dataset.mtConfirm || '', function () {
        var url = action.dataset.mtUrl;
        if (action.hasAttribute('data-toggle-state')) url = toUrl(url, { toggleState: action.dataset.toggleState });
        post(url);
      });
      return;
    }
    var source = event.target.closest('button[data-import]');
    if (source && root.contains(source)) {
      confirmAction(root.dataset.importLabel, source.textContent.trim(), function () {
        post(toUrl('php/server/files.php?action=ToggleImport', { deviceType: source.dataset.import, toggleState: source.dataset.toggleState }));
      });
      return;
    }
    var ignore = event.target.closest('[data-ignore-kind]');
    if (ignore && root.contains(ignore)) {
      confirmAction(root.dataset.ignoreList + ' ' + ignore.dataset.ignoreKind, ignore.dataset.ignoreValue, function () {
        var isMac = ignore.dataset.ignoreKind === 'MAC';
        post(toUrl('php/server/files.php?action=' + (isMac ? 'DeleteBlockDeviceMAC' : 'DeleteBlockDeviceIP'), isMac ? { mac: ignore.dataset.ignoreValue } : { ip: ignore.dataset.ignoreValue }));
      });
      return;
    }
    var filter = event.target.closest('.save-filter');
    if (filter && root.contains(filter)) {
      var row = filter.closest('.mt-filter');
      var payload = { action: 'SaveFilterID', filterid: row.dataset.filterId };
      row.querySelectorAll('[data-field]').forEach(function (field) { payload[field.dataset.field] = field.value; });
      post('php/server/devices.php', payload);
      return;
    }
    var satellite = event.target.closest('.mt-sat-action');
    if (satellite && root.contains(satellite)) {
      var article = satellite.closest('.mt-satellite');
      var operation = satellite.dataset.action === 'delete' ? 'DeleteSatellite' : 'SaveSatellite';
      var data = {
        changed_satellite_name: article.querySelector('.mt-sat-name').value,
        satellite_name: article.dataset.satName,
        sat_id: article.dataset.satId
      };
      var execute = function () { post(toUrl('php/server/devices.php?action=' + operation, data)); };
      if (operation === 'DeleteSatellite') confirmAction(root.dataset.deleteSatellite, article.dataset.satName, execute);
      else execute();
    }
  });

  document.querySelectorAll('[data-bs-toggle="tab"][data-tab]').forEach(function (button) {
    button.addEventListener('shown.bs.tab', function () {
      var url = new URL(window.location.href);
      url.searchParams.set('tab', button.dataset.tab);
      window.history.replaceState(null, '', url.pathname + url.search + url.hash);
    });
  });

  function getStatus() {
    $.getJSON('php/server/files.php?action=GetARPStatus').done(function (values) {
      if (Array.isArray(values)) {
        var count = Number(values[0]);
        element('arpproccounter').textContent = Number.isFinite(count) ? count.toLocaleString() : '0';
      }
    });
    $.getJSON('php/server/files.php?action=GetAutoBackupStatus').done(function (values) {
      if (!Array.isArray(values)) return;
      ['autobackupstatus', 'autobackupdbcount', 'autobackupconfcount', 'autobackupdbsize'].forEach(function (id, index) {
        var target = element(id);
        if (target) target.textContent = String(values[index] == null ? '' : values[index]);
      });
    });
  }
  getStatus();
  var statusInterval = window.setInterval(getStatus, 15000);
  window.addEventListener('pagehide', function () { window.clearInterval(statusInterval); }, { once: true });

  var logNames = ['scan', 'iplog', 'vendor', 'cleanup', 'webservices', 'speedtest', 'nmap'];
  var logModal = element('modal-mt-log');
  logModal.addEventListener('show.bs.modal', function (event) {
    var source = event.relatedTarget;
    var id = source ? source.dataset.log : 'scan';
    element('mt-log-title').textContent = source ? (source.getAttribute('aria-label') || source.textContent.trim()) : root.dataset.logViewer;
    element('mt-log-content').textContent = root.dataset.loading;
    if (id === 'inactivehosts') {
      $.getJSON('php/server/devices.php?action=ListInactiveHosts').done(function (result) {
        element('mt-log-content').textContent = Array.isArray(result) ? String(result[0] || '') : '';
      }).fail(function () { element('mt-log-content').textContent = root.dataset.loadingError; });
    } else {
      $.getJSON('php/server/files.php?action=GetLogfiles').done(function (result) {
        element('mt-log-content').textContent = Array.isArray(result) ? String(result[logNames.indexOf(id)] || '') : '';
      }).fail(function () { element('mt-log-content').textContent = root.dataset.loadingError; });
    }
  });

  element('save-arp-timer').addEventListener('click', function () {
    var minutes = element('txtPiaArpTimer').value;
    if (!minutes) { element('txtPiaArpTimer').focus(); return; }
    post('php/server/files.php', { action: 'setArpTimer', ArpTimer: minutes });
  });
  element('save-favicon').addEventListener('click', function () {
    post('php/server/files.php', { action: 'setFavIconURL', FavIconURL: element('txtFavIconURL').value });
  });
  var faviconInput = element('txtFavIconURL');
  var faviconPreview = element('mt-favicon-preview');
  var faviconPreviewTimer;
  function previewFavicon() {
    var value = faviconInput.value;
    var valid = /^img\/favicons\/(flat|glass)_[a-z]+_(black|white)\.png$/.test(value);
    if (!valid) {
      try {
        var url = new URL(value);
        valid = (url.protocol === 'http:' || url.protocol === 'https:') && !url.username && !url.password;
      } catch (_) { valid = false; }
    }
    faviconPreview.hidden = !valid;
    if (valid) faviconPreview.src = value;
    else faviconPreview.removeAttribute('src');
  }
  faviconInput.addEventListener('input', function () {
    window.clearTimeout(faviconPreviewTimer);
    faviconPreviewTimer = window.setTimeout(previewFavicon, 400);
  });
  faviconInput.addEventListener('change', previewFavicon);
  root.querySelectorAll('[data-favicon-value]').forEach(function (choice) {
    choice.addEventListener('click', function () {
      faviconInput.value = choice.dataset.faviconValue;
      previewFavicon();
    });
  });

  var column = element('txtMTTableColumn');
  var oldValue = element('txtMTColumnContent');
  var newValue = element('txtMTNewColumnContent');
  column.addEventListener('change', function () {
    oldValue.replaceChildren(new Option('', ''));
    var selected = column.selectedOptions[0];
    if (!selected || !selected.dataset.query) return;
    $.getJSON('php/server/devices.php?action=' + encodeURIComponent(selected.dataset.query)).done(function (values) {
      if (!Array.isArray(values)) return;
      values.forEach(function (item) {
        oldValue.add(new Option(String(item.name == null ? '' : item.name), String(item.id == null || item.id === '' ? item.name || '' : item.id)));
      });
    });
  });
  element('reset-column').addEventListener('click', function () {
    column.value = '';
    oldValue.replaceChildren(new Option('', ''));
    newValue.value = '';
  });
  ['update', 'delete'].forEach(function (kind) {
    element(kind + '-column').addEventListener('click', function () {
      if (!column.value) { column.focus(); return; }
      var action = kind === 'update' ? 'MTUpdateColumnContent' : 'MTDeletColumnContent';
      confirmAction(window.pialertV4Text(kind === 'update' ? 'V4_Update_Column_Value' : 'V4_Delete_Column_Value'), oldValue.selectedOptions[0] ? oldValue.selectedOptions[0].text : column.value, function () {
        post(toUrl('php/server/devices.php?action=' + action, { column: column.value, ccontent: oldValue.value, nccontent: newValue.value }));
      });
    });
  });

  var createSatellite = element('create-satellite');
  if (createSatellite) createSatellite.addEventListener('click', function () {
    var name = element('txtNewSatelliteName').value.trim();
    if (!name) { element('txtNewSatelliteName').focus(); return; }
    confirmAction(window.pialertV4Text('V4_Create_Satellite'), name, function () {
      post(toUrl('php/server/devices.php?action=CreateNewSatellite', { new_satellite_name: name }));
    });
  });

  var editor = element('ConfigFileEditor');
  var editorModal = element('modal-mt-config');
  editorModal.addEventListener('show.bs.modal', function () {
    editor.value = '';
    editor.disabled = true;
    $.get('php/server/files.php?action=GetConfigFile').done(function (text) {
      editor.value = String(text == null ? '' : text);
      editor.disabled = false;
    }).fail(function () {
      editor.disabled = true;
      window.showMessage(root.dataset.configError);
    });
  });
  var searchOffset = 0;
  element('config-search').addEventListener('input', function () { searchOffset = 0; });
  element('config-search-next').addEventListener('click', function () {
    var query = element('config-search').value.toLowerCase();
    if (!query) return;
    var text = editor.value.toLowerCase();
    var found = text.indexOf(query, searchOffset);
    if (found < 0) found = text.indexOf(query);
    if (found < 0) return;
    editor.focus();
    editor.setSelectionRange(found, found + query.length);
    searchOffset = found + query.length;
  });
  element('backup-config').addEventListener('click', function () { post('php/server/files.php?action=BackupConfigFile&reload=no', {}, false); });
  element('restore-config').addEventListener('click', function () {
    confirmAction(window.pialertV4Text('V4_Restore_Config'), window.pialertV4Text('V4_Restore_Config_Question'), function () {
      post('php/server/files.php?action=RestoreConfigFile');
    });
  });
  element('save-config').addEventListener('click', function () {
    if (editor.disabled) return;
    post('php/server/files.php', { action: 'SaveConfigFile', configfile: editor.value });
  });
})(window, document, window.jQuery);

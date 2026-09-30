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
      var archive = action.dataset.mtAction === 'restore-db' ? element('mt-restore-archive') : null;
      var selectedArchive = archive ? archive.value : '';
      if (archive && !/^pialertdb_[0-9]{8}_[0-9]{6}\.zip$/.test(selectedArchive)) return;
      var confirmation = action.dataset.mtConfirm || '';
      if (selectedArchive) confirmation += '<br><strong>' + selectedArchive + '</strong>';
      confirmAction(action.dataset.mtTitle || action.textContent.trim(), confirmation, function () {
        var url = action.dataset.mtUrl;
        if (action.hasAttribute('data-toggle-state')) url = toUrl(url, { toggleState: action.dataset.toggleState });
        post(url, selectedArchive ? { archive: selectedArchive } : {});
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
    var scanStatus = element('arpproccounter');
    if (scanStatus) $.getJSON('php/server/files.php?action=GetARPStatus').done(function (values) {
      if (!Array.isArray(values)) return;
      var status = values[0];
      var count = status === '' ? NaN : Number(status);
      var active = status === '' || (Number.isFinite(count) && count > 0);
      scanStatus.textContent = active ? '' : scanStatus.dataset.noScans;
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
  var searchInput = element('config-search');
  var searchOffset = 0;
  editorModal.addEventListener('show.bs.modal', function () {
    searchOffset = 0;
    editor.value = '';
    editor.disabled = true;
    $.get('php/server/files.php?action=GetConfigFile').done(function (text) {
      editor.value = String(text == null ? '' : text);
      editor.disabled = false;
      findConfigMatch(false);
    }).fail(function () {
      editor.disabled = true;
      window.showMessage(root.dataset.configError);
    });
  });

  function scrollToConfigMatch (offset) {
    var before = editor.value.slice(0, offset);
    var lineStart = before.lastIndexOf('\n') + 1;
    var line = before.split('\n').length - 1;
    var style = window.getComputedStyle(editor);
    var fontSize = parseFloat(style.fontSize) || 14;
    var lineHeight = parseFloat(style.lineHeight) || fontSize * 1.5;
    var top = (parseFloat(style.paddingTop) || 0) + line * lineHeight;
    editor.scrollTop = Math.max(0, top - (editor.clientHeight - lineHeight) / 2);
    var prefix = before.slice(lineStart).replace(/\t/g, '    ');
    var measure = document.createElement('canvas').getContext('2d');
    if (measure) {
      measure.font = style.font || fontSize + 'px ' + style.fontFamily;
      editor.scrollLeft = Math.max(0, measure.measureText(prefix).width - editor.clientWidth / 3);
    }
  }

  function showConfigMatch (offset, length, focusEditor) {
    if (focusEditor) editor.focus({ preventScroll: true });
    editor.setSelectionRange(offset, offset + length);
    scrollToConfigMatch(offset);
    window.requestAnimationFrame(function () { scrollToConfigMatch(offset); });
  }

  function configSearchPattern (query) {
    return new RegExp(query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
  }

  function findConfigMatch (advance, focusEditor) {
    var query = searchInput.value.trim();
    if (editor.disabled) return;
    if (!query) {
      editor.setSelectionRange(editor.selectionEnd, editor.selectionEnd);
      return;
    }
    var pattern = configSearchPattern(query);
    pattern.lastIndex = advance ? searchOffset : 0;
    var match = pattern.exec(editor.value);
    if (!match && advance) {
      pattern.lastIndex = 0;
      match = pattern.exec(editor.value);
    }
    if (!match) {
      editor.setSelectionRange(editor.selectionEnd, editor.selectionEnd);
      return;
    }
    showConfigMatch(match.index, match[0].length, focusEditor);
    if (advance) searchOffset = match.index + match[0].length;
  }

  searchInput.addEventListener('input', function () {
    searchOffset = 0;
    findConfigMatch(false);
  });
  searchInput.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    findConfigMatch(true, true);
  });
  element('config-search-next').addEventListener('click', function () {
    findConfigMatch(true, true);
  });
  editor.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter') return;
    var query = searchInput.value.trim();
    var selected = editor.value.slice(editor.selectionStart, editor.selectionEnd);
    if (!query || selected.length !== query.length || !configSearchPattern(query).test(selected)) return;
    event.preventDefault();
    findConfigMatch(true, true);
  });
  editor.addEventListener('input', function () { searchOffset = 0; });
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

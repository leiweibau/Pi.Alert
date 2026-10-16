(function (window, document, $) {
  'use strict';
  var configNode = document.getElementById('logical-devices-config');
  if (!configNode || !$) return;
  var config = JSON.parse(configNode.textContent || '{}'), labels = config.labels || {};
  var groups = [], selectedId = null, baseline = '', dirty = false, busy = false;
  var fields = ['name','device_type','owner','location','vendor','group'];
  var suggestions = {};
  var form = document.getElementById('logical-device-form');
  var viewToggle = document.getElementById('logical-view-mode');
  var savedView = config.viewMode === 'devices';
  var pluralRules;
  var pluralLocale = (document.documentElement.lang || 'en').replace('_', '-');
  pluralLocale = ({'cz-cs':'cs','dk-da':'da','se-sv':'sv','ua-uk':'uk'})[pluralLocale.toLowerCase()] || pluralLocale;
  try { pluralRules = new Intl.PluralRules(pluralLocale); } catch (_) { pluralRules = new Intl.PluralRules('en'); }
  function node(id) { return document.getElementById(id); }
  function text(value) { return String(value == null ? '' : value); }
  function L(key, values) { return text(labels[key]).replace(/\{(\w+)\}/g, function (_, name) { return text((values || {})[name]); }); }
  function memberCount(count) { var form = pluralRules.select(count); return L('LD_MemberCount' + (form === 'one' ? 'One' : form === 'few' && labels.LD_MemberCountFew ? 'Few' : 'Many'), {count:count}); }
  function selected() { return groups.find(function (group) { return text(group.id) === text(selectedId); }); }
  function values() { var result = {}; fields.forEach(function (field) { result[field] = node('logical-field-' + field).value; }); return result; }
  function markDirty() { dirty = JSON.stringify(values()) !== baseline; node('logical-unsaved').hidden = !dirty; node('logical-save').disabled = busy || (selectedId !== null && !dirty); }
  function feedback(message, failed) { var output = node('logical-feedback'); output.textContent = message; output.hidden = !message; output.className = 'alert ' + (failed ? 'alert-danger' : 'alert-success'); output.setAttribute('role', failed ? 'alert' : 'status'); }
  function errorMessage(xhr) { var response = xhr && (xhr.responseJSON || xhr); return L(response && response.error === 'duplicate_name' ? 'LD_DuplicateName' : 'LD_SaveError'); }
  function setBusy(value) { busy = value; form.querySelectorAll('input,button').forEach(function (control) { control.disabled = value; }); node('logical-create').disabled = value; node('logical-device-list').querySelectorAll('button').forEach(function (button) { button.disabled = value; }); markDirty(); }
  function canDiscard() { return !dirty || window.confirm(L('LD_UnsavedChanges')); }
  function renderList() {
    var list = node('logical-device-list'); list.replaceChildren(); node('logical-empty').hidden = groups.length !== 0;
    groups.forEach(function (group) {
      var button = document.createElement('button'); button.type = 'button'; button.className = 'list-group-item list-group-item-action'; button.dataset.logicalId = group.id;
      var active = text(group.id) === text(selectedId); button.classList.toggle('active', active); button.setAttribute('aria-current', active ? 'true' : 'false');
      var name = document.createElement('span'); name.className = 'd-block'; name.textContent = group.name;
      var count = document.createElement('small'); count.textContent = memberCount((group.members || []).length); button.append(name, count);
      button.addEventListener('click', function () { if (!busy && canDiscard()) select(group.id, true); }); list.append(button);
    });
  }
  function renderMembers(group) {
    var members = group ? group.members || [] : [], list = node('logical-editor-members-list');
    node('logical-editor-members').hidden = !group; node('logical-editor-members-title').textContent = group ? L('LD_MembersTitle', {name: group.name}) : '';
    list.replaceChildren();
    members.forEach(function (member) {
      var item = document.createElement('li'); item.className = 'list-group-item';
      var link = document.createElement('a'); link.href = 'deviceDetails.php?' + new URLSearchParams({mac: member.dev_MAC, logical_id: group.id, return_url: config.returnUrl}).toString(); link.textContent = member.dev_MAC;
      var detail = document.createElement('span'); detail.textContent = ' (' + text(member.scan_source_name || member.dev_ScanSource) + ') · ' + text(member.dev_LastIP);
      item.append(link, detail);
      if (Number(member.dev_Archived) === 1) { var archive = document.createElement('span'); archive.className = 'badge text-bg-secondary ms-2'; archive.textContent = labels.archived; item.append(archive); }
      list.append(item);
    });
  }
  function renderOptions() {
    var properties = {device_type:'dev_DeviceType',owner:'dev_Owner',location:'dev_Location',vendor:'dev_Vendor',group:'dev_Group'};
    Object.keys(properties).forEach(function (field) {
      var options = new Set(); groups.forEach(function (group) { if (group[field]) options.add(text(group[field])); (group.members || []).forEach(function (member) { if (member[properties[field]]) options.add(text(member[properties[field]])); }); });
      var list = node('logical-options-' + field); list.replaceChildren();
      if (list.tagName === 'DATALIST') {
        Array.from(options).sort().forEach(function (value) { var option = document.createElement('option'); option.value = value; list.append(option); });
        return;
      }
      var items = [], seen = new Set(), previousOrder = null;
      (suggestions[field] || []).forEach(function (item) {
        var value = text(item.id != null && item.id !== '' ? item.id : item.name);
        if (!value || seen.has(value)) return;
        seen.add(value); items.push({value:value, name:text(item.name || value), order:item.order == null ? null : text(item.order)});
      });
      Array.from(options).sort().forEach(function (value) {
        if (!seen.has(value)) items.push({value:value, name:value, order:'logical'});
      });
      items.forEach(function (item) {
        if (previousOrder !== null && item.order !== previousOrder) {
          var separator = document.createElement('li'), line = document.createElement('hr');
          line.className = 'dropdown-divider'; separator.append(line); list.append(separator);
        }
        previousOrder = item.order;
        var entry = document.createElement('li'), button = document.createElement('button');
        button.type = 'button'; button.className = 'dropdown-item'; button.textContent = item.name;
        button.dataset.value = item.value; button.disabled = busy;
        button.addEventListener('click', function () {
          if (busy) return;
          var target = node('logical-field-' + field);
          target.value = item.value;
          target.dispatchEvent(new Event('input', {bubbles:true}));
          target.focus();
        });
        entry.append(button); list.append(entry);
      });
    });
  }
  function loadSuggestions() {
    form.querySelectorAll('[data-suggestion-action]').forEach(function (menu) {
      $.getJSON('php/server/devices.php?' + new URLSearchParams({action:menu.dataset.suggestionAction}).toString()).done(function (items) {
        suggestions[menu.dataset.suggestionField] = Array.isArray(items) ? items : [];
        renderOptions();
      });
    });
  }
  function select(id, focus) {
    selectedId = id == null ? null : id; var group = selected();
    fields.forEach(function (field) { node('logical-field-' + field).value = group ? text(group[field]) : ''; });
    baseline = JSON.stringify(values()); markDirty();
    node('logical-editor-title').textContent = group ? group.name : L('LD_Create'); node('logical-delete').hidden = !group;
    renderList(); renderMembers(group); if (focus) node('logical-field-name').focus();
  }
  function load() {
    return $.getJSON('php/server/logical_devices.php?action=list').then(function (response) {
      if (!response || !response.ok || !Array.isArray(response.devices)) throw new Error('Invalid logical device response');
      groups = response.devices; renderOptions(); var requestedId = new URLSearchParams(window.location.search).get('logical_id'); var initial = groups.find(function (group) { return text(group.id) === requestedId; }); select(initial ? initial.id : groups.length ? groups[0].id : null, false);
    }).catch(function () { feedback(L('LD_LoadError'), true); }).always(function () { setBusy(false); });
  }
  form.addEventListener('input', markDirty);
  form.addEventListener('submit', function (event) {
    event.preventDefault(); if (busy || !form.reportValidity()) return;
    var payload = Object.assign({action:'save'}, values()); if (selectedId !== null) payload.id = selectedId;
    if (!payload.name.trim()) { feedback(L('LD_SaveError'), true); node('logical-field-name').focus(); return; }
    setBusy(true); feedback('', false);
    window.pialertPost('php/server/logical_devices.php', payload).done(function (response) {
      if (!response || !response.ok || !response.device) { feedback(errorMessage(response), true); return; }
      var old = selected(), saved = response.device;
      if (!Array.isArray(saved.members)) saved.members = old ? old.members || [] : [];
      var index = groups.findIndex(function (group) { return text(group.id) === text(saved.id); });
      if (index === -1) groups.push(saved); else groups[index] = saved;
      groups.sort(function (a,b) { return text(a.name).localeCompare(text(b.name)); });
      select(saved.id, false); renderOptions(); feedback(L('LD_SaveSuccess'), false);
    }).fail(function (xhr) { feedback(errorMessage(xhr), true); }).always(function () { setBusy(false); });
  });
  node('logical-create').addEventListener('click', function () { if (canDiscard()) { feedback('', false); select(null, true); } });
  node('logical-cancel').addEventListener('click', function () { if (canDiscard()) { feedback('', false); select(selectedId, false); } });
  node('logical-delete').addEventListener('click', function () {
    var group = selected(); if (!group || busy) return;
    node('logical-delete-text').textContent = L('LD_DeleteText', {name:group.name}); window.bootstrap.Modal.getOrCreateInstance(node('logical-delete-modal')).show();
  });
  node('logical-delete-confirm').addEventListener('click', function () {
    var group = selected(); if (!group || busy) return;
    window.bootstrap.Modal.getOrCreateInstance(node('logical-delete-modal')).hide(); setBusy(true);
    window.pialertPost('php/server/logical_devices.php', {action:'delete', id:group.id}).done(function (response) {
      if (!response || !response.ok) { feedback(errorMessage(response), true); return; }
      groups = groups.filter(function (item) { return text(item.id) !== text(group.id); }); select(groups.length ? groups[0].id : null, false); renderOptions(); feedback(L('LD_DeleteSuccess'), false);
    }).fail(function (xhr) { feedback(errorMessage(xhr), true); }).always(function () { setBusy(false); });
  });
  viewToggle.addEventListener('change', function () {
    var requested = viewToggle.checked, output = node('logical-view-feedback'); viewToggle.disabled = true; output.hidden = true;
    window.pialertPost('php/server/v4_ui_settings.php', {action:'save',patch:JSON.stringify({'devices.view_mode':requested ? 'devices' : 'interfaces'})}).done(function (response) {
      if (!response || !response.devices || response.devices.view_mode !== (requested ? 'devices' : 'interfaces')) { viewToggle.checked = savedView; output.textContent = L('LD_SaveError'); output.className = 'alert alert-danger w-100 mb-0'; output.hidden = false; return; }
      savedView = requested; output.textContent = L('LD_ViewSaved'); output.className = 'small w-100 text-body-secondary'; output.hidden = false;
    }).fail(function () { viewToggle.checked = savedView; output.textContent = L('LD_SaveError'); output.className = 'alert alert-danger w-100 mb-0'; output.hidden = false; }).always(function () { viewToggle.disabled = false; });
  });
  window.addEventListener('beforeunload', function (event) { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
  baseline = JSON.stringify(values()); setBusy(true); load(); loadSuggestions();
})(window, document, window.jQuery);

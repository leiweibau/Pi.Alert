(function (window, document) {
  'use strict';
  var root = document.getElementById('entity-actions-editor');
  if (!root) return;
  var labels = JSON.parse(root.dataset.labels || '{}');
  var rows = root.querySelector('.entity-actions-rows');
  var message = root.querySelector('.entity-actions-message');
  var add = root.querySelector('.entity-actions-add');
  var save = root.querySelector('.entity-actions-save');
  var reset = root.querySelector('.entity-actions-reset');
  var dialog = document.getElementById('entity-actions-icon-modal');
  var search = document.getElementById('entity-actions-icon-search');
  var family = document.getElementById('entity-actions-icon-family');
  var grid = root.querySelector('.entity-actions-icon-grid');
  var more = root.querySelector('.entity-actions-more');
  var modal = window.bootstrap.Modal.getOrCreateInstance(dialog);
  var leaveDialog = document.getElementById('entity-actions-leave-modal');
  var leaveModal = window.bootstrap.Modal.getOrCreateInstance(leaveDialog);
  var kind = '', key = '', version = '', saved = '[]', icons = null, selectedRow = null, offset = 0, busy = false, generation = 0, available = true;
  function value() {
    return JSON.stringify(Array.from(rows.children).map(function (row) { return {
      action_id: row.dataset.id ? Number(row.dataset.id) : null,
      url: row.querySelector('.entity-actions-url').value.trim(),
      icon_id: row.dataset.icon || 'bi:link-45deg',
      label: row.querySelector('.entity-actions-label').value.trim(),
      color: row.querySelector('.entity-actions-color').value.trim().toLowerCase(),
      text_color: row.querySelector('.entity-actions-text-color').value.trim().toLowerCase()
    }; }));
  }
  function dirty() { return value() !== saved; }
  function status(text, error) { message.textContent = text || ''; message.classList.toggle('text-danger', !!error); message.classList.toggle('text-success', !error); }
  function controls() { add.disabled = busy || !available || !key || rows.children.length >= 3; save.disabled = busy || !available || !key || !dirty(); reset.disabled = busy || !available || !key || !dirty(); }
  function iconClass(id) { return window.pialertEntityActions.iconClass(id); }
  function createRow(action) {
    var row = document.createElement('div'); row.className = 'entity-actions-row';
    if (action.action_id != null) row.dataset.id = String(action.action_id);
    row.dataset.icon = action.icon_id || 'bi:link-45deg';
    var urlWrap = document.createElement('div'); urlWrap.className = 'entity-actions-url-wrap';
    var urlLabel = document.createElement('label'); urlLabel.textContent = labels.url;
    var url = document.createElement('input'); url.type = 'url'; url.className = 'form-control entity-actions-url'; url.maxLength = 2048; url.required = true; url.value = action.url || '';
    urlLabel.append(url); urlWrap.append(urlLabel);
    var iconButton = document.createElement('button'); iconButton.type = 'button'; iconButton.className = 'btn btn-outline-secondary entity-actions-icon-button'; iconButton.title = labels.icon; iconButton.setAttribute('aria-label', labels.icon);
    var icon = document.createElement('i'); icon.className = iconClass(row.dataset.icon); icon.dataset.actionIconId = row.dataset.icon; icon.setAttribute('aria-hidden', 'true'); iconButton.append(icon);
    iconButton.addEventListener('click', function () { selectedRow = row; openIcons(); });
    var labelWrap = document.createElement('div'); labelWrap.className = 'entity-actions-label-wrap';
    var labelText = document.createElement('label'); labelText.textContent = labels.label;
    var label = document.createElement('input'); label.type = 'text'; label.className = 'form-control entity-actions-label'; label.maxLength = 80; label.value = action.label || '';
    labelText.append(label); labelWrap.append(labelText);
    var colorWrap = document.createElement('div'); colorWrap.className = 'entity-actions-color-wrap';
    var colorLabel = document.createElement('label'); colorLabel.textContent = labels.color;
    var color = document.createElement('input'); color.type = 'text'; color.className = 'form-control entity-actions-color'; color.maxLength = 7;
    color.pattern = '#[0-9A-Fa-f]{6}'; color.value = /^#[0-9a-fA-F]{6}$/.test(String(action.color || '')) ? String(action.color).toLowerCase() : '#6c757d';
    color.setAttribute('data-coloris', ''); colorLabel.append(color); colorWrap.append(colorLabel);
    var textColorWrap = document.createElement('div'); textColorWrap.className = 'entity-actions-text-color-wrap';
    var textColorLabel = document.createElement('label'); textColorLabel.textContent = labels.textColor;
    var textColor = document.createElement('input'); textColor.type = 'text'; textColor.className = 'form-control entity-actions-text-color'; textColor.maxLength = 7;
    textColor.pattern = '#[0-9A-Fa-f]{6}'; textColor.value = /^#[0-9a-fA-F]{6}$/.test(String(action.text_color || '')) ? String(action.text_color).toLowerCase() : window.pialertEntityActions.contrastColor(color.value);
    textColor.setAttribute('data-coloris', ''); textColorLabel.append(textColor); textColorWrap.append(textColorLabel);
    var remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-outline-danger entity-actions-remove'; remove.title = labels.remove; remove.setAttribute('aria-label', labels.remove);
    var removeIcon = document.createElement('i'); removeIcon.className = 'fa-solid fa-minus'; removeIcon.setAttribute('aria-hidden', 'true'); remove.append(removeIcon);
    remove.addEventListener('click', function () { row.remove(); controls(); empty(); });
    row.append(urlWrap, iconButton, labelWrap, colorWrap, textColorWrap, remove); rows.append(row);
    if (typeof window.Coloris === 'function' && typeof window.Coloris.wrap === 'function') window.Coloris.wrap(color);
    if (typeof window.Coloris === 'function' && typeof window.Coloris.wrap === 'function') window.Coloris.wrap(textColor);
    row.addEventListener('input', controls); controls(); empty(); return row;
  }
  function empty() {
    var node = root.querySelector('.entity-actions-empty');
    if (!rows.children.length && !node) { node = document.createElement('p'); node.className = 'entity-actions-empty text-body-secondary'; node.textContent = labels.empty; rows.after(node); }
    else if (rows.children.length && node) node.remove();
  }
  function render(actions) {
    rows.replaceChildren(); (Array.isArray(actions) ? actions : []).slice(0, 3).forEach(createRow); empty(); controls();
    var current = generation;
    window.pialertEntityActions.verifyIcons().then(function () {
      if (current !== generation) return;
      Array.from(rows.children).forEach(function (row) {
        if (window.pialertEntityActions.isKnown(row.dataset.icon)) return;
        row.dataset.icon = 'bi:link-45deg';
        var preview = row.querySelector('.entity-actions-icon-button i'); preview.dataset.actionIconId = row.dataset.icon; preview.className = iconClass(row.dataset.icon);
      });
      controls();
    });
  }
  function errorText(code) { return labels[code] || labels.save_failed; }
  function responseError(response) {
    var body = {};
    try { body = JSON.parse(response.responseText || '{}'); } catch (_error) { /* Keep generic message. */ }
    var text = errorText(body.error);
    if (Number.isInteger(body.row) && rows.children[body.row]) {
      var selector = body.error === 'invalid_label' ? '.entity-actions-label'
        : body.error === 'invalid_icon' ? '.entity-actions-icon-button'
        : body.error === 'invalid_color' ? '.entity-actions-color'
        : body.error === 'invalid_text_color' ? '.entity-actions-text-color' : '.entity-actions-url';
      var input = rows.children[body.row].querySelector(selector);
      input.focus(); text = (body.row + 1) + ': ' + text;
    }
    status(text, true);
    if (body.error === 'schema_unavailable') { available = false; controls(); }
  }
  function load() {
    if (!kind || !key) return;
    var current = ++generation;
    busy = true; controls(); status(labels.loading);
    fetch('php/server/entity_actions.php?' + new URLSearchParams({kind:kind,key:key}), {credentials:'same-origin'})
      .then(function (response) { return response.json().then(function (data) { if (!response.ok) throw {responseText:JSON.stringify(data)}; return data; }); })
      .then(function (data) {
        if (current !== generation) return;
        available = data.schema_available !== false; version = data.version || ''; render(data.actions); saved = value();
        status(available ? '' : labels.schema_unavailable, !available); controls();
      })
      .catch(function (error) { if (current !== generation) return; responseError(error); })
      .finally(function () { if (current === generation) { busy = false; controls(); } });
  }
  function filterIcons() {
    if (!icons) return [];
    var term = search.value.trim().toLowerCase(); var fam = family.value;
    return icons.filter(function (entry) { return (!fam || entry.family === fam) && (!term || (entry.name + ' ' + entry.id).toLowerCase().includes(term)); });
  }
  function showIcons(append) {
    if (!append) { offset = 0; grid.replaceChildren(); }
    var found = filterIcons();
    found.slice(offset, offset + 120).forEach(function (entry) {
      var button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-outline-secondary entity-actions-icon-choice';
      button.title = entry.family + ': ' + entry.name; button.setAttribute('aria-label', button.title);
      var icon = document.createElement('i'); icon.className = entry.class; icon.setAttribute('aria-hidden', 'true'); button.append(icon);
      var name = document.createElement('span'); name.textContent = entry.name; button.append(name);
      button.addEventListener('click', function () { if (!selectedRow) return; selectedRow.dataset.icon = entry.id; var preview = selectedRow.querySelector('.entity-actions-icon-button i'); preview.className = entry.class; preview.dataset.actionIconId = entry.id; controls(); modal.hide(); });
      grid.append(button);
    });
    offset += 120; more.hidden = offset >= found.length;
  }
  function openIcons() {
    modal.show();
    if (icons) { showIcons(false); search.focus(); return; }
    status(labels.loading);
    fetch('data/action-icons.json', {credentials:'same-origin'}).then(function (response) { if (!response.ok) throw new Error('catalog'); return response.json(); })
      .then(function (data) { icons = Array.isArray(data) ? data : []; Array.from(new Set(icons.map(function (entry) { return entry.family; }))).forEach(function (name) { var option = document.createElement('option'); option.value = name; option.textContent = name; family.append(option); }); status(''); showIcons(false); search.focus(); })
      .catch(function () { status(labels.save_failed, true); });
  }
  function valid() {
    for (var index = 0; index < rows.children.length; index++) {
      var input = rows.children[index].querySelector('.entity-actions-url');
      if (!window.pialertEntityActions.safeUrl(input.value.trim())) { input.focus(); status((index + 1) + ': ' + labels.invalidLocal, true); return false; }
      var color = rows.children[index].querySelector('.entity-actions-color');
      if (!/^#[0-9a-fA-F]{6}$/.test(color.value.trim())) { color.focus(); status((index + 1) + ': ' + labels.invalid_color, true); return false; }
      var textColor = rows.children[index].querySelector('.entity-actions-text-color');
      if (!/^#[0-9a-fA-F]{6}$/.test(textColor.value.trim())) { textColor.focus(); status((index + 1) + ': ' + labels.invalid_text_color, true); return false; }
    }
    return true;
  }
  add.addEventListener('click', function () { if (rows.children.length < 3) { createRow({}); rows.lastElementChild.querySelector('input').focus(); } });
  reset.addEventListener('click', function () { load(); });
  function saveActions() {
    if (!valid() || busy || !kind || !key) return Promise.resolve(false);
    busy = true; controls(); status('');
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var requestGeneration = generation;
    return fetch('php/server/entity_actions.php', {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf ? csrf.content : ''},body:JSON.stringify({kind:kind,key:key,version:version,actions:JSON.parse(value())})})
      .then(function (response) { return response.json().then(function (data) { if (!response.ok) throw {responseText:JSON.stringify(data)}; return data; }); })
      .then(function (data) { if (requestGeneration !== generation) return false; version = data.version || ''; render(data.actions); saved = value(); status(labels.saved); controls(); return true; })
      .catch(function (error) { if (requestGeneration === generation) responseError(error); return false; })
      .finally(function () { if (requestGeneration === generation) { busy = false; controls(); } });
  }
  save.addEventListener('click', saveActions);
  function navigationChoice() {
    if (!dirty()) return Promise.resolve('clean');
    return new Promise(function (resolve) {
      var done = false;
      function finish(choice) { if (done) return; done = true; leaveDialog.removeEventListener('hidden.bs.modal', onHide); leaveModal.hide(); resolve(choice); }
      function onHide() { finish('cancel'); }
      leaveDialog.addEventListener('hidden.bs.modal', onHide);
      leaveDialog.querySelectorAll('[data-choice]').forEach(function (button) { button.onclick = function () { finish(button.dataset.choice); }; });
      leaveModal.show();
    });
  }
  search.addEventListener('input', function () { showIcons(false); }); family.addEventListener('change', function () { showIcons(false); }); more.addEventListener('click', function () { showIcons(true); });
  window.pialertEntityActionsEditor = {
    setTarget: function (nextKind, nextKey) { if (kind === nextKind && key === nextKey) return; kind = nextKind; key = nextKey; generation++; version = ''; rows.replaceChildren(); saved = '[]'; available = true; status(''); empty(); controls(); if (document.getElementById('tabActions').classList.contains('active')) load(); },
    load: load, dirty: dirty, save: saveActions, navigationChoice: navigationChoice,
    confirmDiscard: function () { return !dirty() || window.confirm(labels.leave); }
  };
  document.getElementById('tabActions').addEventListener('shown.bs.tab', function () { if (kind && key && !version && available) load(); });
  if (typeof window.Coloris === 'function') window.Coloris({el:'.entity-actions-color, .entity-actions-text-color',theme:'pill',themeMode:document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light',format:'hex',alpha:false,clearButton:false,closeButton:true,closeLabel:labels.close});
  window.pialertEntityActions.verifyIcons();
})(window, document);

(function (window, document, $) {
  'use strict';

  var root = document.getElementById('journal-page');
  var state = {
    table: null,
    triggerIndex: 0,
    methodIndex: 0,
    triggerNames: [],
    triggerColors: [],
    methodNames: [],
    methodColors: []
  };

  function parseParameters (data) {
    if (typeof data === 'string') {
      try { data = JSON.parse(data); } catch (_error) { return {}; }
    }
    return data && typeof data === 'object' ? data : {};
  }

  function commaValues (value) {
    return typeof value === 'string' && value !== ''
      ? value.split(',').map(function (entry) { return entry.trim(); })
      : [];
  }

  function safeJournalMarkup (value) {
    var template = document.createElement('template');
    template.innerHTML = String(value == null ? '' : value);
    var output = document.createElement('div');

    function append (node, parent) {
      if (node.nodeType === window.Node.TEXT_NODE) {
        parent.appendChild(document.createTextNode(node.nodeValue || ''));
        return;
      }
      if (node.nodeType !== window.Node.ELEMENT_NODE) return;
      var tag = node.tagName.toLowerCase();
      if (tag === 'br') {
        parent.appendChild(document.createElement('br'));
        return;
      }
      if (tag === 'span') {
        var span = document.createElement('span');
        if (node.classList.contains('text-danger')) span.className = 'text-danger';
        Array.prototype.forEach.call(node.childNodes, function (child) { append(child, span); });
        parent.appendChild(span);
        return;
      }
      Array.prototype.forEach.call(node.childNodes, function (child) { append(child, parent); });
    }

    Array.prototype.forEach.call(template.content.childNodes, function (child) { append(child, output); });
    return output.innerHTML;
  }

  function journalText (value) {
    var container = document.createElement('div');
    container.innerHTML = safeJournalMarkup(value).replace(/<br\s*\/?>/gi, '\n');
    return container.textContent || '';
  }

  function setColoredText (cell, value, color, emphasize, preserveSpaces) {
    var element = document.createElement(emphasize ? 'strong' : 'span');
    element.textContent = String(value == null ? '' : value);
    if (preserveSpaces) element.style.whiteSpace = 'pre';
    if (color) element.style.color = color;
    cell.replaceChildren(element);
  }

  function colorFor (names, colors, value) {
    var index = names.indexOf(String(value == null ? '' : value));
    return index >= 0 ? String(colors[index] || '') : '';
  }

  function colorDate (cell, value) {
    var created = new Date(String(value == null ? '' : value).replace(' ', 'T'));
    var now = new Date();
    var hourAgo = new Date(now.getTime() - 60 * 60 * 1000);
    var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    var color = '';
    if (!Number.isNaN(created.getTime()) && created >= today && created < hourAgo) color = '#3468ff';
    else if (!Number.isNaN(created.getTime()) && created >= hourAgo) color = '#ff644d';
    setColoredText(cell, value, color, true, true);
  }

  function initializeTable () {
    state.table = $('#tableJournal').DataTable({
      paging: true,
      lengthChange: true,
      lengthMenu: [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, root.dataset.lengthAll]],
      searching: true,
      ordering: true,
      info: true,
      autoWidth: true,
      pageLength: 25,
      order: [[0, 'desc']],
      columnDefs: [
        { targets: [0, 1, 2, 3], render: $.fn.dataTable.render.text() },
        { targets: 4, render: function (data, type) { return type === 'display' ? safeJournalMarkup(data) : journalText(data); } },
        { width: '120px', targets: 0 },
        { width: '150px', targets: 1 },
        { targets: 0, createdCell: function (cell, value) { colorDate(cell, value); } },
        { targets: 1, createdCell: function (cell, value) { setColoredText(cell, value, colorFor(state.methodNames, state.methodColors, value), false, false); } },
        { targets: 2, createdCell: function (cell, value) { setColoredText(cell, value, colorFor(state.triggerNames, state.triggerColors, value), false, false); } },
        { visible: false, targets: 3 }
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
  }

  function appendColorRow (kind, name, color) {
    var indexKey = kind + 'Index';
    state[indexKey] += 1;
    var index = state[indexKey];
    var row = document.createElement('div');
    row.id = kind + '_' + index;
    row.className = 'journal-color-row';

    var nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.name = kind + 'Names[]';
    nameInput.className = 'form-control journal-custom-color-name';
    nameInput.placeholder = (kind === 'trigger' ? window.pialertV4Text('V4_Trigger_Name') : window.pialertV4Text('V4_Method_Name')) + ' ' + index;
    nameInput.value = String(name == null ? '' : name);

    var colorInput = document.createElement('input');
    colorInput.type = 'text';
    colorInput.name = kind + 'Colors[]';
    colorInput.className = 'form-control journal-custom-color-value';
    colorInput.placeholder = (kind === 'trigger' ? window.pialertV4Text('V4_Trigger_Color') : window.pialertV4Text('V4_Method_Color')) + ' ' + index;
    colorInput.value = String(color == null ? '' : color);
    colorInput.setAttribute('data-coloris', '');

    var remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'btn btn-outline-danger';
    remove.setAttribute('aria-label', window.pialertV4Text('V4_Remove_Color_Row'));
    remove.innerHTML = '<i class="fa-solid fa-minus" aria-hidden="true"></i>';
    remove.addEventListener('click', function () { row.remove(); });

    row.append(nameInput, colorInput, remove);
    document.getElementById(kind + 'Container').appendChild(row);
    if (typeof window.Coloris === 'function' && typeof window.Coloris.wrap === 'function') window.Coloris.wrap(colorInput);
    return colorInput;
  }

  function values (name) {
    return Array.prototype.map.call(document.querySelectorAll('input[name="' + name + '[]"]'), function (input) { return input.value; });
  }

  function saveColors (kind) {
    var payload = { action: 'setJournalParameter', column: kind };
    payload[kind + 'Names'] = values(kind + 'Names');
    payload[kind + 'Colors'] = values(kind + 'Colors');
    return window.pialertPost('php/server/parameters.php', payload, function (message) { window.showMessage(message); });
  }

  function bindControls () {
    document.getElementById('addTrigger').addEventListener('click', function () { appendColorRow('trigger', '', ''); });
    document.getElementById('addMethod').addEventListener('click', function () { appendColorRow('method', '', ''); });
    document.getElementById('saveTriggerColors').addEventListener('click', function () { saveColors('trigger'); });
    document.getElementById('saveMethodColors').addEventListener('click', function () { saveColors('method'); });
    document.getElementById('reset_joursearch').addEventListener('click', function () { state.table.search('').draw(); });
    document.getElementById('closeJournalColors').addEventListener('click', function () { window.setTimeout(function () { window.location.reload(); }, 1000); });
  }

  function initialize (data) {
    var parameters = parseParameters(data);
    state.triggerNames = commaValues(parameters.journal_trigger_filter);
    state.triggerColors = commaValues(parameters.journal_trigger_filter_color);
    state.methodNames = commaValues(parameters.journal_method_filter);
    state.methodColors = commaValues(parameters.journal_method_filter_color);
    state.triggerNames.forEach(function (name, index) { appendColorRow('trigger', name, state.triggerColors[index] || ''); });
    state.methodNames.forEach(function (name, index) { appendColorRow('method', name, state.methodColors[index] || ''); });
    initializeTable();
    bindControls();
  }

  function init () {
    if (!root || !$.fn || typeof $.fn.DataTable !== 'function' || typeof window.Coloris !== 'function') return;
    var colorModal = document.getElementById('modal-set-journal-colors');
    window.Coloris({
      parent: colorModal,
      theme: 'pill',
      themeMode: 'dark',
      alpha: false,
      focusInput: true,
      selectInput: true,
      closeButton: true,
      closeLabel: root.dataset.okay,
      clearButton: true,
      clearLabel: window.pialertV4Text('V4_Clear')
    });
    window.Coloris.ready(function () {
      var picker = document.getElementById('clr-picker');
      if (!picker || picker.dataset.bootstrapModalKeys === 'true') return;
      picker.dataset.bootstrapModalKeys = 'true';
      picker.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        event.stopPropagation();
        window.Coloris.close(true);
      });
    });
    $.get('php/server/parameters.php?action=getJournalParameter').done(initialize).fail(function () { initialize({}); });
  }

  window.clearInput = function () { if (state.table) state.table.search('').draw(); };
  window.JournalReload = function () { window.setTimeout(function () { window.location.reload(); }, 1000); };
  window.SetTriggerColors = function () { return saveColors('trigger'); };
  window.SetMethodColors = function () { return saveColors('method'); };
  window.addTriggerRow = function (name, color) { return appendColorRow('trigger', name, color); };
  window.addMethodRow = function (name, color) { return appendColorRow('method', name, color); };
  $(init);
})(window, document, window.jQuery);

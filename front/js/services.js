(function (window, document, $) {
  'use strict';

  var root = document.getElementById('services-page');
  if (!root) return;

  var state = { deleteUrl: '', table: null, journalInterval: null };
  var editorElement = document.getElementById('service-editor-modal');
  var editor = window.bootstrap && window.bootstrap.Modal
    ? window.bootstrap.Modal.getOrCreateInstance(editorElement)
    : null;

  function field (id) { return document.getElementById(id); }
  function boolValue (id) { return field(id).checked ? 1 : 0; }

  function escapeHtml (value) {
    var node = document.createElement('div');
    node.textContent = String(value == null ? '' : value);
    return node.innerHTML;
  }

  function detailUrl (value) {
    return root.dataset.detailsRoute + '?url=' + encodeURIComponent(String(value || ''));
  }

  function resetEditor () {
    field('service-editor-title').textContent = document.getElementById('add-service').textContent.trim();
    field('service-editor-form').reset();
    field('serviceURL').readOnly = false;
  }

  function saveService (event) {
    event.preventDefault();
    if (!field('service-editor-form').reportValidity()) return;
    var url = field('serviceURL').value.trim();
    if (!url) return;
    var payload = {
      action: 'insertNewService',
      url: url,
      tags: field('serviceTag').value,
      mac: field('serviceMAC').value,
      alertdown: boolValue('insAlertDown'),
      alertup: boolValue('insAlertUp'),
      alertevents: boolValue('insAlertEvents')
    };
    field('save-service').disabled = true;
    window.pialertPost(root.dataset.servicesEndpoint, payload, function (message) {
      if (editor) editor.hide();
      window.showMessage(message);
      window.setTimeout(function () { window.location.reload(); }, 900);
    }).always(function () { field('save-service').disabled = false; });
  }

  function askDelete (button) {
    state.deleteUrl = button.dataset.url || '';
    window.showModalWarning(
      root.dataset.deleteTitle,
      root.dataset.deleteMessage,
      root.dataset.cancel,
      root.dataset.delete,
      deletePendingService
    );
  }

  function deletePendingService () {
    if (!state.deleteUrl) return;
    window.pialertPost(root.dataset.servicesEndpoint, {
      action: 'deleteService',
      url: state.deleteUrl
    }, function (message) {
      window.showMessage(message);
      window.setTimeout(function () { window.location.reload(); }, 900);
    });
  }

  function applyFilter (filter) {
    var visibleCards = 0;
    document.querySelectorAll('[data-service-card]').forEach(function (card) {
      var visible = filter === 'all' || card.dataset.serviceState === filter;
      card.hidden = !visible;
      if (visible) visibleCards += 1;
    });
    document.querySelectorAll('[data-service-group]').forEach(function (group) {
      group.hidden = !group.querySelector('[data-service-card]:not([hidden])');
    });
    field('services-empty-filter').hidden = visibleCards !== 0;
    document.querySelectorAll('[data-service-filter]').forEach(function (button) {
      var selected = button.dataset.serviceFilter === filter;
      var outline = {
        all: 'btn-outline-primary',
        online: 'btn-outline-success',
        warning: 'btn-outline-warning',
        down: 'btn-outline-danger'
      }[button.dataset.serviceFilter];
      button.classList.toggle('active', selected);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
      button.classList.toggle('btn-primary', selected);
      ['btn-outline-primary', 'btn-outline-success', 'btn-outline-warning', 'btn-outline-danger'].forEach(function (className) { button.classList.remove(className); });
      if (!selected) button.classList.add(outline);
    });
  }

  function journalInfoCell (cell, value) {
    var text = String(value == null ? '' : value);
    var markers = {
      'Service reachable again': 'text-success',
      'Service unreachable': 'text-danger',
      'SSL Subject changed': 'text-info',
      'SSL Issuer changed': 'text-info',
      'SSL Valid_from changed': 'text-info',
      'SSL Valid_to changed': 'text-info',
      'High latency:': 'text-warning',
      'Status changed:': 'text-primary'
    };
    var matched = Object.keys(markers).find(function (marker) { return text.indexOf(marker) !== -1; });
    cell.replaceChildren();
    if (!matched) {
      cell.textContent = text;
      return;
    }
    var position = text.indexOf(matched);
    cell.appendChild(document.createTextNode(text.slice(0, position)));
    var emphasis = document.createElement('span');
    emphasis.className = markers[matched];
    emphasis.textContent = matched;
    cell.appendChild(emphasis);
    cell.appendChild(document.createTextNode(text.slice(position + matched.length)));
  }

  function initializeJournal () {
    if (!$.fn || typeof $.fn.DataTable !== 'function') return;
    state.table = $('#servicesJournalTable').DataTable({
      ajax: { url: root.dataset.servicesEndpoint + '?action=getServicesJournal', type: 'GET', dataSrc: '' },
      searching: false,
      lengthChange: false,
      pageLength: 10,
      order: [[0, 'desc']],
      columns: [
        { data: 'monevj_DateTime', render: $.fn.dataTable.render.text() },
        {
          data: 'monevj_URL',
          render: function (data, type) {
            if (type !== 'display') return String(data == null ? '' : data);
            var value = String(data == null ? '' : data);
            return '<a href="' + escapeHtml(detailUrl(value)) + '">' + escapeHtml(value) + '</a>';
          }
        },
        { data: 'monevj_Additional_Info', render: $.fn.dataTable.render.text(), createdCell: journalInfoCell }
      ],
      scrollY: '145px',
      scrollX: true,
      scrollCollapse: false,
      paging: false,
      info: false,
      language: window.pialertV4DataTableLanguage()
    });
    startJournalTimer();
  }

  function startJournalTimer () {
    if (state.journalInterval || !state.table || !$.fn.dataTable.isDataTable('#servicesJournalTable')) return;
    state.journalInterval = window.setInterval(function () {
      if (state.table && $.fn.dataTable.isDataTable('#servicesJournalTable')) state.table.ajax.reload(null, false);
    }, 30000);
  }

  function stopJournalTimer () {
    if (state.journalInterval) window.clearInterval(state.journalInterval);
    state.journalInterval = null;
  }

  function bind () {
    field('add-service').addEventListener('click', resetEditor);
    field('service-editor-form').addEventListener('submit', saveService);
    document.querySelectorAll('.delete-service').forEach(function (button) { button.addEventListener('click', function () { askDelete(button); }); });
    document.querySelectorAll('[data-service-filter]').forEach(function (button) { button.addEventListener('click', function () { applyFilter(button.dataset.serviceFilter); }); });
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) { window.bootstrap.Tooltip.getOrCreateInstance(element); });
  }

  window.deleteService = deletePendingService;
  window.insertNewService = function () {
    field('service-editor-form').requestSubmit();
  };

  bind();
  applyFilter('all');
  initializeJournal();
  window.addEventListener('pagehide', stopJournalTimer);
  window.addEventListener('pageshow', function (event) { if (event.persisted) startJournalTimer(); });
})(window, document, window.jQuery);

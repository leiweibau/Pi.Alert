(function (window, document, $) {
  'use strict';

  var root = document.getElementById('reports-page');
  var pendingForm = null;

  function filterReports () {
    var query = document.getElementById('report-filter').value.trim().toLocaleLowerCase();
    var type = document.getElementById('report-type-filter').value;
    var visible = 0;
    document.querySelectorAll('.pialert-report').forEach(function (report) {
      var match = (!query || report.dataset.reportSearch.indexOf(query) !== -1) && (!type || report.dataset.reportType === type);
      report.classList.toggle('d-none', !match);
      if (match) visible += 1;
    });
    document.getElementById('report-empty-state').classList.toggle('d-none', visible !== 0);
  }

  function submitPendingForm () {
    if (!pendingForm) return;
    var form = pendingForm;
    pendingForm = null;
    var payload = $(form).serializeArray();
    window.pialertPost(form.getAttribute('action'), payload, function () {
      window.location.reload();
    });
  }

  function confirmSingleAction (event) {
    event.preventDefault();
    pendingForm = event.currentTarget;
    var action = pendingForm.dataset.actionLabel === 'archive' ? window.pialertV4Text('V4_Archive_Report_Title') : root.dataset.confirmTitle;
    var message = pendingForm.dataset.actionLabel === 'archive' ? window.pialertV4Text('V4_Archive_Report_Message') : root.dataset.confirmMessage;
    window.showModalWarning(action, message, root.dataset.cancel, pendingForm.dataset.actionLabel === 'archive' ? window.pialertV4Text('V4_Archive') : root.dataset.delete, submitPendingForm);
  }

  function deleteAll () {
    var action = root.dataset.source === 'archive' ? 'deleteAllNotificationsArchive' : 'deleteAllNotifications';
    window.pialertPost('php/server/files.php?action=' + action, {}, function (message) {
      window.showMessage(message);
      window.setTimeout(function () { window.location.reload(); }, 1000);
    });
  }

  function saveColors () {
    var colors = Array.prototype.map.call(document.querySelectorAll('input[name="HeadLineColors[]"]'), function (input) { return input.value; });
    return window.pialertPost('php/server/parameters.php', { action: 'setReportParameter', HeadLineColors: colors }, function (message) {
      window.showMessage(message);
    });
  }

  function initializeTooltips () {
    if (!window.bootstrap || !window.bootstrap.Tooltip) return;
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
      window.bootstrap.Tooltip.getOrCreateInstance(element);
    });
  }

  function initialize () {
    if (!root || typeof window.Coloris !== 'function') return;
    var colorModal = document.getElementById('modal-set-report-colors');
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
    document.getElementById('report-filter').addEventListener('input', filterReports);
    document.getElementById('report-type-filter').addEventListener('change', filterReports);
    document.getElementById('save-report-colors').addEventListener('click', saveColors);
    document.getElementById('RemoveAllNotifications').addEventListener('click', function () {
      window.showModalWarning(root.dataset.confirmTitle, root.dataset.confirmMessage, root.dataset.cancel, root.dataset.delete, deleteAll);
    });
    document.querySelectorAll('.pialert-report-action-form').forEach(function (form) {
      if (!form.querySelector('button:not([disabled])')) return;
      form.addEventListener('submit', confirmSingleAction);
    });
    initializeTooltips();
  }

  window.pialertSubmitReportAction = submitPendingForm;
  window.deleteAllNotifications = deleteAll;
  window.deleteAllNotificationsArchive = deleteAll;
  window.SetReportColors = saveColors;
  window.ReportReload = function () { window.setTimeout(function () { window.location.reload(); }, 1000); };
  $(initialize);
})(window, document, window.jQuery);

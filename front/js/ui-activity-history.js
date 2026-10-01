(function (window, document) {
  'use strict';

  var toggle = document.getElementById('ui-activity-history');
  if (!toggle) return;

  function cleanMessage(value) {
    return String(value == null ? '' : value).replace(/<meta\b[^>]*>/gi, '').trim();
  }

  toggle.addEventListener('change', function () {
    var previous = toggle.dataset.enabled === '1';
    var enabled = toggle.checked;
    toggle.checked = previous;
    window.showModalWarning(
      toggle.dataset.title,
      toggle.dataset.confirm,
      toggle.dataset.cancelLabel,
      toggle.dataset.confirmLabel,
      function () {
        toggle.disabled = true;
        window.pialertPost('php/server/files.php?action=EnableOnlineHistoryGraph', {
          enabled: enabled ? '1' : '0'
        }, function (response) {
          var message = cleanMessage(response);
          if (message) window.showMessage(message);
          toggle.checked = enabled;
          toggle.dataset.enabled = enabled ? '1' : '0';
          window.setTimeout(function () { window.location.reload(); }, 1300);
        }).fail(function (xhr) {
          toggle.disabled = false;
          window.showMessage(cleanMessage(xhr.responseText) || toggle.dataset.errorLabel);
        });
      }
    );
  });
})(window, document);

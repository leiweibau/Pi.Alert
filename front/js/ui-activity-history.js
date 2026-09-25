(function (window, document) {
  'use strict';

  var button = document.getElementById('ui-activity-history');
  if (!button) return;

  function cleanMessage(value) {
    return String(value == null ? '' : value).replace(/<meta\b[^>]*>/gi, '').trim();
  }

  button.addEventListener('click', function () {
    window.showModalWarning(
      button.dataset.title,
      button.dataset.confirm,
      button.dataset.cancelLabel,
      button.dataset.confirmLabel,
      function () {
        button.disabled = true;
        window.pialertPost('php/server/files.php?action=EnableOnlineHistoryGraph', {
          enabled: button.dataset.enabled === '1' ? '0' : '1'
        }, function (response) {
          var message = cleanMessage(response);
          if (message) window.showMessage(message);
          window.setTimeout(function () { window.location.reload(); }, 1300);
        }).fail(function (xhr) {
          button.disabled = false;
          window.showMessage(cleanMessage(xhr.responseText) || button.dataset.errorLabel);
        });
      }
    );
  });
})(window, document);

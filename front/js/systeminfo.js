(function (window, document) {
  'use strict';

  function actionConfig() {
    var element = document.getElementById('systeminfo-actions');
    return element ? element.dataset : {};
  }

  function renderResolution() {
    var output = document.getElementById('resolution');
    if (!output) return;
    var ratio = window.devicePixelRatio || 1;
    output.replaceChildren(
      document.createTextNode('Width: ' + window.innerWidth + 'px / Height: ' + window.innerHeight + 'px'),
      document.createElement('br'),
      document.createTextNode('Width: ' + Math.round(window.innerWidth * ratio) + 'px / Height: ' + Math.round(window.innerHeight * ratio) + 'px (native)')
    );
  }

  function showSystemActionResult(message, action) {
    window.showMessage(message);
    var match = String(message == null ? '' : message).match(/URL=\.\/lib\/static\/(reboot|shutdown)\.php\?lang=([a-z]{2}_[a-z]{2})/i);
    if (!match || match[1].toLowerCase() !== action) return;
    var waitPage = 'lib/static/' + match[1].toLowerCase() + '.php?lang=' + match[2].toLowerCase();
    window.setTimeout(function () { window.location.assign(waitPage); }, 2000);
  }

  window.askPialertReboot = function () {
    var config = actionConfig();
    window.showModalWarning(config.rebootTitle, config.rebootMessage, config.cancel, config.run, 'PialertReboot');
  };

  window.PialertReboot = function () {
    window.pialertPost('php/server/commands.php?action=PialertReboot', function (message) { showSystemActionResult(message, 'reboot'); });
  };

  window.askPialertShutdown = function () {
    var config = actionConfig();
    window.showModalWarning(config.shutdownTitle, config.shutdownMessage, config.cancel, config.run, 'PialertShutdown');
  };

  window.PialertShutdown = function () {
    window.pialertPost('php/server/commands.php?action=PialertShutdown', function (message) { showSystemActionResult(message, 'shutdown'); });
  };

  renderResolution();
  window.addEventListener('resize', renderResolution, { passive: true });
}(window, document));

(function (window) {
  'use strict';

  var originalBootId = window.pialertRebootBootId;
  var redirected = false;
  var checking = false;
  var CHECK_INTERVAL_MS = 5000;

  async function checkPiAlertAvailability () {
    if (checking || redirected || !originalBootId) return;
    checking = true;
    try {
      var status = await window.fetch('boot_status.php', { cache: 'no-store' });
      if (!status.ok) return;
      var current = await status.json();
      if (!current.bootId || current.bootId === originalBootId) return;

      // The web server can return before the application has finished starting.
      var app = await window.fetch('../../index.php', { cache: 'no-store' });
      if (!app.ok || !app.headers.get('content-type')?.includes('text/html')) return;
      var html = await app.text();
      if (!/<html\b/i.test(html) || !html.includes('Pi.Alert') ||
          !/id=["'](?:devices-page|dashboard-page|loginpassword)["']/i.test(html)) return;
      redirected = true;
      window.location.assign('../../');
    } catch (_) {
      // Keep waiting through temporary network or startup failures.
    } finally {
      checking = false;
    }
  }

  checkPiAlertAvailability();
  window.setInterval(checkPiAlertAvailability, CHECK_INTERVAL_MS);
})(window);

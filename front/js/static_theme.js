/* Static waiting pages cannot read the server-side UI settings while Pi.Alert
   restarts. The normal shell records its server-selected theme in localStorage. */
(function (document, window) {
  'use strict';
  try {
    var theme = window.localStorage.getItem('pialert-ui-theme');
    if (theme === 'glas' || theme === 'piano' || theme === 'console') {
      document.documentElement.setAttribute('data-pialert-theme', theme);
    } else if (window.localStorage.getItem('pialert-ui-mode') === 'dark') {
      document.documentElement.setAttribute('data-wait-mode', 'dark');
    }
  } catch (_) {
    // Keep the standard waiting-page appearance when storage is unavailable.
  }
})(document, window);

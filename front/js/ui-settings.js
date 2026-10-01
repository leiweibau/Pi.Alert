(function (document) {
  'use strict';

  var page = document.getElementById('v4-ui-settings');
  if (!page) return;

  var darkToggle = document.getElementById('ui-dark');
  var selectors = {
    sidebar: document.getElementById('ui-sidebar_color'),
    header: document.getElementById('ui-header_color')
  };
  var lightTextColors = ['light', 'info', 'warning'];

  function colorSpec (value, sidebar, dark) {
    if (value.indexOf('body') === 0) {
      return {
        className: 'bg-' + value,
        mode: sidebar && value === 'body-secondary' ? 'dark' : (dark ? 'dark' : 'light')
      };
    }
    return { className: 'text-bg-' + value, mode: lightTextColors.indexOf(value) === -1 ? 'dark' : 'light' };
  }

  function applyChrome (element, value, sidebar, dark) {
    if (!element) return;
    var previous = element.dataset.uiColorClass;
    if (previous) element.classList.remove(previous);
    else {
      Object.keys(selectors).forEach(function (kind) {
        Array.prototype.forEach.call(selectors[kind].options, function (option) {
          element.classList.remove(colorSpec(option.value, kind === 'sidebar', dark).className);
        });
      });
    }
    var spec = colorSpec(value, sidebar, dark);
    element.classList.add(spec.className);
    element.setAttribute('data-bs-theme', spec.mode);
    element.dataset.uiColorClass = spec.className;
  }

  function preview () {
    var activeTheme = document.documentElement.getAttribute('data-pialert-theme');
    var dark = ['glas', 'piano', 'console'].includes(activeTheme) || darkToggle.checked;
    document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
    var userMenu = document.querySelector('.pialert-user-menu');
    if (userMenu) userMenu.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    applyChrome(document.querySelector('.app-sidebar'), selectors.sidebar.value, true, dark);
    applyChrome(document.querySelector('.app-header'), selectors.header.value, false, dark);
    applyChrome(document.querySelector('.sidebar-brand'), selectors.header.value, false, dark);
    if (activeTheme === 'piano' || activeTheme === 'console') {
      ['.app-sidebar', '.app-header', '.sidebar-brand'].forEach(function (selector) {
        var element = document.querySelector(selector);
        if (element) element.setAttribute('data-bs-theme', 'dark');
      });
    }
    applyChrome(document.getElementById('ui-sidebar-swatch'), selectors.sidebar.value, true, dark);
    applyChrome(document.getElementById('ui-header-swatch'), selectors.header.value, false, dark);
  }

  darkToggle.addEventListener('change', preview);
  selectors.sidebar.addEventListener('change', preview);
  selectors.header.addEventListener('change', preview);

  var faviconInput = document.getElementById('ui-favicon-url');
  var faviconPreview = document.getElementById('ui-favicon-preview');
  var faviconPreviewTimer;
  function previewFavicon () {
    var value = faviconInput.value;
    var source = '';
    if (value.length <= 2048 && !/[\x00-\x20\x7f]/.test(value)) {
      if (/^img\/favicons\/(flat|glass)_(red|blue|green|yellow|purple|black|white)_(black|white)\.png$/.test(value)) {
        source = encodeURI(value);
      } else {
        try {
          var url = new URL(value);
          if ((url.protocol === 'http:' || url.protocol === 'https:') && !url.username && !url.password) {
            // Encode URL metacharacters without altering query separators or existing % escapes.
            source = url.href.replace(/[<>"'`]/g, function (character) {
              return '%' + character.charCodeAt(0).toString(16).toUpperCase();
            });
          }
        } catch (_) { source = ''; }
      }
    }
    faviconPreview.hidden = !source;
    if (source) faviconPreview.src = source;
    else faviconPreview.removeAttribute('src');
  }
  faviconInput.addEventListener('input', function () {
    window.clearTimeout(faviconPreviewTimer);
    faviconPreviewTimer = window.setTimeout(previewFavicon, 400);
  });
  faviconInput.addEventListener('change', previewFavicon);
  page.querySelectorAll('[data-favicon-value]').forEach(function (choice) {
    choice.addEventListener('click', function () {
      faviconInput.value = choice.dataset.faviconValue;
      previewFavicon();
    });
  });
})(document);

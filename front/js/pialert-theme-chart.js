(function (document, Chart) {
  'use strict';
  if (!Chart || !['glas', 'piano', 'console'].includes(document.documentElement.getAttribute('data-pialert-theme'))) return;
  var theme = document.documentElement.getAttribute('data-pialert-theme');
  Chart.defaults.color = theme === 'console' ? '#9cf7a8' : (theme === 'piano' ? '#d7dcdf' : '#d8e7f3');
  Chart.defaults.borderColor = theme === 'console' ? 'rgba(74,222,128,.14)' : (theme === 'piano' ? 'rgba(255,255,255,.12)' : 'rgba(183,217,242,.14)');
  if (theme === 'console') {
    Chart.defaults.plugins.tooltip.backgroundColor = '#050d08';
    Chart.defaults.plugins.tooltip.titleColor = '#b5ffbf';
    Chart.defaults.plugins.tooltip.bodyColor = '#9cf7a8';
    Chart.defaults.plugins.tooltip.borderColor = 'rgba(86,255,142,.68)';
    Chart.defaults.plugins.tooltip.borderWidth = 1;
  }
})(document, window.Chart);

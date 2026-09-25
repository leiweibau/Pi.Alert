(function (document, Chart) {
  'use strict';
  if (!Chart || !['glas', 'piano'].includes(document.documentElement.getAttribute('data-pialert-theme'))) return;
  var piano = document.documentElement.getAttribute('data-pialert-theme') === 'piano';
  Chart.defaults.color = piano ? '#d7dcdf' : '#d8e7f3';
  Chart.defaults.borderColor = piano ? 'rgba(255,255,255,.12)' : 'rgba(183,217,242,.14)';
})(document, window.Chart);

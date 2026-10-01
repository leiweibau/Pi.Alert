(function (window, document) {
  'use strict';
  var container = document.getElementById('service-location-map');
  if (!container || !window.jsVectorMap) return;

  var code = container.dataset.countryCode;
  var validCode = /^[A-Z]{2}$/.test(code);
  var map = null;
  var observer = null;
  var frame = null;
  var width = 0;
  var height = 0;
  var names = null;
  try {
    names = new Intl.DisplayNames([container.dataset.locale || 'en'], { type: 'region' });
  } catch (_error) { /* The selected country name is also supplied by GeoLite. */ }

  function render() {
    frame = null;
    // A saved tab can initially hide the Details panel. Wait for real dimensions.
    if (!container.clientWidth || !container.clientHeight) return;
    if (map) {
      if (width !== container.clientWidth || height !== container.clientHeight) map.updateSize();
    } else {
      map = new window.jsVectorMap({
        selector: '#service-location-map', map: 'world', backgroundColor: 'transparent',
        selectedRegions: validCode ? [code] : [], regionsSelectable: false,
        zoomButtons: false, zoomOnScroll: false, zoomAnimate: false,
        draggable: false, bindTouchEvents: false,
        regionStyle: {
          initial: { fill: 'var(--service-map-land)', stroke: 'var(--service-map-border)', strokeWidth: .6 },
          hover: { fillOpacity: 1, cursor: 'default' },
          selected: { fill: 'var(--service-map-highlight)' },
          selectedHover: { fill: 'var(--service-map-highlight)' }
        },
        onRegionTooltipShow: function (_event, tooltip, regionCode) {
          var label = regionCode === code && container.dataset.countryName ? container.dataset.countryName : tooltip.text();
          if (names && regionCode !== code) {
            try { label = names.of(regionCode) || label; } catch (_error) { /* Use the map's name. */ }
          }
          tooltip.text(label);
          tooltip.getElement().classList.add('service-location-tooltip');
        }
      });
      var hasRegion = map.getSelectedRegions().indexOf(code) !== -1;
      document.getElementById('service-location-key').hidden = !hasRegion;
      document.getElementById('service-location-map-missing').hidden = !container.dataset.countryName || hasRegion;
    }
    width = container.clientWidth;
    height = container.clientHeight;
  }

  function schedule() {
    if (frame === null) frame = window.requestAnimationFrame(render);
  }

  function start() {
    schedule();
    document.getElementById('tabDetails').addEventListener('shown.bs.tab', schedule);
    if (window.ResizeObserver) {
      observer = new ResizeObserver(schedule);
      observer.observe(container);
    } else window.addEventListener('resize', schedule);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
  else start();
  window.addEventListener('pagehide', function (event) {
    if (event.persisted) return;
    if (frame !== null) window.cancelAnimationFrame(frame);
    if (observer) observer.disconnect();
    window.removeEventListener('resize', schedule);
    document.getElementById('tabDetails').removeEventListener('shown.bs.tab', schedule);
    if (map) map.destroy();
  });
})(window, document);

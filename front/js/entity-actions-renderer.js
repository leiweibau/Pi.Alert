(function (window, document) {
  'use strict';
  var families = {bi:'bi', 'fa-solid':'fa-solid', 'fa-regular':'fa-regular', 'fa-brands':'fa-brands', ion:'ion', mdi:'mdi'};
  var known = null;
  var verifying = null;
  function iconClass(id) {
    var match = /^([a-z-]+):([a-z0-9-]+)$/.exec(String(id || ''));
    if (!match || !families[match[1]] || (known && !known.has(id))) return 'bi bi-link-45deg';
    return families[match[1]] + ' ' + ({bi:'bi-', ion:'ion-', mdi:'mdi-', 'fa-solid':'fa-', 'fa-regular':'fa-', 'fa-brands':'fa-'}[match[1]]) + match[2];
  }
  function safeUrl(value) {
    var raw = String(value || '');
    if (!/^https?:\/\//i.test(raw) || /[\u0000-\u001f\u007f]/.test(raw)) return '';
    try { var url = new URL(raw); return /^(http:|https:)$/.test(url.protocol) && url.username === '' && url.password === '' ? raw : ''; }
    catch (_error) { return ''; }
  }
  function safeColor(value) {
    var color = String(value || '').trim().toLowerCase();
    return /^#[0-9a-f]{6}$/.test(color) ? color : '#6c757d';
  }
  function contrastColor(color) {
    var hex = safeColor(color).slice(1);
    var channels = [0, 2, 4].map(function (offset) {
      var channel = parseInt(hex.slice(offset, offset + 2), 16) / 255;
      return channel <= 0.03928 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4);
    });
    return (0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]) > 0.179 ? '#111111' : '#ffffff';
  }
  function applyColor(button, color, textColor) {
    var background = safeColor(color);
    var foreground = String(textColor || '').trim().toLowerCase();
    if (!/^#[0-9a-f]{6}$/.test(foreground)) foreground = contrastColor(background);
    button.style.backgroundColor = background;
    button.style.borderColor = background;
    button.style.color = foreground;
  }
  function append(container, actions, deleteButton) {
    var group = document.createElement('div'); group.className = 'entity-actions-buttons';
    (Array.isArray(actions) ? actions : []).slice(0, 3).forEach(function (action) {
      var url = safeUrl(action.url); if (!url) return;
      var link = document.createElement('a'); link.className = 'btn btn-sm btn-outline-secondary entity-action-link';
      link.href = url; link.target = '_blank'; link.rel = 'noopener noreferrer';
      link.title = String(action.label || url); link.setAttribute('aria-label', link.title);
      applyColor(link, action.color, action.text_color);
      var icon = document.createElement('i'); icon.className = iconClass(action.icon_id); icon.dataset.actionIconId = String(action.icon_id || ''); icon.setAttribute('aria-hidden', 'true'); link.append(icon); group.append(link);
    });
    if (deleteButton) { deleteButton.classList.add('entity-action-delete'); group.append(deleteButton); }
    container.replaceChildren(group);
    return group;
  }
  function verifyIcons() {
    if (verifying) return verifying;
    verifying = fetch('data/action-icon-ids.json', {credentials:'same-origin'}).then(function (response) { if (!response.ok) throw new Error('icons'); return response.json(); })
      .then(function (ids) { known = new Set(Array.isArray(ids) ? ids : []); document.querySelectorAll('[data-action-icon-id]').forEach(function (icon) { icon.className = iconClass(icon.dataset.actionIconId); }); })
      .catch(function () { /* The built-in link icon already handles unknown ID formats. */ });
    return verifying;
  }
  window.pialertEntityActions = { append: append, iconClass: iconClass, safeUrl: safeUrl, safeColor: safeColor, contrastColor: contrastColor, applyColor: applyColor, verifyIcons: verifyIcons, isKnown: function (id) { return !known || known.has(id); } };
})(window, document);

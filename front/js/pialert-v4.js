'use strict';

document.addEventListener('click', function (event) {
  const button = event.target.closest('[data-pialert-toggle="password-info"]');
  if (!button) return;

  const panel = document.getElementById('password-info');
  if (!panel) return;

  const willShow = panel.classList.contains('d-none');
  panel.classList.toggle('d-none', !willShow);
  button.setAttribute('aria-expanded', willShow ? 'true' : 'false');
});

// Keep the legacy shortcut keys for pages that already have a v4 equivalent.
// Add further destinations only when their v4 page has passed integration.
const pialertV4Shortcuts = Object.freeze({
  '0': { url: './maintenance.php', label: 'V4_Shortcut_Settings' },
  '1': { url: './devices.php', label: 'V4_Shortcut_Devices' },
  '2': { url: './services.php', label: 'V4_Shortcut_Services' },
  '3': { url: './icmpmonitor.php', label: 'V4_Shortcut_ICMP' },
  'd': { url: './dashboard.php', label: 'V4_Dashboard' },
  'e': { url: './devicesEvents.php', label: 'V4_Shortcut_Events' },
  'j': { url: './journal.php', label: 'V4_Shortcut_Journal' },
  'r': { url: './reports.php', label: 'V4_Shortcut_Reports' },
  's': { url: './systeminfo.php', label: 'V4_System_Info' },
});

document.addEventListener('keydown', function (event) {
  const active = document.activeElement;
  if (active && (['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName) || active.isContentEditable)) return;
  if (event.repeat || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey) return;
  const destination = pialertV4Shortcuts[event.key.toLowerCase()];
  if (!destination) return;
  event.preventDefault();
  window.location.assign(destination.url);
});

document.addEventListener('DOMContentLoaded', function () {
  const help = document.getElementById('navbar-help-button');
  if (!help) return;
  help.title = window.pialertV4Text('V4_Hotkeys') + ':\n' + Object.entries(pialertV4Shortcuts)
    .map(([key, destination]) => `${key.toUpperCase()} – ${window.pialertV4Text(destination.label)}`)
    .join('\n');
});

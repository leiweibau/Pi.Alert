document.addEventListener('keydown', function (event) {
    const active = document.activeElement;
    if (active && (
        active.tagName === 'INPUT' ||
        active.tagName === 'TEXTAREA' ||
        active.isContentEditable
    )) {
        return;
    }
    if (event.repeat) {
        return;
    }
    if (event.ctrlKey || event.metaKey || event.altKey || event.shiftKey) {
        return;
    }
    const shortcuts = {
        'd': './dashboard.php',
        '1': './devices.php',
        '2': './services.php',
        '3': './icmpmonitor.php',
        'e': './devicesEvents.php',
        'j': './journal.php',
        'r': './reports.php',
        's': './systeminfo.php'
    };
    const key = event.key.toLowerCase();
    if (shortcuts[key]) {
        event.preventDefault();
        window.location.href = shortcuts[key];
    }
});

function buildHotkeyTooltip(hotkeys) {
    return 'Hotkeys:\n' + Object.entries(hotkeys)
        .map(([key, label]) => `${key} – ${label}`)
        .join('\n');
}

document.addEventListener('DOMContentLoaded', function () {
    const helpBtn = document.getElementById('navbar-help-button');
    if (!helpBtn) return;

    helpBtn.title = buildHotkeyTooltip({
        '1': 'Devices',
        '2': 'Services',
        '3': 'ICMP Monitor',
        'D': 'Dashboard',
        'E': 'Events',
        'J': 'Journal',
        'R': 'Reports',
        'S': 'System Info'
    });
});

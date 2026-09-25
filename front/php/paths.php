<?php

function pialert_v4_route(string $name): string {
    static $routes = array(
        'login' => './index.php',
        'home' => './devices.php',
        'dashboard' => './dashboard.php',
        'presence' => './presence.php',
        'network' => './network.php',
        'network_settings' => './networkSettings.php',
        'maintenance' => './maintenance.php',
        'device_details' => './deviceDetails.php',
        'services' => './services.php',
        'icmp' => './icmpmonitor.php',
        'icmp_details' => './icmpmonitorDetails.php',
        'ui_settings' => './ui_settings.php',
        'events' => './devicesEvents.php',
        'journal' => './journal.php',
        'reports' => './reports.php',
        'systeminfo' => './systeminfo.php',
        'updatecheck' => './updatecheck.php',
    );

    if (!isset($routes[$name])) {
        throw new InvalidArgumentException('Unknown v4 route');
    }

    return $routes[$name];
}

function pialert_v4_asset(string $path): string {
    $path = ltrim(str_replace('\\', '/', $path), '/');
    if ($path === '' || str_contains($path, '..') || preg_match('/[\x00-\x1f\x7f]/', $path)) {
        throw new InvalidArgumentException('Invalid v4 asset path');
    }

    return './' . $path;
}

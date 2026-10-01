<?php

// Public app metadata must also be available before login. Relative URLs keep
// each installation in its own scope, including installations under /pialert/.
const PIALERT_V4_FRONT_ROOT = __DIR__;
require_once __DIR__ . '/php/ui-settings.php';

$favicon = pialert_v4_ui_read()['appearance']['favicon'];
$icon = array('src' => $favicon, 'purpose' => 'any');
if (str_starts_with($favicon, 'img/favicons/')) {
    $size = @getimagesize(__DIR__ . '/' . $favicon);
    if (is_array($size)) {
        $icon['sizes'] = $size[0] . 'x' . $size[1];
        $icon['type'] = $size['mime'];
    }
}

header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: no-cache');
echo json_encode(array(
    'id' => './',
    'name' => 'Pi.Alert',
    'short_name' => 'Pi.Alert',
    'start_url' => './index.php',
    'scope' => './',
    'display' => 'standalone',
    'icons' => array($icon),
), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

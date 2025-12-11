<?php
// Token-Validierung
// $expectedToken = @file_get_contents('/opt/pialert/front/php/tmp/reboot_token');
$expectedToken = null;
$foundPath = null;

$paths = [
    '/opt/pialert/front/php/tmp/reboot_token',
    '/tmp/reboot_token'
];

foreach ($paths as $path) {
    if (is_readable($path)) {
        $expectedToken = trim(@file_get_contents($path));
        $foundPath = $path;
        break;
    }
}

$passedToken = $argv[1] ?? '';

if (trim($passedToken) !== trim($expectedToken)) {
    file_put_contents('/opt/pialert/front/php/tmp/reboot_denied.log', "Unauthorized attempt: " . date('c') . "\n", FILE_APPEND);
    exit(1);
}

// unlink('/opt/pialert/front/php/tmp/reboot_token');
if ($foundPath !== null) {
    @unlink($foundPath);

sleep(5);
exec('sudo /usr/sbin/shutdown -r now');
?>

}

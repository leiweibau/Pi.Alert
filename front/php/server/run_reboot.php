<?php
// Token-Validierung
$expectedToken = @file_get_contents('/tmp/reboot_token');
$passedToken = $argv[1] ?? '';

if (trim($passedToken) !== trim($expectedToken)) {
    file_put_contents('/tmp/reboot_denied.log', "Unauthorized attempt: " . date('c') . "\n", FILE_APPEND);
    exit(1);
}

unlink('/tmp/reboot_token');

sleep(5);
exec('sudo /usr/sbin/shutdown -r now');
?>

<?php
// Token-Validierung
$expectedToken = @file_get_contents('/tmp/shutdown_token');
$passedToken = $argv[1] ?? '';

if (trim($passedToken) !== trim($expectedToken)) {
    file_put_contents('/tmp/shutdown_denied.log', "Unauthorized attempt: " . date('c') . "\n", FILE_APPEND);
    exit(1);
}

unlink('/tmp/shutdown_token');

sleep(5);
exec('sudo /usr/sbin/shutdown -h now');
?>

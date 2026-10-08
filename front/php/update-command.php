<?php

// Shared by the saved release notes and the interactive update check.
// Keep the command as plain text; each view escapes it for its HTML attribute.
function pialert_update_instructions(): array {
    return array(
        'command' => 'sudo bash -c "$(curl -fsSL https://github.com/leiweibau/Pi.Alert/raw/main/install/pialert_update.sh)" -s',
    );
}

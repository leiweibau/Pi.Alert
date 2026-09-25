<?php

const PIALERT_V4_FRONT_ROOT = __DIR__ . '/..';

// Legacy helpers use paths relative to front/. Keep their established runtime
// context while all v4-owned includes themselves remain absolute.
chdir(PIALERT_V4_FRONT_ROOT);

require_once PIALERT_V4_FRONT_ROOT . '/php/server/session.php';
require_once PIALERT_V4_FRONT_ROOT . '/php/server/csrf.php';
require_once PIALERT_V4_FRONT_ROOT . '/php/server/util.php';
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/ui-settings.php';

function pialert_v4_start_session(): void {
    pialert_start_session();
}

function pialert_v4_language_file(): string {
    $language = pialert_v4_ui_read()['appearance']['language'];
    $file = PIALERT_V4_FRONT_ROOT . '/php/language/' . $language . '.php';
    return is_file($file) ? $file : PIALERT_V4_FRONT_ROOT . '/php/language/en_us.php';
}

// Load English first and overlay the selected language. Each legacy language
// file starts by unsetting its own arrays, so both files must be evaluated in
// separate scopes before merging. This also covers direct $pia_lang lookups.
function pialert_v4_load_language(): void {
    global $pia_lang, $pia_journ_lang;
    $load = static function (string $file): array {
        $pia_lang = $pia_journ_lang = array();
        require $file;
        return array($pia_lang, $pia_journ_lang);
    };
    [$english, $englishJournal] = $load(PIALERT_V4_FRONT_ROOT . '/php/language/en_us.php');
    $selected = pialert_v4_language_file();
    if ($selected === PIALERT_V4_FRONT_ROOT . '/php/language/en_us.php') {
        $pia_lang = $english;
        $pia_journ_lang = $englishJournal;
        return;
    }
    [$translated, $translatedJournal] = $load($selected);
    $pia_lang = array_replace($english, $translated);
    $pia_journ_lang = array_replace($englishJournal, $translatedJournal);
}

function pialert_v4_asset_version(): string {
    $config = @parse_ini_file(PIALERT_V4_FRONT_ROOT . '/../config/version.conf');
    // Bust cached frontend assets after UI behavior updates.
    return (is_array($config) ? (string) ($config['VERSION_DATE'] ?? '') : '') . '-chart451-icmpcols5-statusbadgesall-font09-favstar7rem-activity150-actionscoloris2-nmapcards-nowrap-icmpnav-labelgrid-switchgrid-restoreclose-calendarfirstevents-bulkalertgradient-bulkmqtt-icmpunsaved-headericons-frontlang1-mainttabs1-serviceedit1-journalicon1-historyjson1-faviconjson1-copyfallback1';
}

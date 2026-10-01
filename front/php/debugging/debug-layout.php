<?php

define('PIALERT_V4_PUBLIC_ENTRY', true);
require_once __DIR__ . '/../bootstrap.php';
pialert_v4_start_session();
if (($_SESSION['login'] ?? 0) != 1) {
    header('Location: ../../index.php');
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Method Not Allowed');
}
pialert_v4_load_language();
$debugUiLabels = array(
    'en_us' => array(
        'V4_Debug_JSON_Intro' => 'Check the JSON endpoints used by the interface. Results are grouped by feature.',
        'V4_Debug_Tables_Intro' => 'Inspect stored device and ICMP records. Select a table and search its rows.',
        'V4_Debug_Languages_Intro' => 'Compare translation keys across installed languages and review values still shown in English.',
        'V4_Debug_Run_Again' => 'Run again',
        'V4_Debug_Checked' => 'Checked',
        'V4_Debug_Progress' => 'Test progress',
        'V4_Debug_Failures_Only' => 'Show failures only',
        'V4_Debug_Search_Rows' => 'Search visible table',
        'V4_Debug_Table_Error' => 'The table could not be loaded.',
        'V4_Debug_Table_Hint' => 'Scroll horizontally to inspect all columns.',
        'V4_Debug_Languages' => 'Languages',
        'V4_Debug_Search_Languages' => 'Search languages or keys',
        'V4_Debug_Only_Missing' => 'Only languages with missing keys',
        'V4_Debug_No_Missing' => 'No missing keys',
        'V4_Debug_No_Failures' => 'No failed checks.',
        'V4_Debug_English_Fallbacks' => 'English placeholders',
        'V4_Debug_Fallback_Intro' => 'These keys exist, but their values still use English. Replace the marked values in the language files to complete the translations.',
        'V4_Debug_All_Keys_Present' => 'All language keys are present.',
    ),
    'de_de' => array(
        'V4_Debug_JSON_Intro' => 'Prüft die JSON-Schnittstellen der Oberfläche. Die Ergebnisse sind nach Funktionen gruppiert.',
        'V4_Debug_Tables_Intro' => 'Gespeicherte Geräte- und ICMP-Datensätze ansehen. Tabelle auswählen und Zeilen durchsuchen.',
        'V4_Debug_Languages_Intro' => 'Übersetzungsschlüssel aller installierten Sprachen vergleichen und noch englische Texte erkennen.',
        'V4_Debug_Run_Again' => 'Erneut prüfen',
        'V4_Debug_Checked' => 'Geprüft',
        'V4_Debug_Progress' => 'Prüffortschritt',
        'V4_Debug_Failures_Only' => 'Nur Fehler anzeigen',
        'V4_Debug_Search_Rows' => 'Sichtbare Tabelle durchsuchen',
        'V4_Debug_Table_Error' => 'Die Tabelle konnte nicht geladen werden.',
        'V4_Debug_Table_Hint' => 'Horizontal scrollen, um alle Spalten zu sehen.',
        'V4_Debug_Languages' => 'Sprachen',
        'V4_Debug_Search_Languages' => 'Sprachen oder Schlüssel suchen',
        'V4_Debug_Only_Missing' => 'Nur Sprachen mit fehlenden Schlüsseln',
        'V4_Debug_No_Missing' => 'Keine Schlüssel fehlen',
        'V4_Debug_No_Failures' => 'Keine fehlgeschlagenen Prüfungen.',
        'V4_Debug_English_Fallbacks' => 'Englische Platzhalter',
        'V4_Debug_Fallback_Intro' => 'Diese Schlüssel sind vorhanden, ihre Werte stehen aber noch auf Englisch. Ersetze die markierten Werte in den Sprachdateien, um die Übersetzung abzuschließen.',
        'V4_Debug_All_Keys_Present' => 'Alle Sprachschlüssel sind vorhanden.',
    ),
);
$debugLocale = pathinfo(pialert_v4_language_file(), PATHINFO_FILENAME);
$pia_lang = array_replace($pia_lang, $debugUiLabels[$debugLocale] ?? $debugUiLabels['en_us']);
require_once __DIR__ . '/../header_func.php';
require_once __DIR__ . '/../shell.php';

function pialert_debug_label(string $key, string $fallback): string {
    global $pia_lang;
    return (string) ($pia_lang[$key] ?? $fallback);
}

function pialert_debug_start(string $title, string $active, string $description): void {
    pialert_v4_shell_start($title, 'maintenance', array('css/debugging.css'), null, '../../');
    $pages = array(
        array('json', 'test_json_calls.php', 'V4_Test_JSON_Calls', 'JSON calls', 'fa-code'),
        array('tables', 'test_main_tables_rawcontent.php', 'V4_Raw_Device_Tables', 'Tables', 'fa-table'),
        array('languages', 'validate_languages.php', 'V4_Compare_Languages', 'Languages', 'fa-language'),
    );
    ?>
    <div class="debug-page">
      <div class="debug-page-intro d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <p class="text-body-secondary mb-0"><?= h($description); ?></p>
        <a class="btn btn-sm btn-outline-secondary pialert-back-link" href="maintenance.php?tab=1"><i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i><?= h(pialert_debug_label('NAV_Maintenance', 'Maintenance')); ?></a>
      </div>
      <nav class="debug-page-nav mb-4" aria-label="<?= h(pialert_debug_label('V4_Debugging', 'Debugging')); ?>">
        <?php foreach ($pages as [$id, $file, $key, $fallback, $icon]): ?>
        <a class="debug-page-nav-link<?= $active === $id ? ' active' : ''; ?>" href="php/debugging/<?= h($file); ?>"<?= $active === $id ? ' aria-current="page"' : ''; ?>><i class="fa-solid <?= h($icon); ?>" aria-hidden="true"></i><span><?= h(pialert_debug_label($key, $fallback)); ?></span></a>
        <?php endforeach; ?>
      </nav>
    <?php
}

function pialert_debug_end(array $scripts = array()): void {
    echo '</div>';
    pialert_v4_shell_end($scripts);
}

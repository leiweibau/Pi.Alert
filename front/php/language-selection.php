<?php

/** Select the UI language without bootstrapping a frontend page or session. */
function pialert_selected_language(?string $configDir = null): string {
    $configDir ??= dirname(__DIR__, 2) . '/config';
    $languageDir = __DIR__ . '/language';
    $valid = static function ($candidate) use ($languageDir): bool {
        return is_string($candidate)
            && preg_match('/^[a-z]{2}_[a-z]{2}$/D', $candidate) === 1
            && is_file($languageDir . '/' . $candidate . '.php');
    };

    $personal = $configDir . '/setting_ui_v4.json';
    $path = file_exists($personal) || is_link($personal)
        ? $personal
        : $configDir . '/setting_ui_v4.default.json';
    $raw = is_link($path) ? false : @file_get_contents($path);
    if (is_string($raw)) {
        $settings = json_decode($raw, true);
        $selected = $settings['appearance']['language'] ?? null;
        if ($valid($selected)) return $selected;
    }
    return 'en_us';
}

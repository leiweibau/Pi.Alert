<?php

// Personal UI preferences are stored in JSON. Until the first save, the
// packaged default file is read without importing legacy marker files.
function pialert_v4_ui_path(): string {
    return defined('PIALERT_V4_UI_SETTINGS_PATH') ? PIALERT_V4_UI_SETTINGS_PATH : PIALERT_V4_FRONT_ROOT . '/../config/setting_ui_v4.json';
}

function pialert_v4_ui_default_path(): string {
    return defined('PIALERT_V4_UI_DEFAULT_PATH') ? PIALERT_V4_UI_DEFAULT_PATH : dirname(pialert_v4_ui_path()) . '/setting_ui_v4.default.json';
}

function pialert_v4_device_columns(): array {
    // The order is the existing devices.php AJAX row order. Keep IDs stable;
    // indexes are derived here for the table, settings form and DataTables.
    return array(
        'Name'=>array('label'=>'Device_TableHead_Name','fallback'=>'Name'),
        'ConnectionType'=>array('label'=>'Device_TableHead_ConnectionType','fallback'=>'Connection type','configurable'=>true),
        'Owner'=>array('label'=>'Device_TableHead_Owner','fallback'=>'Owner','configurable'=>true),
        'Type'=>array('label'=>'Device_TableHead_Type','fallback'=>'Type','configurable'=>true),
        'Favorites'=>array('label'=>'Device_TableHead_Favorite','fallback'=>'Favorite','configurable'=>true),
        'Group'=>array('label'=>'Device_TableHead_Group','fallback'=>'Group','configurable'=>true),
        'Location'=>array('label'=>'Device_TableHead_Location','fallback'=>'Location','configurable'=>true),
        'FirstSession'=>array('label'=>'Device_TableHead_FirstSession','fallback'=>'First session','configurable'=>true),
        'LastSession'=>array('label'=>'Device_TableHead_LastSession','fallback'=>'Last session','configurable'=>true),
        'LastIP'=>array('label'=>'Device_TableHead_LastIP','fallback'=>'Last IP','configurable'=>true),
        'MACType'=>array('label'=>'Device_TableHead_MAC','fallback'=>'MAC type','configurable'=>true),
        'MACAddress'=>array('label'=>'Device_TableHead_MACaddress','fallback'=>'MAC address','configurable'=>true),
        'MACVendor'=>array('label'=>'DevDetail_MainInfo_Vendor','fallback'=>'Vendor','configurable'=>true),
        'Status'=>array('label'=>'Device_TableHead_Status','fallback'=>'Status'),
        'LastIPOrder'=>array('label'=>'Device_TableHead_LastIPOrder','fallback'=>'IP order','internal'=>true),
        'ScanSource'=>array('label'=>null,'fallback'=>'ScanSource','internal'=>true),
        'Rowid'=>array('label'=>'Device_TableHead_Rowid','fallback'=>'Row ID','internal'=>true),
        'WakeOnLAN'=>array('label'=>'Device_TableHead_WakeOnLAN','fallback'=>'Wake on LAN','configurable'=>true),
        'NmapQueue'=>array('label'=>null,'fallback'=>'NmapQueue','internal'=>true),
        'Actions'=>array('label'=>'MT_SET_SatEdit_FORM_Action','fallback'=>'Actions','configurable'=>true),
    );
}

function pialert_v4_icmp_columns(): array {
    // Mirrors the unchanged getDevicesList() JSON row plus its UI-only action cell.
    return array(
        'Name'=>array('label'=>'Device_TableHead_Name','fallback'=>'Name'),
        'IP'=>array('label'=>null,'fallback'=>'IP','configurable'=>true),
        'Favorite'=>array('label'=>'Device_TableHead_Favorite','fallback'=>'Favorite','configurable'=>true),
        'ResponseTime'=>array('label'=>'WEBS_EVE_TableHead_ResponsTime','fallback'=>'Response time','configurable'=>true),
        'ScanTime'=>array('label'=>'WEBS_tablehead_ScanTime','fallback'=>'Scan time','configurable'=>true),
        'Status'=>array('label'=>'Device_TableHead_Status','fallback'=>'Status'),
        'AlertDown'=>array('label'=>null,'fallback'=>'AlertDown','internal'=>true),
        'StatusCode'=>array('label'=>null,'fallback'=>'StatusCode','internal'=>true),
        'Rowid'=>array('label'=>null,'fallback'=>'RowID','internal'=>true),
        'Actions'=>array('label'=>'MT_SET_SatEdit_FORM_Action','fallback'=>'Actions','configurable'=>true),
    );
}

function pialert_v4_ui_plain_label(string $label): string {
    // Preserve legacy spacing/hyphenation while keeping v4 text safely escaped.
    return strtr($label, array('&nbsp;'=>"\u{00A0}", '&shy;'=>"\u{00AD}"));
}

function pialert_v4_ui_favicon_options(): array {
    $options = array();
    foreach (glob(PIALERT_V4_FRONT_ROOT . '/img/favicons/*.png') ?: array() as $file) {
        $name = basename($file);
        if (preg_match('/\A(?:flat|glass)_(?:red|blue|green|yellow|purple|black|white)_(?:black|white)\.png\z/D', $name)) {
            $options[] = 'img/favicons/' . $name;
        }
    }
    sort($options);
    return $options;
}

function pialert_v4_ui_valid_favicon($value): bool {
    if (!is_string($value) || strlen($value) > 2048 || preg_match('/[\x00-\x20\x7f]/', $value)) return false;
    if (in_array($value, pialert_v4_ui_favicon_options(), true)) return true;
    return filter_var($value, FILTER_VALIDATE_URL) !== false
        && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), array('http', 'https'), true)
        && parse_url($value, PHP_URL_USER) === null
        && parse_url($value, PHP_URL_PASS) === null;
}

function pialert_v4_ui_defaults(): array {
    return array(
        'schema'=>8,
        'revision'=>0,
        'devices'=>array(
            'columns'=>array('ConnectionType'=>false,'Owner'=>true,'Type'=>true,'Favorites'=>true,'Group'=>true,'Location'=>false,'FirstSession'=>true,'LastSession'=>true,'LastIP'=>true,'MACType'=>true,'MACAddress'=>false,'MACVendor'=>true,'WakeOnLAN'=>false,'Actions'=>true),
            'page_length'=>10,
            'order'=>array(array('Type','desc'),array('Name','asc')),
        ),
        'icmp'=>array(
            'columns'=>array('IP'=>true,'Favorite'=>true,'ResponseTime'=>true,'ScanTime'=>true,'Actions'=>true),
            'page_length'=>10,
            'order'=>array(array('Name','asc')),
        ),
        'appearance'=>array(
            'language'=>'en_us','skin'=>'skin-blue','theme'=>'standard','dark_mode'=>false,'activity_history'=>true,
            'favicon'=>'img/favicons/flat_blue_white.png',
            'sidebar_color'=>'body-secondary','header_color'=>'body','pihole_url'=>'',
            'header_widgets'=>array(
                'devices'=>array('all'=>true,'con'=>true,'fav'=>true,'dnw'=>true,'arc'=>true,'new'=>true),
                'icmp'=>array('all'=>true,'con'=>true,'fav'=>true,'dnw'=>true,'arc'=>true),
                'presence'=>array('all'=>true,'con'=>true,'fav'=>true,'dnw'=>true,'arc'=>true,'new'=>true),
            ),
        ),
    );
}

function pialert_v4_ui_skin_options(): array { return array('skin-black','skin-black-light','skin-blue','skin-blue-light','skin-green','skin-green-light','skin-purple','skin-purple-light','skin-red','skin-red-light','skin-yellow','skin-yellow-light'); }

function pialert_v4_ui_theme_options(): array { return array('standard', 'glas', 'piano'); }

function pialert_v4_ui_color_options(): array {
    // Keep neutral defaults, but offer only the AdminLTE palette for accents.
    return array(
        'Neutral'=>array('body'=>'Body','body-secondary'=>'Body secondary','body-tertiary'=>'Body tertiary','light'=>'Light','dark'=>'Dark'),
        'AdminLTE 4'=>array('orange'=>'Orange','amber'=>'Amber','olive'=>'Olive','teal'=>'Teal','sky'=>'Sky','indigo'=>'Indigo','violet'=>'Violet','fuchsia'=>'Fuchsia','pink'=>'Pink','navy'=>'Navy','steel'=>'Steel','slate'=>'Slate','graphite'=>'Graphite','midnight'=>'Midnight'),
    );
}

function pialert_v4_ui_legacy_colors(): array {
    // Existing v4 configurations may still contain one of these values.
    return array('primary'=>'Primary','secondary'=>'Secondary','success'=>'Success','info'=>'Info','warning'=>'Warning','danger'=>'Danger');
}

function pialert_v4_ui_color_names(): array {
    return array_merge(array_merge(...array_values(pialert_v4_ui_color_options())), pialert_v4_ui_legacy_colors());
}

function pialert_v4_ui_chrome_color(string $color, bool $dark, bool $sidebar): array {
    if (!array_key_exists($color, pialert_v4_ui_color_names())) throw new InvalidArgumentException('Invalid chrome colour');
    if (str_starts_with($color, 'body')) {
        return array('class'=>'bg-' . $color, 'mode'=>$sidebar && $color === 'body-secondary' ? 'dark' : ($dark ? 'dark' : 'light'));
    }
    $lightText = !in_array($color, array('light','info','warning'), true);
    return array('class'=>'text-bg-' . $color, 'mode'=>$lightText ? 'dark' : 'light');
}

function pialert_v4_ui_language_options(): array {
    $result = array();
    foreach (glob(PIALERT_V4_FRONT_ROOT . '/php/language/*.php') ?: array() as $path) {
        $name = pathinfo($path, PATHINFO_FILENAME);
        if (preg_match('/^[a-z]{2}_[a-z]{2}$/D', $name)) $result[] = $name;
    }
    return $result;
}

function pialert_v4_ui_exact_keys(array $value, array $keys): bool {
    $actual = array_keys($value);
    sort($actual); sort($keys);
    return $actual === $keys;
}

function pialert_v4_ui_validate(array $data): array {
    $defaults = pialert_v4_ui_defaults();
    if (!pialert_v4_ui_exact_keys($data, array_keys($defaults)) || $data['schema'] !== 8 || !is_int($data['revision']) || $data['revision'] < 0 || !is_array($data['devices']) || !is_array($data['icmp']) || !is_array($data['appearance'])) throw new InvalidArgumentException('Invalid UI settings schema');
    $devices = $data['devices']; $icmp = $data['icmp']; $appearance = $data['appearance'];
    if (!pialert_v4_ui_exact_keys($devices, array_keys($defaults['devices'])) || !is_array($devices['columns']) || !pialert_v4_ui_exact_keys($devices['columns'], array_keys($defaults['devices']['columns']))) throw new InvalidArgumentException('Invalid device preferences');
    foreach ($devices['columns'] as $visible) if (!is_bool($visible)) throw new InvalidArgumentException('Invalid column visibility');
    if (!in_array($devices['page_length'], array(-1,10,25,50,100,500), true)) throw new InvalidArgumentException('Invalid page length');
    if (!is_array($devices['order']) || count($devices['order']) < 1 || count($devices['order']) > 3 || !array_is_list($devices['order'])) throw new InvalidArgumentException('Invalid sort order');
    $seen = array(); $columnIds = array_keys(pialert_v4_device_columns());
    foreach ($devices['order'] as $entry) {
        if (!is_array($entry) || !array_is_list($entry) || count($entry) !== 2 || !is_string($entry[0]) || !in_array($entry[0], $columnIds, true) || in_array($entry[0], array('WakeOnLAN','Actions'), true) || !in_array($entry[1], array('asc','desc'), true) || isset($seen[$entry[0]])) throw new InvalidArgumentException('Invalid sort column');
        $seen[$entry[0]] = true;
    }
    if (!pialert_v4_ui_exact_keys($icmp, array_keys($defaults['icmp'])) || !is_array($icmp['columns']) || !pialert_v4_ui_exact_keys($icmp['columns'], array_keys($defaults['icmp']['columns']))) throw new InvalidArgumentException('Invalid ICMP preferences');
    foreach ($icmp['columns'] as $visible) if (!is_bool($visible)) throw new InvalidArgumentException('Invalid ICMP column visibility');
    if (!in_array($icmp['page_length'], array(-1,10,25,50,100,500), true)) throw new InvalidArgumentException('Invalid ICMP page length');
    if (!is_array($icmp['order']) || count($icmp['order']) < 1 || count($icmp['order']) > 3 || !array_is_list($icmp['order'])) throw new InvalidArgumentException('Invalid ICMP sort order');
    $seen = array(); $icmpColumns = pialert_v4_icmp_columns(); $icmpColumnIds = array_keys($icmpColumns);
    foreach ($icmp['order'] as $entry) {
        if (!is_array($entry) || !array_is_list($entry) || count($entry) !== 2 || !is_string($entry[0]) || !in_array($entry[0], $icmpColumnIds, true) || !empty($icmpColumns[$entry[0]]['internal']) || $entry[0] === 'Actions' || !in_array($entry[1], array('asc','desc'), true) || isset($seen[$entry[0]])) throw new InvalidArgumentException('Invalid ICMP sort column');
        $seen[$entry[0]] = true;
    }
    if (!pialert_v4_ui_exact_keys($appearance, array_keys($defaults['appearance']))) throw new InvalidArgumentException('Invalid appearance preferences');
    if (!is_string($appearance['language']) || !in_array($appearance['language'], pialert_v4_ui_language_options(), true)) throw new InvalidArgumentException('Invalid language');
    if (!is_string($appearance['skin']) || !in_array($appearance['skin'], pialert_v4_ui_skin_options(), true)) throw new InvalidArgumentException('Invalid skin');
    if (!is_string($appearance['theme']) || !in_array($appearance['theme'], pialert_v4_ui_theme_options(), true)) throw new InvalidArgumentException('Invalid theme');
    if (!is_bool($appearance['dark_mode'])) throw new InvalidArgumentException('Invalid dark mode');
    if (!is_bool($appearance['activity_history'])) throw new InvalidArgumentException('Invalid activity history setting');
    if (!pialert_v4_ui_valid_favicon($appearance['favicon'])) throw new InvalidArgumentException('Invalid favicon');
    if (!is_string($appearance['sidebar_color']) || !array_key_exists($appearance['sidebar_color'], pialert_v4_ui_color_names()) || !is_string($appearance['header_color']) || !array_key_exists($appearance['header_color'], pialert_v4_ui_color_names())) throw new InvalidArgumentException('Invalid chrome colour');
    $url = $appearance['pihole_url'];
    if (!is_string($url) || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url) || ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), array('http','https'), true) || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null))) throw new InvalidArgumentException('Invalid Pi-hole URL');
    $widgets = $appearance['header_widgets'];
    if (!is_array($widgets) || !pialert_v4_ui_exact_keys($widgets, array_keys($defaults['appearance']['header_widgets']))) throw new InvalidArgumentException('Invalid header widgets');
    foreach ($defaults['appearance']['header_widgets'] as $group => $groupDefaults) {
        if (!is_array($widgets[$group]) || !pialert_v4_ui_exact_keys($widgets[$group], array_keys($groupDefaults))) throw new InvalidArgumentException('Invalid header widget group');
        foreach ($widgets[$group] as $visible) if (!is_bool($visible)) throw new InvalidArgumentException('Invalid header widget visibility');
    }
    return $data;
}

function pialert_v4_ui_read(): array {
    $path = pialert_v4_ui_path();
    if (is_link($path)) throw new RuntimeException('UI settings path must not be a symlink');
    if (!file_exists($path)) {
        $path = pialert_v4_ui_default_path();
        if (is_link($path)) throw new RuntimeException('UI defaults path must not be a symlink');
    }
    if (!is_file($path) || filesize($path) > 65536) throw new RuntimeException('Invalid UI settings file');
    $raw = @file_get_contents($path);
    if (!is_string($raw)) throw new RuntimeException('UI settings cannot be read');
    try { $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR); } catch (JsonException $e) { throw new RuntimeException('Invalid UI settings JSON', 0, $e); }
    if (!is_array($data)) throw new RuntimeException('Invalid UI settings content');
    if (($data['schema'] ?? null) === 1 && is_array($data['appearance'] ?? null)
        && !array_key_exists('sidebar_color', $data['appearance']) && !array_key_exists('header_color', $data['appearance'])) {
        // Read-only upgrade. The first subsequent save writes the current schema.
        $data['appearance']['sidebar_color'] = 'body-secondary';
        $data['appearance']['header_color'] = 'body';
    }
    if (in_array($data['schema'] ?? null, array(1, 2), true) && is_array($data['appearance'] ?? null)) {
        unset($data['appearance']['theme']);
        $data['schema'] = 3;
    }
    if (($data['schema'] ?? null) === 3 && is_array($data['appearance'] ?? null)) {
        // Retired theme values are deliberately ignored. Glas is opt-in only.
        $data['appearance']['theme'] = 'standard';
        $data['schema'] = 4;
    }
    if (($data['schema'] ?? null) === 4) {
        // Existing UI files are upgraded in memory, then persisted on the next save.
        if (!array_key_exists('icmp', $data)) $data['icmp'] = pialert_v4_ui_defaults()['icmp'];
        $data['schema'] = 5;
    }
    if (($data['schema'] ?? null) === 5) {
        // Preserve existing visibility choices; only add the new optional action columns.
        if (is_array($data['devices']['columns'] ?? null) && !array_key_exists('Actions', $data['devices']['columns'])) $data['devices']['columns']['Actions'] = true;
        if (is_array($data['icmp']['columns'] ?? null) && !array_key_exists('Actions', $data['icmp']['columns'])) $data['icmp']['columns']['Actions'] = true;
        $data['schema'] = 6;
    }
    if (($data['schema'] ?? null) === 6) {
        // Old UI files did not contain this setting. Reset it to the enabled default.
        if (is_array($data['appearance'] ?? null)) $data['appearance']['activity_history'] = true;
        $data['schema'] = 7;
    }
    if (($data['schema'] ?? null) === 7) {
        // Favicon settings start with the bundled default; legacy marker files are not imported.
        if (is_array($data['appearance'] ?? null)) $data['appearance']['favicon'] = 'img/favicons/flat_blue_white.png';
        $data['schema'] = 8;
    }
    try { return pialert_v4_ui_validate($data); } catch (InvalidArgumentException $e) { throw new RuntimeException('Invalid UI settings content', 0, $e); }
}

function pialert_v4_ui_update(string $section, $value, ?int $expectedRevision = null): array {
    return pialert_v4_ui_update_many(array($section=>$value), $expectedRevision);
}

function pialert_v4_ui_update_many(array $patches, ?int $expectedRevision = null): array {
    $allowed = array('devices.columns','devices.page_length','devices.order','icmp.columns','icmp.page_length','icmp.order','appearance.language','appearance.theme','appearance.dark_mode','appearance.activity_history','appearance.favicon','appearance.sidebar_color','appearance.header_color','appearance.pihole_url','appearance.header_widgets');
    if ($patches === array() || count($patches) > count($allowed)) throw new InvalidArgumentException('Invalid UI settings patch');
    foreach (array_keys($patches) as $section) if (!is_string($section) || !in_array($section, $allowed, true)) throw new InvalidArgumentException('Unknown UI settings section');
    $path = pialert_v4_ui_path();
    $lockPath = $path . '.lock';
    if (is_link($lockPath)) throw new RuntimeException('UI settings lock must not be a symlink');
    $lock = @fopen($lockPath, 'c');
    if (!$lock) throw new RuntimeException('UI settings lock cannot be opened');
    try {
        @chmod($lockPath, 0640);
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('UI settings lock failed');
        $data = pialert_v4_ui_read();
        if ($expectedRevision !== null && $data['revision'] !== $expectedRevision) throw new DomainException('UI settings changed since loading');
        foreach ($patches as $section => $value) {
            [$group, $key] = explode('.', $section, 2);
            $data[$group][$key] = $value;
        }
        $data['revision']++;
        $data = pialert_v4_ui_validate($data);
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        $tmp = @tempnam(dirname($path), '.setting_ui_v4_');
        if ($tmp === false) throw new RuntimeException('UI settings temporary file cannot be created');
        try {
            $stream = @fopen($tmp, 'wb');
            if (!$stream) throw new RuntimeException('UI settings temporary file cannot be opened');
            try {
                $written = 0;
                while ($written < strlen($encoded)) {
                    $count = fwrite($stream, substr($encoded, $written));
                    if ($count === false || $count === 0) throw new RuntimeException('UI settings cannot be written');
                    $written += $count;
                }
                if (!fflush($stream) || (function_exists('fsync') && !fsync($stream))) throw new RuntimeException('UI settings cannot be flushed');
            } finally { fclose($stream); }
            if (!@chmod($tmp, 0640) || is_link($path) || !@rename($tmp, $path)) throw new RuntimeException('UI settings cannot be saved');
        } finally { if (file_exists($tmp)) @unlink($tmp); }
        return $data;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function pialert_v4_ui_hidden_columns(array $settings): array {
    $hidden = array();
    $index = 0;
    foreach (pialert_v4_device_columns() as $key => $column) {
        if (!empty($column['internal']) || (!empty($column['configurable']) && !$settings['devices']['columns'][$key])) $hidden[] = $index;
        $index++;
    }
    return $hidden;
}

function pialert_v4_ui_numeric_order(array $settings): array {
    $columns = array_flip(array_keys(pialert_v4_device_columns()));
    return array_map(static fn(array $part): array => array($columns[$part[0]], $part[1]), $settings['devices']['order']);
}

function pialert_v4_ui_icmp_hidden_columns(array $settings): array {
    $hidden = array();
    $index = 0;
    foreach (pialert_v4_icmp_columns() as $key => $column) {
        if (!empty($column['internal']) || (!empty($column['configurable']) && !$settings['icmp']['columns'][$key])) $hidden[] = $index;
        $index++;
    }
    return $hidden;
}

function pialert_v4_ui_icmp_numeric_order(array $settings): array {
    $columns = array_flip(array_keys(pialert_v4_icmp_columns()));
    return array_map(static fn(array $part): array => array($columns[$part[0]], $part[1]), $settings['icmp']['order']);
}

<?php
// Read the literal values used by the GUI from Python configuration data.
// Unrelated assignments (including multiline lists) must not affect a lookup.
// This reader never evaluates expressions; full validation belongs to Python.
function pialert_config_decode_string(string $literal): string {
    $escapes = array('a' => "\x07", 'b' => "\x08", 'f' => "\x0c",
        'n' => "\n", 'r' => "\r", 't' => "\t", 'v' => "\x0b",
        '\\' => '\\', "'" => "'", '"' => '"');
    return preg_replace_callback('~\\\\(?:[0-7]{1,3}|x[0-9a-fA-F]{2}|u[0-9a-fA-F]{4}|U[0-9a-fA-F]{8}|.)~s',
        static function ($match) use ($escapes) {
            $escape = substr($match[0], 1);
            if (isset($escapes[$escape])) return $escapes[$escape];
            if (preg_match('/^[0-7]{1,3}$/D', $escape)) {
                $codepoint = octdec($escape);
            } elseif (in_array($escape[0], array('x', 'u', 'U'), true) && strlen($escape) > 1) {
                $codepoint = hexdec(substr($escape, 1));
            } else {
                // Python preserves unknown escape sequences such as \q.
                return $match[0];
            }
            if ($codepoint > 0x10ffff) return $match[0];
            $unicode = $codepoint <= 0xffff
                ? sprintf('\\u%04x', $codepoint)
                : sprintf('\\u%04x\\u%04x', 0xd800 + (($codepoint - 0x10000) >> 10),
                    0xdc00 + (($codepoint - 0x10000) & 0x3ff));
            $decoded = json_decode('"' . $unicode . '"');
            return is_string($decoded) ? $decoded : $match[0];
        }, substr($literal, 1, -1));
}

function pialert_config_value_from_source(string $contents, string $name) {
    if (!preg_match('/^[A-Z][A-Z0-9_]*$/D', $name)) return false;
    $quoted = <<<'REGEX'
(?:'(?:\\[^\r\n]|[^'\\\r\n])*'|"(?:\\[^\r\n]|[^"\\\r\n])*")
REGEX;
    $gap = '(?:\s|#[^\r\n]*)*';
    $list = '\[' . $gap . '(?:' . $quoted . $gap . '(?:,' . $gap . $quoted . $gap . ')*,?' . $gap . ')?\]';
    $pattern = '~^[\t ]*' . preg_quote($name, '~') . '[\t ]*=[\t ]*(' .
        $quoted . '|' . $list . '|true|false|-?[0-9]+)[\t ]*(?:#[^\r\n]*)?\r?$~mi';
    if (preg_match($pattern, $contents, $match) !== 1) return false;
    $literal = $match[1];
    if ($literal[0] === '[') {
        // Skip comments as tokens so quoted text in comments is not a list item.
        preg_match_all('~(' . $quoted . ')|#[^\r\n]*~', $literal, $items, PREG_SET_ORDER);
        $values = array();
        foreach ($items as $item) {
            if (isset($item[1]) && $item[1] !== '') $values[] = pialert_config_decode_string($item[1]);
        }
        return $values;
    }
    if ($literal[0] === "'" || $literal[0] === '"') return pialert_config_decode_string($literal);
    if (strcasecmp($literal, 'true') === 0) return true;
    if (strcasecmp($literal, 'false') === 0) return false;
    return (int) $literal;
}

function pialert_config_value(string $name, ?string $configfile = null) {
    $contents = @file_get_contents($configfile ?? __DIR__ . '/../../../config/pialert.conf');
    return is_string($contents) ? pialert_config_value_from_source($contents, $name) : false;
}

<?php

// Versions of the locally installed frontend libraries. The directory name is
// authoritative where it contains a version; unversioned icon folders expose
// their packaged version in the font URLs at the start of their CSS files.
function pialert_v4_library_versions(string $root): array {
    $directories = array();
    foreach (glob($root . '/*', GLOB_ONLYDIR) ?: array() as $directory) {
        if (is_link($directory)) continue;
        $name = basename($directory);
        if ($name === 'static') continue;
        if ($name === 'datatables') {
            foreach (glob($directory . '/*', GLOB_ONLYDIR) ?: array() as $component) {
                if (!is_link($component)) $directories[] = $component;
            }
        } else {
            $directories[] = $directory;
        }
    }

    $cssVersions = array(
        'material-design-icons' => 'css/materialdesignicons.min.css',
        'ionicons' => 'css/ionicons.min.css',
    );
    $libraries = array();
    foreach ($directories as $directory) {
        $name = basename($directory);
        $version = '';
        $source = '';
        if (preg_match('/^(.+)-([0-9]+(?:\.[0-9]+)+(?:-[a-zA-Z0-9.]+)?)$/D', $name, $match)) {
            $name = $match[1];
            $version = $match[2];
            $source = 'directory';
        } elseif (isset($cssVersions[$name])) {
            $css = $directory . '/' . $cssVersions[$name];
            $prefix = is_readable($css) ? file_get_contents($css, false, null, 0, 2048) : false;
            if (is_string($prefix) && preg_match('/\?v=([0-9]+(?:\.[0-9]+)+(?:-[a-zA-Z0-9.]+)?)/', $prefix, $match)) {
                $version = $match[1];
                $source = 'css';
            }
        }
        $libraries[] = array('name' => $name, 'version' => $version, 'source' => $source);
    }
    usort($libraries, static fn(array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));
    return $libraries;
}

#!/usr/bin/env python3
"""Build the local action icon allowlist from shipped CSS and font coverage."""
import json
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / 'front'
OUT = ROOT / 'data' / 'action-icons.json'
IDS_OUT = ROOT / 'data' / 'action-icon-ids.json'


def glyphs(path):
    ranges = subprocess.check_output(['fc-query', '--format', '%{charset}', str(path)], text=True)
    values = set()
    for part in ranges.split():
        ends = part.split('-')
        values.update(range(int(ends[0], 16), int(ends[-1], 16) + 1))
    return values


def entries(css, pattern, prefix, family, base, font):
    text = (ROOT / css).read_text()
    available = glyphs(ROOT / font)
    found = {}
    seen_points = set()
    for name, code in re.findall(pattern, text):
        point = ord(code) if len(code) == 1 else int(code, 16)
        if point in available and point not in seen_points:
            seen_points.add(point)
            found.setdefault(name, {'id': prefix + ':' + name, 'name': name.replace('-', ' '),
                                    'family': family, 'class': base + ' ' + prefix.split('-')[0] + '-' + name})
    return list(found.values())


items = []
items += entries('lib/bootstrap-icons-1.13.1/font/bootstrap-icons.css',
                 r'\.bi-([a-z0-9-]+)::before\s*\{\s*content:\s*"\\([0-9a-fA-F]+)"',
                 'bi', 'Bootstrap Icons', 'bi', 'lib/bootstrap-icons-1.13.1/font/fonts/bootstrap-icons.woff2')
fa_css = (ROOT / 'lib/font-awesome/css/font-awesome.css').read_text()
fa_pattern = r'\.fa-([a-z0-9-]+):{1,2}before\s*\{\s*content:\s*"\\([0-9a-fA-F]+)"'
brand_start = fa_css.index("--fa-style-family-brands:")
regular_start = fa_css.index("--fa-font-regular:")
fa_solid_icons = re.findall(fa_pattern, fa_css[:brand_start])
fa_brand_icons = re.findall(fa_pattern, fa_css[brand_start:regular_start])
for suffix, weight, label in [('solid', '900', 'Font Awesome Solid'),
                              ('regular', '400', 'Font Awesome Regular'),
                              ('brands', '400', 'Font Awesome Brands')]:
    coverage = glyphs(ROOT / ('lib/font-awesome/webfonts/fa-' + suffix + '-' + weight + '.woff2'))
    seen = set()
    seen_points = set()
    for name, code in (fa_brand_icons if suffix == 'brands' else fa_solid_icons):
        if name in seen or int(code, 16) not in coverage or int(code, 16) in seen_points:
            continue
        seen.add(name)
        seen_points.add(int(code, 16))
        items.append({'id': 'fa-' + suffix + ':' + name, 'name': name.replace('-', ' '),
                      'family': label, 'class': 'fa-' + suffix + ' fa-' + name})
items += entries('lib/ionicons/css/ionicons.css',
                 r'\.ion-((?:ios|md)-[a-z0-9-]+):before\s*\{\s*content:\s*"([^"]+)"',
                 'ion', 'Ionicons', 'ion', 'lib/ionicons/fonts/ionicons.woff2')
items += entries('lib/material-design-icons/css/materialdesignicons.min.css',
                 r'\.mdi-([a-z0-9-]+)::before\s*\{\s*content:\s*"\\([0-9a-fA-F]+)"',
                 'mdi', 'Material Design Icons', 'mdi', 'lib/material-design-icons/fonts/materialdesignicons-webfont.woff2')
items.sort(key=lambda item: (item['family'], item['name']))
OUT.parent.mkdir(exist_ok=True)
OUT.write_text(json.dumps(items, ensure_ascii=False, separators=(',', ':')) + '\n')
IDS_OUT.write_text(json.dumps([item['id'] for item in items], ensure_ascii=False, separators=(',', ':')) + '\n')
print(f'{len(items)} icons -> {OUT}')

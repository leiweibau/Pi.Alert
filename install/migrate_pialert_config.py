#!/usr/bin/env python3
"""Migrate pialert.conf without depending on comments or section order."""
from __future__ import print_function

import argparse
import ast
import os
import re
import stat
import sys
import tempfile
from pathlib import Path


ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / 'back'))

from config_validation import (  # noqa: E402
    ALL_KEYS,
    DEPRECATED_KEYS,
    ConfigValidationError,
    load_pialert_config,
)


ASSIGNMENT_RE = re.compile(r'^[ \t]*([A-Z][A-Z0-9_]*)[ \t]*=', re.MULTILINE)
MIGRATION_REMOVED_KEYS = DEPRECATED_KEYS | frozenset(('REPORTS_FROM',))

# These migration defaults are deliberately internal to the installer. The
# standalone example configuration is distribution content and is never read
# by this helper, the backend, or the WebGUI.
DEFAULT_ASSIGNMENTS_SOURCE = """PIALERT_PATH = '/opt/pialert'
DB_PATH = PIALERT_PATH + '/db/pialert.db'
LOG_PATH = PIALERT_PATH + '/log'
PRINT_LOG = False
VENDORS_DB = '/usr/share/arp-scan/ieee-oui.txt'
PIALERT_APIKEY = ''
PIALERT_WEB_PROTECTION = False
PIALERT_WEB_PASSWORD = ''
NETWORK_DNS_SERVER = 'localhost'
AUTO_UPDATE_CHECK = True
AUTO_DB_BACKUP = True
AUTO_DB_BACKUP_KEEP = 5
REPORT_NEW_CONTINUOUS = False
NEW_DEVICE_PRESET_EVENTS = True
NEW_DEVICE_PRESET_DOWN = False
SYSTEM_TIMEZONE = 'Europe/Berlin'
OFFLINE_MODE = False
SCAN_WEBSERVICES = True
ICMPSCAN_ACTIVE = True
SATELLITES_ACTIVE = False
SCAN_ROGUE_DHCP = False
DHCP_SERVER_ADDRESS = '0.0.0.0'
AUTO_UPDATE_CHECK_CRON = '0 3,9,15,21 * * *'
AUTO_DB_BACKUP_CRON = '6 * * * *'
REPORT_NEW_CONTINUOUS_CRON = '0 * * * *'
SPEEDTEST_TASK_CRON = '51 * * * *'
SMTP_SERVER = 'smtp.example.com'
SMTP_PORT = 587
SMTP_USER = ''
SMTP_PASS = ''
SMTP_SSL = False
SMTP_SKIP_TLS = False
SMTP_SKIP_LOGIN = False
REPORT_WEBGUI = True
REPORT_WEBGUI_WEBMON = True
REPORT_TO_ARCHIVE = 0
REPORT_TO_MQTT = False
REPORT_MQTT_BROKER = 'mqtt.example.com'
REPORT_MQTT_PORT = 1883
REPORT_MQTT_USERNAME = ''
REPORT_MQTT_PASSWORD = ''
REPORT_MQTT_TLS = False
PUBLISH_MQTT_STATUS = False
REPORT_MAIL = False
REPORT_MAIL_WEBMON = False
REPORT_FROM = ''
REPORT_TO = ''
REPORT_DEVICE_URL = 'http://localhost/pialert/deviceDetails.php?mac='
REPORT_DASHBOARD_URL = 'http://localhost/pialert/'
REPORT_PUSHSAFER = False
REPORT_PUSHSAFER_WEBMON = False
PUSHSAFER_TOKEN = ''
PUSHSAFER_DEVICE = 'a'
PUSHSAFER_PRIO = 0
PUSHSAFER_SOUND = 22
REPORT_PUSHOVER = False
REPORT_PUSHOVER_WEBMON = False
PUSHOVER_TOKEN = ''
PUSHOVER_USER = ''
PUSHOVER_PRIO = 0
PUSHOVER_SOUND = 'siren'
REPORT_NTFY = False
REPORT_NTFY_WEBMON = False
NTFY_HOST = 'https://ntfy.sh'
NTFY_TOPIC = 'replace-with-a-private-topic'
NTFY_USER = ''
NTFY_PASSWORD = ''
NTFY_PRIORITY = 'default'
NTFY_CLICKABLE = True
REPORT_DISCORD = False
REPORT_DISCORD_WEBMON = False
DISCORD_BOT_TOKEN_URL = ''
REPORT_TELEGRAM = False
REPORT_TELEGRAM_WEBMON = False
TELEGRAM_BOT_TOKEN = ''
TELEGRAM_CHAT_IDS = []
TELEGRAM_BOT_TOKEN_URL = ''
QUERY_MYIP_SERVER = 'https://myipv4.p1.opendns.com/get_my_ip'
QUERY_MYIP_SERVER_FALLBACK = 'https://api.ipify.org/?format=json'
DDNS_ACTIVE = False
DDNS_DOMAIN = 'your_domain.freeddns.org'
DDNS_USER = ''
DDNS_PASSWORD = ''
DDNS_UPDATE_URL = 'https://api.dynu.com/nic/update?'
SPEEDTEST_TASK_ACTIVE = False
ARPSCAN_ACTIVE = True
MAC_IGNORE_LIST = []
IP_IGNORE_LIST = []
HOSTNAME_IGNORE_LIST = []
SCAN_SUBNETS = '--localnet'
ICMP_ONLINE_TEST = 1
ICMP_GET_AVG_RTT = 2
PIHOLE_ACTIVE = False
PIHOLE_VERSION = 6
PIHOLE_DB = '/etc/pihole/pihole-FTL.db'
PIHOLE6_URL = ''
PIHOLE6_PASSWORD = ''
PIHOLE6_API_MAXCLIENTS = 100
DHCP_ACTIVE = False
DHCP_LEASES = '/etc/pihole/dhcp.leases'
DHCP_INCL_SELF_TO_LEASES = False
FRITZBOX_ACTIVE = False
FRITZBOX_IP = '192.168.1.1'
FRITZBOX_USER = ''
FRITZBOX_PASS = ''
MIKROTIK_ACTIVE = False
MIKROTIK_IP = '192.168.1.1'
MIKROTIK_USER = ''
MIKROTIK_PASS = ''
UNIFI_ACTIVE = False
UNIFI_IP = '192.168.1.1'
UNIFI_API = 'v5'
UNIFI_USER = ''
UNIFI_PASS = ''
OPENWRT_ACTIVE = False
OPENWRT_IP = '192.168.1.1'
OPENWRT_USER = 'root'
OPENWRT_PASS = ''
ASUSWRT_ACTIVE = False
ASUSWRT_IP = '192.168.1.1'
ASUSWRT_USER = ''
ASUSWRT_PASS = ''
ASUSWRT_SSL = False
PFSENSE_ACTIVE = False
PFSENSE_IP = '192.168.1.1'
PFSENSE_PORT = 443
PFSENSE_APIKEY = ''
PFSENSE_SSL = True
PFSENSE_EXCLUDE_INT = ['WAN']
OPNSENSE_ACTIVE = False
OPNSENSE_IP = '192.168.1.1'
OPNSENSE_PORT = 443
OPNSENSE_APIKEY = ''
OPNSENSE_APISECRET = ''
OPNSENSE_SSL = True
OPNSENSE_EXCLUDE_INT = ['WAN']
ADGUARD_ACTIVE = False
ADGUARD_IP = '192.168.1.1'
ADGUARD_PORT = 80
ADGUARD_USER = ''
ADGUARD_PASSWORD = ''
ADGUARD_SSL = False
ADGUARD_QUERY_MINUTES = 5
ADGUARD_ACTIVITY_MINUTES = 10
ADGUARD_QUERY_LIMIT = 1000
SATELLITE_PROXY_MODE = False
SATELLITE_PROXY_URL = ''
DAYS_TO_KEEP_ONLINEHISTORY = 60
DAYS_TO_KEEP_EVENTS = 180
"""


def default_assignment_lines():
    """Return the built-in assignment used for every supported key."""
    assignments = {}
    for line in DEFAULT_ASSIGNMENTS_SOURCE.splitlines():
        match = ASSIGNMENT_RE.match(line)
        if match:
            key = match.group(1)
            if key in assignments:
                raise ConfigValidationError(
                    'migration defaults contain duplicate key {}'.format(key))
            assignments[key] = line

    missing = ALL_KEYS - set(assignments)
    extra = set(assignments) - ALL_KEYS
    if missing:
        raise ConfigValidationError(
            'migration defaults are missing key {}'.format(sorted(missing)[0]))
    if extra:
        raise ConfigValidationError(
            'migration defaults contain unsupported key {}'.format(sorted(extra)[0]))
    return assignments


def remove_deprecated_assignments(source):
    """Remove only known obsolete assignment lines, never surrounding sections."""
    removed = []
    kept = []
    deprecated_line = re.compile(
        r'^[ \t]*#?[ \t]*(' + '|'.join(re.escape(key) for key in MIGRATION_REMOVED_KEYS) +
        r')[ \t]*=')
    for line in source.splitlines(keepends=True):
        match = deprecated_line.match(line)
        if match:
            removed.append(match.group(1))
        else:
            kept.append(line)
    return ''.join(kept), removed


def _literal_string_assignment(source, key):
    match = re.search(
        r'^[ \t]*' + re.escape(key) + r'[ \t]*=[ \t]*(.*?)[ \t]*$',
        source, re.MULTILINE)
    if not match:
        return ''
    try:
        node = ast.parse(match.group(1), mode='eval').body
    except SyntaxError:
        return ''
    return node.value if isinstance(node, ast.Constant) and type(node.value) is str else ''


def _resolve_legacy_report_from(node, smtp_user):
    if isinstance(node, ast.Constant) and type(node.value) is str:
        return node.value
    if isinstance(node, ast.Name) and node.id == 'SMTP_USER':
        return smtp_user
    if isinstance(node, ast.BinOp) and isinstance(node.op, ast.Add):
        return (_resolve_legacy_report_from(node.left, smtp_user) +
                _resolve_legacy_report_from(node.right, smtp_user))
    raise ValueError('unsupported REPORT_FROM expression')


def normalize_legacy_report_from(source):
    """Convert a legacy expression once; runtime config remains literal-only."""
    pattern = re.compile(
        r'^(?P<indent>[ \t]*)REPORT_FROM[ \t]*=[ \t]*(?P<value>.*?)(?P<newline>\r?\n|$)',
        re.MULTILINE)
    match = pattern.search(source)
    if not match:
        return source, False
    try:
        node = ast.parse(match.group('value').strip(), mode='eval').body
    except SyntaxError:
        return source, False
    if isinstance(node, ast.Constant) and type(node.value) is str:
        return source, False

    # Only the former SMTP_USER concatenation is resolved. Any other name or
    # expression becomes the safe REPORT_FROM default and is never executed.
    try:
        value = _resolve_legacy_report_from(
            node, _literal_string_assignment(source, 'SMTP_USER'))
    except ValueError:
        value = ''
    replacement = '{}REPORT_FROM = {}{}'.format(
        match.group('indent'), repr(value), match.group('newline'))
    return source[:match.start()] + replacement + source[match.end():], True


def build_candidate(source, defaults):
    source, removed = remove_deprecated_assignments(source)
    source, normalized_report_from = normalize_legacy_report_from(source)
    existing = set(ASSIGNMENT_RE.findall(source))
    missing = sorted(ALL_KEYS - existing)
    if missing:
        if source and not source.endswith('\n'):
            source += '\n'
        source += '\n# Settings added by the Pi.Alert update\n'
        source += '# Existing settings and their order were left unchanged.\n'
        source += '\n'.join(defaults[key] for key in missing) + '\n'
    return source, missing, removed, normalized_report_from


def migrate_config(config_path, expected_pialert_path):
    config_path = Path(config_path)
    defaults = default_assignment_lines()
    source = config_path.read_text(encoding='utf-8')
    candidate, added, removed, normalized_report_from = build_candidate(source, defaults)

    file_stat = config_path.stat()
    temporary_path = None
    try:
        descriptor, temporary_path = tempfile.mkstemp(
            prefix='.pialert.conf.', dir=str(config_path.parent), text=True)
        with os.fdopen(descriptor, 'w', encoding='utf-8') as handle:
            handle.write(candidate)
            handle.flush()
            os.fsync(handle.fileno())
        os.chmod(temporary_path, stat.S_IMODE(file_stat.st_mode))

        # The live file is replaced only after the complete candidate validates.
        load_pialert_config(temporary_path, expected_pialert_path)
        os.replace(temporary_path, str(config_path))
        temporary_path = None
    finally:
        if temporary_path is not None:
            try:
                os.unlink(temporary_path)
            except OSError:
                pass

    if added:
        print('Added missing settings: {}'.format(', '.join(added)))
    if removed:
        print('Removed deprecated settings: {}'.format(', '.join(sorted(set(removed)))))
    if normalized_report_from:
        print('Converted legacy REPORT_FROM expression to a static value.')
    if not added and not removed and not normalized_report_from:
        print('Configuration is already up to date.')


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('config_path')
    parser.add_argument('expected_pialert_path')
    args = parser.parse_args()
    try:
        migrate_config(args.config_path, args.expected_pialert_path)
    except (ConfigValidationError, OSError, UnicodeError) as exc:
        print('Configuration migration failed: {}'.format(exc), file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())

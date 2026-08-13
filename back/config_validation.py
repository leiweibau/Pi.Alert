"""Strict, non-executing Pi.Alert configuration loader and validator."""
from __future__ import print_function

import ast
import ipaddress
import os
import re


class ConfigValidationError(ValueError):
    pass


BOOLEAN_KEYS = frozenset("""
PRINT_LOG PIALERT_WEB_PROTECTION AUTO_UPDATE_CHECK AUTO_DB_BACKUP
REPORT_NEW_CONTINUOUS NEW_DEVICE_PRESET_EVENTS NEW_DEVICE_PRESET_DOWN OFFLINE_MODE
SCAN_WEBSERVICES ICMPSCAN_ACTIVE SATELLITES_ACTIVE SCAN_ROGUE_DHCP SMTP_SSL
SMTP_SKIP_TLS SMTP_SKIP_LOGIN REPORT_WEBGUI REPORT_WEBGUI_WEBMON REPORT_TO_MQTT
REPORT_MQTT_TLS PUBLISH_MQTT_STATUS REPORT_MAIL REPORT_MAIL_WEBMON REPORT_PUSHSAFER
REPORT_PUSHSAFER_WEBMON REPORT_PUSHOVER REPORT_PUSHOVER_WEBMON REPORT_NTFY
REPORT_NTFY_WEBMON NTFY_CLICKABLE REPORT_DISCORD REPORT_DISCORD_WEBMON
REPORT_TELEGRAM REPORT_TELEGRAM_WEBMON DDNS_ACTIVE SPEEDTEST_TASK_ACTIVE
ARPSCAN_ACTIVE PIHOLE_ACTIVE DHCP_ACTIVE DHCP_INCL_SELF_TO_LEASES FRITZBOX_ACTIVE
MIKROTIK_ACTIVE UNIFI_ACTIVE OPENWRT_ACTIVE ASUSWRT_ACTIVE ASUSWRT_SSL
PFSENSE_ACTIVE PFSENSE_SSL OPNSENSE_ACTIVE OPNSENSE_SSL ADGUARD_ACTIVE ADGUARD_SSL
SATELLITE_PROXY_MODE
""".split())

INTEGER_RULES = {
    'AUTO_DB_BACKUP_KEEP': (0, 3650), 'REPORT_TO_ARCHIVE': (0, 87600),
    'SMTP_PORT': (1, 65535), 'REPORT_MQTT_PORT': (1, 65535),
    'PUSHSAFER_PRIO': (-10, 10), 'PUSHSAFER_SOUND': (0, 1000),
    'PUSHOVER_PRIO': (-2, 2), 'ICMP_ONLINE_TEST': (0, 100),
    'ICMP_GET_AVG_RTT': (0, 100), 'PIHOLE_VERSION': (5, 6),
    'PIHOLE6_API_MAXCLIENTS': (1, 100000), 'PFSENSE_PORT': (1, 65535),
    'OPNSENSE_PORT': (1, 65535), 'ADGUARD_PORT': (1, 65535),
    'ADGUARD_QUERY_MINUTES': (1, 10080), 'ADGUARD_ACTIVITY_MINUTES': (1, 10080),
    'ADGUARD_QUERY_LIMIT': (1, 1000000), 'DAYS_TO_KEEP_ONLINEHISTORY': (0, 36500),
    'DAYS_TO_KEEP_EVENTS': (0, 36500),
}

STRING_KEYS = frozenset("""
PIALERT_PATH VENDORS_DB PIALERT_APIKEY PIALERT_WEB_PASSWORD NETWORK_DNS_SERVER
SYSTEM_TIMEZONE AUTO_UPDATE_CHECK_CRON AUTO_DB_BACKUP_CRON REPORT_NEW_CONTINUOUS_CRON
SPEEDTEST_TASK_CRON SMTP_SERVER SMTP_USER SMTP_PASS REPORT_MQTT_BROKER
REPORT_MQTT_USERNAME REPORT_MQTT_PASSWORD REPORT_FROM REPORT_TO REPORT_DEVICE_URL
REPORT_DASHBOARD_URL PUSHSAFER_TOKEN PUSHSAFER_DEVICE PUSHOVER_TOKEN PUSHOVER_USER
PUSHOVER_SOUND NTFY_HOST NTFY_TOPIC NTFY_USER NTFY_PASSWORD NTFY_PRIORITY
DISCORD_BOT_TOKEN_URL TELEGRAM_BOT_TOKEN_URL TELEGRAM_BOT_TOKEN QUERY_MYIP_SERVER
QUERY_MYIP_SERVER_FALLBACK DDNS_DOMAIN DDNS_USER DDNS_PASSWORD DDNS_UPDATE_URL
PIHOLE_DB PIHOLE6_URL PIHOLE6_PASSWORD DHCP_LEASES FRITZBOX_IP FRITZBOX_USER
FRITZBOX_PASS MIKROTIK_IP MIKROTIK_USER MIKROTIK_PASS UNIFI_IP UNIFI_API UNIFI_USER
UNIFI_PASS OPENWRT_IP OPENWRT_USER OPENWRT_PASS ASUSWRT_IP ASUSWRT_USER ASUSWRT_PASS
PFSENSE_IP PFSENSE_APIKEY OPNSENSE_IP OPNSENSE_APIKEY OPNSENSE_APISECRET ADGUARD_IP
ADGUARD_USER ADGUARD_PASSWORD SATELLITE_PROXY_URL
""".split())

LIST_KEYS = frozenset(('MAC_IGNORE_LIST', 'IP_IGNORE_LIST', 'HOSTNAME_IGNORE_LIST',
                       'PFSENSE_EXCLUDE_INT', 'OPNSENSE_EXCLUDE_INT',
                       'TELEGRAM_CHAT_IDS'))
SPECIAL_KEYS = frozenset(('DB_PATH', 'LOG_PATH', 'DHCP_SERVER_ADDRESS', 'SCAN_SUBNETS'))
OPTIONAL_DEFAULTS = {
    'SMTP_SSL': False,
    'TELEGRAM_BOT_TOKEN': '',
    'TELEGRAM_CHAT_IDS': [],
}
ALL_KEYS = BOOLEAN_KEYS | frozenset(INTEGER_RULES) | STRING_KEYS | LIST_KEYS | SPECIAL_KEYS
DEPRECATED_KEYS = frozenset(('SHOUTRRR_BINARY',))
LOADABLE_KEYS = ALL_KEYS | DEPRECATED_KEYS
VERSION_KEYS = frozenset(('VERSION', 'VERSION_YEAR', 'VERSION_DATE'))
_MAC = re.compile(r'^[0-9a-fA-F]{2}(:[0-9a-fA-F]{2}){5}$')
_INTERFACE = re.compile(r'^[A-Za-z0-9_.:-]{1,64}$')
_TELEGRAM_CHAT_ID = re.compile(
    r'^(?:-?[1-9][0-9]{0,19}|@[A-Za-z][A-Za-z0-9_]{4,31})$')


def require_bool(name, value):
    if type(value) is not bool:
        raise ConfigValidationError('%s must be True or False' % name)
    return value


def require_int(name, value, minimum=None, maximum=None):
    if type(value) is not int:
        raise ConfigValidationError('%s must be an integer' % name)
    if minimum is not None and value < minimum:
        raise ConfigValidationError('%s is below the allowed minimum' % name)
    if maximum is not None and value > maximum:
        raise ConfigValidationError('%s exceeds the allowed maximum' % name)
    return value


def require_string(name, value, allow_empty=True, max_length=4096):
    if type(value) is not str:
        raise ConfigValidationError('%s must be a string' % name)
    if not allow_empty and value == '':
        raise ConfigValidationError('%s must not be empty' % name)
    if '\x00' in value or '\r' in value or '\n' in value or len(value) > max_length:
        raise ConfigValidationError('%s contains invalid characters or is too long' % name)
    return value


def require_string_list(name, value, item_validator=None, maximum_items=1024):
    if type(value) is not list or len(value) > maximum_items:
        raise ConfigValidationError('%s must be a bounded list' % name)
    for index, item in enumerate(value):
        require_string('%s[%d]' % (name, index), item, False, 512)
        if item_validator and not item_validator(item):
            raise ConfigValidationError('%s[%d] is invalid' % (name, index))
    return value


def _is_ip(value):
    try:
        ipaddress.ip_address(value)
        return True
    except ValueError:
        return False


def _literal(name, node):
    if name in ('DB_PATH', 'LOG_PATH'):
        suffix = {'DB_PATH': '/db/pialert.db', 'LOG_PATH': '/log'}[name]
        if (not isinstance(node, ast.BinOp) or not isinstance(node.op, ast.Add) or
                not isinstance(node.left, ast.Name) or node.left.id != 'PIALERT_PATH' or
                not isinstance(node.right, ast.Constant) or node.right.value != suffix):
            raise ConfigValidationError('%s must use its approved compatibility expression' % name)
        return suffix
    if isinstance(node, ast.Constant):
        return node.value
    if isinstance(node, ast.List):
        return [_literal('_item', item) for item in node.elts]
    raise ConfigValidationError('%s contains a disallowed expression' % name)


def _matches_expected_path(value, expected):
    return type(value) is str and os.path.realpath(value) == os.path.realpath(expected)


def validate_values(values, expected_pialert_path=None):
    for name in DEPRECATED_KEYS:
        values.pop(name, None)
    unknown = set(values) - ALL_KEYS
    if unknown:
        raise ConfigValidationError('unknown configuration key %s' % sorted(unknown)[0])
    for name, default in OPTIONAL_DEFAULTS.items():
        values.setdefault(name, list(default) if isinstance(default, list) else default)
    missing = ALL_KEYS - set(values)
    if missing:
        raise ConfigValidationError('missing required configuration key %s' % sorted(missing)[0])
    for name in BOOLEAN_KEYS:
        values[name] = require_bool(name, values[name])
    for name, bounds in INTEGER_RULES.items():
        values[name] = require_int(name, values[name], bounds[0], bounds[1])
    for name in STRING_KEYS:
        values[name] = require_string(name, values[name])
    values['SMTP_SERVER'] = require_string('SMTP_SERVER', values['SMTP_SERVER'], False, 255)
    values['TELEGRAM_BOT_TOKEN'] = require_string(
        'TELEGRAM_BOT_TOKEN', values['TELEGRAM_BOT_TOKEN'], True, 512)
    for name in LIST_KEYS:
        validator = (_MAC.match if name == 'MAC_IGNORE_LIST' else
                     _is_ip if name == 'IP_IGNORE_LIST' else
                     _TELEGRAM_CHAT_ID.match if name == 'TELEGRAM_CHAT_IDS' else
                     _INTERFACE.match if name in ('PFSENSE_EXCLUDE_INT', 'OPNSENSE_EXCLUDE_INT') else None)
        values[name] = require_string_list(name, values[name],
            (lambda item, validator=validator: bool(validator(item))) if validator else None,
            32 if name == 'TELEGRAM_CHAT_IDS' else 1024)
    if len(values['TELEGRAM_CHAT_IDS']) != len(set(values['TELEGRAM_CHAT_IDS'])):
        raise ConfigValidationError('TELEGRAM_CHAT_IDS contains duplicate entries')
    dhcp = values['DHCP_SERVER_ADDRESS']
    if type(dhcp) is str:
        require_string('DHCP_SERVER_ADDRESS', dhcp, False, 45)
        if not _is_ip(dhcp):
            raise ConfigValidationError('DHCP_SERVER_ADDRESS is invalid')
    else:
        values['DHCP_SERVER_ADDRESS'] = require_string_list('DHCP_SERVER_ADDRESS', dhcp, _is_ip)
    scan = values['SCAN_SUBNETS']
    if type(scan) is str:
        require_string('SCAN_SUBNETS', scan, False, 512)
        if not scan.startswith('--'):
            raise ConfigValidationError('SCAN_SUBNETS is invalid')
    else:
        values['SCAN_SUBNETS'] = require_string_list('SCAN_SUBNETS', scan)
    if expected_pialert_path is not None and not _matches_expected_path(values['PIALERT_PATH'], expected_pialert_path):
        raise ConfigValidationError('PIALERT_PATH does not match this installation')
    values['DB_PATH'] = values['PIALERT_PATH'] + values['DB_PATH']
    values['LOG_PATH'] = values['PIALERT_PATH'] + values['LOG_PATH']
    return values


def validate_loaded_config(values, expected_pialert_path=None):
    return validate_values(dict(values), expected_pialert_path)


def load_pialert_config(path, expected_pialert_path=None, maximum_size=262144, validate=True):
    try:
        with open(path, 'r') as handle:
            source = handle.read(maximum_size + 1)
    except OSError as exc:
        raise ConfigValidationError('configuration file is not readable') from exc
    if len(source) > maximum_size:
        raise ConfigValidationError('configuration file is too large')
    try:
        tree = ast.parse(source, filename=path, mode='exec')
    except SyntaxError as exc:
        raise ConfigValidationError('configuration file has invalid syntax') from exc
    values = {}
    for statement in tree.body:
        if (not isinstance(statement, ast.Assign) or len(statement.targets) != 1 or
                not isinstance(statement.targets[0], ast.Name)):
            raise ConfigValidationError('configuration contains a disallowed statement')
        name = statement.targets[0].id
        if name.startswith('_') or name == '__builtins__' or name not in LOADABLE_KEYS or name in values:
            raise ConfigValidationError('configuration contains an unknown or duplicate key')
        values[name] = _literal(name, statement.value)
    for name in DEPRECATED_KEYS:
        values.pop(name, None)
    if validate:
        return validate_loaded_config(values, expected_pialert_path)
    if expected_pialert_path is not None and not _matches_expected_path(
            values.get('PIALERT_PATH'), expected_pialert_path):
        raise ConfigValidationError('PIALERT_PATH does not match this installation')
    return values


def load_version_config(path, maximum_size=4096):
    """Load version metadata as literals without executing the file."""
    try:
        with open(path, 'r') as handle:
            source = handle.read(maximum_size + 1)
    except OSError as exc:
        raise ConfigValidationError('version file is not readable') from exc
    if len(source) > maximum_size:
        raise ConfigValidationError('version file is too large')
    try:
        tree = ast.parse(source, filename=path, mode='exec')
    except SyntaxError as exc:
        raise ConfigValidationError('version file has invalid syntax') from exc

    values = {}
    for statement in tree.body:
        if (not isinstance(statement, ast.Assign) or len(statement.targets) != 1 or
                not isinstance(statement.targets[0], ast.Name)):
            raise ConfigValidationError('version file contains a disallowed statement')
        name = statement.targets[0].id
        if name not in VERSION_KEYS or name in values:
            raise ConfigValidationError('version file contains an unknown or duplicate key')
        value = _literal(name, statement.value)
        values[name] = require_string(name, value, True, 64)

    missing = VERSION_KEYS - set(values)
    if missing:
        raise ConfigValidationError('version file is missing key %s' % sorted(missing)[0])
    if not re.match(r'^\d{4}$', values['VERSION_YEAR']):
        raise ConfigValidationError('VERSION_YEAR has an invalid format')
    if not re.match(r'^\d{4}-\d{2}-\d{2}$', values['VERSION_DATE']):
        raise ConfigValidationError('VERSION_DATE has an invalid format')
    return values

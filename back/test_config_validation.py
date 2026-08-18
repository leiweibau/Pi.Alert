#!/usr/bin/env python3
import ast
import contextlib
import io
import os
import re
import sys
import tempfile
import warnings
import unittest
from pathlib import Path
from unittest import mock

sys.path.insert(0, str(Path(__file__).resolve().parent))
sys.path.insert(0, str(Path(__file__).resolve().parent.parent / 'install'))
from config_validation import (
    ALL_KEYS,
    MAX_CONFIG_BYTES,
    ConfigValidationError,
    build_arpscan_arguments,
    load_pialert_config,
    load_pialert_config_source,
    load_version_config,
    require_bool,
    validate_loaded_config,
    require_int,
    require_string_list,
)
from migrate_pialert_config import default_assignment_lines, migrate_config


ROOT = Path(__file__).resolve().parent.parent
ACTIVE_CONFIG = ROOT / 'config' / 'pialert.conf'
EXAMPLE_CONFIG = ROOT / 'config' / 'pialert.example.conf'


def readable_config_path():
    try:
        with ACTIVE_CONFIG.open('r'):
            return ACTIVE_CONFIG
    except OSError:
        return ROOT / 'config' / 'pialert.conf.back'


CONFIG = readable_config_path()


class ConfigValidationTests(unittest.TestCase):
    def test_version_configuration_is_loaded_as_data(self):
        values = load_version_config(str(ROOT / 'config' / 'version.conf'))
        self.assertEqual(set(values), {'VERSION', 'VERSION_YEAR', 'VERSION_DATE'})
        self.assertRegex(values['VERSION_YEAR'], r'^\d{4}$')
        self.assertRegex(values['VERSION_DATE'], r'^\d{4}-\d{2}-\d{2}$')

    def test_version_configuration_rejects_executable_content(self):
        invalid_sources = (
            "VERSION = __import__('os').system('id')\nVERSION_YEAR = '2026'\nVERSION_DATE = '2026-08-13'\n",
            "VERSION = ''\nVERSION_YEAR = '2026'\nVERSION_DATE = '2026-08-13'\nimport os\n",
            "VERSION = ''\nVERSION_YEAR = '2026'\nVERSION_DATE = '2026-08-13'\nUNKNOWN = ''\n",
            "VERSION = ''\nVERSION_YEAR = '2026'\n",
        )
        for source in invalid_sources:
            with tempfile.NamedTemporaryFile('w', delete=False) as handle:
                handle.write(source)
                path = handle.name
            try:
                with self.assertRaises(ConfigValidationError):
                    load_version_config(path)
            finally:
                Path(path).unlink()

    def test_backends_never_execute_version_configuration(self):
        for script in ('pialert.py', 'pialert_tools.py', 'pialert_reporting_test.py'):
            source = (ROOT / 'back' / script).read_text()
            self.assertIn('load_version_config(', source)
            self.assertNotRegex(source, r'exec(?:file)?\s*\([^\n]*version\.conf')

    def test_installers_protect_version_configuration(self):
        for script in ('pialert_install.sh', 'pialert_update.sh'):
            source = (ROOT / 'install' / script).read_text()
            self.assertIn('chmod 1775', source)
            self.assertIn('chown root:root "$PIALERT_HOME/config/version.conf"', source)
            self.assertIn('chmod 0644 "$PIALERT_HOME/config/version.conf"', source)
            self.assertNotRegex(source, r'chmod -R 775 [^\n]*config')

    def test_active_configuration_is_valid(self):
        values = load_pialert_config(str(CONFIG), str(ROOT))
        self.assertEqual(type(values['SMTP_SSL']), bool)
        self.assertEqual(type(values['SMTP_PORT']), int)

    def test_example_configuration_is_complete_and_valid(self):
        values = load_pialert_config(str(EXAMPLE_CONFIG), str(ROOT))
        self.assertEqual(set(values), set(ALL_KEYS))
        self.assertFalse(values['PIALERT_WEB_PROTECTION'])
        self.assertFalse(values['REPORT_MAIL'])
        self.assertTrue(values['OPENWRT_SSL'])
        self.assertEqual(values['OPENWRT_PORT'], 443)
        self.assertEqual(values['PUSHOVER_RETRY'], 60)
        self.assertEqual(values['PUSHOVER_EXPIRE'], 3600)
        self.assertFalse(values['PUBLISH_MQTT_SUBNET_STATUS'])
        self.assertNotIn('SHOUTRRR_BINARY', EXAMPLE_CONFIG.read_text())

        # Its validity must not depend on the special example filename.
        with tempfile.TemporaryDirectory() as directory:
            renamed = Path(directory) / 'pialert.conf'
            renamed.write_text(EXAMPLE_CONFIG.read_text())
            renamed_values = load_pialert_config(str(renamed), str(ROOT))
            self.assertEqual(values, renamed_values)

    def test_update_migration_is_independent_of_sections_and_key_order(self):
        source = EXAMPLE_CONFIG.read_text()
        smtp_line = "SMTP_SERVER                = 'smtp.example.com'\n"
        self.assertIn(smtp_line, source)
        source = source.replace(smtp_line, '', 1)
        source = "SMTP_SERVER = 'custom.example.net'\n" + source
        for key in ('OPNSENSE_APISECRET', 'REPORT_MQTT_TLS', 'TELEGRAM_CHAT_IDS'):
            source, count = re.subn(
                r'^[ \t]*' + key + r'[ \t]*=.*\n?', '', source,
                count=1, flags=re.MULTILINE)
            self.assertEqual(count, 1)
        source = (
            '# Telegram\n# ----------------------\n'
            '# Shoutrrr\n# ----------------------\n'
            "SHOUTRRR_BINARY = 'arm64'\n"
            "# SHOUTRRR_BINARY = 'x86'\n" + source)

        with tempfile.TemporaryDirectory() as directory:
            candidate = Path(directory) / 'pialert.conf'
            candidate.write_text(source)
            candidate.chmod(0o640)
            with contextlib.redirect_stdout(io.StringIO()):
                migrate_config(str(candidate), str(ROOT))
            migrated_source = candidate.read_text()
            values = load_pialert_config(str(candidate), str(ROOT))

            self.assertEqual(values['SMTP_SERVER'], 'custom.example.net')
            self.assertEqual(values['OPNSENSE_APISECRET'], '')
            self.assertFalse(values['REPORT_MQTT_TLS'])
            self.assertEqual(values['TELEGRAM_CHAT_IDS'], [])
            self.assertIn('# General Settings', migrated_source)
            self.assertIn('DAYS_TO_KEEP_EVENTS', migrated_source)
            self.assertNotIn('SHOUTRRR_BINARY', migrated_source)
            self.assertEqual(candidate.stat().st_mode & 0o777, 0o640)

    def test_update_migration_preserves_owner_and_group_on_atomic_replace(self):
        source = EXAMPLE_CONFIG.read_text()
        source, count = re.subn(
            r'^PUBLISH_MQTT_SUBNET_STATUS\s*=.*\n?', '', source,
            count=1, flags=re.MULTILINE)
        self.assertEqual(count, 1)

        with tempfile.TemporaryDirectory() as directory:
            candidate = Path(directory) / 'pialert.conf'
            candidate.write_text(source)
            original_stat = candidate.stat()
            real_chown = os.chown

            with mock.patch(
                    'migrate_pialert_config.os.chown',
                    side_effect=real_chown) as chown:
                with contextlib.redirect_stdout(io.StringIO()):
                    migrate_config(str(candidate), str(ROOT))

            chown.assert_called_once()
            self.assertEqual(
                chown.call_args.args[1:],
                (original_stat.st_uid, original_stat.st_gid))
            migrated_stat = candidate.stat()
            self.assertEqual(
                (migrated_stat.st_uid, migrated_stat.st_gid),
                (original_stat.st_uid, original_stat.st_gid))

    def test_failed_update_migration_does_not_replace_configuration(self):
        source = EXAMPLE_CONFIG.read_text() + '\nUNSUPPORTED_SETTING = True\n'
        with tempfile.TemporaryDirectory() as directory:
            candidate = Path(directory) / 'pialert.conf'
            candidate.write_text(source)
            with self.assertRaises(ConfigValidationError):
                migrate_config(str(candidate), str(ROOT))
            self.assertEqual(candidate.read_text(), source)

    def test_update_migrates_legacy_report_from_without_runtime_expressions(self):
        source = EXAMPLE_CONFIG.read_text()
        source = re.sub(
            r'^SMTP_USER\s*=.*$', "SMTP_USER = 'sender@example.com'",
            source, count=1, flags=re.MULTILINE)
        source, count = re.subn(
            r'^REPORT_FROM\s*=.*$',
            "REPORT_FROM = 'Test <' + SMTP_USER + '>'",
            source, count=1, flags=re.MULTILINE)
        self.assertEqual(count, 1)

        with tempfile.TemporaryDirectory() as directory:
            candidate = Path(directory) / 'pialert.conf'
            candidate.write_text(source)
            with self.assertRaises(ConfigValidationError):
                load_pialert_config(str(candidate), str(ROOT))
            with contextlib.redirect_stdout(io.StringIO()):
                migrate_config(str(candidate), str(ROOT))
            migrated = candidate.read_text()
            values = load_pialert_config(str(candidate), str(ROOT))
            self.assertEqual(
                values['REPORT_FROM'], 'Test <sender@example.com>')
            report_line = next(
                line for line in migrated.splitlines()
                if line.startswith('REPORT_FROM'))
            self.assertEqual(
                report_line, "REPORT_FROM = 'Test <sender@example.com>'")

        php_source = (ROOT / 'front' / 'php' / 'server' / 'files.php').read_text()
        self.assertNotIn('<+SMTP_USER+>', php_source)
        self.assertNotIn("<' + SMTP_USER + '>", php_source)

    def test_update_migration_defaults_cover_the_complete_schema(self):
        defaults = default_assignment_lines()
        self.assertEqual(set(defaults), set(ALL_KEYS))
        self.assertEqual(defaults['OPENWRT_SSL'], 'OPENWRT_SSL = False')
        self.assertEqual(defaults['OPENWRT_PORT'], 'OPENWRT_PORT = 80')
        install_defaults = default_assignment_lines(new_install=True)
        self.assertEqual(
            install_defaults['OPENWRT_SSL'], 'OPENWRT_SSL = True')
        self.assertEqual(
            install_defaults['OPENWRT_PORT'], 'OPENWRT_PORT = 443')

    def test_installer_selects_new_openwrt_defaults_without_using_example(self):
        installer = (ROOT / 'install' / 'pialert_install.sh').read_text()
        updater = (ROOT / 'install' / 'pialert_update.sh').read_text()
        self.assertIn('--new-install', installer)
        self.assertNotIn('--new-install', updater)
        self.assertNotIn('pialert.example.conf', installer)

    def test_update_adds_compatible_openwrt_and_pushover_defaults(self):
        source = EXAMPLE_CONFIG.read_text()
        for key in ('OPENWRT_SSL', 'OPENWRT_PORT', 'PUSHOVER_RETRY',
                    'PUSHOVER_EXPIRE', 'PUBLISH_MQTT_SUBNET_STATUS'):
            source, count = re.subn(
                r'^' + key + r'\s*=.*\n?', '', source,
                count=1, flags=re.MULTILINE)
            self.assertEqual(count, 1)
        with tempfile.TemporaryDirectory() as directory:
            candidate = Path(directory) / 'pialert.conf'
            candidate.write_text(source)
            with contextlib.redirect_stdout(io.StringIO()):
                migrate_config(str(candidate), str(ROOT))
            values = load_pialert_config(str(candidate), str(ROOT))
        self.assertFalse(values['OPENWRT_SSL'])
        self.assertEqual(values['OPENWRT_PORT'], 80)
        self.assertEqual(values['PUSHOVER_RETRY'], 60)
        self.assertEqual(values['PUSHOVER_EXPIRE'], 3600)
        self.assertFalse(values['PUBLISH_MQTT_SUBNET_STATUS'])

    def test_new_install_adds_https_openwrt_defaults(self):
        source = EXAMPLE_CONFIG.read_text()
        for key in ('OPENWRT_SSL', 'OPENWRT_PORT'):
            source = re.sub(
                r'^' + key + r'\s*=.*\n?', '', source,
                count=1, flags=re.MULTILINE)
        with tempfile.TemporaryDirectory() as directory:
            candidate = Path(directory) / 'pialert.conf'
            candidate.write_text(source)
            with contextlib.redirect_stdout(io.StringIO()):
                migrate_config(str(candidate), str(ROOT), new_install=True)
            values = load_pialert_config(str(candidate), str(ROOT))
        self.assertTrue(values['OPENWRT_SSL'])
        self.assertEqual(values['OPENWRT_PORT'], 443)

    def test_migration_accepts_existing_negative_pushover_priority(self):
        source = re.sub(
            r'^PUSHOVER_PRIO\s*=.*$', 'PUSHOVER_PRIO = -2',
            EXAMPLE_CONFIG.read_text(), count=1, flags=re.MULTILINE)
        for key in ('PUSHOVER_RETRY', 'PUSHOVER_EXPIRE'):
            source = re.sub(
                r'^' + key + r'\s*=.*\n?', '', source,
                count=1, flags=re.MULTILINE)
        with tempfile.TemporaryDirectory() as directory:
            candidate = Path(directory) / 'pialert.conf'
            candidate.write_text(source)
            with contextlib.redirect_stdout(io.StringIO()):
                migrate_config(str(candidate), str(ROOT))
            values = load_pialert_config(str(candidate), str(ROOT))
        self.assertEqual(values['PUSHOVER_PRIO'], -2)
        self.assertEqual(values['PUSHOVER_RETRY'], 60)
        self.assertEqual(values['PUSHOVER_EXPIRE'], 3600)

    def test_legacy_configs_receive_optional_runtime_defaults(self):
        source = EXAMPLE_CONFIG.read_text()
        for key in ('OPENWRT_SSL', 'OPENWRT_PORT', 'PUSHOVER_RETRY',
                    'PUSHOVER_EXPIRE', 'PUBLISH_MQTT_SUBNET_STATUS'):
            source = re.sub(
                r'^' + key + r'\s*=.*\n?', '', source,
                count=1, flags=re.MULTILINE)
        values = load_pialert_config_source(
            source, expected_pialert_path=str(ROOT))
        self.assertFalse(values['OPENWRT_SSL'])
        self.assertEqual(values['OPENWRT_PORT'], 80)
        self.assertEqual(values['PUSHOVER_RETRY'], 60)
        self.assertEqual(values['PUSHOVER_EXPIRE'], 3600)
        self.assertFalse(values['PUBLISH_MQTT_SUBNET_STATUS'])

    def test_update_script_uses_key_based_config_migration(self):
        source = (ROOT / 'install' / 'pialert_update.sh').read_text()
        self.assertIn('migrate_pialert_config.py', source)
        self.assertNotIn('grep -Fq "# OpenWRT Configuration"', source)
        self.assertNotIn('# Shoutrrr[[:space:]]*$/', source)
        self.assertNotIn('chmod 777 "$PIALERT_HOME/config/pialert.conf"', source)
        self.assertIn('Configuration migration failed; continuing update.', source)
        self.assertNotIn('process_error "Invalid configuration after migration"', source)

    def test_install_and_update_packages_deliver_example_configuration(self):
        installer = (ROOT / 'install' / 'pialert_install.sh').read_text()
        updater = (ROOT / 'install' / 'pialert_update.sh').read_text()
        helper = (ROOT / 'install' / 'migrate_pialert_config.py').read_text()
        self.assertNotIn('pialert.example.conf', installer)
        self.assertNotIn('pialert.example.conf', updater)
        self.assertNotIn('pialert.example.conf', helper)
        self.assertIn('--exclude=pialert/config/pialert.conf', updater)
        self.assertNotIn('--exclude=pialert/config/pialert.example.conf', updater)

    def test_example_configuration_is_not_treated_as_a_backup(self):
        source = (ROOT / 'front' / 'php' / 'server' / 'files.php').read_text()
        self.assertIn("glob($Pia_Archive_Path . '/pialert-20*.bak')", source)
        self.assertNotRegex(
            source, r"unlink\([^\n]*pialert\.example\.conf")

    def test_disallowed_ast_payloads_are_rejected(self):
        source = CONFIG.read_text()
        payloads = (
            ("PRINT_LOG", "PRINT_LOG = __import__('os').system('id')"),
            ("PRINT_LOG", "PRINT_LOG = True\nUNKNOWN_SETTING = True"),
            ("PRINT_LOG", "PRINT_LOG = True\nimport os"),
            ("SMTP_PORT", "SMTP_PORT = True"),
        )
        for key, payload in payloads:
            candidate, count = re.subn(
                r'^' + re.escape(key) + r'\s*=.*$', payload, source,
                count=1, flags=re.MULTILINE)
            self.assertEqual(count, 1)
            with tempfile.NamedTemporaryFile('w', delete=False) as handle:
                handle.write(candidate)
                path = handle.name
            try:
                with self.assertRaises(ConfigValidationError):
                    load_pialert_config(path, str(ROOT))
            finally:
                Path(path).unlink()

    def test_type_validators_reject_type_confusion(self):
        for value in (1, 0, 'True', None):
            with self.assertRaises(ConfigValidationError):
                require_bool('TEST', value)
        for value in (True, False, 1.0, '1'):
            with self.assertRaises(ConfigValidationError):
                require_int('TEST', value, 0, 10)
        for value in (('a',), ['a', 1], "['a']"):
            with self.assertRaises(ConfigValidationError):
                require_string_list('TEST', value)

    def test_negative_notification_priorities_reach_range_validation(self):
        source = EXAMPLE_CONFIG.read_text()
        for key in ('PUSHOVER_PRIO', 'PUSHSAFER_PRIO'):
            for value in range(-2, 3):
                candidate = re.sub(
                    r'^' + key + r'\s*=.*$', '{} = {}'.format(key, value),
                    source, count=1, flags=re.MULTILINE)
                values = load_pialert_config_source(
                    candidate, expected_pialert_path=str(ROOT))
                self.assertEqual(values[key], value)
            for value in (-3, 3):
                candidate = re.sub(
                    r'^' + key + r'\s*=.*$', '{} = {}'.format(key, value),
                    source, count=1, flags=re.MULTILINE)
                with self.assertRaises(ConfigValidationError):
                    load_pialert_config_source(
                        candidate, expected_pialert_path=str(ROOT))

    def test_negative_integer_parser_remains_expression_free(self):
        source = EXAMPLE_CONFIG.read_text()
        for literal in ('-True', '--2', '-(1 + 1)', '-2.0', '+2'):
            candidate = re.sub(
                r'^PUSHOVER_PRIO\s*=.*$',
                'PUSHOVER_PRIO = ' + literal, source,
                count=1, flags=re.MULTILINE)
            with self.assertRaises(ConfigValidationError):
                load_pialert_config_source(candidate)
        candidate = re.sub(
            r'^REPORT_FROM\s*=.*$', 'REPORT_FROM = -2', source,
            count=1, flags=re.MULTILINE)
        with self.assertRaises(ConfigValidationError):
            load_pialert_config_source(candidate)

    def test_pushover_emergency_timing_is_cross_validated(self):
        source = EXAMPLE_CONFIG.read_text()
        source = re.sub(
            r'^PUSHOVER_RETRY\s*=.*$', 'PUSHOVER_RETRY = 120', source,
            count=1, flags=re.MULTILINE)
        source = re.sub(
            r'^PUSHOVER_EXPIRE\s*=.*$', 'PUSHOVER_EXPIRE = 60', source,
            count=1, flags=re.MULTILINE)
        with self.assertRaises(ConfigValidationError):
            load_pialert_config_source(source)

    def test_openwrt_host_rejects_schemes_and_embedded_ports(self):
        source = EXAMPLE_CONFIG.read_text()
        for host in ('router.lan', 'router_local', '192.168.1.1',
                     '2001:db8::1'):
            candidate = re.sub(
                r'^OPENWRT_IP\s*=.*$', 'OPENWRT_IP = {!r}'.format(host),
                source, count=1, flags=re.MULTILINE)
            values = load_pialert_config_source(candidate)
            self.assertEqual(values['OPENWRT_IP'], host)
        for host in ('', 'https://router.lan', 'router.lan:443',
                     'user@router.lan', 'router/luci', 'router..lan',
                     ('a' * 64) + '.lan'):
            candidate = re.sub(
                r'^OPENWRT_IP\s*=.*$', 'OPENWRT_IP = {!r}'.format(host),
                source, count=1, flags=re.MULTILINE)
            with self.assertRaises(ConfigValidationError):
                load_pialert_config_source(candidate)

    def test_scan_subnets_accepts_all_documented_forms_and_interface_names(self):
        valid = (
            ('--localnet', [['--localnet']]),
            ('--localnet --interface=wlp2s0',
             [['--localnet', '--interface=wlp2s0']]),
            ([
                '192.168.1.0/24 --interface=eth0',
                '192.168.2.0/24 --interface=ens18',
                '192.168.3.0/24 --interface=custom_bridge-42.100',
                '192.168.4.0/24 --interface=eth0:1',
             ], [
                ['192.168.1.0/24', '--interface=eth0'],
                ['192.168.2.0/24', '--interface=ens18'],
                ['192.168.3.0/24', '--interface=custom_bridge-42.100'],
                ['192.168.4.0/24', '--interface=eth0:1'],
             ]),
        )
        for value, expected in valid:
            self.assertEqual(build_arpscan_arguments(value), expected)

    def test_scan_subnets_rejects_invalid_or_ambiguous_arguments(self):
        invalid = (
            '', [], '--localnet;id', '--localnet --help',
            ['not a subnet'],
            ['192.168.1.0/24'],
            ['192.168.1.0/24 --interface=bad/name'],
            ['192.168.1.0/33 --interface=eth0'],
            ['2001:db8::/64 --interface=eth0'],
            ['192.168.1.0/24 --interface=eth0',
             '192.168.1.1/24 --interface=eth0'],
        )
        for value in invalid:
            with self.assertRaises(ConfigValidationError, msg=repr(value)):
                build_arpscan_arguments(value)

    def test_scan_subnets_enforces_the_bounded_pair_count(self):
        entries = [
            '10.{}.{}.0/24 --interface=scan{}'.format(
                index // 256, index % 256, index)
            for index in range(1024)
        ]
        self.assertEqual(len(build_arpscan_arguments(entries)), 1024)
        with self.assertRaises(ConfigValidationError):
            build_arpscan_arguments(entries + [
                '10.4.0.0/24 --interface=overflow'])

        target = '192.168.0.0/24'
        interface = '--interface=eth0'
        at_limit = target + (' ' * (512 - len(target) - len(interface))) + interface
        self.assertEqual(len(at_limit), 512)
        self.assertEqual(
            build_arpscan_arguments([at_limit]), [[target, interface]])
        with self.assertRaises(ConfigValidationError):
            build_arpscan_arguments([at_limit + ' '])

    def test_arpscan_runtime_uses_validated_argv_without_shell(self):
        source = (ROOT / 'back' / 'pialert.py').read_text()
        tree = ast.parse(source)
        function = next(
            node for node in tree.body
            if isinstance(node, ast.FunctionDef) and
            node.name == 'execute_arpscan_on_interface')
        segment = ast.get_source_segment(source, function)
        self.assertIn("+ subnets", segment)
        self.assertIn('subprocess.check_output', segment)
        self.assertNotIn('shell=True', segment)
        self.assertEqual(
            build_arpscan_arguments([
                '192.168.68.0/24 --interface=eth0.68',
                '192.168.123.0/24 --interface=eth0:1',
            ]), [
                ['192.168.68.0/24', '--interface=eth0.68'],
                ['192.168.123.0/24', '--interface=eth0:1'],
            ])

    def test_configuration_size_limit_is_measured_in_utf8_bytes(self):
        source = EXAMPLE_CONFIG.read_bytes()
        padding_length = MAX_CONFIG_BYTES - len(source) - 2
        self.assertGreater(padding_length, 0)
        exact = source + b'\n#' + (b'x' * padding_length)
        self.assertEqual(len(exact), MAX_CONFIG_BYTES)
        with tempfile.NamedTemporaryFile('wb', delete=False) as handle:
            handle.write(exact)
            path = handle.name
        try:
            load_pialert_config(path, str(ROOT))
            with open(path, 'ab') as handle:
                handle.write(b'x')
            with self.assertRaises(ConfigValidationError):
                load_pialert_config(path, str(ROOT))
        finally:
            Path(path).unlink()

        unicode_source = source + '# ä\n'.encode('utf-8')
        with tempfile.NamedTemporaryFile('wb', delete=False) as handle:
            handle.write(unicode_source)
            unicode_path = handle.name
        try:
            self.assertGreater(len(unicode_source), len(unicode_source.decode('utf-8')))
            load_pialert_config(unicode_path, str(ROOT))
        finally:
            Path(unicode_path).unlink()

    def test_ignore_lists_accept_documented_address_prefixes(self):
        values = load_pialert_config(str(CONFIG), str(ROOT), validate=False)
        values['MAC_IGNORE_LIST'] = ['40:22:d8:', 'aa:bb:cc:dd:ee:ff']
        values['IP_IGNORE_LIST'] = ['172.17.', '172.31.', '192.168.1.10',
                                    '2001:db8::1']
        validated = validate_loaded_config(values, str(ROOT))
        self.assertEqual(validated['MAC_IGNORE_LIST'], values['MAC_IGNORE_LIST'])
        self.assertEqual(validated['IP_IGNORE_LIST'], values['IP_IGNORE_LIST'])

    def test_ignore_lists_reject_malformed_address_prefixes(self):
        invalid_values = {
            'MAC_IGNORE_LIST': ('gg:', 'aa::bb', 'aa:bb:%',
                                'aa:bb:cc:dd:ee:ff:'),
            'IP_IGNORE_LIST': ('172..', '172.256.', '0172.17.',
                               '172.17.%', '172.17.0.0/16'),
        }
        for name, candidates in invalid_values.items():
            for candidate in candidates:
                values = load_pialert_config(str(CONFIG), str(ROOT), validate=False)
                values[name] = [candidate]
                with self.assertRaises(ConfigValidationError):
                    validate_loaded_config(values, str(ROOT))

    def test_secret_recovery_precedes_type_validation(self):
        source = CONFIG.read_text()
        candidate, count = re.subn(
            r'^SMTP_PASS\s*=.*$', lambda _: "SMTP_PASS = 'line\\ntext'", source,
            count=1, flags=re.MULTILINE)
        self.assertEqual(count, 1)
        with tempfile.NamedTemporaryFile('w', delete=False) as handle:
            handle.write(candidate)
            path = handle.name
        try:
            values = load_pialert_config(path, str(ROOT))
            self.assertEqual(values['SMTP_PASS'], r'line\ntext')
            validator_source = (ROOT / 'back' / 'config_validation.py').read_text()
            self.assertIn('_recover_legacy_secret_value', validator_source)
            for script in ('pialert.py', 'pialert_reporting_test.py'):
                script_source = (ROOT / 'back' / script).read_text()
                self.assertNotIn('recover_sensitive_config_values', script_source)
                self.assertNotIn('validate=False', script_source)
        finally:
            Path(path).unlink()

    def test_secret_values_must_be_strings(self):
        source = CONFIG.read_text()
        for literal in ('True', '123', 'None', '[]'):
            candidate, count = re.subn(
                r'^SMTP_PASS\s*=.*$', 'SMTP_PASS = ' + literal, source,
                count=1, flags=re.MULTILINE)
            self.assertEqual(count, 1)
            with tempfile.NamedTemporaryFile('w', delete=False) as handle:
                handle.write(candidate)
                path = handle.name
            try:
                with self.assertRaises(ConfigValidationError):
                    load_pialert_config(path, str(ROOT))
            finally:
                Path(path).unlink()

    def test_all_python_consumers_use_the_shared_validating_loader(self):
        for script in ('pialert.py', 'pialert_reporting_test.py',
                       'pialert_tools.py', 'validate_pialert_config.py'):
            source = (ROOT / 'back' / script).read_text()
            self.assertIn('load_pialert_config(', source)
            self.assertNotIn('execfile(', source)
            self.assertNotRegex(
                source, r'load_pialert_config\([^)]*validate\s*=\s*False')
        editor = (ROOT / 'back' / 'config_editor.py').read_text()
        migration = (ROOT / 'install' / 'migrate_pialert_config.py').read_text()
        self.assertIn('load_pialert_config_source(', editor)
        self.assertNotIn('def _legacy_secret_value', editor)
        self.assertIn('load_pialert_config(', migration)

    def test_migration_normalizes_only_former_pushsafer_integer_values(self):
        for old_priority in (-10, -3, 3, 10):
            source = re.sub(
                r'^PUSHSAFER_PRIO\s*=.*$',
                'PUSHSAFER_PRIO = {}'.format(old_priority),
                EXAMPLE_CONFIG.read_text(), count=1, flags=re.MULTILINE)
            with tempfile.TemporaryDirectory() as directory:
                candidate = Path(directory) / 'pialert.conf'
                candidate.write_text(source)
                output = io.StringIO()
                with contextlib.redirect_stdout(output):
                    migrate_config(str(candidate), str(ROOT))
                values = load_pialert_config(str(candidate), str(ROOT))
                self.assertEqual(values['PUSHSAFER_PRIO'], 0)
                self.assertIn('PUSHSAFER_PRIO', output.getvalue())

        for old_sound in (63, 1000):
            source = re.sub(
                r'^PUSHSAFER_SOUND\s*=.*$',
                'PUSHSAFER_SOUND = {}'.format(old_sound),
                EXAMPLE_CONFIG.read_text(), count=1, flags=re.MULTILINE)
            with tempfile.TemporaryDirectory() as directory:
                candidate = Path(directory) / 'pialert.conf'
                candidate.write_text(source)
                output = io.StringIO()
                with contextlib.redirect_stdout(output):
                    migrate_config(str(candidate), str(ROOT))
                values = load_pialert_config(str(candidate), str(ROOT))
                self.assertEqual(values['PUSHSAFER_SOUND'], 22)
                self.assertIn('PUSHSAFER_SOUND', output.getvalue())

        invalid = re.sub(
            r'^PUSHSAFER_PRIO\s*=.*$', "PUSHSAFER_PRIO = '3'",
            EXAMPLE_CONFIG.read_text(), count=1, flags=re.MULTILINE)
        with tempfile.TemporaryDirectory() as directory:
            candidate = Path(directory) / 'pialert.conf'
            candidate.write_text(invalid)
            with self.assertRaises(ConfigValidationError):
                migrate_config(str(candidate), str(ROOT))
            self.assertEqual(candidate.read_text(), invalid)

    def test_telegram_settings_are_optional_for_legacy_configs(self):
        values = load_pialert_config(str(CONFIG), str(ROOT), validate=False)
        values.pop('SHOUTRRR_BINARY', None)
        values.pop('TELEGRAM_BOT_TOKEN', None)
        values.pop('TELEGRAM_CHAT_IDS', None)
        validated = validate_loaded_config(values, str(ROOT))
        self.assertNotIn('SHOUTRRR_BINARY', validated)
        self.assertEqual(validated['TELEGRAM_BOT_TOKEN'], '')
        self.assertEqual(validated['TELEGRAM_CHAT_IDS'], [])

    def test_telegram_settings_accept_supported_destinations(self):
        values = load_pialert_config(str(CONFIG), str(ROOT), validate=False)
        values['TELEGRAM_BOT_TOKEN'] = '123456:opaque-token_value'
        values['TELEGRAM_CHAT_IDS'] = ['12345', '-1001234567890', '@AlertChannel']
        validated = validate_loaded_config(values, str(ROOT))
        self.assertEqual(validated['TELEGRAM_BOT_TOKEN'], values['TELEGRAM_BOT_TOKEN'])
        self.assertEqual(validated['TELEGRAM_CHAT_IDS'], values['TELEGRAM_CHAT_IDS'])

    def test_telegram_chat_ids_reject_invalid_values(self):
        invalid_lists = (
            '12345',
            [12345],
            ['0'],
            ['-0'],
            ['chat name'],
            ['@bad'],
            ['12345', '12345'],
            ['1'] * 33,
        )
        for invalid in invalid_lists:
            values = load_pialert_config(str(CONFIG), str(ROOT), validate=False)
            values['TELEGRAM_CHAT_IDS'] = invalid
            with self.assertRaises(ConfigValidationError):
                validate_loaded_config(values, str(ROOT))

    def test_telegram_token_rejects_invalid_types_and_controls(self):
        for invalid in (True, 123, [], 'line\nbreak', 'x' * 513):
            values = load_pialert_config(str(CONFIG), str(ROOT), validate=False)
            values['TELEGRAM_BOT_TOKEN'] = invalid
            with self.assertRaises(ConfigValidationError):
                validate_loaded_config(values, str(ROOT))

    def test_telegram_settings_reject_ast_expressions(self):
        source = CONFIG.read_text()
        for assignment in (
                "TELEGRAM_BOT_TOKEN = __import__('os').system('id')",
                "TELEGRAM_CHAT_IDS = list(('12345',))"):
            candidate = source + '\n' + assignment + '\n'
            with tempfile.NamedTemporaryFile('w', delete=False) as handle:
                handle.write(candidate)
                path = handle.name
            try:
                with self.assertRaises(ConfigValidationError):
                    load_pialert_config(path, str(ROOT))
            finally:
                Path(path).unlink()

    def test_web_editor_uses_the_shared_non_executing_parser(self):
        php_source = (ROOT / 'front' / 'php' / 'server' / 'files.php').read_text()
        php_helper = (ROOT / 'front' / 'php' / 'server' / 'config_file.php').read_text()
        python_helper = (ROOT / 'back' / 'config_editor.py').read_text()
        self.assertIn("require_once __DIR__ . '/config_file.php'", php_source)
        self.assertIn('pialert_prepare_editor_candidate(', php_source)
        self.assertIn('config_editor.py', php_helper)
        self.assertIn(
            "define('PIALERT_CONFIG_MAX_BYTES', {});".format(MAX_CONFIG_BYTES),
            php_helper)
        self.assertIn('load_pialert_config_source(', python_helper)
        self.assertIn('MASKED_SECRET_KEYS', python_helper)
        self.assertNotIn('parse_ini_string($configContent)', php_source)
        self.assertNotIn('function serializeList(', php_source)
        self.assertNotIn('$config_template =', php_source)

    def test_obsolete_shoutrrr_setting_is_not_emitted_or_documented(self):
        php_source = (ROOT / 'front' / 'php' / 'server' / 'files.php').read_text()
        editor_source = (ROOT / 'back' / 'config_editor.py').read_text()
        values = load_pialert_config(str(CONFIG), str(ROOT))
        self.assertNotIn('SHOUTRRR_BINARY', values)
        self.assertNotIn('SHOUTRRR_BINARY', ALL_KEYS)
        self.assertNotIn(
            "SHOUTRRR_BINARY            = '", php_source)
        self.assertIn('_remove_deprecated_assignments', editor_source)
        self.assertNotIn(
            'SHOUTRRR_BINARY',
            (ROOT / 'docs' / 'PIALERT_CONF.md').read_text())

        for script in ('pialert_install.sh', 'pialert_update.sh'):
            source = (ROOT / 'install' / script).read_text()
            self.assertNotRegex(
                source, re.compile(r'^\s*SHOUTRRR_BINARY\s*=', re.MULTILINE),
                msg='installer scripts must not re-add SHOUTRRR_BINARY')

if __name__ == '__main__':
    unittest.main()

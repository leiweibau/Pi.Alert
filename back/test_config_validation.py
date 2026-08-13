#!/usr/bin/env python3
import ast
import re
import sys
import tempfile
import warnings
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from config_validation import (
    ALL_KEYS,
    ConfigValidationError,
    load_pialert_config,
    load_version_config,
    require_bool,
    validate_loaded_config,
    require_int,
    require_string_list,
)


ROOT = Path(__file__).resolve().parent.parent
ACTIVE_CONFIG = ROOT / 'config' / 'pialert.conf'


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
            values = load_pialert_config(path, str(ROOT), validate=False)
            for script in ('pialert.py', 'pialert_reporting_test.py'):
                tree = ast.parse((ROOT / 'back' / script).read_text())
                function = next(node for node in tree.body
                                if isinstance(node, ast.FunctionDef) and
                                node.name == 'recover_sensitive_config_values')
                namespace = {'re': re}
                namespace.update(values)
                exec(compile(ast.Module(body=[function], type_ignores=[]), script, 'exec'), namespace)
                namespace['recover_sensitive_config_values'](path, ['SMTP_PASS'])
                validated = validate_loaded_config(
                    {name: namespace[name] for name in values}, str(ROOT))
                self.assertEqual(validated['SMTP_PASS'], r'line\ntext')
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

    def test_php_and_python_config_schema_keys_match(self):
        php_source = (ROOT / 'front' / 'php' / 'server' / 'files.php').read_text()
        schema_match = re.search(
            r'\$groups\s*=\s*\[(.*?)\n\s*\];', php_source, re.DOTALL)
        self.assertIsNotNone(schema_match)
        groups = re.findall(
            r"'(?:bool|int|string|list|special)'\s*=>\s*'([^']*)'",
            schema_match.group(1))
        php_keys = set()
        for group in groups:
            php_keys.update(group.split())
        self.assertEqual(set(ALL_KEYS), php_keys)

    def test_php_masks_and_escapes_telegram_secrets(self):
        php_source = (ROOT / 'front' / 'php' / 'server' / 'files.php').read_text()
        mask_match = re.search(
            r'\$maskKeys\s*=\s*\[(.*?)\n\];', php_source, re.DOTALL)
        self.assertIsNotNone(mask_match)
        self.assertIn("'TELEGRAM_BOT_TOKEN'", mask_match.group(1))
        self.assertIn("'TELEGRAM_BOT_TOKEN_URL'", mask_match.group(1))
        self.assertIn(
            "escape_python_config_string($telegramBotToken)", php_source)
        self.assertIn(
            "escape_python_config_string($configArray['TELEGRAM_BOT_TOKEN_URL'])",
            php_source)

    def test_obsolete_shoutrrr_setting_is_not_emitted_or_documented(self):
        php_source = (ROOT / 'front' / 'php' / 'server' / 'files.php').read_text()
        values = load_pialert_config(str(CONFIG), str(ROOT))
        self.assertNotIn('SHOUTRRR_BINARY', values)
        self.assertNotIn('SHOUTRRR_BINARY', ALL_KEYS)
        self.assertNotIn(
            "SHOUTRRR_BINARY            = '", php_source)
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

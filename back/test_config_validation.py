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
    ConfigValidationError,
    load_pialert_config,
    require_bool,
    validate_loaded_config,
    require_int,
    require_string_list,
)


ROOT = Path(__file__).resolve().parent.parent
CONFIG = ROOT / 'config' / 'pialert.conf'


class ConfigValidationTests(unittest.TestCase):
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

if __name__ == '__main__':
    unittest.main()


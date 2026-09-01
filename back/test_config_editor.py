#!/usr/bin/env python3
import ast
import re
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from config_editor import mask_config_source, mask_secret, prepare_editor_candidate
from config_validation import ConfigValidationError, load_pialert_config_source


ROOT = Path(__file__).resolve().parent.parent
EXAMPLE = (ROOT / 'config' / 'pialert.example.conf').read_text(encoding='utf-8')


def replace_assignment(source, key, literal):
    candidate, count = re.subn(
        r'^' + re.escape(key) + r'\s*=.*$',
        lambda _match: '{} = {}'.format(key, literal),
        source, count=1, flags=re.MULTILINE)
    if count != 1:
        raise AssertionError('assignment not found: ' + key)
    return candidate


class ConfigEditorTests(unittest.TestCase):
    def backup_source(self):
        source = replace_assignment(
            EXAMPLE, 'PIALERT_WEB_PASSWORD', repr("S'ecret\\value"))
        source = replace_assignment(
            source, 'TELEGRAM_BOT_TOKEN', repr('123456:telegram-token'))
        source = replace_assignment(
            source, 'TELEGRAM_BOT_TOKEN_URL', repr('https://api.telegram.test/token'))
        return source

    def test_masked_secrets_round_trip_from_the_current_backup(self):
        backup = self.backup_source()
        masked = mask_config_source(backup)
        self.assertNotIn("S'ecret\\value", masked)
        self.assertIn(repr(mask_secret("S'ecret\\value")), masked)
        self.assertNotIn('123456:telegram-token', masked)

        submitted = replace_assignment(
            masked, 'SCAN_SUBNETS',
            "['192.168.1.0/24 --interface=eth0',"
            "'192.168.2.0/24 --interface=wlp2s0',"
            "'192.168.3.0/24 --interface=custom_bridge-42.100',"
            "'192.168.4.0/24 --interface=eth0:1']")
        submitted = replace_assignment(
            submitted, 'REPORT_FROM', repr("O'Reilly, #tag = \\server"))
        submitted = replace_assignment(
            submitted, 'HOSTNAME_IGNORE_LIST',
            repr(['comma,value', "O'Reilly", '#hash=equals',
                  r'back\slash', 'Gerät']))
        submitted += "\n# User comment retained by the editor\n"
        candidate, metadata = prepare_editor_candidate(
            submitted, backup, str(ROOT))
        values = load_pialert_config_source(candidate, expected_pialert_path=str(ROOT))

        self.assertEqual(values['PIALERT_WEB_PASSWORD'], "S'ecret\\value")
        self.assertEqual(values['TELEGRAM_BOT_TOKEN'], '123456:telegram-token')
        self.assertEqual(values['REPORT_FROM'], "O'Reilly, #tag = \\server")
        self.assertEqual(values['HOSTNAME_IGNORE_LIST'],
                         ['comma,value', "O'Reilly", '#hash=equals',
                          r'back\slash', 'Gerät'])
        self.assertEqual(len(values['SCAN_SUBNETS']), 4)
        self.assertIn('# User comment retained by the editor', candidate)
        self.assertFalse(metadata['password_changed'])
        self.assertEqual(metadata['changed_secrets'], [])

    def test_complete_schema_round_trip_preserves_values_and_comments(self):
        source = EXAMPLE.replace('\n', '\r\n')
        source = replace_assignment(
            source, 'REPORT_TO', repr('user@example.test'))
        source = source.replace(
            "REPORT_TO = 'user@example.test'",
            "REPORT_TO = 'user@example.test'  # retained inline comment")
        candidate, metadata = prepare_editor_candidate(source, source, str(ROOT))
        original_values = load_pialert_config_source(
            source, expected_pialert_path=str(ROOT))
        candidate_values = load_pialert_config_source(
            candidate, expected_pialert_path=str(ROOT))
        self.assertEqual(candidate_values, original_values)
        self.assertIn('# retained inline comment', candidate)
        self.assertIn('\r\n', candidate)
        self.assertEqual(metadata['key_count'], len(original_values))

    def test_changed_secret_is_serialized_as_data_and_reported(self):
        backup = self.backup_source()
        submitted = mask_config_source(backup)
        submitted = replace_assignment(
            submitted, 'PIALERT_WEB_PASSWORD', repr("N'ew\\password"))
        candidate, metadata = prepare_editor_candidate(
            submitted, backup, str(ROOT))
        values = load_pialert_config_source(candidate, expected_pialert_path=str(ROOT))
        self.assertEqual(values['PIALERT_WEB_PASSWORD'], "N'ew\\password")
        self.assertTrue(metadata['password_changed'])
        self.assertIn('PIALERT_WEB_PASSWORD', metadata['changed_secrets'])

    def test_short_secrets_are_masked_and_restored(self):
        backup = replace_assignment(EXAMPLE, 'SMTP_PASS', repr('ab'))
        masked = mask_config_source(backup)
        self.assertIn("SMTP_PASS = '**'", masked)
        candidate, _ = prepare_editor_candidate(masked, backup, str(ROOT))
        values = load_pialert_config_source(candidate, expected_pialert_path=str(ROOT))
        self.assertEqual(values['SMTP_PASS'], 'ab')

    def test_legacy_backslash_secret_is_preserved_as_an_opaque_value(self):
        backup = replace_assignment(EXAMPLE, 'SMTP_PASS', r"'legacy\npassword'")
        masked = mask_config_source(backup)
        candidate, _ = prepare_editor_candidate(masked, backup, str(ROOT))
        values = load_pialert_config_source(candidate, expected_pialert_path=str(ROOT))
        self.assertEqual(values['SMTP_PASS'], r'legacy\npassword')

    def test_deprecated_assignments_are_removed_and_optional_values_added(self):
        backup = self.backup_source()
        submitted = mask_config_source(backup)
        submitted = re.sub(
            r'^TELEGRAM_CHAT_IDS\s*=.*\n?', '', submitted,
            count=1, flags=re.MULTILINE)
        submitted += (
            "\nSHOUTRRR_BINARY = 'arm64'\n"
            "# SHOUTRRR_BINARY = 'x86'\n")
        candidate, _ = prepare_editor_candidate(submitted, backup, str(ROOT))
        values = load_pialert_config_source(candidate, expected_pialert_path=str(ROOT))
        self.assertEqual(values['TELEGRAM_CHAT_IDS'], [])
        self.assertNotIn('SHOUTRRR_BINARY', candidate)
        self.assertIn('# Settings added by the Pi.Alert configuration editor', candidate)

    def test_openwrt_and_pushover_settings_survive_editor_round_trip(self):
        backup = self.backup_source()
        submitted = replace_assignment(
            mask_config_source(backup), 'OPENWRT_SSL', 'False')
        submitted = replace_assignment(submitted, 'OPENWRT_PORT', '8443')
        submitted = replace_assignment(submitted, 'PUSHOVER_PRIO', '-2')
        submitted = replace_assignment(submitted, 'PUSHOVER_RETRY', '120')
        submitted = replace_assignment(submitted, 'PUSHOVER_EXPIRE', '7200')
        candidate, _ = prepare_editor_candidate(
            submitted, backup, str(ROOT))
        values = load_pialert_config_source(
            candidate, expected_pialert_path=str(ROOT))
        self.assertFalse(values['OPENWRT_SSL'])
        self.assertEqual(values['OPENWRT_PORT'], 8443)
        self.assertEqual(values['PUSHOVER_PRIO'], -2)
        self.assertEqual(values['PUSHOVER_RETRY'], 120)
        self.assertEqual(values['PUSHOVER_EXPIRE'], 7200)

    def test_editor_adds_legacy_compatibility_defaults(self):
        backup = self.backup_source()
        for key in ('OPENWRT_SSL', 'OPENWRT_PORT', 'PUSHOVER_RETRY',
                    'PUSHOVER_EXPIRE', 'PUBLISH_MQTT_SUBNET_STATUS'):
            backup = re.sub(
                r'^' + key + r'\s*=.*\n?', '', backup,
                count=1, flags=re.MULTILINE)
        submitted = mask_config_source(backup)
        candidate, _ = prepare_editor_candidate(
            submitted, backup, str(ROOT))
        values = load_pialert_config_source(
            candidate, expected_pialert_path=str(ROOT))
        self.assertFalse(values['OPENWRT_SSL'])
        self.assertEqual(values['OPENWRT_PORT'], 80)
        self.assertEqual(values['PUSHOVER_RETRY'], 60)
        self.assertEqual(values['PUSHOVER_EXPIRE'], 3600)
        self.assertFalse(values['PUBLISH_MQTT_SUBNET_STATUS'])

    def test_editor_accepts_only_boolean_subnet_mqtt_setting(self):
        backup = self.backup_source()
        for literal, expected in (("True", True), ("False", False)):
            submitted = replace_assignment(
                mask_config_source(backup), 'PUBLISH_MQTT_SUBNET_STATUS', literal)
            candidate, _ = prepare_editor_candidate(submitted, backup, str(ROOT))
            values = load_pialert_config_source(
                candidate, expected_pialert_path=str(ROOT))
            self.assertIs(values['PUBLISH_MQTT_SUBNET_STATUS'], expected)
        for literal in ("'True'", "1", "None", "not False"):
            submitted = replace_assignment(
                mask_config_source(backup), 'PUBLISH_MQTT_SUBNET_STATUS', literal)
            with self.assertRaises(ConfigValidationError):
                prepare_editor_candidate(submitted, backup, str(ROOT))

    def test_editor_rejects_invalid_openwrt_and_priority_values(self):
        backup = self.backup_source()
        invalid_assignments = (
            ('OPENWRT_SSL', "'true'"),
            ('OPENWRT_PORT', '0'),
            ('OPENWRT_PORT', '65536'),
            ('OPENWRT_IP', "'https://router.lan'"),
            ('PUSHOVER_PRIO', '-3'),
            ('PUSHSAFER_PRIO', '3'),
            ('PUSHSAFER_SOUND', '63'),
            ('PUSHOVER_RETRY', '29'),
        )
        for key, literal in invalid_assignments:
            submitted = replace_assignment(
                mask_config_source(backup), key, literal)
            with self.assertRaises(ConfigValidationError, msg=key):
                prepare_editor_candidate(submitted, backup, str(ROOT))

    def test_invalid_scan_value_is_rejected_before_candidate_output(self):
        backup = self.backup_source()
        submitted = replace_assignment(
            mask_config_source(backup), 'SCAN_SUBNETS', "['not a subnet']")
        with self.assertRaises(ConfigValidationError):
            prepare_editor_candidate(submitted, backup, str(ROOT))

    def test_editor_keeps_last_valid_submitted_scan_assignment(self):
        backup = replace_assignment(
            self.backup_source(), 'SCAN_SUBNETS',
            repr('--localnet --interface=backup0'))
        submitted = replace_assignment(
            mask_config_source(backup), 'SCAN_SUBNETS',
            "'--localnet --interface=editor0'\n"
            "SCAN_SUBNETS = ['192.168.50.0/24 --interface=editor1']")

        candidate, metadata = prepare_editor_candidate(
            submitted, backup, str(ROOT))
        values = load_pialert_config_source(
            candidate, expected_pialert_path=str(ROOT))
        active_assignments = [
            statement for statement in ast.parse(candidate).body
            if (isinstance(statement, ast.Assign) and
                isinstance(statement.targets[0], ast.Name) and
                statement.targets[0].id == 'SCAN_SUBNETS')
        ]

        self.assertEqual(values['SCAN_SUBNETS'],
                         ['192.168.50.0/24 --interface=editor1'])
        self.assertNotIn('backup0', candidate)
        self.assertNotIn('editor0', candidate)
        self.assertEqual(len(active_assignments), 1)
        self.assertEqual(metadata['scan_subnets_assignments_removed'], 1)

    def test_editor_keeps_earlier_valid_scan_if_later_one_is_invalid(self):
        backup = self.backup_source()
        submitted = replace_assignment(
            mask_config_source(backup), 'SCAN_SUBNETS',
            "'--localnet --interface=last-valid'\n"
            "SCAN_SUBNETS = ['not a subnet']")

        candidate, metadata = prepare_editor_candidate(
            submitted, backup, str(ROOT))
        values = load_pialert_config_source(
            candidate, expected_pialert_path=str(ROOT))

        self.assertEqual(values['SCAN_SUBNETS'],
                         '--localnet --interface=last-valid')
        self.assertNotIn('not a subnet', candidate)
        self.assertEqual(metadata['scan_subnets_assignments_removed'], 1)

    def test_editor_keeps_later_valid_scan_if_earlier_one_is_invalid(self):
        backup = self.backup_source()
        submitted = replace_assignment(
            mask_config_source(backup), 'SCAN_SUBNETS',
            "['not a subnet']\n"
            "SCAN_SUBNETS = '--localnet --interface=wlan0'")

        candidate, _ = prepare_editor_candidate(
            submitted, backup, str(ROOT))
        values = load_pialert_config_source(
            candidate, expected_pialert_path=str(ROOT))

        self.assertEqual(values['SCAN_SUBNETS'],
                         '--localnet --interface=wlan0')
        self.assertNotIn('not a subnet', candidate)

    def test_editor_rejects_duplicate_scan_assignments_if_none_is_valid(self):
        backup = self.backup_source()
        submitted = replace_assignment(
            mask_config_source(backup), 'SCAN_SUBNETS',
            "['not a subnet']\nSCAN_SUBNETS = '--localnet;id'")

        with self.assertRaises(ConfigValidationError):
            prepare_editor_candidate(submitted, backup, str(ROOT))

    def test_backend_loader_remains_strict_for_duplicate_scan_assignments(self):
        source = replace_assignment(
            EXAMPLE, 'SCAN_SUBNETS',
            "'--localnet'\nSCAN_SUBNETS = '--localnet --interface=wlan0'")
        with self.assertRaises(ConfigValidationError):
            load_pialert_config_source(source)

    def test_multiline_scan_list_supported_by_backend_survives_editor(self):
        backup = self.backup_source()
        submitted = replace_assignment(
            mask_config_source(backup), 'SCAN_SUBNETS',
            "[\n    '192.168.10.0/24 --interface=br-lan',\n"
            "    '192.168.20.0/24 --interface=wlan0',\n]")
        candidate, _ = prepare_editor_candidate(submitted, backup, str(ROOT))
        values = load_pialert_config_source(candidate, expected_pialert_path=str(ROOT))
        self.assertEqual(values['SCAN_SUBNETS'], [
            '192.168.10.0/24 --interface=br-lan',
            '192.168.20.0/24 --interface=wlan0',
        ])

    def test_vlan_scan_list_survives_editor(self):
        backup = self.backup_source()
        value = [
            '192.168.65.0/24 --vlan=1',
            '192.168.42.0/24 --interface=eth0 --vlan=42',
            '192.168.35.0/24 --vlan=35',
        ]
        submitted = replace_assignment(
            mask_config_source(backup), 'SCAN_SUBNETS', repr(value))
        candidate, _ = prepare_editor_candidate(submitted, backup, str(ROOT))
        values = load_pialert_config_source(
            candidate, expected_pialert_path=str(ROOT))
        self.assertEqual(values['SCAN_SUBNETS'], value)

    def test_both_localnet_string_forms_survive_editor(self):
        backup = self.backup_source()
        for value in ('--localnet', '--localnet --interface=wlan0'):
            submitted = replace_assignment(
                mask_config_source(backup), 'SCAN_SUBNETS', repr(value))
            candidate, _ = prepare_editor_candidate(
                submitted, backup, str(ROOT))
            values = load_pialert_config_source(
                candidate, expected_pialert_path=str(ROOT))
            self.assertEqual(values['SCAN_SUBNETS'], value)

    def test_backup_is_the_only_source_for_a_masked_secret(self):
        old_backup = replace_assignment(
            EXAMPLE, 'PIALERT_WEB_PASSWORD', repr('old-backup-secret'))
        current_backup = replace_assignment(
            EXAMPLE, 'PIALERT_WEB_PASSWORD', repr('current-backup-secret'))
        submitted = mask_config_source(current_backup)
        old_mask = mask_secret('old-backup-secret')
        self.assertNotIn(repr(old_mask), submitted)
        candidate, _ = prepare_editor_candidate(
            submitted, current_backup, str(ROOT))
        values = load_pialert_config_source(candidate, expected_pialert_path=str(ROOT))
        self.assertEqual(values['PIALERT_WEB_PASSWORD'], 'current-backup-secret')
        self.assertNotEqual(values['PIALERT_WEB_PASSWORD'],
                            load_pialert_config_source(old_backup)['PIALERT_WEB_PASSWORD'])


if __name__ == '__main__':
    unittest.main()

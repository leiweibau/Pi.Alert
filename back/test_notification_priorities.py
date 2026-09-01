#!/usr/bin/env python3
import contextlib
import importlib
import io
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

import config_validation
from notification_http import (
    NotificationDeliveryError,
    build_pushover_payload,
    build_pushsafer_payload,
    send_pushover_notification,
    send_pushsafer_notification,
)


ROOT = Path(__file__).resolve().parent.parent


class FakeResponse:
    def __init__(self, status=1, http_error=None, invalid_json=False):
        self.status = status
        self.http_error = http_error
        self.invalid_json = invalid_json

    def raise_for_status(self):
        if self.http_error is not None:
            raise self.http_error

    def json(self):
        if self.invalid_json:
            raise ValueError('invalid json')
        return {'status': self.status}


class FakeSession:
    def __init__(self, response=None, error=None):
        self.response = response or FakeResponse()
        self.error = error
        self.calls = []

    def post(self, url, data, timeout):
        self.calls.append((url, data, timeout))
        if self.error is not None:
            raise self.error
        return self.response


class NotificationPriorityTests(unittest.TestCase):
    def test_pushsafer_payload_keeps_priority_and_sound(self):
        for priority in range(-2, 3):
            payload = build_pushsafer_payload(
                'message', 'title', 'https://dashboard', 'token', 'a',
                priority, 62)
            self.assertEqual(payload['pr'], priority)
            self.assertEqual(payload['s'], 62)

    def test_pushover_payload_adds_emergency_fields_only_for_priority_two(self):
        for priority in range(-2, 3):
            payload = build_pushover_payload(
                'message', 'title', 'token', 'user', priority, 'siren',
                60, 3600)
            self.assertEqual(payload['priority'], priority)
            if priority == 2:
                self.assertEqual(payload['retry'], 60)
                self.assertEqual(payload['expire'], 3600)
            else:
                self.assertNotIn('retry', payload)
                self.assertNotIn('expire', payload)

    def test_successful_requests_have_timeouts_and_validate_pushover_json(self):
        pushsafer = FakeSession()
        send_pushsafer_notification(
            'message', 'title', 'dashboard', 'token', 'a', -2, 22,
            session=pushsafer)
        self.assertGreater(pushsafer.calls[0][2], 0)

        pushover = FakeSession(FakeResponse(status=1))
        send_pushover_notification(
            'message', 'title', 'token', 'user', 2, 'siren', 60, 3600,
            session=pushover)
        self.assertEqual(pushover.calls[0][1]['priority'], 2)
        self.assertEqual(pushover.calls[0][1]['retry'], 60)

    def test_api_and_transport_failures_do_not_expose_payload_secrets(self):
        cases = (
            lambda: send_pushsafer_notification(
                'private message', 'title', 'dashboard', 'private-token',
                'a', 0, 22,
                session=FakeSession(error=RuntimeError('private-token'))),
            lambda: send_pushsafer_notification(
                'private message', 'title', 'dashboard', 'private-token',
                'a', 0, 22,
                session=FakeSession(FakeResponse(status=0))),
            lambda: send_pushsafer_notification(
                'private message', 'title', 'dashboard', 'private-token',
                'a', 0, 22,
                session=FakeSession(FakeResponse(invalid_json=True))),
            lambda: send_pushover_notification(
                'private message', 'title', 'private-token', 'private-user',
                0, 'siren', 60, 3600,
                session=FakeSession(FakeResponse(status=0))),
            lambda: send_pushover_notification(
                'private message', 'title', 'private-token', 'private-user',
                0, 'siren', 60, 3600,
                session=FakeSession(FakeResponse(invalid_json=True))),
        )
        for action in cases:
            with self.assertRaises(NotificationDeliveryError) as raised:
                action()
            message = str(raised.exception)
            self.assertNotIn('private-token', message)
            self.assertNotIn('private-user', message)
            self.assertNotIn('private message', message)

    def test_reporting_test_continues_after_one_channel_fails(self):
        original_loader = config_validation.load_pialert_config
        example_values = original_loader(
            str(ROOT / 'config' / 'pialert.example.conf'), str(ROOT))
        config_validation.load_pialert_config = (
            lambda *_args, **_kwargs: dict(example_values))
        sys.modules.pop('pialert_reporting_test', None)
        try:
            module = importlib.import_module('pialert_reporting_test')
        finally:
            config_validation.load_pialert_config = original_loader

        for name in (
                'REPORT_MAIL', 'REPORT_MAIL_WEBMON', 'REPORT_PUSHSAFER',
                'REPORT_PUSHSAFER_WEBMON', 'REPORT_PUSHOVER',
                'REPORT_PUSHOVER_WEBMON', 'REPORT_TELEGRAM',
                'REPORT_TELEGRAM_WEBMON', 'REPORT_NTFY',
                'REPORT_NTFY_WEBMON', 'REPORT_DISCORD',
                'REPORT_DISCORD_WEBMON', 'REPORT_WEBGUI',
                'REPORT_WEBGUI_WEBMON'):
            setattr(module, name, False)
        module.REPORT_PUSHSAFER = True
        module.REPORT_PUSHOVER = True
        calls = []
        module.send_pushsafer_test = lambda _message: (_ for _ in ()).throw(
            RuntimeError('failed'))
        module.send_pushover_test = lambda _message: calls.append('pushover')
        with contextlib.redirect_stdout(io.StringIO()):
            result = module.sending_notifications_test('Test')
        self.assertEqual(result, 0)
        self.assertEqual(calls, ['pushover'])

    def test_runtime_scripts_no_longer_overwrite_configured_priorities(self):
        for script in ('pialert.py', 'pialert_reporting_test.py'):
            source = (ROOT / 'back' / script).read_text()
            self.assertNotIn('PUSHOVER_PRIO = 0', source)
            self.assertNotIn('PUSHSAFER_PRIO = 0', source)
            self.assertIn('send_pushover_notification(', source)
            self.assertIn('send_pushsafer_notification(', source)


if __name__ == '__main__':
    unittest.main()

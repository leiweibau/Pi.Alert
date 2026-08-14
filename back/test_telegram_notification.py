#!/usr/bin/env python3
import ast
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from telegram_notification import (
    TELEGRAM_MESSAGE_LIMIT,
    TelegramConfigError,
    TelegramDeliveryError,
    parse_legacy_shoutrrr_url,
    prepare_telegram_message,
    resolve_telegram_credentials,
    send_telegram_message,
    split_telegram_message,
)


ROOT = Path(__file__).resolve().parent.parent
TOKEN = '123456:ABC_def-test'


class FakeResponse:
    def __init__(self, status_code=200, payload=None, json_error=None):
        self.status_code = status_code
        self.payload = {'ok': True} if payload is None else payload
        self.json_error = json_error

    def json(self):
        if self.json_error is not None:
            raise self.json_error
        return self.payload


class FakeHttpClient:
    def __init__(self, responses=None, error=None):
        self.responses = list(responses or [FakeResponse()])
        self.error = error
        self.calls = []

    def post(self, url, **kwargs):
        self.calls.append((url, kwargs))
        if self.error is not None:
            raise self.error
        if len(self.responses) > 1:
            return self.responses.pop(0)
        return self.responses[0]


class TelegramNotificationTests(unittest.TestCase):
    def test_direct_send_uses_post_data_and_hardened_transport(self):
        message = "Device $(touch /tmp/x); `id` \\\"quoted\\\"\nnext"
        client = FakeHttpClient()
        count = send_telegram_message(
            TOKEN, ['12345'], message, http_client=client)

        self.assertEqual(count, 1)
        self.assertEqual(len(client.calls), 1)
        url, kwargs = client.calls[0]
        self.assertEqual(url, 'https://api.telegram.org/bot{}/sendMessage'.format(TOKEN))
        self.assertEqual(kwargs['data']['chat_id'], '12345')
        self.assertEqual(kwargs['data']['text'], message)
        self.assertEqual(kwargs['data']['link_preview_options'],
                         '{"is_disabled":true}')
        self.assertEqual(kwargs['data']['disable_notification'], 'false')
        self.assertEqual(kwargs['timeout'], (5, 15))
        self.assertFalse(kwargs['allow_redirects'])
        self.assertTrue(kwargs['verify'])
        self.assertNotIn('parse_mode', kwargs['data'])

    def test_multiple_destinations_are_sent_individually(self):
        client = FakeHttpClient([FakeResponse(), FakeResponse(), FakeResponse()])
        count = send_telegram_message(
            TOKEN, ['12345', '-1001234567890', '@AlertChannel'], 'test',
            http_client=client)
        self.assertEqual(count, 3)
        self.assertEqual(
            [call[1]['data']['chat_id'] for call in client.calls],
            ['12345', '-1001234567890', '@AlertChannel'])

    def test_message_title_is_plain_text_and_not_duplicated(self):
        body = 'Report Date: now\nServer: host\n\nEvents\n---'
        title = 'Pi.Alert - Events'
        prepared = prepare_telegram_message(body, title)
        self.assertTrue(prepared.startswith(title + '\n\n'))
        self.assertEqual(prepare_telegram_message(prepared, title), prepared)

    def test_long_messages_split_without_data_loss(self):
        message = ('a' * 3000) + '\n' + ('b' * 3000) + ' ü'
        parts = split_telegram_message(message)
        self.assertGreater(len(parts), 1)
        self.assertEqual(''.join(parts), message)
        self.assertTrue(all(len(part) <= TELEGRAM_MESSAGE_LIMIT for part in parts))
        self.assertEqual(split_telegram_message('x' * TELEGRAM_MESSAGE_LIMIT),
                         ['x' * TELEGRAM_MESSAGE_LIMIT])

    def test_valid_legacy_url_is_parsed_without_execution(self):
        legacy = ('telegram://{}@telegram?chats=-100123%2C12345'
                  '&preview=No').format(TOKEN)
        token, chat_ids, options = parse_legacy_shoutrrr_url(legacy)
        self.assertEqual(token, TOKEN)
        self.assertEqual(chat_ids, ['-100123', '12345'])
        self.assertTrue(options['disable_preview'])
        self.assertFalse(options['disable_notification'])

    def test_legacy_delivery_flags_are_preserved(self):
        legacy = ('telegram://{}@telegram?chats=12345&preview=Yes'
                  '&notification=No').format(TOKEN)
        _, _, options = parse_legacy_shoutrrr_url(legacy)
        self.assertFalse(options['disable_preview'])
        self.assertTrue(options['disable_notification'])

        _, _, defaults = parse_legacy_shoutrrr_url(
            'telegram://{}@telegram?chats=12345'.format(TOKEN))
        self.assertFalse(defaults['disable_preview'])
        self.assertFalse(defaults['disable_notification'])

    def test_direct_credentials_take_precedence_over_legacy(self):
        token, chat_ids, options = resolve_telegram_credentials(
            'opaque direct token', ['12345'], 'not a legacy URL')
        self.assertEqual(token, 'opaque direct token')
        self.assertEqual(chat_ids, ['12345'])
        self.assertTrue(options['disable_preview'])
        self.assertFalse(options['disable_notification'])

    def test_partial_direct_configuration_fails_closed(self):
        for token, chat_ids in ((TOKEN, []), ('', ['12345'])):
            with self.assertRaises(TelegramConfigError):
                resolve_telegram_credentials(
                    token, chat_ids,
                    'telegram://{}@telegram?chats=999'.format(TOKEN))

    def test_invalid_legacy_urls_are_rejected(self):
        invalid = (
            '',
            'https://{}@telegram?chats=123'.format(TOKEN),
            'telegram://{}@evil.example?chats=123'.format(TOKEN),
            'telegram://{}@telegram?chats=123&chats=456'.format(TOKEN),
            'telegram://{}@telegram?chats=123&unknown=yes'.format(TOKEN),
            'telegram://{}@telegram?chats=123&preview=perhaps'.format(TOKEN),
            'telegram://{}@telegram?chats=%ZZ'.format(TOKEN),
            'telegram://bad-token@telegram?chats=123',
        )
        for legacy in invalid:
            with self.assertRaises(TelegramConfigError):
                parse_legacy_shoutrrr_url(legacy)

    def test_transport_errors_never_expose_token_or_url(self):
        client = FakeHttpClient(error=RuntimeError(
            'failed https://api.telegram.org/bot{}/sendMessage'.format(TOKEN)))
        with self.assertRaises(TelegramDeliveryError) as context:
            send_telegram_message(TOKEN, ['12345'], 'test', http_client=client)
        error = str(context.exception)
        self.assertNotIn(TOKEN, error)
        self.assertNotIn('api.telegram.org', error)

    def test_http_and_api_errors_are_sanitized(self):
        responses = (
            FakeResponse(status_code=500),
            FakeResponse(payload={'ok': False, 'description': TOKEN}),
            FakeResponse(json_error=ValueError('bad JSON ' + TOKEN)),
        )
        for response in responses:
            with self.assertRaises(TelegramDeliveryError) as context:
                send_telegram_message(
                    TOKEN, ['12345'], 'test',
                    http_client=FakeHttpClient([response]))
            self.assertNotIn(TOKEN, str(context.exception))

    def test_destination_failure_does_not_skip_later_destinations(self):
        client = FakeHttpClient([
            FakeResponse(status_code=500),
            FakeResponse(),
        ])
        with self.assertRaises(TelegramDeliveryError):
            send_telegram_message(
                TOKEN, ['12345', '67890'], 'test', http_client=client)
        self.assertEqual(len(client.calls), 2)

    def test_notification_wrappers_do_not_start_shoutrrr(self):
        wrappers = (
            (ROOT / 'back' / 'pialert.py', 'send_telegram'),
            (ROOT / 'back' / 'pialert_reporting_test.py', 'send_telegram_test'),
        )
        for path, function_name in wrappers:
            tree = ast.parse(path.read_text())
            function = next(
                node for node in tree.body
                if isinstance(node, ast.FunctionDef) and node.name == function_name)
            calls = [node for node in ast.walk(function) if isinstance(node, ast.Call)]
            for call in calls:
                name = ''
                if isinstance(call.func, ast.Name):
                    name = call.func.id
                elif isinstance(call.func, ast.Attribute):
                    name = call.func.attr
                self.assertNotIn(name, ('popen', 'system', 'run', 'Popen'))
            self.assertNotIn('shoutrrr', ast.get_source_segment(path.read_text(), function))


if __name__ == '__main__':
    unittest.main()

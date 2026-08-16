#!/usr/bin/env python3
import ipaddress
import json
import sys
import unittest
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parent))
from service_url_policy import (
    ServiceUrlError,
    address_allowed,
    resolve_service_target,
    validate_service_url,
)


ROOT = Path(__file__).resolve().parent.parent
FIXTURES = json.loads((ROOT / 'tests/fixtures/service_url_policy.json').read_text())


class ServiceUrlPolicyTests(unittest.TestCase):
    def test_shared_valid_urls(self):
        for value in FIXTURES['valid']:
            with self.subTest(value=value):
                self.assertIsNotNone(validate_service_url(value))

    def test_shared_invalid_urls(self):
        for value in FIXTURES['invalid']:
            with self.subTest(value=value):
                with self.assertRaises(ServiceUrlError):
                    validate_service_url(value)

    def test_internal_and_external_addresses_are_allowed(self):
        for value in ('127.0.0.1', '10.0.0.1', '::1', 'fe80::1', '93.184.216.34'):
            self.assertTrue(address_allowed(ipaddress.ip_address(value)))

    def test_every_dns_answer_must_be_allowed(self):
        records = [
            (2, 1, 6, '', ('93.184.216.34', 80)),
            (2, 1, 6, '', ('169.254.169.254', 80)),
        ]
        with patch('service_url_policy.socket.getaddrinfo', return_value=records):
            with self.assertRaisesRegex(ServiceUrlError, 'blocked'):
                resolve_service_target(validate_service_url('http://example.com'))

    def test_first_of_multiple_valid_dns_answers_is_pinned(self):
        records = [
            (2, 1, 6, '', ('10.0.0.20', 8080)),
            (2, 1, 6, '', ('10.0.0.21', 8080)),
        ]
        with patch('service_url_policy.socket.getaddrinfo', return_value=records):
            address, port = resolve_service_target(
                validate_service_url('http://service.example.lan:8080/status'))
        self.assertEqual(address, '10.0.0.20')
        self.assertEqual(port, 8080)

    def test_metadata_addresses_are_always_blocked(self):
        self.assertFalse(address_allowed(ipaddress.ip_address('169.254.169.254')))
        self.assertFalse(address_allowed(ipaddress.ip_address('100.100.100.200')))


if __name__ == '__main__':
    unittest.main()

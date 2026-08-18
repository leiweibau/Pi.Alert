#!/usr/bin/env python3
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from requests.exceptions import ConnectionError, SSLError, Timeout

from openwrt_transport import (
    build_openwrt_host,
    fetch_openwrt_devices,
    format_openwrt_exception,
)


class FakeRouter:
    def __init__(self, result):
        self.result = result

    def get_all_connected_devices(self, only_reachable, wlan_interfaces):
        if isinstance(self.result, Exception):
            raise self.result
        return self.result


class FakeFactory:
    def __init__(self, results):
        self.results = list(results)
        self.calls = []

    def __call__(self, **kwargs):
        self.calls.append(kwargs)
        result = self.results.pop(0)
        if isinstance(result, Exception):
            raise result
        return FakeRouter(result)


class InvalidLuciLoginError(Exception):
    pass


class MessageOnlyError(Exception):
    def __init__(self, message):
        self.message = message


class OpenWrtTransportTests(unittest.TestCase):
    def test_host_and_port_are_built_for_all_address_families(self):
        self.assertEqual(build_openwrt_host('router.lan', 443),
                         'router.lan:443')
        self.assertEqual(build_openwrt_host('192.168.1.1', 80),
                         '192.168.1.1:80')
        self.assertEqual(build_openwrt_host('2001:db8::1', 8443),
                         '[2001:db8::1]:8443')

    def test_https_uses_self_signed_compatible_client_options(self):
        factory = FakeFactory([['device']])
        messages = []
        result = fetch_openwrt_devices(
            factory, 'router.lan', 8443, 'root', 'secret', True,
            messages.append)
        self.assertEqual(result, ['device'])
        self.assertEqual(len(factory.calls), 1)
        self.assertEqual(factory.calls[0], {
            'host_url': 'router.lan:8443',
            'username': 'root',
            'password': 'secret',
            'is_https': True,
            'verify_https': False,
        })
        self.assertEqual(messages,
                         ['OpenWRT transport: HTTPS (port 8443)'])

    def test_transport_errors_fall_back_once_to_http_port_80(self):
        for error in (SSLError('tls'), ConnectionError('down'), Timeout('slow')):
            factory = FakeFactory([error, ['fallback-device']])
            messages = []
            errors = []
            result = fetch_openwrt_devices(
                factory, '192.168.1.1', 443, 'root', 'secret', True,
                messages.append, errors.append)
            self.assertEqual(result, ['fallback-device'])
            self.assertEqual(len(factory.calls), 2)
            self.assertTrue(factory.calls[0]['is_https'])
            self.assertEqual(factory.calls[0]['host_url'], '192.168.1.1:443')
            self.assertFalse(factory.calls[1]['is_https'])
            self.assertEqual(factory.calls[1]['host_url'], '192.168.1.1:80')
            self.assertNotIn('verify_https', factory.calls[1])
            self.assertEqual(messages, [
                'OpenWRT HTTPS unavailable; retrying via HTTP on port 80',
                'OpenWRT transport: HTTP fallback (port 80)',
            ])
            self.assertEqual(errors, [error])

    def test_application_errors_never_trigger_downgrade(self):
        factory = FakeFactory([InvalidLuciLoginError('invalid login')])
        with self.assertRaises(InvalidLuciLoginError):
            fetch_openwrt_devices(
                factory, 'router.lan', 443, 'root', 'secret', True)
        self.assertEqual(len(factory.calls), 1)

    def test_explicit_http_uses_only_the_configured_port(self):
        factory = FakeFactory([[]])
        messages = []
        self.assertEqual(fetch_openwrt_devices(
            factory, 'router.lan', 8080, 'root', 'secret', False,
            messages.append), [])
        self.assertEqual(factory.calls, [{
            'host_url': 'router.lan:8080',
            'username': 'root',
            'password': 'secret',
            'is_https': False,
        }])
        self.assertEqual(messages,
                         ['OpenWRT transport: HTTP (port 8080)'])

    def test_second_transport_failure_is_not_retried(self):
        factory = FakeFactory([
            SSLError('tls'), ConnectionError('http unavailable')])
        with self.assertRaises(ConnectionError):
            fetch_openwrt_devices(
                factory, 'router.lan', 443, 'root', 'secret', True)
        self.assertEqual(len(factory.calls), 2)

    def test_error_formatter_is_useful_and_redacts_details(self):
        error = MessageOnlyError(
            'request https://router.lan/rpc?auth=token for root secret')
        normal = format_openwrt_exception(error)
        extended = format_openwrt_exception(
            error, include_detail=True,
            secrets=('router.lan', 'root', 'secret'))
        self.assertIn('MessageOnlyError', normal)
        self.assertIn('<url>', extended)
        self.assertNotIn('router.lan', extended)
        self.assertNotIn('root', extended)
        self.assertNotIn('secret', extended)


if __name__ == '__main__':
    unittest.main()

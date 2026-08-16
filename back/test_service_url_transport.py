#!/usr/bin/env python3
import datetime
import ipaddress
import ssl
import sys
import tempfile
import threading
import unittest
from email.message import Message
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from unittest.mock import patch

from cryptography import x509
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.x509.oid import NameOID

sys.path.insert(0, str(Path(__file__).resolve().parent))
from service_url_policy import (
    ServiceUrlError,
    check_service_url,
    fetch_service_url,
    service_url_for_log,
)
from service_url_check import check as check_service_helper


class FakeResponse:
    def __init__(self, status, headers=None):
        self.status = status
        self.msg = Message()
        for name, value in headers or ():
            self.msg.add_header(name, value)

    def getheader(self, name, default=None):
        return self.msg.get(name, default)

    def info(self):
        return self.msg


class FakeConnection:
    def __init__(self, handler, calls):
        self.handler = handler
        self.calls = calls
        self.request_data = None
        self.peer_certificate = None

    def request(self, method, path, headers=None):
        self.request_data = (method, path, dict(headers or {}))
        self.calls.append(self.request_data)

    def getresponse(self):
        return self.handler(*self.request_data)

    def close(self):
        pass


class FakeConnectionFactory:
    def __init__(self, handler):
        self.handler = handler
        self.calls = []

    def __call__(self, *args, **kwargs):
        return FakeConnection(self.handler, self.calls)


def fixed_resolver(parsed):
    return '127.0.0.1', parsed.port or (443 if parsed.scheme == 'https' else 80)


class ServiceUrlMockTransportTests(unittest.TestCase):
    def fetch_with_handler(self, url, handler, **kwargs):
        factory = FakeConnectionFactory(handler)
        with patch('service_url_policy.resolve_service_target', side_effect=fixed_resolver), \
                patch('service_url_policy.http.client.HTTPConnection', factory):
            result = fetch_service_url(url, **kwargs)
        return result, factory.calls

    @staticmethod
    def chain_handler(method, path, headers):
        index = int(path.rsplit('/', 1)[-1])
        if index < 5:
            return FakeResponse(302, [('Location', '/chain/{}'.format(index + 1))])
        return FakeResponse(204)

    def test_legitimate_chain_longer_than_three_redirects(self):
        result, calls = self.fetch_with_handler(
            'http://service.lan/chain/0', self.chain_handler)
        self.assertEqual(result['status'], 204)
        self.assertEqual(result['initial_status'], 302)
        self.assertEqual(result['redirect_count'], 5)
        self.assertEqual(len(calls), 6)
        self.assertEqual(calls[0][2]['Host'], 'service.lan')

    def test_lower_explicit_limit_remains_enforced(self):
        factory = FakeConnectionFactory(self.chain_handler)
        with patch('service_url_policy.resolve_service_target', side_effect=fixed_resolver), \
                patch('service_url_policy.http.client.HTTPConnection', factory):
            with self.assertRaises(ServiceUrlError) as raised:
                fetch_service_url('http://service.lan/chain/0', max_redirects=3)
        self.assertEqual(raised.exception.code, 'redirect_limit')
        self.assertEqual(len(raised.exception.history), 4)

    def test_self_redirect_is_reported_as_loop(self):
        def handler(method, path, headers):
            return FakeResponse(302, [('Location', '/self')])

        factory = FakeConnectionFactory(handler)
        with patch('service_url_policy.resolve_service_target', side_effect=fixed_resolver), \
                patch('service_url_policy.http.client.HTTPConnection', factory):
            result = check_service_url('http://service.lan/self')
        self.assertEqual(result['error_code'], 'redirect_loop')
        self.assertEqual(result['note'], 'Redirect loop detected')
        self.assertEqual(len(factory.calls), 1)

    def test_two_url_cycle_is_reported_as_loop(self):
        def handler(method, path, headers):
            target = '/loop-b' if path == '/loop-a' else '/loop-a'
            return FakeResponse(302, [('Location', target)])

        factory = FakeConnectionFactory(handler)
        with patch('service_url_policy.resolve_service_target', side_effect=fixed_resolver), \
                patch('service_url_policy.http.client.HTTPConnection', factory):
            result = check_service_url('http://service.lan/loop-a')
        self.assertEqual(result['error_code'], 'redirect_loop')
        self.assertEqual(len(factory.calls), 2)

    def test_cookie_state_can_complete_same_url_redirect(self):
        def handler(method, path, headers):
            if 'monitor=ready' in headers.get('Cookie', ''):
                return FakeResponse(204)
            return FakeResponse(302, [
                ('Location', '/cookie'),
                ('Set-Cookie', 'monitor=ready; Path=/; HttpOnly'),
            ])

        result, calls = self.fetch_with_handler('http://service.lan/cookie', handler)
        self.assertEqual(result['status'], 204)
        self.assertEqual(result['redirect_count'], 1)
        self.assertNotIn('Cookie', calls[0][2])
        self.assertEqual(calls[1][2]['Cookie'], 'monitor=ready')
        self.assertTrue(result['redirect_history'][0]['cookie_set'])
        self.assertTrue(result['redirect_history'][1]['cookie_sent'])

    def test_redirect_cookie_count_and_value_are_bounded(self):
        def handler(method, path, headers):
            if path == '/cookies':
                response_headers = [('Location', '/final')]
                response_headers.extend(
                    ('Set-Cookie', 'cookie{}=x; Path=/'.format(index))
                    for index in range(40))
                response_headers.append(
                    ('Set-Cookie', 'oversized={}; Path=/'.format('x' * 5000)))
                return FakeResponse(302, response_headers)
            return FakeResponse(204)

        result, calls = self.fetch_with_handler('http://service.lan/cookies', handler)
        self.assertEqual(result['status'], 204)
        cookie_header = calls[1][2]['Cookie']
        self.assertLessEqual(len(cookie_header.split('; ')), 32)
        self.assertNotIn('oversized=', cookie_header)

    def test_total_check_runtime_is_bounded(self):
        with patch('service_url_policy.time.monotonic', side_effect=(0.0, 31.0, 31.0)):
            result = check_service_url('http://service.lan/start')
        self.assertEqual(result['error_code'], 'connection_error')
        self.assertEqual(result['note'], 'Connection failed')

    def test_query_values_are_redacted_from_diagnostics(self):
        def handler(method, path, headers):
            if path == '/start':
                return FakeResponse(302, [('Location', '/final?token=secret-value')])
            return FakeResponse(204)

        factory = FakeConnectionFactory(handler)
        with patch('service_url_policy.resolve_service_target', side_effect=fixed_resolver), \
                patch('service_url_policy.http.client.HTTPConnection', factory):
            result = check_service_url('http://service.lan/start')
        self.assertIn('token=secret-value', factory.calls[1][1])
        self.assertNotIn('secret-value', result['diagnostic'])
        self.assertNotIn('secret-value', str(result['redirect_history']))
        self.assertEqual(result['redirect_history'][0]['location'],
                         'http://service.lan/final?<redacted>')
        self.assertEqual(
            service_url_for_log('http://service.lan/start?token=secret-value'),
            'http://service.lan/start?<redacted>')

    def test_redirect_target_is_validated_again(self):
        def handler(method, path, headers):
            return FakeResponse(302, [
                ('Location', 'http://169.254.169.254/latest/meta-data/'),
            ])

        def resolver(parsed):
            if parsed.hostname == '169.254.169.254':
                raise ServiceUrlError(
                    'blocked_by_policy', 'Service target is blocked by network policy')
            return fixed_resolver(parsed)

        factory = FakeConnectionFactory(handler)
        with patch('service_url_policy.resolve_service_target', side_effect=resolver), \
                patch('service_url_policy.http.client.HTTPConnection', factory):
            result = check_service_url('http://service.lan/start')
        self.assertEqual(result['error_code'], 'blocked_by_policy')
        self.assertEqual(result['note'], 'Blocked by network policy')


class Handler(BaseHTTPRequestHandler):
    tls_port = None
    last_host = None
    last_sni = None

    def do_GET(self):
        Handler.last_host = self.headers.get('Host')
        if self.path == '/redirect':
            self.send_response(302)
            self.send_header('Location', '/final?a=1,2')
            self.end_headers()
        elif self.path == '/blocked-redirect':
            self.send_response(302)
            self.send_header('Location', 'http://169.254.169.254/latest/meta-data/')
            self.end_headers()
        elif self.path == '/to-self-signed':
            self.send_response(302)
            self.send_header(
                'Location', 'https://127.0.0.1:{}/self-signed'.format(self.tls_port))
            self.end_headers()
        else:
            self.send_response(204)
            self.end_headers()

    def log_message(self, *args):
        pass


class ServiceUrlSocketTransportTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        try:
            cls.server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
        except PermissionError as exc:
            raise unittest.SkipTest('listening sockets are unavailable') from exc

        cls.temporary_directory = tempfile.TemporaryDirectory()
        private_key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
        subject = x509.Name([x509.NameAttribute(NameOID.COMMON_NAME, '127.0.0.1')])
        now = datetime.datetime.now(datetime.timezone.utc)
        certificate = (
            x509.CertificateBuilder()
            .subject_name(subject)
            .issuer_name(subject)
            .public_key(private_key.public_key())
            .serial_number(x509.random_serial_number())
            .not_valid_before(now - datetime.timedelta(minutes=1))
            .not_valid_after(now + datetime.timedelta(days=1))
            .add_extension(
                x509.SubjectAlternativeName([
                    x509.IPAddress(ipaddress.ip_address('127.0.0.1'))]),
                critical=False)
            .sign(private_key, hashes.SHA256())
        )
        key_path = Path(cls.temporary_directory.name) / 'key.pem'
        cert_path = Path(cls.temporary_directory.name) / 'cert.pem'
        key_path.write_bytes(private_key.private_bytes(
            serialization.Encoding.PEM,
            serialization.PrivateFormat.TraditionalOpenSSL,
            serialization.NoEncryption()))
        cert_path.write_bytes(certificate.public_bytes(serialization.Encoding.PEM))

        cls.tls_server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
        tls_context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
        tls_context.load_cert_chain(str(cert_path), str(key_path))
        tls_context.set_servername_callback(
            lambda sock, server_name, context: setattr(Handler, 'last_sni', server_name))
        cls.tls_server.socket = tls_context.wrap_socket(
            cls.tls_server.socket, server_side=True)
        Handler.tls_port = cls.tls_server.server_port

        cls.thread = threading.Thread(target=cls.server.serve_forever, daemon=True)
        cls.tls_thread = threading.Thread(
            target=cls.tls_server.serve_forever, daemon=True)
        cls.thread.start()
        cls.tls_thread.start()

    @classmethod
    def tearDownClass(cls):
        cls.server.shutdown()
        cls.tls_server.shutdown()
        cls.server.server_close()
        cls.tls_server.server_close()
        cls.thread.join()
        cls.tls_thread.join()
        cls.temporary_directory.cleanup()

    def url(self, path):
        return 'http://127.0.0.1:{}{}'.format(self.server.server_port, path)

    def tls_url(self, path):
        return 'https://127.0.0.1:{}{}'.format(self.tls_server.server_port, path)

    def test_internal_address_and_relative_redirect(self):
        result = fetch_service_url(self.url('/redirect'))
        self.assertEqual(result['initial_status'], 302)
        self.assertEqual(result['status'], 204)
        self.assertEqual(result['target_ip'], '127.0.0.1')

    def test_each_redirect_is_checked_against_policy(self):
        with self.assertRaisesRegex(ServiceUrlError, 'blocked'):
            fetch_service_url(self.url('/blocked-redirect'))

    def test_self_signed_https_is_supported(self):
        result = fetch_service_url(self.tls_url('/self-signed'))
        self.assertEqual(result['status'], 204)
        self.assertIsNotNone(result['certificate'])

    def test_redirect_to_self_signed_https_is_supported(self):
        result = fetch_service_url(self.url('/to-self-signed'))
        self.assertEqual(result['initial_status'], 302)
        self.assertEqual(result['status'], 204)
        self.assertEqual(result['redirect_count'], 1)
        self.assertIsNotNone(result['certificate'])

    def test_helper_reports_final_self_signed_certificate(self):
        result = check_service_helper(self.url('/to-self-signed'))
        self.assertEqual(result['status'], 204)
        self.assertEqual(result['initial_status'], 302)
        self.assertEqual(result['redirect_count'], 1)
        self.assertEqual(result['error_code'], '')
        self.assertIn('127.0.0.1', result['ssl_subject'])

    def test_local_dns_name_preserves_host_header_and_tls_sni(self):
        url = 'https://service.example.lan:{}/self-signed'.format(
            self.tls_server.server_port)
        with patch('service_url_policy.resolve_service_target', return_value=(
                '127.0.0.1', self.tls_server.server_port)):
            result = fetch_service_url(url)
        self.assertEqual(result['status'], 204)
        self.assertEqual(
            Handler.last_host, 'service.example.lan:{}'.format(self.tls_server.server_port))
        self.assertEqual(Handler.last_sni, 'service.example.lan')


if __name__ == '__main__':
    unittest.main()

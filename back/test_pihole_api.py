#!/usr/bin/env python3
"""Exercise the real Pi-hole functions without loading config or contacting devices."""
import ast
import contextlib
import datetime
import io
import sqlite3
import unittest
from pathlib import Path
from unittest import mock
from urllib.parse import urlparse

import requests


FUNCTIONS = {
    'pihole_six_api_request', 'pihole_six_api_auth', 'pihole_six_api_deauth',
    'copy_pihole_network', 'copy_pihole_network_six', 'get_pihole_interface_data',
    'read_DHCP_leases', 'read_DHCP_leases_six',
}


class PiholeApiTests(unittest.TestCase):
    def setUp(self):
        path = Path(__file__).with_name('pialert.py')
        tree = ast.parse(path.read_text())
        module = ast.Module(body=[node for node in tree.body
                                  if isinstance(node, ast.FunctionDef) and node.name in FUNCTIONS],
                            type_ignores=[])
        self.http = mock.Mock(wraps=requests)
        self.http.exceptions = requests.exceptions
        self.env = {
            'requests': self.http, 'urlparse': urlparse,
            'InsecureRequestWarning': requests.packages.urllib3.exceptions.InsecureRequestWarning,
            'PIHOLE6_URL': 'https://pi.hole', 'PIHOLE6_PASSWORD': 'test-password',
            'PIHOLE6_SES_VALID': False, 'PIHOLE6_SES_SID': '', 'PIHOLE6_SES_CSRF': '',
            'PIHOLE_ACTIVE': True, 'PIHOLE_VERSION': 6, 'DHCP_ACTIVE': True,
            'PIHOLE6_API_MAXCLIENTS': 100, 'VERSION_DATE': 'test',
            'sql': mock.Mock(), 'sql_connection': mock.Mock(),
            'datetime': datetime, 'DHCP_INCL_SELF_TO_LEASES': False,
        }
        exec(compile(module, str(path), 'exec'), self.env)
        self.output = io.StringIO()
        # Keep the context manager alive and restore stdout after every test.
        self.redirect = contextlib.redirect_stdout(self.output)
        self.redirect.__enter__()
        self.addCleanup(self.redirect.__exit__, None, None, None)

    def response(self, payload=None, status=200):
        response = requests.Response()
        response.status_code = status
        response.url = 'https://pi.hole/api/auth'
        response.json = mock.Mock(return_value=payload)
        return response

    def valid_session(self):
        return self.response({'session': {'valid': True, 'sid': 'test-sid', 'csrf': 'test-csrf'}})

    def call(self, name):
        return self.env[name]()

    def test_admin_suffix_is_removed_before_auth_and_all_following_requests(self):
        for suffix in ('/admin', '/admin/', '/admin///'):
            with self.subTest(suffix=suffix):
                self.http.request.reset_mock()
                self.http.request.side_effect = [self.valid_session(), self.response({'devices': []}),
                                                 self.response({'interfaces': []}), self.response(status=204)]
                self.env['PIHOLE6_URL'] = 'https://pi.hole:8443/pihole' + suffix
                self.call('copy_pihole_network')
                self.assertTrue(self.env['PIHOLE6_SES_VALID'])
                self.call('pihole_six_api_deauth')
                calls = self.http.request.call_args_list
                self.assertEqual([(call.args[0], call.args[1]) for call in calls], [
                    ('POST', 'https://pi.hole:8443/pihole/api/auth'),
                    ('GET', 'https://pi.hole:8443/pihole/api/network/devices?max_devices=100&max_addresses=2'),
                    ('GET', 'https://pi.hole:8443/pihole/api/network/interfaces'),
                    ('DELETE', 'https://pi.hole:8443/pihole/api/auth'),
                ])
                self.assertTrue(all(call.kwargs['timeout'] == 15 for call in calls))
                self.assertFalse(self.env['PIHOLE6_SES_VALID'])
                self.assertEqual(self.env['PIHOLE6_SES_SID'], '')
        self.assertIn('ends in /admin', self.output.getvalue())
        self.assertNotIn('test-password', self.output.getvalue())
        self.assertNotIn('test-sid', self.output.getvalue())

    def test_base_urls_and_ipv6_keep_their_scheme_host_port_and_prefix(self):
        for base in ('http://pi.hole', 'https://pi.hole/', 'https://[::1]:8443/pihole/',
                     'https://admin.example/admin-proxy'):
            with self.subTest(base=base):
                self.env['PIHOLE6_URL'] = base
                self.http.request.return_value = self.valid_session()
                self.call('pihole_six_api_auth')
                self.assertEqual(self.http.request.call_args.args[1], base.rstrip('/') + '/api/auth')
                self.assertTrue(self.env['PIHOLE6_SES_VALID'])
        self.assertNotIn('ends in /admin', self.output.getvalue())

    def test_invalid_base_urls_are_logged_without_requests(self):
        for url in ('', 'pi.hole/admin', 'ftp://pi.hole', 'https://[broken',
                    'https://pi.hole:bad', 'https://pi.hole/admin?x=1', 'https://pi.hole/#admin'):
            with self.subTest(url=url):
                self.env['PIHOLE6_URL'] = url
                self.call('pihole_six_api_auth')
                self.assertFalse(self.env['PIHOLE6_SES_VALID'])
        self.http.request.assert_not_called()
        self.assertIn('Invalid PIHOLE6_URL', self.output.getvalue())

    def test_html_response_does_not_abort_import_or_keep_old_session(self):
        response = self.response()
        response.json.side_effect = requests.exceptions.JSONDecodeError('not JSON', '<html>', 0)
        self.http.request.return_value = response
        self.env.update(PIHOLE6_SES_VALID=True, PIHOLE6_SES_SID='old-sid', PIHOLE6_SES_CSRF='old-csrf')
        self.call('copy_pihole_network')
        self.assertFalse(self.env['PIHOLE6_SES_VALID'])
        self.assertEqual(self.env['PIHOLE6_SES_SID'], '')
        self.assertEqual(self.env['PIHOLE6_SES_CSRF'], '')
        self.assertEqual(self.http.request.call_count, 1)
        self.assertIn('invalid JSON', self.output.getvalue())

    def test_http_errors_skip_json_and_log_status(self):
        for status in (401, 403, 404, 500):
            with self.subTest(status=status):
                response = self.response(status=status)
                self.http.request.return_value = response
                self.call('copy_pihole_network')
                self.assertFalse(self.env['PIHOLE6_SES_VALID'])
                response.json.assert_not_called()
                self.assertIn(f'HTTP {status}', self.output.getvalue())

    def test_timeout_and_connection_errors_do_not_escape(self):
        for error in (requests.exceptions.Timeout(), requests.exceptions.ConnectionError()):
            with self.subTest(error=type(error)):
                self.http.request.side_effect = error
                self.call('copy_pihole_network')
                self.assertFalse(self.env['PIHOLE6_SES_VALID'])

    def test_malformed_session_payloads_do_not_escape(self):
        for payload in ([], None, {}, {'session': None}, {'session': []},
                        {'session': {'valid': False}}, {'session': {'valid': True}},
                        {'session': {'valid': True, 'sid': 'x', 'csrf': None}}):
            with self.subTest(payload=payload):
                self.http.request.return_value = self.response(payload)
                self.call('copy_pihole_network')
                self.assertFalse(self.env['PIHOLE6_SES_VALID'])

    def test_passwordless_session_does_not_require_csrf(self):
        self.env['PIHOLE6_PASSWORD'] = ''
        self.http.request.return_value = self.response({'session': {'valid': True, 'sid': 'x'}})
        self.call('pihole_six_api_auth')
        self.assertTrue(self.env['PIHOLE6_SES_VALID'])

    def test_dhcp_only_import_uses_normalized_url_and_survives_http_failure(self):
        self.env['PIHOLE_ACTIVE'] = False
        self.env['PIHOLE6_URL'] = 'https://pi.hole/admin/'
        self.http.request.side_effect = [self.valid_session(), self.response(status=404)]
        self.call('read_DHCP_leases')
        self.assertEqual(self.http.request.call_args.args[1], 'https://pi.hole/api/dhcp/leases')
        self.env['sql'].execute.assert_not_called()

    def test_read_endpoints_reject_non_json_and_missing_collections(self):
        self.env['PIHOLE6_SES_VALID'] = True
        for function in ('copy_pihole_network_six', 'get_pihole_interface_data', 'read_DHCP_leases_six'):
            for response in (self.response({}), self.response([]), self.response(status=502)):
                with self.subTest(function=function, status=response.status_code):
                    self.http.request.return_value = response
                    self.call(function)
        self.env['sql'].execute.assert_not_called()

    def test_logout_failure_clears_local_session(self):
        self.env.update(PIHOLE6_SES_VALID=True, PIHOLE6_SES_SID='old-sid', PIHOLE6_SES_CSRF='old-csrf')
        self.http.request.side_effect = requests.exceptions.Timeout()
        self.call('pihole_six_api_deauth')
        self.assertFalse(self.env['PIHOLE6_SES_VALID'])
        self.assertEqual(self.env['PIHOLE6_SES_SID'], '')
        self.assertEqual(self.env['PIHOLE6_SES_CSRF'], '')

    def test_successful_network_import_still_writes_device_data(self):
        db = sqlite3.connect(':memory:')
        self.addCleanup(db.close)
        db.execute('CREATE TABLE PiHole_Network (PH_MAC, PH_Vendor, PH_LastQuery, PH_Name, PH_IP)')
        self.env.update(sql=db.cursor(), sql_connection=db)
        self.http.request.side_effect = [self.valid_session(), self.response({'devices': [{
            'hwaddr': 'aa:bb:cc:dd:ee:ff', 'lastQuery': 12345, 'macVendor': 'Example',
            'ips': [{'ip': '192.168.10.20', 'name': 'client'}],
        }]}), self.response({'interfaces': []})]
        self.call('copy_pihole_network')
        self.assertEqual(db.execute('SELECT * FROM PiHole_Network').fetchall(), [
            ('aa:bb:cc:dd:ee:ff', 'Example', 12345, 'client', '192.168.10.20')])

    def test_successful_dhcp_import_still_writes_leases(self):
        db = sqlite3.connect(':memory:')
        self.addCleanup(db.close)
        db.execute('CREATE TABLE DHCP_Leases (DHCP_DateTime, DHCP_MAC, DHCP_IP, DHCP_Name, DHCP_MAC2)')
        self.env.update(sql=db.cursor(), sql_connection=db)
        self.http.request.side_effect = [self.valid_session(), self.response({'leases': [{
            'hwaddr': 'aa:bb:cc:dd:ee:ff', 'expires': 12345, 'ip': '192.168.10.20',
            'name': 'client', 'clientid': 'id',
        }]}), self.response({'interfaces': []})]
        self.call('read_DHCP_leases')
        self.assertEqual(db.execute('SELECT * FROM DHCP_Leases').fetchall(), [
            (12345, 'aa:bb:cc:dd:ee:ff', '192.168.10.20', 'client', 'id')])


if __name__ == '__main__':
    unittest.main()

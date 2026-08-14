#!/usr/bin/env python3
import sys
import threading
import unittest
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from service_url_policy import ServiceUrlError, fetch_service_url


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path == '/redirect':
            self.send_response(302)
            self.send_header('Location', '/final?a=1,2')
            self.end_headers()
        elif self.path == '/blocked-redirect':
            self.send_response(302)
            self.send_header('Location', 'http://169.254.169.254/latest/meta-data/')
            self.end_headers()
        else:
            self.send_response(204)
            self.end_headers()

    def log_message(self, *args):
        pass


class ServiceUrlTransportTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
        cls.thread = threading.Thread(target=cls.server.serve_forever, daemon=True)
        cls.thread.start()

    @classmethod
    def tearDownClass(cls):
        cls.server.shutdown()
        cls.server.server_close()
        cls.thread.join()

    def url(self, path):
        return 'http://localhost:{}{}'.format(self.server.server_port, path)

    def test_explicit_private_allowlist_and_relative_redirect(self):
        result = fetch_service_url(self.url('/redirect'))
        self.assertEqual(result['initial_status'], 302)
        self.assertEqual(result['status'], 204)
        self.assertEqual(result['target_ip'], '127.0.0.1')

    def test_each_redirect_is_checked_against_policy(self):
        with self.assertRaisesRegex(ServiceUrlError, 'blocked'):
            fetch_service_url(self.url('/blocked-redirect'))


if __name__ == '__main__':
    unittest.main()

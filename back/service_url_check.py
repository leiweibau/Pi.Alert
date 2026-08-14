#!/usr/bin/env python3
"""Run one hardened Pi.Alert service check and emit a small JSON result."""
import json
import sys

from cryptography import x509
from cryptography.hazmat.backends import default_backend
from service_url_policy import ServiceUrlError, fetch_service_url


def empty_result():
    return {
        'status': 0,
        'latency': '99999999',
        'target_ip': '',
        'ssl_subject': '',
        'ssl_issuer': '',
        'ssl_valid_from': '',
        'ssl_valid_to': '',
    }


def certificate_fields(certificate_data):
    if not certificate_data:
        return {
            'ssl_subject': '',
            'ssl_issuer': '',
            'ssl_valid_from': '',
            'ssl_valid_to': '',
        }
    certificate = x509.load_der_x509_certificate(certificate_data, default_backend())
    if hasattr(certificate, 'not_valid_before_utc'):
        valid_from = certificate.not_valid_before_utc
        valid_to = certificate.not_valid_after_utc
    else:
        valid_from = certificate.not_valid_before
        valid_to = certificate.not_valid_after
    return {
        'ssl_subject': str(certificate.subject),
        'ssl_issuer': str(certificate.issuer),
        'ssl_valid_from': str(valid_from) + (' (UTC)' if hasattr(certificate, 'not_valid_before_utc') else ''),
        'ssl_valid_to': str(valid_to) + (' (UTC)' if hasattr(certificate, 'not_valid_after_utc') else ''),
    }


def check(url):
    try:
        result = fetch_service_url(url)
        output = {
            'status': int(result['status']),
            'latency': str(result['latency']),
            'target_ip': str(result['target_ip']),
        }
        output.update(certificate_fields(result.get('certificate')))
        return output
    except (ServiceUrlError, OSError, ValueError):
        return empty_result()


def main(argv):
    if len(argv) != 2:
        return 2
    print(json.dumps(check(argv[1]), separators=(',', ':')))
    return 0


if __name__ == '__main__':
    raise SystemExit(main(sys.argv))

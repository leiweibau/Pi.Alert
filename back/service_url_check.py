#!/usr/bin/env python3
"""Run one hardened Pi.Alert service check and emit a small JSON result."""
import json
import sys

from cryptography import x509
from cryptography.hazmat.backends import default_backend
from service_url_policy import check_service_url


def empty_result():
    return {
        'status': 0,
        'initial_status': 0,
        'latency': '99999999',
        'target_ip': '',
        'final_url': '',
        'redirect_count': 0,
        'error_code': 'connection_error',
        'error_message': 'Service check failed',
        'note': 'Service check failed',
        'diagnostic': 'connection_error:',
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
        result = check_service_url(url)
        history = result.get('redirect_history', ())
        output = {
            'status': int(result['status']),
            'initial_status': int(result['initial_status']),
            'latency': str(result['latency']),
            'target_ip': str(result['target_ip']),
            'final_url': str(history[-1]['url']) if history else '',
            'redirect_count': int(result['redirect_count']),
            'error_code': str(result['error_code']),
            'error_message': str(result['error_message']),
            'note': str(result['note']),
            'diagnostic': str(result['diagnostic']),
        }
        output.update(certificate_fields(result.get('certificate')))
        return output
    except (OSError, TypeError, ValueError):
        return empty_result()


def main(argv):
    if len(argv) != 2:
        return 2
    print(json.dumps(check(argv[1]), separators=(',', ':')))
    return 0


if __name__ == '__main__':
    raise SystemExit(main(sys.argv))

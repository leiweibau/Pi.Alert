"""Structural validation and SSRF-safe transport for monitored service URLs."""
from __future__ import print_function

import http.client
import ipaddress
import re
import socket
import ssl
import time
from urllib.parse import urljoin, urlsplit


MAX_URL_LENGTH = 2048
MAX_REDIRECTS = 3
_BAD_CHARACTERS = re.compile(r'[\x00-\x20\x7f"\'`]')
_BAD_PERCENT = re.compile(r'%(?![0-9A-Fa-f]{2})')
_DNS_LABEL = re.compile(r'^[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?$')
_METADATA_ADDRESSES = frozenset((
    ipaddress.ip_address('169.254.169.254'),
    ipaddress.ip_address('100.100.100.200'),
))


class ServiceUrlError(ValueError):
    def __init__(self, code, message):
        super().__init__(message)
        self.code = code


def validate_service_url(value):
    if not isinstance(value, str) or not value or len(value) > MAX_URL_LENGTH:
        raise ServiceUrlError('invalid_url', 'URL is empty or too long')
    if _BAD_CHARACTERS.search(value) or _BAD_PERCENT.search(value):
        raise ServiceUrlError('invalid_url', 'URL contains invalid characters')
    try:
        parsed = urlsplit(value)
        port = parsed.port
    except ValueError as exc:
        raise ServiceUrlError('invalid_url', 'URL contains an invalid port or address') from exc
    if parsed.scheme.lower() not in ('http', 'https') or not parsed.hostname:
        raise ServiceUrlError('invalid_url', 'Only HTTP(S) URLs with a host are allowed')
    if parsed.username is not None or parsed.password is not None or parsed.fragment:
        raise ServiceUrlError('invalid_url', 'Credentials and fragments are not allowed')
    if port is not None and not 1 <= port <= 65535:
        raise ServiceUrlError('invalid_url', 'Port is outside the allowed range')

    host = parsed.hostname
    try:
        host.encode('ascii')
    except UnicodeEncodeError as exc:
        raise ServiceUrlError('invalid_url', 'International hostnames must use ASCII IDNA form') from exc
    try:
        ipaddress.ip_address(host)
    except ValueError:
        candidate = host[:-1] if host.endswith('.') else host
        labels = candidate.split('.')
        if len(host) > 253 or not labels or not all(_DNS_LABEL.match(label) for label in labels):
            raise ServiceUrlError('invalid_url', 'Host name is invalid')
        if re.match(r'^[0-9.]+$', candidate):
            raise ServiceUrlError('invalid_url', 'Ambiguous numeric host is not allowed')
    return parsed


def _mapped_address(address):
    if isinstance(address, ipaddress.IPv6Address) and address.ipv4_mapped:
        return address.ipv4_mapped
    return address


def address_allowed(address):
    address = _mapped_address(address)
    return address not in _METADATA_ADDRESSES


def resolve_service_target(parsed):
    port = parsed.port or (443 if parsed.scheme.lower() == 'https' else 80)
    try:
        records = socket.getaddrinfo(parsed.hostname, port, type=socket.SOCK_STREAM)
    except socket.gaierror as exc:
        raise ServiceUrlError('dns_error', 'Service host could not be resolved') from exc
    addresses = []
    for record in records:
        address = ipaddress.ip_address(record[4][0])
        if address not in addresses:
            addresses.append(address)
    if not addresses:
        raise ServiceUrlError('dns_error', 'Service host has no usable address')
    if not all(address_allowed(address) for address in addresses):
        raise ServiceUrlError('blocked_by_policy', 'Service target is blocked by network policy')
    return str(addresses[0]), port


class _PinnedHTTPSConnection(http.client.HTTPSConnection):
    def __init__(self, hostname, pinned_ip, port, timeout):
        context = ssl._create_unverified_context()
        super().__init__(hostname, port=port, timeout=timeout, context=context)
        self._pinned_ip = pinned_ip

    def connect(self):
        self.sock = socket.create_connection((self._pinned_ip, self.port), self.timeout)
        self.sock = self._context.wrap_socket(self.sock, server_hostname=self.host)

        self.peer_certificate = self.sock.getpeercert(binary_form=True)

def _host_header(parsed, port):
    host = parsed.hostname
    try:
        if ipaddress.ip_address(host).version == 6:
            host = '[' + host + ']'
    except ValueError:
        pass
    default = 443 if parsed.scheme.lower() == 'https' else 80
    return host if port == default else '{}:{}'.format(host, port)


def fetch_service_url(url, timeout=10, max_redirects=MAX_REDIRECTS):
    current = url
    initial_status = None
    started = time.monotonic()
    for redirect_count in range(max_redirects + 1):
        parsed = validate_service_url(current)
        pinned_ip, port = resolve_service_target(parsed)
        certificate = None
        connection_class = _PinnedHTTPSConnection if parsed.scheme.lower() == 'https' else http.client.HTTPConnection
        if connection_class is _PinnedHTTPSConnection:
            connection = connection_class(parsed.hostname, pinned_ip, port, timeout)
        else:
            connection = connection_class(pinned_ip, port=port, timeout=timeout)
        path = parsed.path or '/'
        if parsed.query:
            path += '?' + parsed.query
        try:
            connection.request('GET', path, headers={
                'Host': _host_header(parsed, port),
                'User-Agent': 'Pi.Alert service monitor',
                'Accept': '*/*',
                'Connection': 'close',
            })
            response = connection.getresponse()
            status = int(response.status)
            if initial_status is None:
                initial_status = status
            location = response.getheader('Location')
        finally:
            certificate = getattr(connection, 'peer_certificate', None)
            connection.close()
        if status not in (301, 302, 303, 307, 308) or not location:
            return {
                'status': status,
                'initial_status': initial_status,
                'latency': '{:.6f}'.format(time.monotonic() - started),
                'target_ip': pinned_ip,
                'final_url': current,
                'certificate': certificate,
            }
        if redirect_count >= max_redirects:
            raise ServiceUrlError('redirect_limit', 'Service redirect limit exceeded')
        current = urljoin(current, location)
    raise ServiceUrlError('redirect_limit', 'Service redirect limit exceeded')


def get_service_certificate(url, timeout=10):
    parsed = validate_service_url(url)
    if parsed.scheme.lower() != 'https':
        return None
    pinned_ip, port = resolve_service_target(parsed)
    context = ssl._create_unverified_context()
    with socket.create_connection((pinned_ip, port), timeout=timeout) as sock:
        with context.wrap_socket(sock, server_hostname=parsed.hostname) as tls_socket:
            return tls_socket.getpeercert(binary_form=True)

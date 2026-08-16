"""Structural validation and SSRF-safe transport for monitored service URLs."""
from __future__ import print_function

import http.client
import hashlib
import ipaddress
import re
import socket
import ssl
import time
from http.cookiejar import CookieJar
from urllib.parse import urljoin, urlsplit, urlunsplit
from urllib.request import Request


MAX_URL_LENGTH = 2048
MAX_REDIRECTS = 10
MAX_TOTAL_SECONDS = 30
MAX_COOKIES = 32
MAX_COOKIE_HEADER_LENGTH = 8192
MAX_COOKIE_NAME_LENGTH = 256
MAX_COOKIE_VALUE_LENGTH = 4096
REDIRECT_STATUSES = frozenset((301, 302, 303, 307, 308))
_BAD_CHARACTERS = re.compile(r'[\x00-\x20\x7f"\'`]')
_BAD_PERCENT = re.compile(r'%(?![0-9A-Fa-f]{2})')
_DNS_LABEL = re.compile(r'^[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?$')
_METADATA_ADDRESSES = frozenset((
    ipaddress.ip_address('169.254.169.254'),
    ipaddress.ip_address('100.100.100.200'),
))


class ServiceUrlError(ValueError):
    def __init__(self, code, message, history=None, target_ip='', elapsed=0.0):
        super().__init__(message)
        self.code = code
        self.history = list(history or ())
        self.target_ip = target_ip
        self.elapsed = elapsed


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


def _normalized_url(parsed):
    """Return a stable identity for loop detection without changing requests."""
    scheme = parsed.scheme.lower()
    host = parsed.hostname.lower()
    try:
        if ipaddress.ip_address(host).version == 6:
            host = '[' + host + ']'
    except ValueError:
        pass
    port = parsed.port
    default_port = 443 if scheme == 'https' else 80
    netloc = host if port is None or port == default_port else '{}:{}'.format(host, port)
    return urlunsplit((scheme, netloc, parsed.path or '/', parsed.query, ''))


def _diagnostic_url(value):
    """Return a bounded URL representation without query values."""
    try:
        parsed = validate_service_url(value)
    except ServiceUrlError:
        return '<invalid redirect URL>'
    scheme = parsed.scheme.lower()
    host = parsed.hostname.lower()
    try:
        if ipaddress.ip_address(host).version == 6:
            host = '[' + host + ']'
    except ValueError:
        pass
    port = parsed.port
    default_port = 443 if scheme == 'https' else 80
    netloc = host if port is None or port == default_port else '{}:{}'.format(host, port)
    query = '<redacted>' if parsed.query else ''
    return urlunsplit((scheme, netloc, parsed.path or '/', query, ''))


def service_url_for_log(value):
    """Expose the redacted URL form to monitor and helper logging code."""
    return _diagnostic_url(value)


def _apply_error_context(error, history, started, target_ip=''):
    error.history = list(history)
    error.target_ip = target_ip
    error.elapsed = time.monotonic() - started
    return error


def _trim_cookie_jar(cookie_jar):
    """Keep response-controlled redirect state small and request-local."""
    for index, cookie in enumerate(list(cookie_jar)):
        if (index >= MAX_COOKIES or len(cookie.name) > MAX_COOKIE_NAME_LENGTH or
                len(cookie.value or '') > MAX_COOKIE_VALUE_LENGTH):
            try:
                cookie_jar.clear(cookie.domain, cookie.path, cookie.name)
            except KeyError:
                pass


def _redirect_count(history):
    return sum(1 for hop in history
               if hop.get('status') in REDIRECT_STATUSES and hop.get('location'))


def service_result_note(result):
    """Create the short, non-sensitive note stored with a monitored service."""
    error_notes = {
        'invalid_url': 'Invalid service URL',
        'dns_error': 'DNS resolution failed',
        'connection_error': 'Connection failed',
        'tls_error': 'TLS connection failed',
        'blocked_by_policy': 'Blocked by network policy',
        'redirect_loop': 'Redirect loop detected',
        'redirect_limit': 'Redirect limit exceeded',
    }
    error_code = result.get('error_code', '')
    if error_code:
        return error_notes.get(error_code, 'Service check failed')
    count = int(result.get('redirect_count', 0))
    if count:
        initial_status = int(result.get('initial_status', 0))
        return 'Redirected by {} ({} redirect{})'.format(
            initial_status, count, '' if count == 1 else 's')
    return ''


def service_result_diagnostic(result):
    """Create a bounded redirect trace suitable for the service monitor log."""
    parts = []
    for hop in result.get('redirect_history', ())[:MAX_REDIRECTS + 1]:
        item = '{} {} [{}]'.format(
            hop.get('status', 0), hop.get('url', '<unknown>'),
            hop.get('target_ip', ''))
        if hop.get('location'):
            item += ' -> ' + hop['location']
        if hop.get('cookie_set'):
            item += ' (cookie set)'
        parts.append(item)
    prefix = result.get('error_code', '') or 'ok'
    return '{}: {}'.format(prefix, ' | '.join(parts))[:8192]


def fetch_service_url(url, timeout=10, max_redirects=MAX_REDIRECTS):
    if type(max_redirects) is not int or max_redirects < 0 or max_redirects > MAX_REDIRECTS:
        raise ValueError('max_redirects is outside the supported range')
    current = url
    initial_status = None
    started = time.monotonic()
    history = []
    visited_states = set()
    cookie_jar = CookieJar()
    last_target_ip = ''

    for redirect_index in range(max_redirects + 1):
        remaining = MAX_TOTAL_SECONDS - (time.monotonic() - started)
        if remaining <= 0:
            raise ServiceUrlError(
                'connection_error', 'Service check timed out', history,
                last_target_ip, time.monotonic() - started)
        connection_timeout = min(timeout, remaining)
        try:
            parsed = validate_service_url(current)
            pinned_ip, port = resolve_service_target(parsed)
        except ServiceUrlError as exc:
            raise _apply_error_context(exc, history, started, last_target_ip)
        last_target_ip = pinned_ip

        cookie_request = Request(current, method='GET')
        cookie_jar.add_cookie_header(cookie_request)
        cookie_header = cookie_request.get_header('Cookie', '')
        if len(cookie_header) > MAX_COOKIE_HEADER_LENGTH:
            cookie_header = ''
        cookie_state = hashlib.sha256(cookie_header.encode('utf-8')).digest()
        visit_state = (_normalized_url(parsed), cookie_state)
        if visit_state in visited_states:
            raise ServiceUrlError(
                'redirect_loop', 'Service redirect loop detected', history,
                last_target_ip, time.monotonic() - started)
        visited_states.add(visit_state)

        certificate = None
        connection_class = (_PinnedHTTPSConnection if parsed.scheme.lower() == 'https'
                            else http.client.HTTPConnection)
        if connection_class is _PinnedHTTPSConnection:
            connection = connection_class(parsed.hostname, pinned_ip, port, connection_timeout)
        else:
            connection = connection_class(pinned_ip, port=port, timeout=connection_timeout)
        path = parsed.path or '/'
        if parsed.query:
            path += '?' + parsed.query
        headers = {
            'Host': _host_header(parsed, port),
            'User-Agent': 'Pi.Alert service monitor',
            'Accept': '*/*',
            'Connection': 'close',
        }
        if cookie_header:
            headers['Cookie'] = cookie_header
        try:
            connection.request('GET', path, headers=headers)
            response = connection.getresponse()
            status = int(response.status)
            if initial_status is None:
                initial_status = status
            location = response.getheader('Location')
            set_cookie_headers = response.msg.get_all('Set-Cookie', [])
            try:
                cookie_jar.extract_cookies(response, cookie_request)
                _trim_cookie_jar(cookie_jar)
            except (AttributeError, TypeError, ValueError):
                pass
        except ssl.SSLError as exc:
            raise ServiceUrlError(
                'tls_error', 'TLS connection failed', history, last_target_ip,
                time.monotonic() - started) from exc
        except (OSError, http.client.HTTPException) as exc:
            raise ServiceUrlError(
                'connection_error', 'Service connection failed', history,
                last_target_ip, time.monotonic() - started) from exc
        finally:
            certificate = getattr(connection, 'peer_certificate', None)
            connection.close()

        next_url = urljoin(current, location) if location else ''
        history.append({
            'url': _diagnostic_url(current),
            'status': status,
            'target_ip': pinned_ip,
            'location': _diagnostic_url(next_url) if next_url else '',
            'cookie_sent': bool(cookie_header),
            'cookie_set': bool(set_cookie_headers),
        })
        if status not in REDIRECT_STATUSES or not location:
            return {
                'status': status,
                'initial_status': initial_status,
                'latency': '{:.6f}'.format(time.monotonic() - started),
                'target_ip': pinned_ip,
                'final_url': current,
                'certificate': certificate,
                'redirect_count': _redirect_count(history),
                'redirect_history': history,
                'error_code': '',
                'error_message': '',
            }
        if redirect_index >= max_redirects:
            raise ServiceUrlError(
                'redirect_limit', 'Service redirect limit exceeded', history,
                last_target_ip, time.monotonic() - started)
        current = next_url

    raise ServiceUrlError(
        'redirect_limit', 'Service redirect limit exceeded', history,
        last_target_ip, time.monotonic() - started)


def check_service_url(url, timeout=10, max_redirects=MAX_REDIRECTS):
    """Return the same bounded result shape for successful and failed checks."""
    try:
        result = fetch_service_url(url, timeout, max_redirects)
    except ServiceUrlError as exc:
        history = list(exc.history)
        result = {
            'status': 0,
            'initial_status': history[0]['status'] if history else 0,
            'latency': '99999999',
            'target_ip': exc.target_ip,
            'final_url': '',
            'certificate': None,
            'redirect_count': _redirect_count(history),
            'redirect_history': history,
            'error_code': exc.code,
            'error_message': str(exc),
        }
    result['note'] = service_result_note(result)
    result['diagnostic'] = service_result_diagnostic(result)
    return result

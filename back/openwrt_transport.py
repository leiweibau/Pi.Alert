"""Transport helpers for the optional OpenWrt LuCI RPC integration."""
from __future__ import print_function

import inspect
import ipaddress
import re

from requests.exceptions import ConnectionError, SSLError, Timeout


TRANSPORT_EXCEPTIONS = (SSLError, ConnectionError, Timeout)


def build_openwrt_host(host, port):
    """Add a validated port and bracket an IPv6 literal when necessary."""
    try:
        address = ipaddress.ip_address(host)
    except ValueError:
        return '{}:{}'.format(host, port)
    if address.version == 6:
        return '[{}]:{}'.format(address.compressed, port)
    return '{}:{}'.format(address.compressed, port)


def _connected_devices(router):
    method = router.get_all_connected_devices
    try:
        parameters = inspect.signature(method).parameters
    except (TypeError, ValueError):
        parameters = {}
    kwargs = {'only_reachable': True}
    if 'wlan_interfaces' in parameters:
        kwargs['wlan_interfaces'] = []
    return method(**kwargs)


def _request_devices(client_factory, host, port, username, password,
                     use_https):
    kwargs = {
        'host_url': build_openwrt_host(host, port),
        'username': str(username),
        'password': str(password),
        'is_https': use_https,
    }
    if use_https:
        kwargs['verify_https'] = False
    router = client_factory(**kwargs)
    return _connected_devices(router)


def fetch_openwrt_devices(client_factory, host, port, username, password,
                          use_https, status_callback=None,
                          error_callback=None):
    """Fetch devices with one narrowly scoped HTTPS-to-HTTP fallback."""
    notify = status_callback or (lambda _message: None)
    if not use_https:
        result = _request_devices(
            client_factory, host, port, username, password, False)
        notify('OpenWRT transport: HTTP (port {})'.format(port))
        return result

    try:
        result = _request_devices(
            client_factory, host, port, username, password, True)
        notify('OpenWRT transport: HTTPS (port {})'.format(port))
        return result
    except TRANSPORT_EXCEPTIONS as exc:
        notify('OpenWRT HTTPS unavailable; retrying via HTTP on port 80')
        if error_callback is not None:
            error_callback(exc)

    result = _request_devices(
        client_factory, host, 80, username, password, False)
    notify('OpenWRT transport: HTTP fallback (port 80)')
    return result


_ERROR_LABELS = {
    'InvalidLuciLoginError': 'OpenWRT authentication failed',
    'InvalidLuciTokenError': 'OpenWRT token was rejected',
    'PageNotFoundError': 'OpenWRT LuCI RPC endpoint was not found',
    'LuciRpcMethodNotFoundError': 'OpenWRT LuCI RPC method was not found',
    'LuciRpcUnknownError': 'OpenWRT LuCI RPC returned an invalid response',
    'LuciConfigError': 'OpenWRT target configuration is invalid',
    'SSLError': 'OpenWRT TLS connection failed',
    'ConnectTimeout': 'OpenWRT connection timed out',
    'ReadTimeout': 'OpenWRT connection timed out',
    'Timeout': 'OpenWRT connection timed out',
    'ConnectionError': 'OpenWRT connection failed',
}


def _safe_exception_detail(exception, secrets=()):
    detail = getattr(exception, 'message', '')
    if not detail:
        detail = ' '.join(
            str(item) for item in getattr(exception, 'args', ())
            if isinstance(item, (str, int, float)))
    detail = str(detail or '')
    detail = re.sub(r'https?://\S+', '<url>', detail, flags=re.IGNORECASE)
    detail = re.sub(
        r'(?i)\b(auth|password|token|secret|api[_ -]?key)\b\s*[:=]\s*\S+',
        r'\1=<redacted>', detail)
    for secret in secrets:
        if secret:
            detail = detail.replace(str(secret), '<redacted>')
    detail = ' '.join(detail.split())
    return detail[:300]


def format_openwrt_exception(exception, include_detail=False, secrets=()):
    label = _ERROR_LABELS.get(
        type(exception).__name__,
        'OpenWRT request failed ({})'.format(type(exception).__name__))
    if not include_detail:
        return label
    detail = _safe_exception_detail(exception, secrets)
    return '{}: {}'.format(label, detail) if detail else label

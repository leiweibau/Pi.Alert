"""Safe Telegram Bot API transport shared by Pi.Alert notification paths."""
from __future__ import print_function

import json
import re
from urllib.parse import parse_qsl, unquote, urlsplit

import requests


TELEGRAM_API_BASE = 'https://api.telegram.org'
TELEGRAM_MESSAGE_LIMIT = 4096
TELEGRAM_REQUEST_TIMEOUT = (5, 15)
MAX_CHAT_IDS = 32

_BOT_TOKEN = re.compile(r'^[0-9]+:[A-Za-z0-9_-]+$')
_NUMERIC_CHAT_ID = re.compile(r'^-?[1-9][0-9]{0,19}$')
_CHANNEL_USERNAME = re.compile(r'^@[A-Za-z][A-Za-z0-9_]{4,31}$')
_INVALID_PERCENT_ESCAPE = re.compile(r'%(?![0-9A-Fa-f]{2})')
_LEGACY_QUERY_KEYS = frozenset((
    'chats', 'channels', 'preview', 'notification', 'parsemode', 'title'
))
_TRUE_VALUES = frozenset(('1', 'yes', 'true', 'on'))
_FALSE_VALUES = frozenset(('0', 'no', 'false', 'off'))


class TelegramError(RuntimeError):
    """Base class for sanitized Telegram errors."""


class TelegramConfigError(TelegramError):
    """Raised when Telegram credentials are missing or invalid."""


class TelegramDeliveryError(TelegramError):
    """Raised when Telegram rejects or cannot receive a message."""


def is_valid_chat_id(value):
    """Return whether *value* is a supported Telegram chat destination."""
    return (isinstance(value, str) and len(value) <= 64 and
            (_NUMERIC_CHAT_ID.fullmatch(value) is not None or
             _CHANNEL_USERNAME.fullmatch(value) is not None))


def validate_chat_ids(chat_ids):
    """Validate and return a copy of a bounded chat-ID list."""
    if not isinstance(chat_ids, list) or not chat_ids or len(chat_ids) > MAX_CHAT_IDS:
        raise TelegramConfigError('Telegram chat IDs are missing or invalid')

    result = []
    seen = set()
    for chat_id in chat_ids:
        if not is_valid_chat_id(chat_id) or chat_id in seen:
            raise TelegramConfigError('Telegram chat IDs are missing or invalid')
        seen.add(chat_id)
        result.append(chat_id)
    return result


def validate_bot_token(token, strict_format=False):
    """Validate an opaque token without changing its contents."""
    if (not isinstance(token, str) or not token or len(token) > 512 or
            any(ord(character) < 32 for character in token)):
        raise TelegramConfigError('Telegram bot token is missing or invalid')
    if strict_format and _BOT_TOKEN.fullmatch(token) is None:
        raise TelegramConfigError('Legacy Telegram bot token is invalid')
    return token


def _strict_unquote(value):
    if _INVALID_PERCENT_ESCAPE.search(value):
        raise TelegramConfigError('Legacy Telegram URL contains invalid encoding')
    return unquote(value)


def _legacy_bool(values, key, default):
    if key not in values:
        return default
    normalized = values[key].lower()
    if normalized in _TRUE_VALUES:
        return True
    if normalized in _FALSE_VALUES:
        return False
    raise TelegramConfigError('Legacy Telegram URL contains invalid options')


def parse_legacy_shoutrrr_url(legacy_url):
    """Extract Telegram credentials from the old Shoutrrr URL as data only."""
    if (not isinstance(legacy_url, str) or not legacy_url or
            len(legacy_url) > 4096 or
            any(ord(character) < 32 for character in legacy_url)):
        raise TelegramConfigError('Legacy Telegram URL is missing or invalid')

    try:
        parts = urlsplit(legacy_url)
    except ValueError:
        raise TelegramConfigError('Legacy Telegram URL is invalid') from None

    if (parts.scheme.lower() != 'telegram' or parts.fragment or
            parts.path not in ('', '/') or parts.netloc.count('@') != 1):
        raise TelegramConfigError('Legacy Telegram URL is invalid')

    raw_token, raw_host = parts.netloc.rsplit('@', 1)
    if raw_host.lower() != 'telegram' or not raw_token:
        raise TelegramConfigError('Legacy Telegram URL is invalid')

    token = validate_bot_token(_strict_unquote(raw_token), strict_format=True)
    if _INVALID_PERCENT_ESCAPE.search(parts.query):
        raise TelegramConfigError('Legacy Telegram URL contains invalid encoding')

    try:
        query_items = parse_qsl(parts.query, keep_blank_values=True,
                                strict_parsing=False)
    except ValueError:
        raise TelegramConfigError('Legacy Telegram URL is invalid') from None

    values = {}
    for key, value in query_items:
        normalized_key = key.lower()
        if normalized_key not in _LEGACY_QUERY_KEYS or normalized_key in values:
            raise TelegramConfigError('Legacy Telegram URL contains unsupported options')
        values[normalized_key] = value

    target_values = []
    for target_key in ('chats', 'channels'):
        if target_key in values:
            target_values.extend(values[target_key].split(','))

    options = {
        'disable_preview': not _legacy_bool(values, 'preview', True),
        'disable_notification': not _legacy_bool(values, 'notification', True),
    }
    return token, validate_chat_ids(target_values), options


def resolve_telegram_credentials(bot_token, chat_ids, legacy_url=''):
    """Prefer complete direct credentials and otherwise parse legacy data."""
    direct_token_set = isinstance(bot_token, str) and bot_token != ''
    direct_chats_set = isinstance(chat_ids, list) and len(chat_ids) > 0

    if direct_token_set or direct_chats_set:
        if not direct_token_set or not direct_chats_set:
            raise TelegramConfigError('Telegram direct configuration is incomplete')
        return (validate_bot_token(bot_token), validate_chat_ids(chat_ids),
                {'disable_preview': True, 'disable_notification': False})

    return parse_legacy_shoutrrr_url(legacy_url)


def prepare_telegram_message(message, title=None):
    """Apply the legacy line-break cleanup and an optional plain-text title."""
    if not isinstance(message, str) or message == '':
        raise TelegramDeliveryError('Telegram message is empty or invalid')

    prepared = message.replace('\n\n\n', '\n\n')
    if title is not None:
        if not isinstance(title, str) or any(ord(character) < 32 for character in title):
            raise TelegramDeliveryError('Telegram title is invalid')
        title = title.strip()
        if title and not (prepared == title or prepared.startswith(title + '\n')):
            prepared = title + '\n\n' + prepared
    return prepared


def split_telegram_message(message, limit=TELEGRAM_MESSAGE_LIMIT):
    """Split text without losing characters and keep every part within limit."""
    if not isinstance(limit, int) or isinstance(limit, bool) or limit < 1:
        raise ValueError('limit must be a positive integer')
    if not isinstance(message, str) or message == '':
        raise TelegramDeliveryError('Telegram message is empty or invalid')

    parts = []
    remaining = message
    while len(remaining) > limit:
        split_at = remaining.rfind('\n', 0, limit + 1)
        if split_at >= limit // 2:
            split_at += 1
        else:
            split_at = remaining.rfind(' ', 0, limit + 1)
            if split_at >= limit // 2:
                split_at += 1
            else:
                split_at = limit
        parts.append(remaining[:split_at])
        remaining = remaining[split_at:]
    if remaining:
        parts.append(remaining)
    return parts


def _telegram_error_reason(response):
    if response.status_code != 200:
        return 'HTTP status {}'.format(response.status_code)
    try:
        payload = response.json()
    except (TypeError, ValueError):
        return 'invalid API response'
    if not isinstance(payload, dict) or payload.get('ok') is not True:
        return 'API rejected the request'
    return None


def send_telegram_message(bot_token, chat_ids, message, title=None,
                          legacy_url='', http_client=requests,
                          timeout=TELEGRAM_REQUEST_TIMEOUT):
    """Send a plain-text message to all configured Telegram destinations."""
    token, destinations, delivery_options = resolve_telegram_credentials(
        bot_token, chat_ids, legacy_url)
    prepared = prepare_telegram_message(message, title)
    message_parts = split_telegram_message(prepared)
    endpoint = '{}/bot{}/sendMessage'.format(TELEGRAM_API_BASE, token)

    failures = []
    sent_requests = 0
    for destination_index, chat_id in enumerate(destinations, 1):
        for part_index, part in enumerate(message_parts, 1):
            try:
                payload = {
                    'chat_id': chat_id,
                    'text': part,
                    'link_preview_options': json.dumps({
                        'is_disabled': delivery_options['disable_preview']
                    }, separators=(',', ':')),
                    'disable_notification': (
                        'true' if delivery_options['disable_notification'] else 'false'),
                }
                response = http_client.post(
                    endpoint,
                    data=payload,
                    timeout=timeout,
                    allow_redirects=False,
                    verify=True,
                )
            except Exception:
                failures.append(
                    'destination {} part {}: request failed'.format(
                        destination_index, part_index))
                break

            reason = _telegram_error_reason(response)
            if reason is not None:
                failures.append(
                    'destination {} part {}: {}'.format(
                        destination_index, part_index, reason))
                break
            sent_requests += 1

    if failures:
        raise TelegramDeliveryError(
            'Telegram delivery failed ({})'.format('; '.join(failures)))
    return sent_requests

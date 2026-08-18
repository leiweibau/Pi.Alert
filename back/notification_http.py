"""Shared, validated HTTP payloads for Pushsafer and Pushover."""
from __future__ import print_function

import requests


REQUEST_TIMEOUT_SECONDS = 15


class NotificationDeliveryError(RuntimeError):
    """A notification endpoint rejected or did not complete a request."""


def build_pushsafer_payload(message, title, dashboard_url, token, device,
                            priority, sound):
    return {
        't': title,
        'm': message,
        's': sound,
        'v': 3,
        'i': 148,
        'c': '#ef7f7f',
        'd': device,
        'u': dashboard_url,
        'ut': 'Open Pi.Alert',
        'k': token,
        'pr': priority,
    }


def build_pushover_payload(message, title, token, user, priority, sound,
                           retry, expire):
    payload = {
        'token': token,
        'user': user,
        'title': title,
        'message': message,
        'priority': priority,
        'sound': sound,
    }
    if priority == 2:
        payload['retry'] = retry
        payload['expire'] = expire
    return payload


def _post_form(session, url, payload, channel):
    try:
        response = session.post(
            url, data=payload, timeout=REQUEST_TIMEOUT_SECONDS)
        response.raise_for_status()
        return response
    except Exception as exc:
        raise NotificationDeliveryError(
            '{} request failed ({})'.format(
                channel, type(exc).__name__)) from exc


def send_pushsafer_notification(message, title, dashboard_url, token, device,
                                priority, sound, session=requests):
    payload = build_pushsafer_payload(
        message, title, dashboard_url, token, device, priority, sound)
    response = _post_form(
        session, 'https://www.pushsafer.com/api', payload, 'Pushsafer')
    try:
        body = response.json()
    except Exception as exc:
        raise NotificationDeliveryError(
            'Pushsafer returned an invalid response') from exc
    if type(body) is not dict or body.get('status') != 1:
        raise NotificationDeliveryError('Pushsafer rejected the notification')


def send_pushover_notification(message, title, token, user, priority, sound,
                               retry, expire, session=requests):
    payload = build_pushover_payload(
        message, title, token, user, priority, sound, retry, expire)
    response = _post_form(
        session, 'https://api.pushover.net/1/messages.json', payload,
        'Pushover')
    try:
        body = response.json()
    except Exception as exc:
        raise NotificationDeliveryError(
            'Pushover returned an invalid response') from exc
    if type(body) is not dict or body.get('status') != 1:
        raise NotificationDeliveryError('Pushover rejected the notification')

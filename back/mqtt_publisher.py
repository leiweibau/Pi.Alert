#!/usr/bin/env python3
"""Single-connection MQTT publishing and persistent retained-topic lifecycle."""

from __future__ import annotations

import json
import os
import re
import tempfile
from dataclasses import dataclass


MANIFEST_VERSION = 1
MAX_MANIFEST_BYTES = 1024 * 1024
MAX_MANIFEST_ENTRIES = 10000
MAX_TOPIC_LENGTH = 512
CATEGORIES = frozenset(("status", "local", "satellite", "device", "subnet"))
_DISCOVERY_TOPIC = re.compile(
    r"^homeassistant/(?:sensor|binary_sensor)/pialert.*/config$")


class ManifestError(RuntimeError):
    pass


class PublishError(RuntimeError):
    pass


@dataclass(frozen=True)
class RetainedMessage:
    topic: str
    payload: object
    category: str


def valid_topic(topic):
    if type(topic) is not str:
        return False
    try:
        encoded_length = len(topic.encode("utf-8"))
    except UnicodeEncodeError:
        return False
    return (0 < encoded_length <= MAX_TOPIC_LENGTH and
            all(ord(character) >= 32 and ord(character) != 127
                for character in topic) and
            "+" not in topic and "#" not in topic and
            (topic.startswith("pi_alert/") or bool(_DISCOVERY_TOPIC.match(topic))))


def _validated_entries(entries):
    if type(entries) is not list or len(entries) > MAX_MANIFEST_ENTRIES:
        raise ManifestError("MQTT manifest has an invalid topic list")
    topics = {}
    for entry in entries:
        if type(entry) is not dict or set(entry) != {"topic", "category"}:
            raise ManifestError("MQTT manifest has an invalid entry")
        topic, category = entry["topic"], entry["category"]
        if not valid_topic(topic) or category not in CATEGORIES or topic in topics:
            raise ManifestError("MQTT manifest has an unsafe or duplicate entry")
        topics[topic] = category
    return topics


class TopicManifest:
    def __init__(self, path):
        self.path = os.path.abspath(path)

    def load(self):
        if not os.path.exists(self.path):
            return False, {}
        try:
            if os.path.getsize(self.path) > MAX_MANIFEST_BYTES:
                raise ManifestError("MQTT manifest is too large")
            with open(self.path, "r", encoding="utf-8") as handle:
                value = json.load(handle)
        except ManifestError:
            raise
        except (OSError, UnicodeError, json.JSONDecodeError) as exc:
            raise ManifestError("MQTT manifest cannot be read safely") from exc
        if (type(value) is not dict or set(value) != {"version", "initialized", "topics"} or
                value["version"] != MANIFEST_VERSION or type(value["initialized"]) is not bool):
            raise ManifestError("MQTT manifest has an invalid structure")
        return value["initialized"], _validated_entries(value["topics"])

    def write(self, initialized, topics):
        if type(initialized) is not bool or type(topics) is not dict:
            raise ManifestError("invalid MQTT manifest state")
        entries = [{"topic": topic, "category": topics[topic]}
                   for topic in sorted(topics)]
        _validated_entries(entries)
        value = {"version": MANIFEST_VERSION, "initialized": initialized,
                 "topics": entries}
        encoded = (json.dumps(value, separators=(",", ":"), sort_keys=True) + "\n").encode("utf-8")
        if len(encoded) > MAX_MANIFEST_BYTES:
            raise ManifestError("MQTT manifest is too large")
        directory = os.path.dirname(self.path)
        try:
            os.makedirs(directory, mode=0o700, exist_ok=True)
            fd, temporary = tempfile.mkstemp(prefix=".mqtt-topics-", dir=directory)
            try:
                os.fchmod(fd, 0o600)
                with os.fdopen(fd, "wb") as handle:
                    fd = -1
                    handle.write(encoded)
                    handle.flush()
                    os.fsync(handle.fileno())
                os.replace(temporary, self.path)
                os.chmod(self.path, 0o600)
            finally:
                if fd >= 0:
                    os.close(fd)
                if os.path.exists(temporary):
                    os.unlink(temporary)
        except OSError as exc:
            raise ManifestError("MQTT manifest cannot be updated safely") from exc


class MQTTBatchPublisher:
    """Connect once and wait a bounded amount of time for every publish."""
    def __init__(self, client_factory, broker, port, username="", password="",
                 use_tls=False, publish_timeout=5):
        self.client_factory = client_factory
        self.broker = broker
        self.port = port
        self.username = username
        self.password = password
        self.use_tls = use_tls
        self.publish_timeout = publish_timeout

    def publish(self, messages):
        messages = list(messages)
        if not messages:
            return
        client = self.client_factory()
        started = False
        try:
            if self.username and self.password:
                client.username_pw_set(self.username, self.password)
            if self.use_tls:
                client.tls_set()
            result = client.connect(self.broker, self.port, 60)
            if result not in (0, None):
                raise PublishError("MQTT connect failed ({})".format(result))
            client.loop_start()
            started = True
            for message in messages:
                payload = message.payload if isinstance(message.payload, str) else json.dumps(message.payload)
                info = client.publish(message.topic, payload, retain=True)
                if getattr(info, "rc", 0) != 0:
                    raise PublishError("MQTT publish failed ({})".format(info.rc))
                waiter = getattr(info, "wait_for_publish", None)
                if waiter is not None:
                    completed = waiter(timeout=self.publish_timeout)
                    if completed is False:
                        raise PublishError("MQTT publish timed out")
                published = getattr(info, "is_published", None)
                if published is not None and not published():
                    raise PublishError("MQTT publish was not acknowledged")
        except PublishError:
            raise
        except Exception as exc:
            raise PublishError("MQTT connection or publish failed: {}".format(exc)) from exc
        finally:
            try:
                client.disconnect()
            except Exception:
                pass
            if started:
                try:
                    client.loop_stop()
                except Exception:
                    pass


def reconcile(manifest, publisher, desired, bootstrap=None, preserved_categories=()):
    """Write ahead, remove stale retained values, publish desired, then commit."""
    initialized, previous = manifest.load()
    if not initialized:
        previous.update(bootstrap() if bootstrap else {})
        initialized = True
    desired_map = {}
    for message in desired:
        if (not isinstance(message, RetainedMessage) or not valid_topic(message.topic) or
                message.category not in CATEGORIES or message.topic in desired_map):
            raise ManifestError("unsafe or duplicate desired MQTT topic")
        desired_map[message.topic] = message
    preserved = {topic: category for topic, category in previous.items()
                 if category in set(preserved_categories)}
    final = {topic: message.category for topic, message in desired_map.items()}
    final.update(preserved)
    write_ahead = dict(previous)
    write_ahead.update(final)
    manifest.write(initialized, write_ahead)
    stale = [RetainedMessage(topic, "", category)
             for topic, category in previous.items() if topic not in final]
    publisher.publish(stale + list(desired_map.values()))
    manifest.write(True, final)


def cleanup_all(manifest, publisher, bootstrap=None):
    initialized, topics = manifest.load()
    if not initialized:
        topics.update(bootstrap() if bootstrap else {})
        manifest.write(True, topics)
    if not topics:
        return False
    publisher.publish(RetainedMessage(topic, "", category)
                      for topic, category in topics.items())
    manifest.write(True, {})
    return True

#!/usr/bin/env python3
import json
import os
import sqlite3
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from mqtt_publisher import (
    MQTTBatchPublisher, ManifestError, PublishError, RetainedMessage,
    TopicManifest, cleanup_all, reconcile,
)


class RecordingPublisher:
    def __init__(self, fail=False):
        self.calls = []
        self.fail = fail

    def publish(self, messages):
        batch = list(messages)
        self.calls.append(batch)
        if self.fail:
            raise PublishError("no broker")


class ManifestTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.path = Path(self.directory.name) / "mqtt.json"
        self.manifest = TopicManifest(str(self.path))

    def tearDown(self):
        self.directory.cleanup()

    def test_write_is_restrictive_and_round_trips(self):
        topics = {"pi_alert/status/all": "status"}
        self.manifest.write(True, topics)
        self.assertEqual(self.manifest.load(), (True, topics))
        self.assertEqual(self.path.stat().st_mode & 0o777, 0o600)

    def test_manifest_rejects_wildcards_and_unrelated_discovery(self):
        for topic in ("pi_alert/status/+", "other/topic",
                      "homeassistant/sensor/not_pialert/config"):
            with self.assertRaises(ManifestError):
                self.manifest.write(True, {topic: "status"})

    def test_write_ahead_survives_failed_publish(self):
        new = RetainedMessage("pi_alert/subnet_010000000000/all", 1, "subnet")
        publisher = RecordingPublisher(fail=True)
        with self.assertRaises(PublishError):
            reconcile(self.manifest, publisher, [new])
        initialized, topics = self.manifest.load()
        self.assertTrue(initialized)
        self.assertEqual(topics, {new.topic: "subnet"})

    def test_stale_cleanup_then_manifest_reduction(self):
        old = "pi_alert/device/pialert_a/online"
        self.manifest.write(True, {old: "device"})
        wanted = RetainedMessage("pi_alert/status/all", 3, "status")
        publisher = RecordingPublisher()
        reconcile(self.manifest, publisher, [wanted])
        self.assertEqual([(m.topic, m.payload) for m in publisher.calls[0]],
                         [(old, ""), (wanted.topic, 3)])
        self.assertEqual(self.manifest.load()[1], {wanted.topic: "status"})

    def test_preserved_category_is_not_cleaned(self):
        old = "pi_alert/subnet_010000000000/all"
        self.manifest.write(True, {old: "subnet"})
        publisher = RecordingPublisher()
        reconcile(self.manifest, publisher, [], preserved_categories={"subnet"})
        self.assertEqual(publisher.calls[0], [])
        self.assertIn(old, self.manifest.load()[1])

    def test_global_cleanup_retries_after_failure_and_then_stays_empty(self):
        topics = {
            "pi_alert/status/all": "status",
            "homeassistant/sensor/pialert_status_all/config": "status",
            "pi_alert/device/pialert_a/ip": "device",
            "pi_alert/subnet_010000000000/all": "subnet",
        }
        self.manifest.write(True, topics)
        with self.assertRaises(PublishError):
            cleanup_all(self.manifest, RecordingPublisher(fail=True))
        self.assertEqual(self.manifest.load()[1], topics)
        publisher = RecordingPublisher()
        self.assertTrue(cleanup_all(self.manifest, publisher))
        self.assertTrue(all(message.payload == "" for message in publisher.calls[0]))
        self.assertEqual(self.manifest.load(), (True, {}))
        self.assertFalse(cleanup_all(self.manifest, RecordingPublisher()))

    def test_missing_manifest_initializes_empty_without_publisher_call(self):
        publisher = RecordingPublisher(fail=True)
        self.assertFalse(cleanup_all(self.manifest, publisher))
        self.assertEqual(publisher.calls, [])
        self.assertEqual(self.manifest.load(), (True, {}))

    def test_disabled_host_removes_four_state_and_four_discovery_topics(self):
        identifier = "pialert_aabbccddeeff"
        suffixes = ("online", "ip", "location", "scansource")
        topics = {
            **{f"pi_alert/device/{identifier}/{suffix}": "device"
               for suffix in suffixes},
            **{f"homeassistant/{'binary_sensor' if suffix == 'online' else 'sensor'}/{identifier}_{suffix}/config": "device"
               for suffix in suffixes},
            "pi_alert/device/pialert_other/online": "device",
        }
        self.manifest.write(True, topics)
        other = RetainedMessage(
            "pi_alert/device/pialert_other/online", "ON", "device")
        publisher = RecordingPublisher()
        reconcile(self.manifest, publisher, [other])
        tombstones = [message for message in publisher.calls[0]
                      if message.payload == ""]
        self.assertEqual(len(tombstones), 8)
        self.assertTrue(all(identifier in message.topic for message in tombstones))
        self.assertEqual(self.manifest.load()[1], {other.topic: "device"})


class FakeInfo:
    rc = 0

    def wait_for_publish(self, timeout):
        self.timeout = timeout

    def is_published(self):
        return True


class FakeClient:
    def __init__(self):
        self.connects = 0
        self.messages = []

    def connect(self, broker, port, keepalive):
        self.connects += 1
        return 0

    def loop_start(self): pass
    def loop_stop(self): pass
    def disconnect(self): pass

    def publish(self, topic, payload, retain):
        self.messages.append((topic, payload, retain))
        return FakeInfo()


class BatchPublisherTests(unittest.TestCase):
    def test_pialert_uses_wal_aware_readonly_uri(self):
        source = (Path(__file__).resolve().parent / "pialert.py").read_text()
        function = source.split("def _open_mqtt_database():", 1)[1].split(
            "\ndef _mqtt_snapshot", 1)[0]
        self.assertIn('"?mode=ro"', function)
        self.assertNotIn('"?mode=ro&immutable=1"', function)
        self.assertIn('PRAGMA query_only=ON', function)

    def test_master_off_path_does_not_request_legacy_bootstrap(self):
        source = (Path(__file__).resolve().parent / "pialert.py").read_text()
        branch = source.split("if not REPORT_TO_MQTT:", 1)[1].split(
            "connection = _open_mqtt_database()", 1)[0]
        self.assertIn("cleanup_all(manifest, publisher)", branch)
        self.assertNotIn("mqtt_legacy_topics", branch)

    def test_one_connection_and_compatible_json_payloads(self):
        client = FakeClient()
        publisher = MQTTBatchPublisher(lambda: client, "broker", 1883)
        publisher.publish([
            RetainedMessage("pi_alert/status/all", 2, "status"),
            RetainedMessage("pi_alert/status/status", "on", "status"),
            RetainedMessage("homeassistant/sensor/pialert_status_all/config",
                            {"name": "test"}, "status"),
        ])
        self.assertEqual(client.connects, 1)
        self.assertEqual(client.messages, [
            ("pi_alert/status/all", "2", True),
            ("pi_alert/status/status", "on", True),
            ("homeassistant/sensor/pialert_status_all/config",
             json.dumps({"name": "test"}), True),
        ])

    def test_readonly_database_sees_committed_wal_and_rejects_writes(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "wal.db"
            writer = sqlite3.connect(path)
            try:
                self.assertEqual(writer.execute("PRAGMA journal_mode=WAL").fetchone()[0],
                                 "wal")
                writer.execute("PRAGMA wal_autocheckpoint=0")
                writer.execute("CREATE TABLE sample (value INTEGER)")
                writer.commit()
                writer.execute("INSERT INTO sample VALUES (42)")
                writer.commit()
                self.assertTrue(Path(str(path) + "-wal").exists())

                uri = path.resolve().as_uri() + "?mode=ro"
                reader = sqlite3.connect(uri, uri=True)
                reader.execute("PRAGMA query_only=ON")
                try:
                    self.assertEqual(reader.execute(
                        "SELECT value FROM sample").fetchone()[0], 42)
                    with self.assertRaises(sqlite3.OperationalError):
                        reader.execute("INSERT INTO sample VALUES (43)")
                finally:
                    reader.close()
            finally:
                writer.close()


if __name__ == "__main__":
    unittest.main()

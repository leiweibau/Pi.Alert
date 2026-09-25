import sqlite3
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path
import importlib.util
from contextlib import contextmanager


MIGRATOR = Path(__file__).with_name("migrate_entity_actions.py")


class EntityActionsMigrationTests(unittest.TestCase):
    @contextmanager
    def connect(self):
        connection = sqlite3.connect(self.db)
        try:
            with connection:
                yield connection
        finally:
            connection.close()

    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.db = Path(self.temp.name) / "pialert.db"
        with self.connect() as connection:
            connection.executescript("""
                CREATE TABLE Devices (dev_MAC TEXT COLLATE NOCASE PRIMARY KEY, dev_LastIP TEXT);
                CREATE TABLE ICMP_Mon (icmp_ip TEXT PRIMARY KEY);
                INSERT INTO Devices VALUES ('AA:BB', '192.0.2.1');
                INSERT INTO Devices VALUES ('Internet', '192.0.2.2');
                INSERT INTO ICMP_Mon VALUES ('192.0.2.1');
            """)

    def run_migration(self):
        return subprocess.run([sys.executable, str(MIGRATOR), str(self.db)],
                              capture_output=True, text=True)

    def test_repeat_preserves_ids_and_creates_single_backup(self):
        self.assertEqual(self.run_migration().returncode, 0)
        with self.connect() as connection:
            connection.execute("INSERT INTO Entity_Actions (device_mac,url,icon_id,label,position,created_at,updated_at) "
                               "VALUES ('aa:bb','http://localhost','bi:link-45deg','',0,'now','now')")
            original_id = connection.execute("SELECT action_id FROM Entity_Actions").fetchone()[0]
        backups = list(self.db.parent.glob("*.bak"))
        self.assertEqual(len(backups), 1)
        self.assertEqual(self.run_migration().returncode, 0)
        self.assertEqual(len(list(self.db.parent.glob("*.bak"))), 1)
        with self.connect() as connection:
            self.assertEqual(connection.execute("SELECT action_id FROM Entity_Actions").fetchone()[0], original_id)

    def test_limit_cleanup_and_key_update(self):
        self.assertEqual(self.run_migration().returncode, 0)
        with self.connect() as connection:
            with self.assertRaises(sqlite3.IntegrityError):
                connection.execute("INSERT INTO Entity_Actions (device_mac,url,icon_id,label,color,position,created_at,updated_at) "
                                   "VALUES ('AA:BB','http://localhost','bi:link-45deg','','red',0,'now','now')")
            with self.assertRaises(sqlite3.IntegrityError):
                connection.execute("INSERT INTO Entity_Actions (device_mac,url,icon_id,label,color,text_color,position,created_at,updated_at) "
                                   "VALUES ('AA:BB','http://localhost','bi:link-45deg','','#112233','white',0,'now','now')")
            for position in range(3):
                connection.execute("INSERT INTO Entity_Actions (device_mac,url,icon_id,label,position,created_at,updated_at) "
                                   "VALUES (?,?,?,?,?,?,?)", ('AA:BB', 'http://localhost', 'bi:link-45deg', '', position, 'now', 'now'))
            with self.assertRaises(sqlite3.IntegrityError):
                connection.execute("INSERT INTO Entity_Actions (device_mac,url,icon_id,label,position,created_at,updated_at) "
                                   "VALUES ('AA:BB','http://localhost','bi:link-45deg','',3,'now','now')")
            connection.execute("UPDATE Devices SET dev_LastIP='192.0.2.3' WHERE dev_MAC='AA:BB'")
            self.assertEqual(connection.execute("SELECT COUNT(*) FROM Entity_Actions WHERE device_mac='AA:BB'").fetchone()[0], 3)
            connection.execute("UPDATE Devices SET dev_MAC='CC:DD' WHERE dev_MAC='AA:BB'")
            self.assertEqual(connection.execute("SELECT COUNT(*) FROM Entity_Actions WHERE device_mac='CC:DD'").fetchone()[0], 3)
            connection.execute("DELETE FROM Devices WHERE dev_MAC='CC:DD'")
            self.assertEqual(connection.execute("SELECT COUNT(*) FROM Entity_Actions").fetchone()[0], 0)
            connection.execute("INSERT INTO Entity_Actions (icmp_ip,url,icon_id,label,position,created_at,updated_at) "
                               "VALUES ('192.0.2.1','https://localhost','bi:link-45deg','',0,'now','now')")
            connection.execute("UPDATE ICMP_Mon SET icmp_ip='192.0.2.9' WHERE icmp_ip='192.0.2.1'")
            self.assertEqual(connection.execute("SELECT COUNT(*) FROM Entity_Actions WHERE icmp_ip='192.0.2.9'").fetchone()[0], 1)
            connection.execute("DELETE FROM ICMP_Mon WHERE icmp_ip='192.0.2.9'")
            self.assertEqual(connection.execute("SELECT COUNT(*) FROM Entity_Actions").fetchone()[0], 0)

    def test_conflicting_schema_is_rejected_without_modification(self):
        with self.connect() as connection:
            connection.execute("CREATE TABLE Entity_Actions (wrong INTEGER)")
        result = self.run_migration()
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("conflicting schema object", result.stderr)
        self.assertEqual(list(self.db.parent.glob("*.bak")), [])

    def test_legacy_schema_adds_color_without_losing_actions(self):
        spec = importlib.util.spec_from_file_location("entity_actions_migration", MIGRATOR)
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        with self.connect() as connection:
            connection.execute(module.LEGACY_TABLE_SQL)
            for name, (kind, sql) in module.OBJECTS.items():
                if name != "Entity_Actions":
                    connection.execute(sql)
            connection.execute("INSERT INTO Entity_Actions (device_mac,url,icon_id,label,position,created_at,updated_at) "
                               "VALUES ('AA:BB','https://localhost','bi:link-45deg','Router',0,'now','now')")
        self.assertEqual(self.run_migration().returncode, 0)
        with self.connect() as connection:
            row = connection.execute("SELECT url, color, text_color FROM Entity_Actions WHERE device_mac='AA:BB'").fetchone()
            self.assertEqual(row, ('https://localhost', '#6c757d', '#ffffff'))
        self.assertEqual(len(list(self.db.parent.glob("*.bak"))), 1)
        self.assertEqual(self.run_migration().returncode, 0)
        self.assertEqual(len(list(self.db.parent.glob("*.bak"))), 1)

    def test_color_schema_adds_contrasting_text_color(self):
        spec = importlib.util.spec_from_file_location("entity_actions_migration", MIGRATOR)
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        with self.connect() as connection:
            connection.execute(module.COLOR_TABLE_SQL)
            for name, (kind, sql) in module.OBJECTS.items():
                if name != "Entity_Actions":
                    connection.execute(sql)
            connection.execute("INSERT INTO Entity_Actions (device_mac,url,icon_id,label,color,position,created_at,updated_at) "
                               "VALUES ('AA:BB','https://localhost','bi:link-45deg','Router','#ffcc00',0,'now','now')")
        self.assertEqual(self.run_migration().returncode, 0)
        with self.connect() as connection:
            row = connection.execute("SELECT color, text_color FROM Entity_Actions WHERE device_mac='AA:BB'").fetchone()
            self.assertEqual(row, ('#ffcc00', '#111111'))

    def test_replace_same_key_preserves_association_with_default_sqlite_setting(self):
        self.assertEqual(self.run_migration().returncode, 0)
        with self.connect() as connection:
            connection.execute("INSERT INTO Entity_Actions (device_mac,url,icon_id,label,position,created_at,updated_at) "
                               "VALUES ('AA:BB','http://localhost','bi:link-45deg','',0,'now','now')")
            connection.execute("INSERT OR REPLACE INTO Devices VALUES ('AA:BB', '192.0.2.5')")
            self.assertEqual(connection.execute("SELECT COUNT(*) FROM Entity_Actions WHERE device_mac='AA:BB'").fetchone()[0], 1)
            connection.execute("DELETE FROM Devices WHERE dev_MAC='AA:BB'")
            connection.execute("INSERT INTO Devices VALUES ('AA:BB', '192.0.2.6')")
            self.assertEqual(connection.execute("SELECT COUNT(*) FROM Entity_Actions WHERE device_mac='AA:BB'").fetchone()[0], 0)

    def test_index_failure_rolls_back_all_new_objects(self):
        spec = importlib.util.spec_from_file_location("entity_actions_migration", MIGRATOR)
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        with self.connect() as connection:
            connection.execute(module.OBJECTS["Entity_Actions"][1])
            insert = ("INSERT INTO Entity_Actions (device_mac,url,icon_id,label,position,created_at,updated_at) "
                      "VALUES ('AA:BB','http://localhost','bi:link-45deg','',0,'now','now')")
            connection.execute(insert)
            connection.execute(insert)
        result = self.run_migration()
        self.assertNotEqual(result.returncode, 0)
        with self.connect() as connection:
            self.assertEqual(connection.execute("SELECT COUNT(*) FROM Entity_Actions").fetchone()[0], 2)
            self.assertIsNone(connection.execute("SELECT name FROM sqlite_master WHERE name='idx_entity_actions_device_position'").fetchone())
        self.assertEqual(len(list(self.db.parent.glob("*.bak"))), 1)


if __name__ == "__main__":
    unittest.main()

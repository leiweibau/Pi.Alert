#!/usr/bin/env python3
"""Install the additive Entity_Actions schema in an existing Pi.Alert DB."""

import argparse
import os
import sqlite3
import sys
from datetime import datetime
from pathlib import Path


LEGACY_TABLE_SQL = """CREATE TABLE Entity_Actions (
        action_id INTEGER PRIMARY KEY,
        device_mac TEXT COLLATE NOCASE,
        icmp_ip TEXT,
        url TEXT NOT NULL,
        icon_id TEXT NOT NULL,
        label TEXT NOT NULL DEFAULT '',
        position INTEGER NOT NULL CHECK(position IN (0, 1, 2)),
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        CHECK ((device_mac IS NOT NULL AND icmp_ip IS NULL) OR
               (device_mac IS NULL AND icmp_ip IS NOT NULL)),
        FOREIGN KEY (device_mac) REFERENCES Devices(dev_MAC),
        FOREIGN KEY (icmp_ip) REFERENCES ICMP_Mon(icmp_ip)
    )"""

COLOR_TABLE_SQL = """CREATE TABLE Entity_Actions (
        action_id INTEGER PRIMARY KEY,
        device_mac TEXT COLLATE NOCASE,
        icmp_ip TEXT,
        url TEXT NOT NULL,
        icon_id TEXT NOT NULL,
        label TEXT NOT NULL DEFAULT '',
        position INTEGER NOT NULL CHECK(position IN (0, 1, 2)),
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        color TEXT NOT NULL DEFAULT '#6c757d'
            CHECK(length(color) = 7 AND color GLOB '#[0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f]'),
        CHECK ((device_mac IS NOT NULL AND icmp_ip IS NULL) OR
               (device_mac IS NULL AND icmp_ip IS NOT NULL)),
        FOREIGN KEY (device_mac) REFERENCES Devices(dev_MAC),
        FOREIGN KEY (icmp_ip) REFERENCES ICMP_Mon(icmp_ip)
    )"""

ICMP_UNIQUE_INDEX = "idx_icmp_mon_ip_unique"
ICMP_UNIQUE_INDEX_SQL = (
    "CREATE UNIQUE INDEX idx_icmp_mon_ip_unique ON ICMP_Mon(icmp_ip)"
)


OBJECTS = {
    "Entity_Actions": ("table", """CREATE TABLE Entity_Actions (
        action_id INTEGER PRIMARY KEY,
        device_mac TEXT COLLATE NOCASE,
        icmp_ip TEXT,
        url TEXT NOT NULL,
        icon_id TEXT NOT NULL,
        label TEXT NOT NULL DEFAULT '',
        position INTEGER NOT NULL CHECK(position IN (0, 1, 2)),
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        color TEXT NOT NULL DEFAULT '#6c757d'
            CHECK(length(color) = 7 AND color GLOB '#[0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f]'),
        text_color TEXT NOT NULL DEFAULT '#ffffff'
            CHECK(length(text_color) = 7 AND text_color GLOB '#[0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f]'),
        CHECK ((device_mac IS NOT NULL AND icmp_ip IS NULL) OR
               (device_mac IS NULL AND icmp_ip IS NOT NULL)),
        FOREIGN KEY (device_mac) REFERENCES Devices(dev_MAC),
        FOREIGN KEY (icmp_ip) REFERENCES ICMP_Mon(icmp_ip)
    )"""),
    "idx_entity_actions_device_position": ("index", """CREATE UNIQUE INDEX idx_entity_actions_device_position
        ON Entity_Actions(device_mac, position) WHERE device_mac IS NOT NULL"""),
    "idx_entity_actions_icmp_position": ("index", """CREATE UNIQUE INDEX idx_entity_actions_icmp_position
        ON Entity_Actions(icmp_ip, position) WHERE icmp_ip IS NOT NULL"""),
    "trg_entity_actions_device_delete": ("trigger", """CREATE TRIGGER trg_entity_actions_device_delete AFTER DELETE ON Devices
        BEGIN DELETE FROM Entity_Actions WHERE device_mac = OLD.dev_MAC; END"""),
    "trg_entity_actions_icmp_delete": ("trigger", """CREATE TRIGGER trg_entity_actions_icmp_delete AFTER DELETE ON ICMP_Mon
        BEGIN DELETE FROM Entity_Actions WHERE icmp_ip = OLD.icmp_ip; END"""),
    "trg_entity_actions_device_update": ("trigger", """CREATE TRIGGER trg_entity_actions_device_update AFTER UPDATE OF dev_MAC ON Devices
        WHEN NEW.dev_MAC <> OLD.dev_MAC
        BEGIN UPDATE Entity_Actions SET device_mac = NEW.dev_MAC WHERE device_mac = OLD.dev_MAC; END"""),
    "trg_entity_actions_icmp_update": ("trigger", """CREATE TRIGGER trg_entity_actions_icmp_update AFTER UPDATE OF icmp_ip ON ICMP_Mon
        WHEN NEW.icmp_ip <> OLD.icmp_ip
        BEGIN UPDATE Entity_Actions SET icmp_ip = NEW.icmp_ip WHERE icmp_ip = OLD.icmp_ip; END"""),
}


def canonical(sql):
    return " ".join(sql.lower().split()).replace('"', '')


def contrast_color(color):
    try:
        channels = [int(color[offset:offset + 2], 16) / 255 for offset in (1, 3, 5)]
    except (TypeError, ValueError):
        return "#ffffff"
    channels = [channel / 12.92 if channel <= 0.03928
                else ((channel + 0.055) / 1.055) ** 2.4 for channel in channels]
    luminance = 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]
    return "#111111" if luminance > 0.179 else "#ffffff"


def migrate(path):
    db_path = Path(path).resolve(strict=True)
    if not db_path.is_file() or not os.access(db_path, os.W_OK):
        raise RuntimeError("database is not a writable file")
    uri = db_path.as_uri() + "?mode=rw"
    connection = sqlite3.connect(uri, uri=True, timeout=10)
    try:
        device_fields = {row[1]: row for row in connection.execute('PRAGMA table_info("Devices")')}
        if "dev_MAC" not in device_fields or device_fields["dev_MAC"][5] != 1:
            raise RuntimeError("expected primary key Devices.dev_MAC is missing")

        icmp_fields = {row[1]: row for row in connection.execute('PRAGMA table_info("ICMP_Mon")')}
        if "icmp_ip" not in icmp_fields:
            raise RuntimeError("expected key ICMP_Mon.icmp_ip is missing")
        # Older backups can have a NOT NULL IP column without a primary key.
        # SQLite accepts a unique index as a foreign-key parent, so repair the
        # key in place without rebuilding ICMP_Mon or changing its rows.
        needs_icmp_index = icmp_fields["icmp_ip"][5] != 1
        if needs_icmp_index:
            if not icmp_fields["icmp_ip"][3]:
                raise RuntimeError("ICMP_Mon.icmp_ip must be NOT NULL")
            index = connection.execute(
                "SELECT type, sql FROM sqlite_master WHERE name = ?", (ICMP_UNIQUE_INDEX,)
            ).fetchone()
            if index and (index[0] != "index" or
                          canonical(index[1] or "") != canonical(ICMP_UNIQUE_INDEX_SQL)):
                raise RuntimeError(f"conflicting schema object: {ICMP_UNIQUE_INDEX}")
            duplicate = connection.execute("""SELECT 1 FROM ICMP_Mon
                GROUP BY icmp_ip HAVING COUNT(*) > 1 LIMIT 1""").fetchone()
            if duplicate:
                raise RuntimeError("duplicate ICMP_Mon.icmp_ip values prevent unique index")
            needs_icmp_index = index is None
        existing = {row[0]: (row[1], row[2]) for row in connection.execute(
            "SELECT name, type, sql FROM sqlite_master WHERE name IN (%s)" %
            ",".join("?" for _ in OBJECTS), tuple(OBJECTS))}
        legacy_table = ("Entity_Actions" in existing
                        and existing["Entity_Actions"][0] == "table"
                        and canonical(existing["Entity_Actions"][1] or "") == canonical(LEGACY_TABLE_SQL))
        color_table = ("Entity_Actions" in existing
                       and existing["Entity_Actions"][0] == "table"
                       and canonical(existing["Entity_Actions"][1] or "") == canonical(COLOR_TABLE_SQL))
        for name, (kind, sql) in OBJECTS.items():
            if name in existing and (existing[name][0] != kind or
                    (name == "Entity_Actions" and not legacy_table and not color_table and
                     canonical(existing[name][1] or "") != canonical(sql)) or
                    (name != "Entity_Actions" and canonical(existing[name][1] or "") != canonical(sql))):
                raise RuntimeError(f"conflicting schema object: {name}")
        if len(existing) == len(OBJECTS) and not legacy_table and not color_table and not needs_icmp_index:
            print("Entity_Actions schema already current")
            return

        stamp = datetime.now().strftime("%Y%m%d-%H%M%S-%f")
        backup_path = db_path.with_name(db_path.name + f".entity-actions-{stamp}.bak")
        with sqlite3.connect(backup_path) as backup:
            connection.backup(backup)
        # This internal rollback copy contains the full database and does not
        # need the live database's group or world access rights.
        os.chmod(backup_path, 0o600)
        connection.execute("BEGIN IMMEDIATE")
        try:
            if needs_icmp_index:
                connection.execute(ICMP_UNIQUE_INDEX_SQL)
            if legacy_table:
                connection.execute("""ALTER TABLE Entity_Actions ADD COLUMN color TEXT NOT NULL
                    DEFAULT '#6c757d' CHECK(length(color) = 7 AND
                    color GLOB '#[0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f]')""")
            if legacy_table or color_table:
                connection.execute("""ALTER TABLE Entity_Actions ADD COLUMN text_color TEXT NOT NULL
                    DEFAULT '#ffffff' CHECK(length(text_color) = 7 AND
                    text_color GLOB '#[0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f]')""")
                for action_id, color in connection.execute("SELECT action_id, color FROM Entity_Actions"):
                    connection.execute("UPDATE Entity_Actions SET text_color = ? WHERE action_id = ?",
                                       (contrast_color(color), action_id))
            for name, (_, sql) in OBJECTS.items():
                if name not in existing:
                    connection.execute(sql)
            connection.commit()
        except Exception:
            connection.rollback()
            raise
        print(f"Entity_Actions schema installed; backup: {backup_path}")
    finally:
        connection.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("database", help="existing pialert.db path")
    args = parser.parse_args()
    try:
        migrate(args.database)
    except (OSError, sqlite3.Error, RuntimeError) as error:
        print(f"Entity_Actions migration failed: {error}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())

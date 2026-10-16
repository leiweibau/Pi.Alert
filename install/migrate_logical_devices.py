#!/usr/bin/env python3
"""Add independent device identities. Explicit CLI migration; never run on reads.

UTC ISO seconds are used for both timestamps. Imported legacy rows without a
created_at start at migration time; empty_since is initialized only if absent.
Unicode uniqueness uses NFC followed by Unicode casefold and NFC again.
"""
import argparse
import json
from pathlib import Path
import sqlite3
import sys
import unicodedata


def name_key(name):
    return unicodedata.normalize('NFC', unicodedata.normalize('NFC', name).casefold())


def migrate(connection):
    if connection.in_transaction:
        raise RuntimeError('Migration requires its own transaction')
    connection.execute('BEGIN IMMEDIATE')
    try:
        connection.execute('''CREATE TABLE IF NOT EXISTS LogicalDevices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL CHECK(length(trim(name)) BETWEEN 1 AND 250),
            name_key TEXT NOT NULL UNIQUE,
            device_type TEXT NOT NULL DEFAULT '', owner TEXT NOT NULL DEFAULT '',
            location TEXT NOT NULL DEFAULT '', vendor TEXT NOT NULL DEFAULT '',
            "group" TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now')),
            empty_since TEXT DEFAULT (strftime('%Y-%m-%d %H:%M:%S','now'))
        )''')
        columns = {row[1] for row in connection.execute('PRAGMA table_info(Devices)')}
        if 'dev_LogicalDeviceID' not in columns:
            connection.execute('ALTER TABLE Devices ADD COLUMN dev_LogicalDeviceID INTEGER REFERENCES LogicalDevices(id)')
        connection.execute('CREATE INDEX IF NOT EXISTS Devices_LogicalDeviceID ON Devices(dev_LogicalDeviceID)')
        connection.execute('''CREATE TABLE IF NOT EXISTS LogicalDeviceContext (
            id INTEGER PRIMARY KEY CHECK(id=1), trigger TEXT NOT NULL DEFAULT 'database',
            suppress_journal INTEGER NOT NULL DEFAULT 0 CHECK(suppress_journal IN (0,1)))''')
        connection.execute("INSERT OR IGNORE INTO LogicalDeviceContext(id) VALUES(1)")
        for row in connection.execute('SELECT id, name, name_key FROM LogicalDevices'):
            if row[2] != name_key(row[1]):
                raise RuntimeError('Invalid logical device name key')
        if connection.execute('''SELECT 1 FROM Devices d WHERE dev_LogicalDeviceID IS NOT NULL
            AND NOT EXISTS(SELECT 1 FROM LogicalDevices l WHERE l.id=d.dev_LogicalDeviceID) LIMIT 1''').fetchone():
            raise RuntimeError('Orphaned logical device reference')
        # Each DDL statement remains inside this transaction (executescript commits).
        statement = ''
        for line in Path(__file__).with_name('logical_devices.sql').read_text().splitlines(True):
            statement += line
            if sqlite3.complete_statement(statement):
                connection.execute(statement)
                statement = ''
        if statement.strip():
            raise RuntimeError('Incomplete schema SQL')
        connection.commit()
    except Exception:
        connection.rollback()
        raise


def cleanup_empty(connection, now=None):
    """Called by the regular cleanup. Its existing journal entry covers this."""
    connection.execute('BEGIN IMMEDIATE')
    try:
        connection.execute("UPDATE LogicalDeviceContext SET trigger='cronjob', suppress_journal=1 WHERE id=1")
        result = connection.execute('''DELETE FROM LogicalDevices WHERE empty_since IS NOT NULL
            AND julianday(empty_since) <= julianday(COALESCE(?, 'now')) - 30
            AND NOT EXISTS(SELECT 1 FROM Devices WHERE dev_LogicalDeviceID=LogicalDevices.id)''', (now,))
        removed = result.rowcount
        connection.execute("UPDATE LogicalDeviceContext SET trigger='database', suppress_journal=0 WHERE id=1")
        connection.commit()
        return removed
    except Exception:
        connection.rollback()
        raise


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('database', nargs='?')
    parser.add_argument('--name-key', action='store_true')
    args = parser.parse_args()
    if args.name_key:
        value = json.load(sys.stdin)
        if not isinstance(value, str) or len(value) > 250:
            raise ValueError('Invalid name')
        print(json.dumps(name_key(value), ensure_ascii=False))
        return
    if not args.database:
        parser.error('database required')
    path = Path(args.database).resolve(strict=True)
    connection = sqlite3.connect(str(path), timeout=30)
    try:
        migrate(connection)
        print('LogicalDevices migration complete')
    finally:
        connection.close()


if __name__ == '__main__':
    main()

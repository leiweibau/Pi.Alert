#!/usr/bin/env python3
import importlib
import sqlite3
import sys
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest import mock


BACK_PATH = Path(__file__).resolve().parent
sys.path.insert(0, str(BACK_PATH))

import config_validation

config_validation.load_pialert_config = lambda *args, **kwargs: {
    'DB_PATH': ':memory:',
    'PRINT_LOG': False,
}
config_validation.validate_loaded_config = lambda values, *args, **kwargs: values

pialert_tools = importlib.import_module('pialert_tools')
pialert_reporting = importlib.import_module('pialert_reporting_test')


NMAP_XML = '''<?xml version="1.0"?>
<nmaprun>
  <host><ports>
    <port protocol="tcp" portid="443">
      <state state="open"/>
      <service name="https" product="nginx" version="1.26"/>
    </port>
    <port protocol="udp" portid="53">
      <state state="open"/>
      <service name="domain"/>
    </port>
    <port protocol="udp" portid="161">
      <state state="open|filtered"/>
      <service name="snmp"/>
    </port>
    <port protocol="tcp" portid="22"><state state="closed"/></port>
  </ports></host>
</nmaprun>'''


class NmapQueueTests(unittest.TestCase):
    def test_detail_command_scans_tcp_and_udp_without_shell(self):
        root_commands = pialert_tools.build_nmap_detail_commands('192.0.2.10', 0)
        tcp_command = root_commands[0][1]
        udp_command = root_commands[1][1]
        self.assertEqual(tcp_command[0], '/usr/bin/nmap')
        self.assertIn('-sS', tcp_command)
        self.assertNotIn('-sU', tcp_command)
        self.assertEqual(tcp_command[tcp_command.index('--top-ports') + 1], '1000')
        self.assertNotIn('-O', tcp_command)
        self.assertIn('-sU', udp_command)
        self.assertNotIn('-sS', udp_command)
        self.assertEqual(udp_command[udp_command.index('--top-ports') + 1], '140')
        self.assertEqual(udp_command[udp_command.index('--version-intensity') + 1], '0')
        self.assertNotIn('-O', udp_command)
        self.assertEqual(tcp_command[-1], '192.0.2.10')

        user_commands = pialert_tools.build_nmap_detail_commands('2001:db8::10', 1000)
        for _, user_command, _ in user_commands:
            self.assertEqual(user_command[:4], ['/usr/bin/sudo', '-n', '--', '/usr/bin/nmap'])
            self.assertIn('-6', user_command)
            self.assertNotIn('sh', user_command)

    def test_xml_parser_keeps_udp_open_filtered(self):
        ports = pialert_tools.parse_nmap_xml(NMAP_XML)
        self.assertEqual(len(ports), 3)
        self.assertTrue(any(port['protocol'] == 'udp' and port['status'] == 'open|filtered' for port in ports))
        serialized = pialert_tools.serialize_nmap_ports(ports)
        self.assertIn('443###tcp###open###https nginx 1.26', serialized)
        self.assertIn('161###udp###open|filtered###snmp', serialized)
        self.assertNotIn('22###tcp###closed', serialized)

    def test_schema_is_idempotent_and_queue_is_deduplicated(self):
        connection = sqlite3.connect(':memory:')
        connection.row_factory = sqlite3.Row
        pialert_tools.ensure_nmap_queue_schema(connection)
        pialert_tools.ensure_nmap_queue_schema(connection)
        connection.execute(
            """INSERT INTO Tools_Nmap_Queue
               (device_mac, target_ip, scan_type, source, status, requested_at)
               VALUES ('AA:BB:CC:DD:EE:FF', '192.0.2.10', 'detail', 'manual', 'queued', '2026-01-01 00:00:00')"""
        )
        with self.assertRaises(sqlite3.IntegrityError):
            connection.execute(
                """INSERT INTO Tools_Nmap_Queue
                   (device_mac, target_ip, scan_type, source, status, requested_at)
                   VALUES ('aa:bb:cc:dd:ee:ff', '192.0.2.10', 'detail', 'manual', 'queued', '2026-01-01 00:00:01')"""
            )
        schedule = connection.execute("SELECT COUNT(*) FROM Tools_Nmap_Schedule WHERE enabled = 1").fetchone()[0]
        self.assertEqual(schedule, 0)
        connection.close()

    def test_reporting_reads_queue_and_counts_protocols(self):
        with tempfile.TemporaryDirectory() as temp_dir:
            tools_path = str(Path(temp_dir) / 'tools.db')
            main_path = str(Path(temp_dir) / 'main.db')
            tools = sqlite3.connect(tools_path)
            pialert_tools.ensure_nmap_queue_schema(tools)
            tools.execute(
                """CREATE TABLE Tools_Nmap_ManScan (
                       ID INTEGER PRIMARY KEY AUTOINCREMENT,
                       scan_result TEXT, reserve_a INTEGER)"""
            )
            result_id = tools.execute(
                "INSERT INTO Tools_Nmap_ManScan (scan_result, reserve_a) VALUES (?, ?)",
                ('443###tcp###open###https\n53###udp###open###domain', 17)
            ).lastrowid
            tools.execute(
                """INSERT INTO Tools_Nmap_Queue
                   (device_mac, target_ip, status, requested_at, started_at,
                    completed_at, result_id)
                   VALUES (?, ?, 'completed', ?, ?, ?, ?)""",
                ('AA:BB:CC:DD:EE:FF', '192.0.2.10', '2026-01-01 00:00:00',
                 '2026-01-01 00:01:00.123456', '2026-01-01 00:01:17.492332', result_id)
            )
            queue_id = tools.execute('SELECT queue_id FROM Tools_Nmap_Queue').fetchone()[0]
            tools.commit()
            tools.close()

            main = sqlite3.connect(main_path)
            main.execute('CREATE TABLE Devices (dev_MAC TEXT, dev_Name TEXT)')
            main.execute('INSERT INTO Devices VALUES (?, ?)', ('AA:BB:CC:DD:EE:FF', 'Test device'))
            main.commit()
            main.close()

            old_tools = pialert_reporting.PIALERT_DBTOOLS_FILE
            old_main = pialert_reporting.PIALERT_DB_FILE
            try:
                pialert_reporting.PIALERT_DBTOOLS_FILE = tools_path
                pialert_reporting.PIALERT_DB_FILE = main_path
                message = pialert_reporting.load_nmap_scan_notification(queue_id)
            finally:
                pialert_reporting.PIALERT_DBTOOLS_FILE = old_tools
                pialert_reporting.PIALERT_DB_FILE = old_main

            self.assertIn('Name: Test device', message)
            self.assertIn('\n\tMAC: AA:BB:CC:DD:EE:FF', message)
            self.assertIn('\n\tStarted: 2026-01-01 00:01:00', message)
            self.assertIn('\n\tCompleted: 2026-01-01 00:01:17', message)
            self.assertNotIn('.123456', message)
            self.assertNotIn('.492332', message)
            self.assertIn('\n\tTCP findings: 1', message)
            self.assertIn('\n\tUDP findings: 1', message)

    def test_claim_is_atomic_and_increments_attempts(self):
        connection = sqlite3.connect(':memory:', isolation_level=None)
        connection.row_factory = sqlite3.Row
        pialert_tools.ensure_nmap_queue_schema(connection)
        connection.execute(
            """INSERT INTO Tools_Nmap_Queue
               (device_mac, target_ip, requested_at) VALUES (?, ?, ?)""",
            ('AA:BB:CC:DD:EE:FF', '192.0.2.10', '2026-01-01 00:00:00')
        )
        job = pialert_tools._claim_next_nmap_job(connection)
        self.assertEqual(job['status'], 'running')
        self.assertEqual(job['attempts'], 1)
        self.assertIsNone(pialert_tools._claim_next_nmap_job(connection))
        connection.close()

    def test_worker_persists_result_before_finalizing_queue(self):
        with tempfile.TemporaryDirectory() as temp_dir:
            main_path = str(Path(temp_dir) / 'main.db')
            tools_path = str(Path(temp_dir) / 'tools.db')

            main = sqlite3.connect(main_path)
            main.executescript("""
                CREATE TABLE Devices (dev_MAC TEXT, dev_LastIP TEXT, dev_Name TEXT);
                CREATE TABLE pialert_journal (
                    Journal_DateTime TEXT, LogClass TEXT, Trigger TEXT,
                    LogString TEXT, Hash TEXT, Additional_Info TEXT);
            """)
            main.execute('INSERT INTO Devices VALUES (?, ?, ?)', ('AA:BB:CC:DD:EE:FF', '192.0.2.10', 'Test device'))
            main.commit()
            main.close()

            tools = sqlite3.connect(tools_path, isolation_level=None)
            tools.row_factory = sqlite3.Row
            pialert_tools.ensure_nmap_queue_schema(tools)
            tools.execute("""CREATE TABLE Tools_Nmap_ManScan (
                ID INTEGER PRIMARY KEY AUTOINCREMENT, scan_date TEXT,
                scan_target TEXT, scan_type TEXT, scan_result TEXT,
                reserve_a INTEGER, reserve_b TEXT, reserve_c TEXT, reserve_d TEXT)""")
            tools.execute(
                """INSERT INTO Tools_Nmap_Queue
                   (device_mac, target_ip, requested_at) VALUES (?, ?, ?)""",
                ('AA:BB:CC:DD:EE:FF', '192.0.2.9', '2026-01-01 00:00:00')
            )
            job = pialert_tools._claim_next_nmap_job(tools)

            old_db_path = pialert_tools.DB_PATH
            try:
                pialert_tools.DB_PATH = main_path
                tcp_xml = NMAP_XML.replace('<port protocol="udp" portid="53">\n      <state state="open"/>\n      <service name="domain"/>\n    </port>', '').replace('<port protocol="udp" portid="161">\n      <state state="open|filtered"/>\n      <service name="snmp"/>\n    </port>', '')
                udp_xml = NMAP_XML.replace('<port protocol="tcp" portid="443">\n      <state state="open"/>\n      <service name="https" product="nginx" version="1.26"/>\n    </port>', '').replace('<port protocol="tcp" portid="22"><state state="closed"/></port>', '')
                completed = [
                    SimpleNamespace(returncode=0, stdout=tcp_xml, stderr=''),
                    SimpleNamespace(returncode=0, stdout=udp_xml, stderr=''),
                ]
                with mock.patch.object(pialert_tools.os.path, 'isfile', return_value=True), \
                     mock.patch.object(pialert_tools.os, 'access', return_value=True), \
                     mock.patch.object(pialert_tools.subprocess, 'run', side_effect=completed):
                    pialert_tools._process_nmap_job(tools, job)
            finally:
                pialert_tools.DB_PATH = old_db_path

            stored = tools.execute('SELECT * FROM Tools_Nmap_ManScan').fetchone()
            queued = tools.execute('SELECT * FROM Tools_Nmap_Queue').fetchone()
            self.assertEqual(stored['scan_target'], '192.0.2.10')
            self.assertIn('53###udp###open###domain', stored['scan_result'])
            self.assertEqual(queued['status'], 'completed')
            self.assertEqual(queued['result_id'], stored['ID'])

            with mock.patch.object(pialert_tools.subprocess, 'run', return_value=SimpleNamespace(returncode=0)):
                pialert_tools._finalize_nmap_job(tools, queued['queue_id'])
            self.assertEqual(tools.execute('SELECT COUNT(*) FROM Tools_Nmap_Queue').fetchone()[0], 0)
            tools.close()


if __name__ == '__main__':
    unittest.main()

#!/usr/bin/env python3
import ipaddress
import json
import sqlite3
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

sys.path.insert(0, str(Path(__file__).resolve().parent))

from mqtt_subnet_status import (
    SubnetDetectionError, _route_ipv4, count_subnet_rows,
    discover_local_ipv4_subnets, discover_with_ip, read_subnet_counts,
    discover_with_fallback, subnet_identifiers,
)


class Result:
    returncode = 0
    stderr = b""

    def __init__(self, value):
        self.stdout = json.dumps(value).encode()


class SubnetDetectionTests(unittest.TestCase):
    def test_ip_json_accepts_up_unknown_vlan_and_deduplicates(self):
        result = Result([
            {"ifname": "lo", "flags": ["UP"], "addr_info": [
                {"family": "inet", "local": "127.0.0.1", "prefixlen": 8}]},
            {"ifname": "eth0", "operstate": "UNKNOWN", "flags": ["UP"],
             "addr_info": [
                 {"family": "inet", "local": "192.168.1.5", "prefixlen": 24},
                 {"family": "inet", "local": "192.168.1.6", "prefixlen": 24}]},
            {"ifname": "eth0.20", "flags": ["UP"], "addr_info": [
                {"family": "inet", "local": "10.1.3.4", "prefixlen": 21}]},
            {"ifname": "down0", "flags": [], "addr_info": [
                {"family": "inet", "local": "172.16.0.1", "prefixlen": 16}]},
        ])
        run = mock.Mock(return_value=result)
        self.assertEqual(discover_with_ip(run), [
            ipaddress.ip_network("10.1.0.0/21"),
            ipaddress.ip_network("192.168.1.0/24")])
        self.assertEqual(run.call_args.args[0], ["ip", "-j", "-4", "address", "show"])
        self.assertFalse(run.call_args.kwargs["shell"])

    def test_primary_errors_use_fallback(self):
        with mock.patch("mqtt_subnet_status.discover_with_fallback",
                        return_value=[ipaddress.ip_network("10.0.0.0/24")]):
            result = discover_local_ipv4_subnets(
                run=mock.Mock(side_effect=subprocess.TimeoutExpired("ip", 3)))
        self.assertEqual(result, [ipaddress.ip_network("10.0.0.0/24")])

    def test_both_discovery_paths_failing_is_not_an_empty_success(self):
        with mock.patch("mqtt_subnet_status.discover_with_fallback",
                        side_effect=SubnetDetectionError("blocked")):
            with self.assertRaises(SubnetDetectionError):
                discover_local_ipv4_subnets(run=mock.Mock(side_effect=OSError("missing")))

    def test_proc_route_values_are_little_endian(self):
        self.assertEqual(str(_route_ipv4("0001A8C0")), "192.168.1.0")
        self.assertEqual(str(_route_ipv4("00FFFFFF")), "255.255.255.0")

    def test_proc_only_fallback_filters_gateway_default_and_host_routes(self):
        with tempfile.TemporaryDirectory() as directory:
            dev = Path(directory) / "dev"
            route = Path(directory) / "route"
            dev.write_text(
                "header\nheader\n  lo: 0\n  eth9: 0\n", encoding="ascii")
            route.write_text(
                "Iface Destination Gateway Flags RefCnt Use Metric Mask MTU Window IRTT\n"
                "eth9 00000000 0100000A 0003 0 0 0 00000000 0 0 0\n"
                "eth9 0000000A 00000000 0001 0 0 0 00FFFFFF 0 0 0\n"
                "eth9 0002000A 0100000A 0003 0 0 0 00FFFFFF 0 0 0\n"
                "eth9 0500000A 00000000 0001 0 0 0 FFFFFFFF 0 0 0\n",
                encoding="ascii")
            networks = discover_with_fallback(
                route_path=str(route), interface_path=str(dev),
                interface_provider=mock.Mock(side_effect=PermissionError()),
                socket_factory=mock.Mock(side_effect=PermissionError()))
        self.assertEqual(networks, [ipaddress.ip_network("10.0.0.0/24")])


class SubnetCounterTests(unittest.TestCase):
    def test_identifiers_and_collisions(self):
        identifiers = subnet_identifiers([
            "192.168.0.0/24", "192.168.100.0/24", "10.1.0.0/21"])
        self.assertEqual(identifiers[ipaddress.ip_network("192.168.0.0/24")],
                         "192168000000")
        self.assertEqual(identifiers[ipaddress.ip_network("192.168.100.0/24")],
                         "192168100000")
        self.assertEqual(identifiers[ipaddress.ip_network("10.1.0.0/21")],
                         "010001000000")
        collision = subnet_identifiers(["192.168.0.0/16", "192.168.0.0/24"])
        self.assertEqual(set(collision.values()),
                         {"192168000000_p16", "192168000000_p24"})

    def test_longest_prefix_and_all_status_groups(self):
        networks = ["10.0.0.0/8", "10.1.0.0/16", "192.168.1.0/24"]
        devices = [
            ("10.1.2.3", 0, 1, 1, 0),
            ("10.2.2.3", 0, 0, 0, 1),
            ("192.168.1.3", 0, 0, 0, 0),
            ("192.168.1.4", 1, 0, 0, 0),
            ("Internet", 0, 1, 0, 0), ("2001:db8::1", 0, 1, 0, 0),
        ]
        icmp = [("10.1.9.9", 0, 1), ("10.2.9.9", 0, 0),
                ("192.168.1.8", 1, 1), ("bad", 0, 1)]
        counts, skipped = count_subnet_rows(networks, devices, icmp)
        broad = counts[ipaddress.ip_network("10.0.0.0/8")]
        narrow = counts[ipaddress.ip_network("10.1.0.0/16")]
        lan = counts[ipaddress.ip_network("192.168.1.0/24")]
        self.assertEqual((broad["all"], broad["down"], broad["icmp_offline"]),
                         (1, 1, 1))
        self.assertEqual((narrow["all"], narrow["online"], narrow["new"],
                          narrow["icmp_online"]), (1, 1, 1, 1))
        self.assertEqual((lan["all"], lan["offline"], lan["archive"],
                          lan["icmp_all"]), (1, 1, 1, 0))
        self.assertEqual(skipped, 3)

    def test_empty_subnet_has_all_nine_zero_values(self):
        counts, _ = count_subnet_rows(["172.16.0.0/16"], [], [])
        values = counts[ipaddress.ip_network("172.16.0.0/16")]
        self.assertEqual(len(values), 9)
        self.assertEqual(set(values.values()), {0})

    def test_database_helper_is_query_only(self):
        connection = sqlite3.connect(":memory:")
        try:
            connection.execute("CREATE TABLE Devices (dev_LastIP, dev_Archived, dev_PresentLastScan, dev_NewDevice, dev_AlertDeviceDown)")
            connection.execute("CREATE TABLE ICMP_Mon (icmp_ip, icmp_Archived, icmp_PresentLastScan)")
            read_subnet_counts(connection, ["10.0.0.0/8"])
            with self.assertRaises(sqlite3.OperationalError):
                connection.execute("INSERT INTO Devices VALUES ('10.0.0.1',0,1,0,0)")
        finally:
            connection.close()


if __name__ == "__main__":
    unittest.main()

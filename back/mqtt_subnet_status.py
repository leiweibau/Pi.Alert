#!/usr/bin/env python3
"""Discover local IPv4 networks and build Pi.Alert subnet MQTT counters."""

from __future__ import annotations

import fcntl
import ipaddress
import json
import socket
import struct
import subprocess


IP_COMMAND = ("ip", "-j", "-4", "address", "show")
IP_TIMEOUT_SECONDS = 3
MAX_IP_OUTPUT_BYTES = 1024 * 1024
SIOCGIFFLAGS = 0x8913
SIOCGIFADDR = 0x8915
SIOCGIFNETMASK = 0x891b
IFF_UP = 0x1


class SubnetDetectionError(RuntimeError):
    """Neither supported unprivileged discovery mechanism succeeded."""


def _usable_network(network):
    return (network.prefixlen not in (0, 32) and not network.is_loopback and
            not network.is_multicast and not network.network_address.is_unspecified)


def _normalize_network(value):
    try:
        network = ipaddress.IPv4Network(value, strict=False)
    except (ValueError, TypeError, ipaddress.AddressValueError):
        return None
    return network if _usable_network(network) else None


def _deduplicate(networks):
    return sorted(set(networks), key=lambda network: (
        int(network.network_address), network.prefixlen))


def discover_with_ip(run=None):
    """Return UP-interface networks reported by ``ip -j``."""
    run = run or subprocess.run
    result = run(
        list(IP_COMMAND), shell=False, stdout=subprocess.PIPE,
        stderr=subprocess.PIPE, timeout=IP_TIMEOUT_SECONDS, check=False)
    if result.returncode != 0:
        error = result.stderr[:512].decode("utf-8", "replace") if isinstance(result.stderr, bytes) else str(result.stderr)[:512]
        raise SubnetDetectionError("ip address discovery failed: " + error)
    output = result.stdout if isinstance(result.stdout, bytes) else str(result.stdout).encode()
    if len(output) > MAX_IP_OUTPUT_BYTES:
        raise SubnetDetectionError("ip address discovery output is too large")
    try:
        interfaces = json.loads(output.decode("utf-8"))
    except (UnicodeDecodeError, json.JSONDecodeError) as exc:
        raise SubnetDetectionError("ip address discovery returned invalid JSON") from exc
    if not isinstance(interfaces, list):
        raise SubnetDetectionError("ip address discovery returned an invalid structure")

    networks = []
    for interface in interfaces:
        if not isinstance(interface, dict):
            continue
        name = interface.get("ifname")
        flags = interface.get("flags", [])
        if name == "lo" or not isinstance(flags, list) or "UP" not in flags:
            continue
        entries = interface.get("addr_info", [])
        if not isinstance(entries, list):
            continue
        for address in entries:
            if not isinstance(address, dict) or address.get("family") != "inet":
                continue
            local = address.get("local")
            prefix = address.get("prefixlen")
            if type(local) is not str or type(prefix) is not int:
                continue
            network = _normalize_network("{}/{}".format(local, prefix))
            if network is not None:
                networks.append(network)
    return _deduplicate(networks)


def _ioctl(sock, request, interface):
    ifname = interface.encode("ascii")[:15]
    return fcntl.ioctl(sock.fileno(), request, struct.pack("256s", ifname))


def _route_ipv4(hex_value):
    raw = bytes.fromhex(hex_value)
    if len(raw) != 4:
        raise ValueError("route value is not IPv4")
    return ipaddress.IPv4Address(raw[::-1])


def _read_direct_routes(route_path, known_interfaces, up_interfaces):
    networks = []
    try:
        with open(route_path, "r", encoding="ascii", errors="strict") as handle:
            lines = handle.readlines()
    except (OSError, UnicodeError) as exc:
        raise SubnetDetectionError("cannot read local IPv4 routes") from exc
    for line in lines[1:]:
        fields = line.split()
        if len(fields) < 8:
            continue
        interface, destination, gateway, flags_hex, _refcnt, _use, _metric, mask = fields[:8]
        if (interface == "lo" or interface not in known_interfaces or
                (up_interfaces is not None and interface not in up_interfaces)):
            continue
        try:
            flags = int(flags_hex, 16)
            if not flags & IFF_UP or int(gateway, 16) != 0 or int(mask, 16) == 0:
                continue
            network = _normalize_network(
                "{}/{}".format(_route_ipv4(destination), _route_ipv4(mask)))
        except (ValueError, ipaddress.AddressValueError):
            continue
        if network is not None:
            networks.append(network)
    return networks


def _proc_interface_names(path="/proc/net/dev"):
    try:
        with open(path, "r", encoding="ascii", errors="strict") as handle:
            lines = handle.readlines()[2:]
    except (OSError, UnicodeError) as exc:
        raise SubnetDetectionError("cannot enumerate network interfaces") from exc
    names = []
    for line in lines:
        if ":" not in line:
            continue
        name = line.split(":", 1)[0].strip()
        if name and len(name.encode("ascii", "ignore")) == len(name):
            names.append(name)
    if not names:
        raise SubnetDetectionError("cannot enumerate network interfaces")
    return names


def discover_with_fallback(route_path="/proc/net/route", socket_factory=socket.socket,
                           interface_provider=socket.if_nameindex,
                           interface_path="/proc/net/dev"):
    """Discover primary addresses with ioctl and extra direct routes in procfs."""
    try:
        interfaces = [name for _index, name in interface_provider()]
    except OSError:
        # Some restricted containers implement if_nameindex through the same
        # forbidden Netlink socket as iproute2. procfs remains unprivileged.
        interfaces = _proc_interface_names(interface_path)
    known = set(interfaces)
    up = set()
    probed_flags = False
    networks = []
    try:
        sock = socket_factory(socket.AF_INET, socket.SOCK_DGRAM)
    except OSError:
        sock = None
        up = None
    if sock is not None:
        try:
            for interface in interfaces:
                if interface == "lo":
                    continue
                try:
                    flags = struct.unpack("H", _ioctl(sock, SIOCGIFFLAGS, interface)[16:18])[0]
                except (OSError, struct.error, UnicodeEncodeError):
                    continue
                probed_flags = True
                if not flags & IFF_UP:
                    continue
                up.add(interface)
                try:
                    address = socket.inet_ntoa(_ioctl(sock, SIOCGIFADDR, interface)[20:24])
                    netmask = socket.inet_ntoa(_ioctl(sock, SIOCGIFNETMASK, interface)[20:24])
                except OSError:
                    continue
                network = _normalize_network("{}/{}".format(address, netmask))
                if network is not None:
                    networks.append(network)
        finally:
            sock.close()
    try:
        networks.extend(_read_direct_routes(
            route_path, known, up if probed_flags else None))
    except SubnetDetectionError:
        if not networks:
            raise
    return _deduplicate(networks)


def discover_local_ipv4_subnets(run=None, **fallback_kwargs):
    """Use iproute2 first and the ioctl/procfs fallback if it is unavailable."""
    primary_error = None
    try:
        return discover_with_ip(run)
    except (OSError, subprocess.TimeoutExpired, SubnetDetectionError) as exc:
        primary_error = exc
    try:
        return discover_with_fallback(**fallback_kwargs)
    except (OSError, SubnetDetectionError) as exc:
        raise SubnetDetectionError(
            "local IPv4 subnet discovery failed (ip: {}; fallback: {})".format(
                primary_error, exc)) from exc


def subnet_identifiers(networks):
    """Map normalized networks to deterministic collision-safe topic IDs."""
    normalized = _deduplicate([
        network for network in (_normalize_network(item) for item in networks)
        if network is not None
    ])
    address_counts = {}
    for network in normalized:
        address_counts[network.network_address] = address_counts.get(network.network_address, 0) + 1
    result = {}
    for network in normalized:
        base = "".join("{:03d}".format(int(part))
                       for part in str(network.network_address).split("."))
        if address_counts[network.network_address] > 1:
            base += "_p{}".format(network.prefixlen)
        result[network] = base
    return result


DEVICE_KEYS = ("all", "online", "new", "down", "offline", "archive")
ICMP_KEYS = ("icmp_all", "icmp_online", "icmp_offline")


def count_subnet_rows(networks, device_rows, icmp_rows):
    """Assign each valid row using longest-prefix match and count its state."""
    normalized = list(subnet_identifiers(networks))
    by_specificity = sorted(normalized, key=lambda item: item.prefixlen, reverse=True)
    counts = {network: {key: 0 for key in DEVICE_KEYS + ICMP_KEYS}
              for network in normalized}
    skipped = 0

    def match(value):
        nonlocal skipped
        try:
            address = ipaddress.ip_address(value)
        except (ValueError, TypeError):
            skipped += 1
            return None
        if not isinstance(address, ipaddress.IPv4Address):
            skipped += 1
            return None
        return next((network for network in by_specificity if address in network), None)

    for row in device_rows:
        network = match(row[0])
        if network is None:
            continue
        archived, present, new, alert_down = (bool(row[index]) for index in range(1, 5))
        if archived:
            counts[network]["archive"] += 1
        else:
            counts[network]["all"] += 1
            if present:
                counts[network]["online"] += 1
            if new:
                counts[network]["new"] += 1
            if not present:
                counts[network]["down" if alert_down else "offline"] += 1

    for row in icmp_rows:
        network = match(row[0])
        if network is None:
            continue
        archived, present = bool(row[1]), bool(row[2])
        if not archived:
            counts[network]["icmp_all"] += 1
            counts[network]["icmp_online" if present else "icmp_offline"] += 1
    return counts, skipped


def read_subnet_counts(connection, networks):
    """Read one snapshot per managed-host table from a query-only connection."""
    connection.execute("PRAGMA query_only=ON")
    devices = connection.execute(
        "SELECT dev_LastIP, dev_Archived, dev_PresentLastScan, dev_NewDevice, "
        "dev_AlertDeviceDown FROM Devices").fetchall()
    icmp = connection.execute(
        "SELECT icmp_ip, icmp_Archived, icmp_PresentLastScan FROM ICMP_Mon").fetchall()
    return count_subnet_rows(networks, devices, icmp)

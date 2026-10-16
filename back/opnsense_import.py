"""OPNsense inventory: validated API snapshots and deterministic MAC/IP merging.

No configuration loading, network access or database opening at import time.
API calls are supplied by the caller; writes use its existing SQLite connection.
"""

import datetime
import ipaddress
import re


class ImportFailure(ValueError):
    """The snapshot is unusable; do not evaluate device presence this cycle."""


class APIError(ImportFailure):
    def __init__(self, message, status=None):
        super().__init__(message)
        self.status = status


def rows(payload):
    """Distinguish an explicit empty list from an error or truncated result."""
    result = payload
    if isinstance(payload, dict):
        if payload.get('error') or payload.get('status') in ('failed', 'error'):
            raise ImportFailure('API reported an error')
        result = payload.get('rows', payload.get('data'))
        if 'total' in payload:
            try:
                if not isinstance(result, list) or int(payload['total']) != len(result):
                    raise ImportFailure('Incomplete API result')
            except (TypeError, ValueError) as exc:
                raise ImportFailure('Incomplete API result') from exc
    if not isinstance(result, list) or any(not isinstance(r, dict) for r in result):
        raise ImportFailure('Invalid API row list')
    return result


def flag(value):
    if value is True or value == 1 or value == '1' or value == 'true':
        return True
    if value is False or value == 0 or value == '0' or value == 'false':
        return False
    raise ImportFailure('Invalid API boolean')


def text(value):
    return str(value).strip() if isinstance(value, (str, int, float)) else ''


def first(record, *keys):
    return next((text(record.get(k)) for k in keys if text(record.get(k))), '')


def mac(value):
    value = re.sub(r'[:.\-]', '', text(value)).lower()
    if not re.fullmatch(r'[0-9a-f]{12}', value) or value == '000000000000':
        return ''
    if int(value[:2], 16) & 1:
        return ''
    return ':'.join(value[i:i + 2] for i in range(0, 12, 2))


def ipv4(value):
    try:
        address = ipaddress.IPv4Address(text(value))
        return str(address) if not address.is_unspecified and not address.is_multicast and int(address) != 0xffffffff else ''
    except ipaddress.AddressValueError:
        return ''


def name(value):
    value = text(value)
    return '' if value in ('?', '*', '(unknown)') else value


def fetch_arp(fetch, log=lambda _: None):
    # The raw endpoint has no grid pagination and preserves null/error responses.
    try:
        return rows(fetch('/api/diagnostics/interface/get_arp', 'ARP'))
    except APIError as exc:
        if exc.status not in (403, 404):
            raise
        # Older Pi.Alert custom ACLs grant search_arp but not get_arp. This is
        # the same ARP source, with explicit completeness validation below.
        log('OPNsense ARP: raw endpoint unavailable/permitted only via search; validating search result')
    return rows(fetch('/api/diagnostics/interface/search_arp', 'ARP'))


def fetch_dhcp(fetch, log):
    """Import only demonstrably active services. DHCP is optional metadata."""
    active = []
    endpoints = (
        ('Legacy', '/api/dhcpv4/service/status', '/api/dhcpv4/leases/search_lease?inactive=1'),
        ('Dnsmasq', '/api/dnsmasq/service/status', '/api/dnsmasq/leases/search'),
        ('Kea', '/api/kea/service/status', '/api/kea/leases4/search'),
    )
    try:
        for backend, status_url, lease_url in endpoints:
            try:
                result = fetch(status_url, backend + ' status')
            except APIError as exc:
                if exc.status == 404:  # Backend absent in this installation.
                    continue
                raise
            status = result.get('status') if isinstance(result, dict) else None
            if status in ('disabled', 'stopped'):
                continue
            if status != 'running':
                raise ImportFailure('DHCP service activity is unknown')
            if backend == 'Kea':
                config = fetch('/api/kea/dhcpv4/get', 'Kea IPv4 settings')
                if not flag(config['dhcpv4']['general']['enabled']):
                    continue  # A running IPv6 server does not confirm IPv4 DHCP.
            elif backend == 'Dnsmasq':
                config = fetch('/api/dnsmasq/settings/get', 'Dnsmasq settings')['dnsmasq']
                if not flag(config['enable']):
                    continue
                ranges = config['dhcp_ranges']
                if not isinstance(ranges, (dict, list)):
                    raise ImportFailure('Invalid DHCP ranges')
                entries = ranges.values() if isinstance(ranges, dict) else ranges
                if not any(isinstance(r, dict) and ipv4(r.get('start_addr')) for r in entries):
                    continue  # DNS-only or IPv6-only Dnsmasq, no IPv4 leases.
            active.append((backend, lease_url))
        leases = []
        for backend, endpoint in active:
            current = rows(fetch(endpoint, backend + ' leases'))
            leases.extend(current)
            log('OPNsense DHCP: {} active, {} lease rows'.format(backend, len(current)))
        return leases
    except (ImportFailure, KeyError, TypeError) as exc:
        # Do not publish a partial DHCP snapshot or silently fall back to old files.
        reason = str(exc) if isinstance(exc, ImportFailure) else 'Missing/invalid DHCP settings'
        log('OPNsense DHCP unavailable/undetermined; using ARP only ({})'.format(reason))
        return []


def interface_context(interfaces, interface_names):
    if not isinstance(interfaces, dict) or not isinstance(interface_names, dict):
        raise ImportFailure('Invalid interface information')
    if any(not isinstance(v, dict) for v in interfaces.values()):
        raise ImportFailure('Invalid interface configuration')
    if any(not isinstance(v, str) for v in interface_names.values()):
        raise ImportFailure('Invalid interface names')
    local = {}
    for key in sorted(interfaces):
        address = mac(first(interfaces[key], 'mac', 'macaddr', 'ether'))
        if address:
            local.setdefault(address, interface_names.get(key, key.upper()))
    return local


def arp_connected(entry):
    if 'expired' in entry and flag(entry['expired']):
        return False
    if 'incomplete' in entry and flag(entry['incomplete']):
        return False
    permanent = flag(entry['permanent']) if 'permanent' in entry else False
    expires = text(entry.get('expires'))
    if permanent or expires.lower() == 'permanent':
        return True
    match = re.fullmatch(r'Expires\s+in\s+(\d+)\s+seconds', expires, re.I)
    if match:
        return int(match.group(1)) > 0
    if re.fullmatch(r'-?\d+', expires):
        seconds = int(expires)
        if seconds >= 0:
            return seconds > 0
        # OPNsense uses -1 for entries without a known expiry time.
    elif expires:
        raise ImportFailure('Unknown ARP expiry format')
    if 'expired' in entry:
        return not flag(entry['expired'])
    raise ImportFailure('Missing ARP validity information')


def lease_end(entry):
    value = first(entry, 'ends', 'expire')
    try:
        return int(value)
    except ValueError:
        try:
            return int(datetime.datetime.strptime(value, '%Y/%m/%d %H:%M:%S').timestamp())
        except ValueError:
            return 0


def merge_snapshot(leases, arp, interfaces, interface_names, excluded, previous=(), log=lambda _: None):
    """Produce one row per MAC/IP. Only current ARP entries establish presence."""
    local = interface_context(interfaces, interface_names)
    excluded = {text(v).casefold() for v in excluded}
    groups = {}
    old = {}
    for row in sorted(previous, key=lambda r: tuple(text(r.get(c)) for c in COLUMNS)):
        pair = (mac(row['MAC']), ipv4(row['IP']))
        old.setdefault(pair, row)
    for is_arp, entries in ((False, leases), (True, arp)):
        for entry in entries:
            address = mac(first(entry, 'mac', 'hwaddr', 'mac-address', 'mac_address'))
            ip = ipv4(first(entry, 'address', 'ip', 'ip-address', 'ip_address'))
            if not address or not ip:
                # Incomplete ARP neighbours are an expected non-presence result.
                if is_arp and not ('incomplete' in entry and flag(entry['incomplete'])):
                    raise ImportFailure('Invalid ARP MAC/IP pair')
                log('OPNsense: skipped unusable {} row'.format('ARP' if is_arp else 'DHCP'))
                continue
            raw_interface = first(entry, 'intf', 'interface', 'if_name', 'if')
            interface = first(entry, 'intf_description', 'if_descr') or interface_names.get(raw_interface, raw_interface)
            if address not in local and {interface.casefold(), raw_interface.casefold()} & excluded:
                continue
            record = {
                'MAC': address, 'IP': ip,
                'Name': name(first(entry, 'hostname', 'host', 'name', 'descr')),
                'Vendor': first(entry, 'manufacturer', 'vendor', 'man', 'mac_info'),
                'Method': 'OPNsense', 'Interface': interface,
                'Custom_a': text(entry.get('expires')) if is_arp else text(entry.get('type')),
                'Custom_b': '' if is_arp else text(entry.get('state')),
                'Connected': int(arp_connected(entry)) if is_arp else 0,
                'Datetime': '' if is_arp else lease_end(entry),
            }
            if is_arp and not record['Name']:
                record['Name'] = name(entry.get('dnsresolve'))
            groups.setdefault((address, ip), []).append((is_arp, record))

    result = []
    for pair in sorted(groups):
        candidates = sorted(groups[pair], key=lambda item: (
            not item[0], not item[1]['Connected'],
            item[1]['Interface'], item[1]['Name'], item[1]['Vendor'],
            item[1]['Custom_a'], item[1]['Custom_b'], str(item[1]['Datetime'])))
        current = dict(candidates[0][1])
        current['Connected'] = int(any(r['Connected'] for _, r in candidates))
        # Current ARP metadata takes priority; DHCP can fill gaps for this IP only.
        for field in ('Name', 'Vendor', 'Interface'):
            values = [r[field] for _, r in candidates if r[field]]
            fallback = old.get(pair, {}).get(field, '')
            current[field] = values[0] if values else (name(fallback) if field == 'Name' else text(fallback))
            if len(set(values)) > 1:
                log('OPNsense: conflicting {} for {} / {}; stable merge applied'.format(field, *pair))
        if not current['Name']:
            current['Name'] = 'OPNsense ' + local[pair[0]] if pair[0] in local else '(unknown)'
        result.append(current)
    return result


COLUMNS = ('MAC', 'IP', 'Name', 'Vendor', 'Method', 'Interface',
           'Custom_a', 'Custom_b', 'Connected', 'Datetime')


def previous_rows(connection):
    return [dict(zip(COLUMNS, row)) for row in connection.execute(
        'SELECT ' + ', '.join('OPN_' + c for c in COLUMNS) + ' FROM opnsense_Network')]


def replace_snapshot(connection, snapshot):
    """Publish the fully validated snapshot atomically, including an empty one."""
    connection.execute('SAVEPOINT opnsense_snapshot')
    try:
        connection.execute('DELETE FROM opnsense_Network')
        connection.executemany(
            'INSERT INTO opnsense_Network (' + ', '.join('OPN_' + c for c in COLUMNS) +
            ') VALUES (' + ', '.join('?' for _ in COLUMNS) + ')',
            [tuple(row[c] for c in COLUMNS) for row in snapshot])
        connection.execute('RELEASE opnsense_snapshot')
    except Exception:
        connection.execute('ROLLBACK TO opnsense_snapshot')
        connection.execute('RELEASE opnsense_snapshot')
        raise


def insert_current_scan(connection, cycle, log):
    candidates = {}
    for row in previous_rows(connection):
        if row['Connected']:
            address, ip = mac(row['MAC']), ipv4(row['IP'])
            if address and ip:
                row = dict(row, MAC=address, IP=ip)
                candidates.setdefault(address, []).append(row)
    previous = {mac(r[0]): (ipv4(r[1]), r[2]) for r in connection.execute(
        "SELECT dev_MAC, dev_LastIP, dev_Vendor FROM Devices WHERE dev_ScanSource = 'local'")}
    present = {mac(r[0]) for r in connection.execute(
        'SELECT cur_MAC FROM CurrentScan WHERE cur_ScanCycle = ?', (cycle,))}
    for address in sorted(candidates):
        if address in present:
            continue
        old_ip, old_vendor = previous.get(address, ('', ''))
        # Inspected OPNsense APIs expose cache lifetime, not last-seen timestamps.
        # Do not rank expiry durations or DHCP lease ends as evidence of recency.
        chosen = min(candidates[address], key=lambda r: (
            r['IP'] != old_ip, int(ipaddress.IPv4Address(r['IP'])),
            text(r['Interface']), text(r['Name']), text(r['Vendor'])))
        if len({r['IP'] for r in candidates[address]}) > 1:
            log('OPNsense: multiple valid ARP IPs for {}; selected {}'.format(address, chosen['IP']))
        connection.execute(
            'INSERT INTO CurrentScan (cur_ScanCycle, cur_MAC, cur_IP, cur_Vendor, cur_ScanMethod) '
            'VALUES (?, ?, ?, ?, ?)',
            (cycle, address, chosen['IP'], chosen['Vendor'] or old_vendor, 'OPNsense'))

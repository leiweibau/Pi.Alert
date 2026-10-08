/* Device aggregation uses full memberships; table columns keep their legacy indexes. */
(function (root, factory) {
  'use strict';
  var model = factory();
  if (typeof module === 'object' && module.exports) module.exports = model;
  else root.pialertLogicalDevices = model;
})(typeof window === 'object' ? window : this, function () {
  'use strict';
  function text(value) { return String(value == null ? '' : value); }
  function normalize(value) { var s = text(value); return s.normalize ? s.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : s; }
  function escapeRegex(value) { return value.replace(/[.*+?^${}()|[\]\\/]/g, '\\$&'); }
  // Same smart search rules as the bundled DataTables: phrases, AND terms,
  // negation, case and accent folding. No values from two members are joined.
  function matcher(query) {
    var negatives = [];
    var terms = (escapeRegex(normalize(query)).match(/!?["\u201C][^"\u201D]+["\u201D]|[^ ]+/g) || ['']).map(function (term) {
      var negative = term.charAt(0) === '!';
      if (negative) term = term.substring(1);
      var quoted = term.match(/^["\u201C](.*)["\u201D]$/);
      if (quoted) term = quoted[1];
      if (negative) { if (term.length > 1) negatives.push('(?!' + term + ')'); term = ''; }
      return term.replace(/"/g, '');
    });
    var regex = new RegExp('^(?=.*?' + terms.join(')(?=.*?') + ')(' + negatives.join('') + '.)*$', 'i');
    return function (value) { return regex.test(normalize(text(value).replace(/[\r\n\u2028]/g, ''))); };
  }
  function sourceMatches(member, source, status) {
    if (text(member.dev_ScanSource) !== text(source)) return false;
    var archived = Number(member.dev_Archived) === 1;
    if (status === 'archived') return archived;
    if (archived) return false;
    switch (status) {
      case 'connected': return Number(member.dev_PresentLastScan) === 1;
      case 'favorites': return Number(member.dev_Favorite) === 1;
      case 'new': return Number(member.dev_NewDevice) === 1;
      case 'down': return Number(member.dev_AlertDeviceDown) === 1 && Number(member.dev_PresentLastScan) === 0;
      case 'presence': return Number(member.dev_PresencePage) === 1;
      default: return status === 'all';
    }
  }
  function ipKey(value) {
    var address = text(value).trim();
    if (/^(?:\d{1,3}\.){3}\d{1,3}$/.test(address)) {
      var parts = address.split('.').map(Number);
      if (parts.every(function (part) { return part <= 255; })) return '4:' + parts.map(function (part) { return part.toString(16).padStart(2, '0'); }).join('');
    }
    // Support stored IPv6, including compressed and IPv4-mapped addresses.
    if (address.includes(':') && /^[0-9a-f:.]+$/i.test(address)) {
      var v6 = address.toLowerCase();
      if (v6.includes('.')) {
        var tail = v6.substring(v6.lastIndexOf(':') + 1), key = ipKey(tail);
        if (!key || !key.startsWith('4:')) return null;
        v6 = v6.substring(0, v6.lastIndexOf(':') + 1) + key.slice(2, 6) + ':' + key.slice(6);
      }
      var halves = v6.split('::');
      if (halves.length > 2) return null;
      var left = halves[0] ? halves[0].split(':') : [], right = halves.length > 1 && halves[1] ? halves[1].split(':') : [];
      if (!left.concat(right).every(function (part) { return /^[0-9a-f]{1,4}$/.test(part); })) return null;
      if (halves.length === 1 && left.length !== 8) return null;
      if (halves.length === 2 && left.length + right.length >= 8) return null;
      return '6:' + left.concat(Array(8 - left.length - right.length).fill('0'), right).map(function (part) { return part.padStart(4, '0'); }).join('');
    }
    return null;
  }
  function searchText(row, excluded) {
    return Array.from({ length: 19 }, function (_, index) {
      return index === 17 || excluded.indexOf(index) !== -1 ? '' : text(row[index]);
    }).join('  ');
  }
  function aggregate(group) {
    var seen = new Set();
    var members = (group.members || []).filter(function (member) {
      var mac = text(member.dev_MAC).toLowerCase();
      if (!mac || seen.has(mac)) return false;
      seen.add(mac); return true;
    });
    var active = members.filter(function (member) { return Number(member.dev_Archived) !== 1; });
    var online = active.filter(function (member) { return Number(member.dev_PresentLastScan) === 1; }).length;
    var ips = active.filter(function (member) { return typeof member.has_valid_ip === 'boolean' ? member.has_valid_ip : ipKey(member.dev_LastIP) !== null; }).length;
    var status = active.length === 0 ? 'Archived' : online === active.length ? 'On-line' : online === 0 ? 'Off-line' : 'Partial';
    var row = [group.name, '', group.owner, group.device_type, active.some(function (m) { return Number(m.dev_Favorite) === 1; }) ? 1 : 0, group.group, group.location, '', '', ips, '', active.length, group.vendor, status, '', '', '', null, false, null, ''];
    row._logical = { id: group.id, group: group, members: members, online: online, active: active.length, ips: ips, archived: members.length - active.length, matches: [], ownMatch: false };
    return row;
  }
  function filter(row, source, status, query, excluded) {
    var test = matcher(query), skip = excluded || [];
    if (!row._logical) return test(searchText(row, skip));
    var state = row._logical;
    var eligible = state.members.filter(function (member) { return sourceMatches(member, source, status); });
    var ownRow = Array(19).fill('');
    [0, 2, 3, 5, 6, 12].forEach(function (index) { ownRow[index] = row[index]; });
    var ownText = searchText(ownRow, skip);
    state.matches = eligible.filter(function (member) { return test(searchText(member.list_row || [], skip)); }).map(function (member) { return text(member.dev_MAC); });
    state.ownMatch = query !== '' && eligible.some(function (member) {
      return test(ownText) || (test(searchText(member.list_row || [], skip) + '  ' + ownText) && !test(searchText(member.list_row || [], skip)));
    });
    return eligible.length > 0 && (state.matches.length > 0 || state.ownMatch);
  }
  function rows(devices, groups, source, status) {
    var assigned = new Set(), result = [];
    (groups || []).forEach(function (group) {
      var row = aggregate(group);
      row._logical.members.forEach(function (member) { assigned.add(text(member.dev_MAC).toLowerCase()); });
      if (row._logical.members.some(function (member) { return sourceMatches(member, source, status); })) result.push(row);
    });
    (devices || []).forEach(function (row) { if (!assigned.has(text(row[11]).toLowerCase())) result.push(row); });
    return result;
  }
  function totals(macTotals, groups, source) {
    return ['all', 'connected', 'favorites', 'new', 'down', 'archived'].map(function (status, index) {
      var total = Number(macTotals[index] || 0);
      (groups || []).forEach(function (group) {
        var count = aggregate(group)._logical.members.filter(function (member) { return sourceMatches(member, source, status); }).length;
        if (count > 0) total -= count - 1;
      });
      return total;
    });
  }
  function addressSortKey(row, column) {
    if (row._logical) return { logical: true, count: column === 9 ? row._logical.ips : row._logical.active, stable: Number(row._logical.id) };
    var value = text(row[column]);
    return { logical: false, key: column === 9 ? ipKey(value) || '0:' + value.toLowerCase() : value.toLowerCase(), stable: text(row[11]).toLowerCase() };
  }
  function compareAddress(a, b, direction) {
    if (a.logical !== b.logical) return a.logical ? -1 : 1;
    var result = a.logical ? a.count - b.count : a.key < b.key ? -1 : a.key > b.key ? 1 : 0;
    if (result) return result * direction;
    return a.stable < b.stable ? -1 : a.stable > b.stable ? 1 : 0;
  }
  return { matcher: matcher, sourceMatches: sourceMatches, ipKey: ipKey, aggregate: aggregate, filter: filter, rows: rows, totals: totals, addressSortKey: addressSortKey, compareAddress: compareAddress };
});

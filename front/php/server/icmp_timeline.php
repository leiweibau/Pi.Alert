<?php

// Convert ICMP scan samples into a continuous 24-hour status timeline.
// Periods without a positive scan result are shown as offline.
function pialert_icmp_timeline_events(SQLite3 $db, string $hostIp, string $start, string $end, array $labels): array {
    $startTime = (new DateTimeImmutable($start))->getTimestamp();
    $endTime = (new DateTimeImmutable($end))->getTimestamp();
    if ($startTime >= $endTime) return array();

    $previous = db_execute_prepared($db,
        'SELECT icmpeve_DateTime, icmpeve_Present FROM ICMP_Mon_Events
         WHERE icmpeve_ip = :ip AND datetime(icmpeve_DateTime) < :start
         ORDER BY datetime(icmpeve_DateTime) DESC, rowid DESC LIMIT 1',
        array(':ip' => $hostIp, ':start' => $start));
    $result = db_execute_prepared($db,
        'SELECT icmpeve_DateTime, icmpeve_Present FROM ICMP_Mon_Events
         WHERE icmpeve_ip = :ip AND datetime(icmpeve_DateTime) >= :start
           AND datetime(icmpeve_DateTime) < :end
         ORDER BY datetime(icmpeve_DateTime), rowid',
        array(':ip' => $hostIp, ':start' => $start, ':end' => $end));
    $samples = array();
    $addSample = static function (array $row) use (&$samples): void {
        $time = (new DateTimeImmutable($row['icmpeve_DateTime']))->getTimestamp();
        $present = $row['icmpeve_Present'];
        $status = (int) $present === 1 && $present !== null ? 'online' : 'offline';
        $last = count($samples) - 1;
        if ($last >= 0 && $samples[$last]['time'] === $time) {
            $samples[$last]['status'] = $status;
        } else {
            $samples[] = array('time' => $time, 'status' => $status);
        }
    };
    $previousRow = $previous ? $previous->fetchArray(SQLITE3_ASSOC) : false;
    if ($previousRow) $addSample($previousRow);
    while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) $addSample($row);

    $intervals = array();
    for ($index = 1, $count = count($samples); $index < $count; $index++) {
        $seconds = $samples[$index]['time'] - $samples[$index - 1]['time'];
        if ($seconds > 0 && $seconds <= 14400) $intervals[] = $seconds;
    }
    sort($intervals, SORT_NUMERIC);
    $cadence = $intervals ? $intervals[intdiv(count($intervals), 2)] : 300;
    $maxGap = max(900, min(7200, 3 * $cadence));

    $segments = array();
    $append = static function (string $status, int $from, int $to) use (&$segments): void {
        if ($from >= $to) return;
        $last = count($segments) - 1;
        if ($last >= 0 && $segments[$last]['status'] === $status && $segments[$last]['end'] === $from) {
            $segments[$last]['end'] = $to;
        } else {
            $segments[] = array('status' => $status, 'start' => $from, 'end' => $to);
        }
    };

    $cursor = $startTime;
    foreach ($samples as $index => $sample) {
        $from = max($startTime, $sample['time']);
        $nextTime = $samples[$index + 1]['time'] ?? $endTime;
        $to = min($endTime, $nextTime, $sample['time'] + $maxGap);
        if ($to <= $from) continue;
        if ($cursor < $from) $append('offline', $cursor, $from);
        $append($sample['status'], $from, $to);
        $cursor = $to;
    }
    if ($cursor < $endTime) $append('offline', $cursor, $endTime);

    $colors = array('online' => '#00a659', 'offline' => '#dc3545');
    $events = array();
    foreach ($segments as $segment) {
        $status = $segment['status'];
        $title = (string) ($labels[$status] ?? ucfirst($status));
        $events[] = array(
            'title' => '',
            'start' => date(DateTimeInterface::ATOM, $segment['start']),
            'end' => date(DateTimeInterface::ATOM, $segment['end']),
            'color' => $colors[$status],
            'status' => $status,
            'tooltip' => $title . "\n" . date('Y-m-d H:i', $segment['start']) . ' – ' . date('Y-m-d H:i', $segment['end']),
        );
    }
    return $events;
}

<?php

// Return every service check in the selected rolling window for FullCalendar.
function pialert_service_timeline_snapshot(SQLite3 $db, string $serviceUrl, array $labels, string $period = '24h', ?int $now = null): array {
    if ($period !== '24h' && $period !== '7d') throw new InvalidArgumentException('Invalid service timeline period');
    $end = $now ?? time();
    $start = $end - ($period === '7d' ? 7 * 86400 : 86400);
    $startDate = date('Y-m-d H:i:s', $start);
    $endDate = date('Y-m-d H:i:s', $end);
    $result = db_execute_prepared($db,
        'SELECT moneve_DateTime, moneve_StatusCode, moneve_Latency FROM Services_Events
         WHERE moneve_URL = :url AND moneve_DateTime >= :start AND moneve_DateTime < :end
         ORDER BY moneve_DateTime, rowid',
        array(':url' => $serviceUrl, ':start' => $startDate, ':end' => $endDate));
    $rows = array();
    while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) $rows[] = $row;

    $counts = array_fill_keys(array('2xx', '3xx', '4xx', '5xx', 'down'), 0);
    if (!$rows) return array(
        'start' => date(DateTimeInterface::ATOM, $start),
        'end' => date(DateTimeInterface::ATOM, $end),
        'events' => array(),
        'counts' => $counts,
    );

    $samples = array();
    foreach ($rows as $row) {
        $code = (int) $row['moneve_StatusCode'];
        $status = $code === 0
            ? 'down' : ($code >= 200 && $code < 600 ? (string) intdiv($code, 100) . 'xx' : '');
        if ($status !== '') $counts[$status]++;
        $samples[] = array(
            'time' => (new DateTimeImmutable($row['moneve_DateTime']))->getTimestamp(),
            'status' => $status,
            'code' => $code,
            'latency' => $row['moneve_Latency'],
        );
    }

    $intervals = array();
    for ($index = 1, $count = count($samples); $index < $count; $index++) {
        $seconds = $samples[$index]['time'] - $samples[$index - 1]['time'];
        if ($seconds > 0 && $seconds <= 14400) $intervals[] = $seconds;
    }
    sort($intervals, SORT_NUMERIC);
    $cadence = $intervals ? $intervals[intdiv(count($intervals), 2)] : 600;
    $cadence = max(60, min(7200, $cadence));

    $colors = array('2xx' => '#00a659', '3xx' => '#0d6efd', '4xx' => '#e3a008', '5xx' => '#fe4c00', 'down' => '#dc3545');
    $events = array();
    foreach ($samples as $index => $sample) {
        if ($sample['status'] === '') continue;
        $next = $samples[$index + 1]['time'] ?? $end;
        $to = min($next, $sample['time'] + $cadence, $end);
        if ($to <= $sample['time']) continue;
        $status = $sample['status'];
        $tooltip = (string) ($labels[$status] ?? $status);
        if ($status !== 'down') $tooltip .= ' (' . $sample['code'] . ')';
        $tooltip .= "\n" . date('Y-m-d H:i:s', $sample['time']);
        if ($status !== 'down' && (string) $sample['latency'] !== '99999999' && is_numeric($sample['latency'])) {
            $tooltip .= "\n" . round((float) $sample['latency'], 3) . ' ms';
        }
        $events[] = array(
            'title' => '',
            'start' => date(DateTimeInterface::ATOM, $sample['time']),
            'end' => date(DateTimeInterface::ATOM, $to),
            'color' => $colors[$status],
            'status' => $status,
            'tooltip' => $tooltip,
        );
    }

    return array(
        'start' => date(DateTimeInterface::ATOM, $start),
        'end' => date(DateTimeInterface::ATOM, $end),
        'events' => $events,
        'counts' => $counts,
    );
}

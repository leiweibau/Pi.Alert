<?php

// Build visible presence intervals from the ICMP connection transitions.
// The event log contains state changes, not ready-made sessions.
function pialert_icmp_presence_events(SQLite3 $db, string $hostIp, string $start, string $end, array $labels, ?string $now = null): array {
    $now = $now ?? date('Y-m-d H:i:s');
    $visibleEnd = min($end, $now);
    if ($start >= $visibleEnd) return array();

    $params = array(':ip' => $hostIp, ':start' => $start, ':end' => $visibleEnd);
    $previous = db_execute_prepared($db,
        'SELECT icmpeve_DateTime, icmpeve_Present FROM ICMP_Mon_Connections
         WHERE icmpeve_ip = :ip AND datetime(icmpeve_DateTime) < :start
         ORDER BY datetime(icmpeve_DateTime) DESC, rowid DESC LIMIT 1',
        array(':ip' => $hostIp, ':start' => $start));
    $previousRow = $previous ? $previous->fetchArray(SQLITE3_ASSOC) : false;
    $connected = $previousRow && (int) $previousRow['icmpeve_Present'] === 1;
    $connectedAt = $connected ? (string) $previousRow['icmpeve_DateTime'] : null;
    $visibleStart = $connected ? $start : null;

    $result = db_execute_prepared($db,
        'SELECT icmpeve_DateTime, icmpeve_Present FROM ICMP_Mon_Connections
         WHERE icmpeve_ip = :ip AND datetime(icmpeve_DateTime) >= :start
           AND datetime(icmpeve_DateTime) < :end
         ORDER BY datetime(icmpeve_DateTime), rowid', $params);
    $events = array();
    $append = static function (string $from, string $to, string $connectedTime, ?string $disconnectedTime, bool $ongoing) use (&$events, $hostIp, $labels): void {
        if ($from >= $to) return;
        $tooltip = $labels['connection'] . ': ' . $connectedTime . "\n"
            . $labels['disconnection'] . ': ' . ($disconnectedTime ?? $labels['online']) . "\n"
            . $labels['ip'] . ': ' . $hostIp;
        $events[] = array(
            'title' => '',
            'start' => (new DateTimeImmutable($from))->format(DateTimeInterface::ATOM),
            'end' => (new DateTimeImmutable($to))->format(DateTimeInterface::ATOM),
            'color' => $ongoing ? '#00a659' : '#0073b7',
            'tooltip' => $tooltip,
        );
    };

    while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
        $eventTime = (new DateTimeImmutable($row['icmpeve_DateTime']))->format('Y-m-d H:i:s');
        if ((int) $row['icmpeve_Present'] === 1) {
            if (!$connected) {
                $connected = true;
                $connectedAt = (string) $row['icmpeve_DateTime'];
                $visibleStart = $eventTime;
            }
        } elseif ($connected) {
            $append($visibleStart, $eventTime, $connectedAt, (string) $row['icmpeve_DateTime'], false);
            $connected = false;
            $connectedAt = null;
            $visibleStart = null;
        }
    }

    if ($connected) {
        // A slice can end before the session does. Look ahead so a historical
        // slice uses the completed-session color and actual disconnect time.
        $next = db_execute_prepared($db,
            'SELECT icmpeve_DateTime FROM ICMP_Mon_Connections
             WHERE icmpeve_ip = :ip AND icmpeve_Present = 0
               AND datetime(icmpeve_DateTime) >= :end
             ORDER BY datetime(icmpeve_DateTime), rowid LIMIT 1',
            array(':ip' => $hostIp, ':end' => $visibleEnd));
        $nextRow = $next ? $next->fetchArray(SQLITE3_ASSOC) : false;
        $append($visibleStart, $visibleEnd, $connectedAt,
            $nextRow ? (string) $nextRow['icmpeve_DateTime'] : null, !$nextRow);
    }

    return $events;
}

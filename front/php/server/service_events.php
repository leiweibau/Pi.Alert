<?php

function pialert_service_event_latency($value): ?float {
    if (!is_numeric($value)) return null;
    $latency = (float) $value;
    // 99999999 represents a failed check, not a measured response time.
    return is_finite($latency) && $latency >= 0 && $latency < 99999999 ? $latency : null;
}

function pialert_service_visible_events(SQLite3 $db, string $url, string $filter): SQLite3Result|false {
    $db->createFunction('pialert_valid_service_latency', 'pialert_service_event_latency', 1, SQLITE3_DETERMINISTIC);
    $filters = array(
        '2' => 'AND e.moneve_StatusCode LIKE "2%"',
        '3' => 'AND e.moneve_StatusCode LIKE "3%"',
        '4' => 'AND e.moneve_StatusCode LIKE "4%"',
        '5' => 'AND e.moneve_StatusCode LIKE "5%"',
        '99999999' => 'AND e.moneve_Latency = "99999999"',
    );
    // Calculate the baseline from the complete HTTP-200 history of this service,
    // independently of the selected category and the displayed row limit.
    return db_execute_prepared($db, '
        WITH http200_average AS (
            SELECT AVG(pialert_valid_service_latency(moneve_Latency)) AS latency
            FROM Services_Events WHERE moneve_URL = :url AND moneve_StatusCode = 200
        )
        SELECT e.* FROM Services_Events e CROSS JOIN http200_average average
        WHERE e.moneve_URL = :url
          AND (e.moneve_StatusCode IS NULL OR e.moneve_StatusCode <> 200
               OR pialert_valid_service_latency(e.moneve_Latency) > 2 * average.latency)
        ' . ($filters[$filter] ?? '') . '
        ORDER BY e.rowid DESC LIMIT 2000', array(':url' => $url));
}

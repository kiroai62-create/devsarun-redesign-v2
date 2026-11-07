<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* POST /api/event/ — lightweight client-side event beacon. */
if (!is_post()) {
    json_out(['ok' => false], 405);
}
if (!csrf_valid() || is_bot() || admin()) {
    json_out(['ok' => true]); // silently ignore
}
if (rate_limited('event', 120, 600)) {
    json_out(['ok' => true]);
}
$name = input('name', 40);
$sid = (int)input('service_id', 10);
track_event($name, single_line(input('label', 190)), $sid > 0 ? $sid : null);
json_out(['ok' => true]);

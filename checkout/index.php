<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require APP . '/checkout_view.php';

$slug = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['service'] ?? ''));
$s = $slug ? service_by_slug($slug) : null;
if (!$s || !(int)$s['is_buyable'] || (int)$s['buy_price'] <= 0) {
    redirect($s ? 'services/' . $s['slug'] . '/' : 'services/');
}
track_event('checkout_open', $s['slug'], (int)$s['id']);
render_checkout([
    'type' => 'service',
    'key' => $s['slug'],
    'title' => $s['buy_label'] ?: $s['title'],
    'desc' => $s['summary'],
    'amount' => (int)$s['buy_price'],
    'lines' => json_list($s['features']),
]);

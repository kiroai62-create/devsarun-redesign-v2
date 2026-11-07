<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require APP . '/checkout_view.php';

/* /pay/{token}/ — secure payment link for a negotiated quote created in the admin. */
$token = preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? ''));
$q = $token !== '' ? db_row('SELECT * FROM quotes WHERE token = ?', [$token]) : null;

if (!$q || $q['status'] === 'cancelled' || ($q['valid_until'] && $q['valid_until'] < date('Y-m-d') && $q['status'] !== 'paid')) {
    http_response_code(404);
    page_start(['title' => 'Payment link unavailable', 'noindex' => true]);
    echo '<section class="mx-auto max-w-xl px-5 pt-40 text-center"><h1 class="text-3xl font-bold">This payment link is not available</h1><p class="mt-4 text-white/60">It may have expired or been replaced. Please contact me for a fresh link.</p><a class="btn-primary mt-8" href="' . e(url('contact/')) . '">Contact</a></section>';
    page_end();
    exit;
}
if ($q['status'] === 'paid') {
    page_start(['title' => 'Already paid', 'noindex' => true]);
    echo '<section class="mx-auto max-w-xl px-5 pt-40 text-center"><div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-neo-green/15 text-neo-green">' . icon('check', 'h-8 w-8') . '</div><h1 class="mt-6 text-3xl font-bold">This quote is already paid ✅</h1><p class="mt-4 text-white/60">Thank you! Check your email for the receipt.</p></section>';
    page_end();
    exit;
}
track_event('quote_view', $q['title']);
$lead = $q['lead_id'] ? db_row('SELECT name, email, phone FROM leads WHERE id = ?', [$q['lead_id']]) : null;
render_checkout([
    'type' => 'quote',
    'key' => $q['token'],
    'title' => $q['title'],
    'desc' => (string)$q['description'] . ($q['valid_until'] ? "\n\nValid until " . date('d M Y', strtotime($q['valid_until'])) . '.' : ''),
    'amount' => (int)round($q['amount_paise'] / 100),
    'lines' => [],
    'prefill' => $lead ?: [],
]);

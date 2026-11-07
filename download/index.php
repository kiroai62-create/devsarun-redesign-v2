<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

/* GET /download/?o=ORDER&t=TOKEN — streams the purchased PDF to a paid order (link from receipt + email). */
$o = preg_replace('/[^A-Z0-9\-]/', '', (string)($_GET['o'] ?? ''));
$t = preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? ''));
$order = $o ? db_row('SELECT * FROM orders WHERE order_no = ?', [$o]) : null;
$fail = static function (string $title, string $msg, int $code = 403) use ($o): never {
    http_response_code($code);
    page_start(['title' => $title, 'noindex' => true]);
    echo '<section class="pt-32 sm:pt-40"><div class="mx-auto max-w-xl px-5 text-center sm:px-6"><div class="card p-10"><h1 class="text-2xl font-bold">' . e($title) . '</h1><p class="mt-3 text-white/60">' . e($msg) . '</p>'
        . '<a class="btn-primary mt-6" href="mailto:' . e(setting('contact_email')) . '?subject=' . rawurlencode('Download help ' . $o) . '">Email for help</a></div></div></section>';
    page_end();
    exit;
};

if (!$order || $t === '' || !hash_equals((string)$order['access_token'], $t) || empty($order['product_id'])) {
    $fail('Download not found', 'This download link is not valid. Please use the link from your purchase email.', 404);
}
if ($order['status'] !== 'paid') {
    $fail('Payment not confirmed yet', 'Your payment is still being confirmed. Please refresh in a minute — if money was deducted, it will be confirmed automatically.', 402);
}
if (rate_limited('download', 30, 600)) {
    $fail('Too many downloads', 'Please wait a few minutes and try again.', 429);
}
$p = db_row('SELECT id, slug, title, file_path FROM products WHERE id = ?', [(int)$order['product_id']]);
$file = $p ? store_pdf_path($p['file_path']) : null;
if (!$file) {
    $fail('File unavailable', 'The file is temporarily unavailable. Please email me with your order number and I will send it to you.', 410);
}
$limit = (int)setting('store_download_limit', '10');
if ($limit > 0 && (int)$order['download_count'] >= $limit) {
    $fail('Download limit reached', "This order has been downloaded {$limit} times. Email me with your order number if you need another copy.");
}

db_exec('UPDATE orders SET download_count = download_count + 1, last_download_at = NOW() WHERE id = ?', [(int)$order['id']]);
track_event('product_download', $p['slug']);

$name = slugify($p['title']) . '.pdf';
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($file));
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
readfile($file);

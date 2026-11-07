<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* POST /api/order/ — create a local order + Razorpay order. Amount is always taken from the DB, never from the client. */
if (!is_post()) {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}
csrf_check(true);
if (!razorpay_enabled()) {
    json_out(['ok' => false, 'error' => 'Online payments are not enabled yet.'], 503);
}
if (rate_limited('order', 10, 600)) {
    json_out(['ok' => false, 'error' => 'Too many attempts. Please wait a few minutes.'], 429);
}

$type = input('type', 10);
$key = input('key', 120);
$name = single_line(input('name', 120));
$email = strtolower(input('email', 190));
$phone = input('phone', 20);
$phoneOk = valid_phone($phone) || ($type === 'product' && $phone === ''); // phone is optional for digital products
if (mb_strlen($name) < 2 || !valid_email($email) || !$phoneOk) {
    json_out(['ok' => false, 'error' => $type === 'product' ? 'Please enter your name and a valid email (the PDF link is sent there).' : 'Please enter a valid name, email and phone number.'], 422);
}
if (input('agree') !== '1') {
    json_out(['ok' => false, 'error' => 'Please accept the terms and refund policy.'], 422);
}

$serviceId = null;
$quoteId = null;
$leadId = null;
$productId = null;
$currency = 'INR';

if ($type === 'product') {
    $p = store_enabled() ? product_by_slug(preg_replace('/[^a-z0-9-]/', '', $key)) : null;
    if (!$p || !store_pdf_path($p['file_path'])) {
        json_out(['ok' => false, 'error' => 'This product is not available right now.'], 410);
    }
    $currency = strtoupper(input('currency', 3)) === 'USD' ? 'USD' : 'INR';
    if (!in_array($currency, product_currencies($p), true)) {
        $currency = product_currencies($p)[0] ?? 'INR';
    }
    $amountPaise = product_amount($p, $currency);
    if ($amountPaise <= 0) {
        json_out(['ok' => false, 'error' => 'This product is not available right now.'], 410);
    }
    $title = mb_substr($p['title'], 0, 190);
    $productId = (int)$p['id'];
} elseif ($type === 'quote') {
    $q = db_row('SELECT * FROM quotes WHERE token = ?', [preg_replace('/[^a-f0-9]/', '', $key)]);
    if (!$q || $q['status'] !== 'sent' || ($q['valid_until'] && $q['valid_until'] < date('Y-m-d'))) {
        json_out(['ok' => false, 'error' => 'This quote is no longer payable.'], 410);
    }
    $title = $q['title'];
    $amountPaise = (int)$q['amount_paise'];
    $quoteId = (int)$q['id'];
    $leadId = $q['lead_id'] ? (int)$q['lead_id'] : null;
} else {
    $s = service_by_slug(preg_replace('/[^a-z0-9-]/', '', $key));
    if (!$s || !(int)$s['is_buyable'] || (int)$s['buy_price'] <= 0) {
        json_out(['ok' => false, 'error' => 'This service cannot be booked online.'], 422);
    }
    $title = $s['buy_label'] ?: $s['title'];
    $amountPaise = (int)$s['buy_price'] * 100;
    $serviceId = (int)$s['id'];
}
if ($amountPaise < ($currency === 'USD' ? 50 : 100)) {
    json_out(['ok' => false, 'error' => 'Invalid amount.'], 422);
}

$orderNo = 'ORD-' . date('ymd') . '-' . strtoupper(substr(random_token(4), 0, 5));
$accessToken = random_token(16);

// Create a CRM lead for service bookings so every buyer shows in the pipeline (store purchases stay in Payments only).
if (!$leadId && !$productId) {
    $attr = attribution();
    $leadId = db_insert('leads', [
        'ref' => lead_ref(), 'source' => 'checkout', 'service_id' => $serviceId, 'service_label' => $title, 'project_title' => 'Online booking: ' . $title,
        'description' => input('notes', 1000) ?: 'Started online checkout.', 'features' => '[]', 'name' => $name, 'email' => $email, 'phone' => $phone,
        'status' => 'qualified', 'priority' => 'high', 'value_estimate' => (int)round($amountPaise / 100),
        'landing' => $attr['landing'] ?: null, 'referrer' => $attr['referrer'] ?: null, 'utm_source' => $attr['utm_source'] ?: null,
        'ip_hash' => ip_hash(), 'user_agent' => user_agent(), 'created_at' => now(),
    ]);
    lead_activity($leadId, 'checkout', 'Started online checkout for ' . $title . ' (' . inr($amountPaise / 100) . ')', false);
}

try {
    $rzp = razorpay_create_order($amountPaise, $orderNo, ['order_no' => $orderNo, 'item' => $title, 'email' => $email], $currency);
} catch (Throwable $e) {
    error_log('Razorpay order error: ' . $e->getMessage());
    if ($currency === 'USD') {
        json_out(['ok' => false, 'error' => 'USD payments are not available right now. Please switch to INR (₹) and try again.', 'switch_currency' => 'INR'], 502);
    }
    json_out(['ok' => false, 'error' => 'Payment gateway is unavailable right now. Please try again shortly.'], 502);
}

db_insert('orders', [
    'order_no' => $orderNo, 'access_token' => $accessToken, 'service_id' => $serviceId, 'quote_id' => $quoteId, 'product_id' => $productId, 'lead_id' => $leadId,
    'item_title' => mb_substr($title, 0, 190), 'amount_paise' => $amountPaise, 'currency' => $currency,
    'customer_name' => $name, 'customer_email' => $email, 'customer_phone' => $phone !== '' ? $phone : null, 'notes' => input('notes', 1000) ?: null,
    'status' => 'created', 'razorpay_order_id' => $rzp['id'], 'created_at' => now(),
]);

json_out([
    'ok' => true, 'key' => setting('razorpay_key_id'), 'amount' => $amountPaise, 'currency' => $currency, 'razorpay_order_id' => $rzp['id'], 'order_no' => $orderNo,
    'title' => $title, 'site' => setting('site_name', 'Devs Arun'), 'prefill' => array_filter(['name' => $name, 'email' => $email, 'contact' => $phone]),
]);

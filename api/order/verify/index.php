<?php
declare(strict_types=1);
require dirname(__DIR__, 3) . '/app/bootstrap.php';

/* POST /api/order/verify/ — verify Razorpay signature after checkout success. */
if (!is_post()) {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}
csrf_check(true);

$orderNo = preg_replace('/[^A-Z0-9\-]/', '', input('order_no', 30));
$rzpOrder = input('razorpay_order_id', 60);
$rzpPayment = input('razorpay_payment_id', 60);
$signature = input('razorpay_signature', 200);

$order = db_row('SELECT * FROM orders WHERE order_no = ?', [$orderNo]);
if (!$order || !hash_equals((string)$order['razorpay_order_id'], $rzpOrder)) {
    json_out(['ok' => false, 'error' => 'Order not found.'], 404);
}
if (!razorpay_verify_payment($rzpOrder, $rzpPayment, $signature)) {
    db_update('orders', ['status' => 'failed'], 'id = ? AND status = ?', [$order['id'], 'created']);
    audit('payment.signature_failed', 'order', (int)$order['id']);
    json_out(['ok' => false, 'error' => 'Payment verification failed.'], 400);
}

// Fetch method (best effort) for the record.
$method = '';
try {
    $p = razorpay_fetch_payment($rzpPayment);
    $method = (string)($p['method'] ?? '');
} catch (Throwable) {
}
order_mark_paid($order, $rzpPayment, $method);

json_out(['ok' => true, 'redirect' => url('receipt/?o=' . $order['order_no'] . '&t=' . $order['access_token'])]);

<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/*
 * POST /api/razorpay-webhook/
 * Configure in Razorpay Dashboard → Settings → Webhooks with events: payment.captured, order.paid, payment.failed.
 * Signature is verified with the webhook secret saved in Admin → Settings.
 */
if (!is_post()) {
    json_out(['ok' => false], 405);
}
$body = file_get_contents('php://input', false, null, 0, 1_000_000) ?: '';
$sig = (string)($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');
if (!razorpay_verify_webhook($body, $sig)) {
    json_out(['ok' => false, 'error' => 'invalid signature'], 400);
}
$evt = json_decode($body, true) ?: [];
$event = (string)($evt['event'] ?? '');
$payment = $evt['payload']['payment']['entity'] ?? [];
$rzpOrderId = (string)($payment['order_id'] ?? ($evt['payload']['order']['entity']['id'] ?? ''));

if ($rzpOrderId !== '') {
    $order = db_row('SELECT * FROM orders WHERE razorpay_order_id = ?', [$rzpOrderId]);
    if ($order) {
        if (in_array($event, ['payment.captured', 'order.paid'], true)) {
            order_mark_paid($order, (string)($payment['id'] ?? $order['razorpay_payment_id'] ?? ''), (string)($payment['method'] ?? ''));
        } elseif ($event === 'payment.failed' && $order['status'] === 'created') {
            db_update('orders', ['status' => 'failed'], 'id = ?', [$order['id']]);
        }
    }
}
json_out(['ok' => true]);

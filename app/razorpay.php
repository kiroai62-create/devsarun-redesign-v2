<?php
declare(strict_types=1);

/*
 * Razorpay integration.
 * Uses the official razorpay/razorpay SDK (bundled in /vendor) when available,
 * with a dependency-free cURL fallback so payments never break.
 */

function razorpay_enabled(): bool
{
    return setting('razorpay_enabled', '0') === '1' && setting('razorpay_key_id') !== '' && setting('razorpay_key_secret') !== '';
}

function razorpay_sdk_available(): bool
{
    return class_exists(\Razorpay\Api\Api::class);
}

function razorpay_mode(): string
{
    $k = setting('razorpay_key_id');
    return str_starts_with($k, 'rzp_live_') ? 'live' : (str_starts_with($k, 'rzp_test_') ? 'test' : 'unknown');
}

function razorpay_engine(): string
{
    return razorpay_sdk_available() ? 'Official Razorpay PHP SDK ' . (\Razorpay\Api\Api::VERSION ?? '') : 'Built-in cURL client';
}

function razorpay_api(): \Razorpay\Api\Api
{
    static $api = null;
    return $api ??= new \Razorpay\Api\Api(setting('razorpay_key_id'), setting('razorpay_key_secret'));
}

/** Low-level REST call (fallback + endpoints the SDK doesn't wrap). */
function razorpay_request(string $method, string $path, array $payload = []): array
{
    $ch = curl_init('https://api.razorpay.com/v1/' . ltrim($path, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_USERPWD => setting('razorpay_key_id') . ':' . setting('razorpay_key_secret'),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        throw new RuntimeException('Razorpay connection error: ' . $err);
    }
    $data = json_decode((string)$resp, true) ?: [];
    if ($code >= 400) {
        throw new RuntimeException('Razorpay error: ' . ($data['error']['description'] ?? ('HTTP ' . $code)));
    }
    return $data;
}

/** Verify saved credentials (used by Admin → Settings → Test). */
function razorpay_test_credentials(): void
{
    if (razorpay_sdk_available()) {
        try {
            razorpay_api()->order->all(['count' => 1]);
            return;
        } catch (\Throwable $e) {
            throw new RuntimeException('Razorpay error: ' . $e->getMessage());
        }
    }
    razorpay_request('GET', 'orders?count=1');
}

function razorpay_create_order(int $amountPaise, string $receipt, array $notes = []): array
{
    $payload = [
        'amount' => $amountPaise,
        'currency' => 'INR',
        'receipt' => mb_substr($receipt, 0, 40),
        'payment_capture' => 1,
        'notes' => array_map(static fn($v) => mb_substr((string)$v, 0, 250), array_slice($notes, 0, 10, true)),
    ];
    if (razorpay_sdk_available()) {
        try {
            return razorpay_api()->order->create($payload)->toArray();
        } catch (\Throwable $e) {
            throw new RuntimeException('Razorpay error: ' . $e->getMessage());
        }
    }
    return razorpay_request('POST', 'orders', $payload);
}

function razorpay_fetch_payment(string $paymentId): array
{
    if (razorpay_sdk_available()) {
        return razorpay_api()->payment->fetch($paymentId)->toArray();
    }
    return razorpay_request('GET', 'payments/' . rawurlencode($paymentId));
}

function razorpay_verify_payment(string $orderId, string $paymentId, string $signature): bool
{
    if ($signature === '' || $orderId === '' || $paymentId === '') {
        return false;
    }
    if (razorpay_sdk_available()) {
        try {
            razorpay_api()->utility->verifyPaymentSignature([
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
    return hash_equals(hash_hmac('sha256', $orderId . '|' . $paymentId, setting('razorpay_key_secret')), $signature);
}

function razorpay_verify_webhook(string $body, string $signature): bool
{
    $secret = setting('razorpay_webhook_secret');
    if ($secret === '' || $signature === '' || $body === '') {
        return false;
    }
    if (razorpay_sdk_available()) {
        try {
            razorpay_api()->utility->verifyWebhookSignature($body, $signature, $secret);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
    return hash_equals(hash_hmac('sha256', $body, $secret), $signature);
}

/** Mark an order paid (idempotent) and fire notifications. */
function order_mark_paid(array $order, string $paymentId, string $method = ''): void
{
    if ($order['status'] === 'paid') {
        return;
    }
    // Atomic transition: only the first caller (verify endpoint or webhook) continues.
    $changed = db_update('orders', ['status' => 'paid', 'razorpay_payment_id' => $paymentId, 'payment_method' => mb_substr($method, 0, 40), 'paid_at' => now()], 'id = ? AND status <> ?', [$order['id'], 'paid']);
    if ($changed === 0) {
        return;
    }
    if (!empty($order['quote_id'])) {
        db_update('quotes', ['status' => 'paid', 'paid_at' => now()], 'id = ?', [$order['quote_id']]);
    }
    if (!empty($order['lead_id'])) {
        db_update('leads', ['status' => 'won', 'updated_at' => now()], 'id = ?', [$order['lead_id']]);
        lead_activity((int)$order['lead_id'], 'payment', 'Payment received: ' . inr($order['amount_paise'] / 100) . ' (' . $order['order_no'] . ')', true);
    }
    track_event('payment_success', $order['order_no'], (int)$order['service_id'] ?: null);

    $receiptUrl = abs_url('receipt/?o=' . $order['order_no'] . '&t=' . $order['access_token']);
    $body = '<p>Hi ' . e($order['customer_name']) . ',</p><p>Thank you! We have received your payment of <strong>' . e(inr($order['amount_paise'] / 100)) . '</strong> for <strong>' . e($order['item_title']) . '</strong>.</p>'
        . '<p>Order: <strong>' . e($order['order_no']) . '</strong><br>Payment ID: ' . e($paymentId) . '</p><p>I will reach out within 24 hours to kick things off.</p>';
    send_mail($order['customer_email'], "Payment received — {$order['order_no']}", email_layout('Payment received 🎉', $body, 'View receipt', $receiptUrl), ['kind' => 'order_receipt', 'lead_id' => $order['lead_id'] ?? null]);
    $notify = setting('notify_email', setting('contact_email'));
    if ($notify) {
        send_mail($notify, "💰 New payment: {$order['item_title']} — " . inr($order['amount_paise'] / 100), email_layout('New payment received', '<p>' . e($order['customer_name']) . ' (' . e($order['customer_email']) . ', ' . e($order['customer_phone']) . ') paid <strong>' . e(inr($order['amount_paise'] / 100)) . '</strong> for ' . e($order['item_title']) . '.</p><p>Order ' . e($order['order_no']) . '</p>', 'Open admin', abs_url('admin/orders/')), ['kind' => 'admin_notify']);
    }
}

<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$o = preg_replace('/[^A-Z0-9\-]/', '', (string)($_GET['o'] ?? ''));
$t = preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? ''));
$order = $o ? db_row('SELECT * FROM orders WHERE order_no = ?', [$o]) : null;
if (!$order || $t === '' || !hash_equals($order['access_token'], $t)) {
    http_response_code(404);
    require ROOT . '/404.php';
    exit;
}
page_start(['title' => 'Receipt ' . $order['order_no'], 'noindex' => true]);
$paid = $order['status'] === 'paid';
?>
<section class="pt-32 sm:pt-40">
  <div class="mx-auto max-w-2xl px-5 sm:px-6">
    <div class="card p-8 sm:p-10">
      <div class="flex items-center justify-between">
        <img src="<?= e(brand_logo_url()) ?>" alt="" class="h-10 w-10 rounded-xl">
        <?= status_badge($order['status']) ?>
      </div>
      <h1 class="mt-6 text-3xl font-extrabold tracking-tight"><?= $paid ? 'Payment received 🎉' : 'Payment pending' ?></h1>
      <p class="mt-2 text-white/60"><?= $paid ? 'Thank you! I will contact you within 24 hours to get started.' : 'If money was deducted, it will be confirmed automatically within a few minutes.' ?></p>
      <dl class="mt-8 divide-y divide-white/[0.06] text-sm">
        <?php foreach (['Order' => $order['order_no'], 'Item' => $order['item_title'], 'Amount' => inr($order['amount_paise'] / 100), 'Name' => $order['customer_name'], 'Email' => $order['customer_email'], 'Payment ID' => $order['razorpay_payment_id'] ?: '—', 'Method' => $order['payment_method'] ?: '—', 'Date' => date('d M Y, h:i A', strtotime($order['paid_at'] ?: $order['created_at']))] as $k => $v): ?>
          <div class="flex justify-between gap-6 py-3"><dt class="text-white/45"><?= e($k) ?></dt><dd class="text-right font-medium"><?= e((string)$v) ?></dd></div>
        <?php endforeach; ?>
      </dl>
      <div class="mt-8 flex flex-col gap-3 sm:flex-row">
        <button type="button" class="btn-ghost" data-print><?= icon('download', 'h-4 w-4') ?> Save as PDF</button>
        <a href="<?= e(url('')) ?>" class="btn-primary">Back to home</a>
      </div>
    </div>
  </div>
</section>
<?php page_end();

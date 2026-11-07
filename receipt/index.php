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
$product = !empty($order['product_id']) ? db_row('SELECT id, slug, title, page_count FROM products WHERE id = ?', [(int)$order['product_id']]) : null;
$limit = (int)setting('store_download_limit', '10');
$left = $limit > 0 ? max(0, $limit - (int)$order['download_count']) : null;
$dl = url('download/?o=' . $order['order_no'] . '&t=' . $order['access_token']);
?>
<section class="pt-32 sm:pt-40">
  <div class="mx-auto max-w-2xl px-5 sm:px-6">
    <div class="card p-8 sm:p-10">
      <div class="flex items-center justify-between">
        <img src="<?= e(brand_logo_url()) ?>" alt="" class="h-10 w-10 rounded-xl">
        <?= status_badge($order['status']) ?>
      </div>
      <h1 class="mt-6 text-3xl font-extrabold tracking-tight"><?= $paid ? ($product ? 'Your notes are ready 📚' : 'Payment received 🎉') : 'Payment pending' ?></h1>
      <p class="mt-2 text-white/60"><?= $paid ? ($product ? 'Thank you for your purchase! Download your PDF below — the link has also been emailed to ' . e($order['customer_email']) . '.' : 'Thank you! I will contact you within 24 hours to get started.') : 'If money was deducted, it will be confirmed automatically within a few minutes. Refresh this page in a moment.' ?></p>
      <?php if ($paid && $product): ?>
        <div class="mt-6 rounded-3xl border border-neo-green/25 bg-neo-green/[0.06] p-5">
          <p class="font-semibold"><?= e($product['title']) ?></p>
          <p class="mt-1 text-xs text-white/50">PDF<?= $product['page_count'] ? ' · ' . (int)$product['page_count'] . ' pages' : '' ?><?= $left !== null ? ' · ' . $left . ' download' . ($left === 1 ? '' : 's') . ' left' : '' ?></p>
          <?php if ($left === null || $left > 0): ?>
            <a href="<?= e($dl) ?>" class="btn-primary mt-4 w-full sm:w-auto" data-track="product_download" data-label="<?= e($product['slug']) ?>"><?= icon('download', 'h-4 w-4') ?> Download PDF</a>
          <?php else: ?>
            <p class="mt-3 text-sm text-amber-100">The download limit for this order has been reached. Email <?= e(setting('contact_email')) ?> with your order number and I'll help.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <dl class="mt-8 divide-y divide-white/[0.06] text-sm">
        <?php foreach (['Order' => $order['order_no'], 'Item' => $order['item_title'], 'Amount' => money((int)$order['amount_paise'], (string)$order['currency']), 'Name' => $order['customer_name'], 'Email' => $order['customer_email'], 'Payment ID' => $order['razorpay_payment_id'] ?: '—', 'Method' => $order['payment_method'] ?: '—', 'Date' => date('d M Y, h:i A', strtotime($order['paid_at'] ?: $order['created_at']))] as $k => $v): ?>
          <div class="flex justify-between gap-6 py-3"><dt class="text-white/45"><?= e($k) ?></dt><dd class="text-right font-medium"><?= e((string)$v) ?></dd></div>
        <?php endforeach; ?>
      </dl>
      <div class="mt-8 flex flex-col gap-3 sm:flex-row">
        <button type="button" class="btn-ghost" data-print><?= icon('file', 'h-4 w-4') ?> Save receipt as PDF</button>
        <a href="<?= e(url($product ? 'store/' : '')) ?>" class="btn-ghost"><?= $product ? 'Back to the store' : 'Back to home' ?></a>
      </div>
    </div>
  </div>
</section>
<?php page_end();

<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/admin_boot.php';
require_admin();

$status = in_array($_GET['status'] ?? '', ['created', 'paid', 'failed'], true) ? $_GET['status'] : '';
$where = $status ? 'WHERE o.status = ?' : '';
$params = $status ? [$status] : [];
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 30;
$total = (int)db_val("SELECT COUNT(*) FROM orders o $where", $params);
$rows = db_all("SELECT o.*, l.ref lead_ref FROM orders o LEFT JOIN leads l ON l.id = o.lead_id $where ORDER BY o.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
$paidAll = (int)db_val("SELECT COALESCE(SUM(amount_paise),0) FROM orders WHERE status='paid'") / 100;
$paidMonth = (int)db_val("SELECT COALESCE(SUM(amount_paise),0) FROM orders WHERE status='paid' AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')") / 100;
$abandoned = (int)db_val("SELECT COUNT(*) FROM orders WHERE status='created' AND created_at < (NOW() - INTERVAL 1 HOUR)");
admin_start('Payments', 'orders');
?>
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
  <?= admin_stat('Collected (all time)', inr($paidAll), '', 'lock') ?>
  <?= admin_stat('This month', inr($paidMonth), date('F Y'), 'chart') ?>
  <?= admin_stat('Paid orders', (string)db_val("SELECT COUNT(*) FROM orders WHERE status='paid'"), '', 'check') ?>
  <?= admin_stat('Abandoned checkouts', (string)$abandoned, 'follow up — they were interested!', 'refresh') ?>
</div>
<?php if (!razorpay_enabled()): ?><p class="mt-4 rounded-2xl border border-amber-400/20 bg-amber-400/[0.06] px-4 py-3 text-sm text-amber-100">Razorpay is disabled. <a class="underline" href="<?= e(url('admin/settings/?tab=payments')) ?>">Configure payments →</a></p><?php endif; ?>
<div class="mt-4 flex gap-2"><?php foreach (['' => 'All', 'paid' => 'Paid', 'created' => 'Pending / abandoned', 'failed' => 'Failed'] as $k => $l): ?><a href="?status=<?= e($k) ?>" class="chip <?= $status === $k ? 'is-active' : '' ?>"><?= e($l) ?></a><?php endforeach; ?></div>
<div class="a-card mt-3 overflow-x-auto p-0">
  <table class="a-table"><thead><tr><th>Order</th><th>Customer</th><th>Item</th><th>Amount</th><th>Status</th><th>Razorpay</th><th>Date</th></tr></thead><tbody>
  <?php foreach ($rows as $o): ?>
    <tr>
      <td class="font-mono text-xs"><?= e($o['order_no']) ?><?php if ($o['lead_ref']): ?><a class="block text-neo-green" href="<?= e(url('admin/leads/view/?id=' . $o['lead_id'])) ?>"><?= e($o['lead_ref']) ?></a><?php endif; ?></td>
      <td><?= e($o['customer_name']) ?><span class="block text-xs text-white/40"><?= e($o['customer_email']) ?> · <?= e($o['customer_phone']) ?></span></td>
      <td class="max-w-[220px]"><?= e($o['item_title']) ?><?php if ($o['notes']): ?><span class="block truncate text-xs text-white/35"><?= e($o['notes']) ?></span><?php endif; ?></td>
      <td class="whitespace-nowrap font-semibold"><?= e(inr($o['amount_paise'] / 100)) ?></td>
      <td><?= status_badge($o['status']) ?><?php if ($o['payment_method']): ?><span class="block text-xs text-white/40"><?= e($o['payment_method']) ?></span><?php endif; ?></td>
      <td class="font-mono text-[11px] text-white/40"><?= e($o['razorpay_order_id']) ?><span class="block"><?= e($o['razorpay_payment_id'] ?: '') ?></span></td>
      <td class="whitespace-nowrap text-xs text-white/45"><?= e(date('d M Y, h:i A', strtotime($o['paid_at'] ?: $o['created_at']))) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="py-12 text-center text-white/40">No orders yet.</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?= paginate($total, $per, $page, $status ? ['status' => $status] : []) ?>
<?php admin_end();

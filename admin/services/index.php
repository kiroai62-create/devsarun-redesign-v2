<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/admin_boot.php';
require_admin();

if (is_post()) {
    admin_post_guard('manager');
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'toggle') {
        db_exec('UPDATE services SET is_active = 1 - is_active, updated_at = NOW() WHERE id = ?', [$id]);
        audit('service.toggle', 'service', $id);
    } elseif (($_POST['action'] ?? '') === 'delete' && can('owner')) {
        db_exec('DELETE FROM services WHERE id = ?', [$id]);
        audit('service.delete', 'service', $id);
        flash('success', 'Service deleted.');
    }
    redirect('admin/services/');
}
$rows = db_all('SELECT s.*, (SELECT COUNT(*) FROM leads l WHERE l.service_id = s.id) leads, (SELECT COUNT(*) FROM events e WHERE e.service_id = s.id AND e.name = "service_view") views, (SELECT COUNT(*) FROM events e WHERE e.service_id = s.id AND e.name = "brochure_download") downloads FROM services s ORDER BY sort_order, id');
admin_start('Services', 'services');
?>
<div class="flex items-center justify-between"><p class="text-sm text-white/45">Each service becomes a product page, a pricing card, a brochure PDF and AI-assistant knowledge.</p><?php if (can('manager')): ?><a href="<?= e(url('admin/services/edit/')) ?>" class="a-btn">+ New service</a><?php endif; ?></div>
<div class="a-card mt-4 overflow-x-auto p-0">
  <table class="a-table"><thead><tr><th>Service</th><th>Price</th><th>Online booking</th><th>Views</th><th>Brochure DLs</th><th>Leads</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $s): ?>
    <tr>
      <td><div class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-neo-green/10 text-neo-green"><?= icon($s['icon'], 'h-4 w-4') ?></span><span><a class="font-medium text-white hover:text-neo-green" href="<?= e(url('admin/services/edit/?id=' . $s['id'])) ?>"><?= e($s['title']) ?></a><span class="block text-xs text-white/35">/services/<?= e($s['slug']) ?>/ · <?= e(SERVICE_CATEGORIES[$s['category']] ?? $s['category']) ?></span></span></div></td>
      <td class="whitespace-nowrap"><?= e(price_text($s)) ?></td>
      <td><?= (int)$s['is_buyable'] ? '<span class="text-neo-green">' . e(inr((int)$s['buy_price'])) . '</span>' : '<span class="text-white/30">—</span>' ?></td>
      <td><?= (int)$s['views'] ?></td><td><?= (int)$s['downloads'] ?></td><td><?= (int)$s['leads'] ?></td>
      <td><?= (int)$s['is_active'] ? status_badge('sent') : status_badge('draft') ?></td>
      <td class="whitespace-nowrap text-right">
        <a class="a-btn-ghost !py-1.5 text-xs" href="<?= e(url('services/' . $s['slug'] . '/')) ?>" target="_blank">View</a>
        <a class="a-btn-ghost !py-1.5 text-xs" href="<?= e(url('brochure/' . $s['slug'] . '/')) ?>" target="_blank">Brochure</a>
        <?php if (can('manager')): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="a-btn-ghost !py-1.5 text-xs"><?= (int)$s['is_active'] ? 'Hide' : 'Publish' ?></button></form><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php admin_end();

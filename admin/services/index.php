<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/admin_boot.php';
require_admin();

if (is_post()) {
    admin_post_guard('manager');
    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'toggle') {
        db_exec('UPDATE services SET is_active = 1 - is_active, updated_at = NOW() WHERE id = ?', [$id]);
        audit('service.toggle', 'service', $id);
    } elseif ($action === 'delete' && can('owner')) {
        db_exec('DELETE FROM services WHERE id = ?', [$id]);
        audit('service.delete', 'service', $id);
        flash('success', 'Service deleted.');
    } elseif ($action === 'duplicate') {
        $src = service_by_id($id);
        if ($src) {
            $slug = $src['slug'] . '-copy';
            for ($n = 2; db_val('SELECT id FROM services WHERE slug = ?', [$slug]); $n++) {
                $slug = $src['slug'] . '-copy-' . $n;
            }
            unset($src['id']);
            $newId = db_insert('services', array_merge($src, [
                'slug' => $slug, 'title' => mb_substr($src['title'] . ' (copy)', 0, 150), 'is_active' => 0, 'is_featured' => 0,
                'sort_order' => (int)$src['sort_order'] + 1, 'created_at' => now(), 'updated_at' => null,
            ]));
            audit('service.duplicate', 'service', $newId);
            flash('success', 'Copy created as a hidden draft — change the title, URL and prices, then publish.');
            redirect('admin/services/edit/?id=' . $newId);
        }
    } elseif ($action === 'prices') {
        // Quick price editor: every row of the table is saved at once.
        $num = static function ($v): ?int { $v = preg_replace('/\D/', '', (string)$v); return $v === '' ? null : (int)$v; };
        $cents = static function ($v): ?int { $v = preg_replace('/[^0-9.]/', '', (string)$v); return is_numeric($v) && (float)$v >= 0.5 ? (int)round((float)$v * 100) : null; };
        $saved = 0;
        $skipped = [];
        foreach ((array)($_POST['p'] ?? []) as $sid => $row) {
            $svc = is_array($row) ? service_by_id((int)$sid) : null;
            if (!$svc) {
                continue;
            }
            $data = [
                'price_currency' => ($row['cur'] ?? '') === 'USD' ? 'USD' : 'INR',
                'price_from' => $num($row['from'] ?? ''),
                'price_to' => $num($row['to'] ?? ''),
                'buy_price' => $num($row['buy'] ?? ''),
                'buy_usd_cents' => $cents($row['buy_usd'] ?? ''),
            ];
            if ((int)$svc['is_buyable'] && (int)$data['buy_price'] < 1 && (int)$data['buy_usd_cents'] < 1) {
                $skipped[] = $svc['title'];
                continue; // online booking needs at least one booking price
            }
            $changed = false;
            foreach ($data as $k => $val) {
                if ((string)($svc[$k] ?? '') !== (string)($val ?? '')) {
                    $changed = true;
                }
            }
            if ($changed) {
                db_update('services', $data + ['updated_at' => now()], 'id = ?', [(int)$svc['id']]);
                audit('service.prices', 'service', (int)$svc['id']);
                $saved++;
            }
        }
        flash('success', $saved . ' service' . ($saved === 1 ? '' : 's') . ' updated.');
        if ($skipped) {
            flash('error', 'Not saved (online booking is on but no booking price): ' . implode(', ', $skipped) . '. Add a ₹ or $ booking price, or turn off online booking in the service editor.');
        }
    }
    redirect('admin/services/');
}
$rows = db_all('SELECT s.*, (SELECT COUNT(*) FROM leads l WHERE l.service_id = s.id) leads, (SELECT COUNT(*) FROM events e WHERE e.service_id = s.id AND e.name = "service_view") views, (SELECT COUNT(*) FROM events e WHERE e.service_id = s.id AND e.name = "brochure_download") downloads FROM services s ORDER BY sort_order, id');
$edit = can('manager');
$usd = static fn(array $s) => !empty($s['buy_usd_cents']) ? rtrim(rtrim(number_format((int)$s['buy_usd_cents'] / 100, 2, '.', ''), '0'), '.') : '';
admin_start('Services', 'services');
?>
<div class="flex flex-wrap items-center justify-between gap-3">
  <p class="text-sm text-white/45">Each service becomes a product page, a pricing card, a brochure PDF and AI-assistant knowledge.<?= $edit ? ' Edit prices right in the table and press “Save prices”.' : '' ?></p>
  <?php if ($edit): ?><div class="flex gap-2"><button form="bulk-prices" class="a-btn-ghost">Save prices</button><a href="<?= e(url('admin/services/edit/')) ?>" class="a-btn">+ New service</a></div><?php endif; ?>
</div>
<?php if ($edit): ?><form id="bulk-prices" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="prices"></form><?php endif; ?>
<div class="a-card mt-4 overflow-x-auto p-0">
  <table class="a-table"><thead><tr><th>Service</th><th>Price (currency · from · to)</th><th>Online booking (₹ · $)</th><th>Views</th><th>Brochure DLs</th><th>Leads</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $s): $id = (int)$s['id']; $f = 'bulk-prices'; ?>
    <tr>
      <td><div class="flex items-center gap-3"><span class="flex h-9 w-9 flex-none items-center justify-center rounded-xl bg-neo-green/10 text-neo-green"><?= icon($s['icon'], 'h-4 w-4') ?></span><span><a class="font-medium text-white hover:text-neo-green" href="<?= e(url('admin/services/edit/?id=' . $id)) ?>"><?= e($s['title']) ?></a><span class="block text-xs text-white/35">/services/<?= e($s['slug']) ?>/ · <?= e(SERVICE_CATEGORIES[$s['category']] ?? $s['category']) ?></span></span></div></td>
      <?php if ($edit): ?>
        <td class="whitespace-nowrap">
          <div class="flex items-center gap-1.5">
            <select form="<?= $f ?>" name="p[<?= $id ?>][cur]" class="a-input !w-[72px] !px-2 !py-1.5 text-xs" aria-label="Currency"><option value="INR" <?= service_currency($s) === 'INR' ? 'selected' : '' ?>>₹</option><option value="USD" <?= service_currency($s) === 'USD' ? 'selected' : '' ?>>$</option></select>
            <input form="<?= $f ?>" name="p[<?= $id ?>][from]" value="<?= e((string)($s['price_from'] ?? '')) ?>" class="a-input !w-24 !px-2 !py-1.5 text-xs" inputmode="numeric" placeholder="from" aria-label="Price from">
            <input form="<?= $f ?>" name="p[<?= $id ?>][to]" value="<?= e((string)($s['price_to'] ?? '')) ?>" class="a-input !w-24 !px-2 !py-1.5 text-xs" inputmode="numeric" placeholder="to" aria-label="Price to">
          </div>
          <span class="mt-1 block text-[11px] text-white/35">Shows as: <?= e(price_text($s)) ?></span>
        </td>
        <td class="whitespace-nowrap">
          <div class="flex items-center gap-1.5">
            <input form="<?= $f ?>" name="p[<?= $id ?>][buy]" value="<?= e((string)($s['buy_price'] ?? '')) ?>" class="a-input !w-24 !px-2 !py-1.5 text-xs" inputmode="numeric" placeholder="₹" aria-label="Booking price in rupees">
            <input form="<?= $f ?>" name="p[<?= $id ?>][buy_usd]" value="<?= e($usd($s)) ?>" class="a-input !w-20 !px-2 !py-1.5 text-xs" inputmode="decimal" placeholder="$" aria-label="Booking price in US dollars">
          </div>
          <span class="mt-1 block text-[11px] <?= (int)$s['is_buyable'] ? 'text-neo-green/80' : 'text-white/30' ?>"><?= (int)$s['is_buyable'] ? 'Booking on · ' . e(service_buy_text($s) ?: 'no price') : 'Booking off (turn on in editor)' ?></span>
        </td>
      <?php else: ?>
        <td class="whitespace-nowrap"><?= e(price_text($s)) ?></td>
        <td><?= service_buyable($s) ? '<span class="text-neo-green">' . e(service_buy_text($s)) . '</span>' : '<span class="text-white/30">—</span>' ?></td>
      <?php endif; ?>
      <td><?= (int)$s['views'] ?></td><td><?= (int)$s['downloads'] ?></td><td><?= (int)$s['leads'] ?></td>
      <td><?= (int)$s['is_active'] ? status_badge('sent') : status_badge('draft') ?></td>
      <td class="whitespace-nowrap text-right">
        <a class="a-btn-ghost !py-1.5 text-xs" href="<?= e(url('admin/services/edit/?id=' . $id)) ?>">Edit</a>
        <a class="a-btn-ghost !py-1.5 text-xs" href="<?= e(url('services/' . $s['slug'] . '/')) ?>" target="_blank">View</a>
        <a class="a-btn-ghost !py-1.5 text-xs" href="<?= e(url('brochure/' . $s['slug'] . '/')) ?>" target="_blank">Brochure</a>
        <?php if ($edit): ?>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?= $id ?>"><button class="a-btn-ghost !py-1.5 text-xs" title="Copy this service as a starting point for a new one">Duplicate</button></form>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $id ?>"><button class="a-btn-ghost !py-1.5 text-xs"><?= (int)$s['is_active'] ? 'Hide' : 'Publish' ?></button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php if ($edit): ?><div class="mt-4 flex justify-end"><button form="bulk-prices" class="a-btn">Save prices</button></div><?php endif; ?>
<p class="mt-3 text-xs text-white/35">Package prices (inside each service) are edited in the service editor. Visitors outside India see all prices converted to their local currency automatically; payments are charged in ₹ or $.</p>
<?php admin_end();

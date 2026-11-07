<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/admin_boot.php';
require_admin();

if (is_post()) {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'settings') {
        admin_post_guard('owner');
        setting_set('store_enabled', !empty($_POST['store_enabled']) ? '1' : '0');
        setting_set('store_usd_enabled', !empty($_POST['store_usd_enabled']) ? '1' : '0');
        setting_set('store_download_limit', (string)max(0, min(1000, (int)($_POST['store_download_limit'] ?? 10))));
        setting_set('store_title', mb_substr(single_line((string)($_POST['store_title'] ?? '')), 0, 120));
        setting_set('store_intro', mb_substr(trim((string)($_POST['store_intro'] ?? '')), 0, 500));
        audit('store.settings', 'settings');
        flash('success', 'Store settings saved.');
    } elseif ($action === 'toggle') {
        admin_post_guard('manager');
        $p = db_row('SELECT * FROM products WHERE id = ?', [$id]);
        if ($p) {
            $to = $p['status'] === 'published' ? 'draft' : 'published';
            if ($to === 'published' && (!store_pdf_path($p['file_path']) || !product_currencies($p) || trim((string)$p['title']) === '')) {
                flash('error', 'Add a title, a PDF and at least one price before publishing.');
            } else {
                db_update('products', ['status' => $to, 'updated_at' => now()], 'id = ?', [$id]);
                audit('product.' . $to, 'product', $id);
                flash('success', $to === 'published' ? 'Published — it is live in the store.' : 'Moved to drafts.');
            }
        }
    } elseif ($action === 'delete') {
        admin_post_guard('owner');
        $p = db_row('SELECT * FROM products WHERE id = ?', [$id]);
        if ($p) {
            store_delete_previews($p);
            store_delete_image($p['cover_image']);
            if ($f = store_pdf_path($p['file_path'])) {
                @unlink($f);
            }
            db_exec('DELETE FROM products WHERE id = ?', [$id]);
            audit('product.delete', 'product', $id, ['title' => $p['title']]);
            flash('success', 'Product deleted. Past orders keep their records.');
        }
    }
    redirect('admin/store/');
}

$rows = db_all("SELECT p.id, p.slug, p.title, p.category, p.status, p.is_featured, p.price_inr, p.compare_inr, p.price_usd_cents, p.compare_usd_cents, p.page_count, p.cover_image, p.preview_images, p.views, p.sales_count, p.updated_at, p.created_at,
    (SELECT COALESCE(SUM(o.amount_paise),0) FROM orders o WHERE o.product_id = p.id AND o.status = 'paid' AND o.currency = 'INR') rev_inr,
    (SELECT COALESCE(SUM(o.amount_paise),0) FROM orders o WHERE o.product_id = p.id AND o.status = 'paid' AND o.currency = 'USD') rev_usd
    FROM products p ORDER BY p.status = 'published' DESC, p.sort_order, p.id DESC");
$salesAll = (int)db_val("SELECT COUNT(*) FROM orders WHERE status = 'paid' AND product_id IS NOT NULL");
$sales30 = (int)db_val("SELECT COUNT(*) FROM orders WHERE status = 'paid' AND product_id IS NOT NULL AND paid_at >= (NOW() - INTERVAL 30 DAY)");
$views30 = (int)db_val("SELECT COUNT(*) FROM events WHERE name = 'product_view' AND created_at >= (NOW() - INTERVAL 30 DAY)");
admin_start('Store', 'store');
?>
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
  <?= admin_stat('Store revenue', revenue_text('product_id IS NOT NULL'), 'all time, paid orders', 'cart') ?>
  <?= admin_stat('Sales', (string)$salesAll, $sales30 . ' in the last 30 days', 'check') ?>
  <?= admin_stat('Product views (30d)', (string)$views30, $views30 ? round($sales30 / $views30 * 100, 1) . '% view → sale' : '', 'users') ?>
  <?= admin_stat('Products', (string)count($rows), count(array_filter($rows, static fn($r) => $r['status'] === 'published')) . ' published', 'file') ?>
</div>
<?php if (!razorpay_enabled()): ?><p class="mt-4 rounded-2xl border border-amber-400/20 bg-amber-400/[0.06] px-4 py-3 text-sm text-amber-100">Razorpay is disabled — visitors cannot buy yet. <a class="underline" href="<?= e(url('admin/settings/?tab=payments')) ?>">Configure payments →</a></p><?php endif; ?>
<?php if (!ai_configured()): ?><p class="mt-4 rounded-2xl border border-amber-400/20 bg-amber-400/[0.06] px-4 py-3 text-sm text-amber-100">Add a Groq or Cloudflare key so AI can write titles, descriptions and product pages from your PDFs. <a class="underline" href="<?= e(url('admin/settings/?tab=ai')) ?>">AI settings →</a></p><?php endif; ?>

<div class="mt-6 flex flex-wrap items-center justify-between gap-3">
  <p class="text-sm text-white/45">Upload a PDF → AI writes the title, description and full product page as a draft → edit, set ₹ / $ prices → publish.</p>
  <?php if (can('manager')): ?><a href="<?= e(url('admin/store/edit/')) ?>" class="a-btn">+ New product (upload PDF)</a><?php endif; ?>
</div>
<div class="a-card mt-3 overflow-x-auto p-0">
  <table class="a-table"><thead><tr><th>Product</th><th>Price</th><th>Views</th><th>Sales</th><th>Revenue</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $p): $cover = product_cover_url($p); ?>
    <tr>
      <td><div class="flex items-center gap-3">
        <span class="flex h-14 w-10 flex-none items-center justify-center overflow-hidden rounded-lg bg-white/[0.05] text-white/30"><?php if ($cover): ?><img src="<?= e($cover) ?>" alt="" class="h-full w-full object-cover object-top"><?php else: ?><?= icon('file', 'h-4 w-4') ?><?php endif; ?></span>
        <span><a class="font-medium text-white hover:text-neo-green" href="<?= e(url('admin/store/edit/?id=' . $p['id'])) ?>"><?= e($p['title']) ?></a><?php if ((int)$p['is_featured']): ?> <span class="text-[10px] text-neo-green">★ featured</span><?php endif; ?>
        <span class="block text-xs text-white/35">/store/<?= e($p['slug']) ?>/ · <?= $p['page_count'] ? (int)$p['page_count'] . ' pages' : 'no PDF' ?><?= $p['category'] ? ' · ' . e($p['category']) : '' ?></span></span>
      </div></td>
      <td class="whitespace-nowrap text-xs"><?= e(product_price_summary($p)) ?></td>
      <td><?= (int)$p['views'] ?></td>
      <td><?= (int)$p['sales_count'] ?></td>
      <td class="whitespace-nowrap text-xs"><?= e(implode(' + ', array_filter([(int)$p['rev_inr'] ? money((int)$p['rev_inr'], 'INR') : '', (int)$p['rev_usd'] ? money((int)$p['rev_usd'], 'USD') : ''])) ?: '—') ?></td>
      <td><?= $p['status'] === 'published' ? status_badge('sent') : status_badge('draft') ?></td>
      <td class="whitespace-nowrap text-right">
        <?php if ($p['status'] === 'published'): ?><a class="a-btn-ghost !py-1.5 text-xs" href="<?= e(url('store/' . $p['slug'] . '/')) ?>" target="_blank">View</a><?php endif; ?>
        <a class="a-btn-ghost !py-1.5 text-xs" href="<?= e(url('admin/store/edit/?id=' . $p['id'])) ?>">Edit</a>
        <?php if (can('manager')): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="a-btn-ghost !py-1.5 text-xs"><?= $p['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button></form><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="py-14 text-center text-white/40">No products yet. Upload your first PDF — AI will draft the product page for you.</td></tr><?php endif; ?>
  </tbody></table>
</div>

<form method="post" class="a-card mt-6 grid max-w-3xl gap-4 sm:grid-cols-2">
  <?= csrf_field() ?><input type="hidden" name="action" value="settings">
  <h2 class="font-semibold sm:col-span-2">Store settings</h2>
  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="store_enabled" value="1" <?= store_enabled() ? 'checked' : '' ?> class="accent-[#4ADE80]"> Store visible on the website (header, home, about…)</label>
  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="store_usd_enabled" value="1" <?= store_usd_enabled() ? 'checked' : '' ?> class="accent-[#4ADE80]"> Sell in USD too</label>
  <p class="text-xs text-white/40 sm:col-span-2">USD payments need <strong>International payments</strong> activated in your Razorpay dashboard (Account &amp; Settings → International payments). If a USD payment can't be created, buyers are asked to switch to ₹ automatically.</p>
  <div><label class="a-label">Store heading</label><input class="a-input" name="store_title" value="<?= e(setting('store_title', 'Notes & study resources')) ?>"></div>
  <div><label class="a-label">Downloads allowed per order (0 = unlimited)</label><input class="a-input" name="store_download_limit" inputmode="numeric" value="<?= e(setting('store_download_limit', '10')) ?>"></div>
  <div class="sm:col-span-2"><label class="a-label">Store intro (shown under the heading)</label><textarea class="a-input min-h-[80px]" name="store_intro"><?= e(setting('store_intro')) ?></textarea></div>
  <?php if (can('owner')): ?><div><button class="a-btn">Save store settings</button></div><?php endif; ?>
</form>
<?php admin_end();

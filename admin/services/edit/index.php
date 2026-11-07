<?php
declare(strict_types=1);
require dirname(__DIR__, 3) . '/app/admin_boot.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$s = $id ? db_row('SELECT * FROM services WHERE id = ?', [$id]) : null;
if ($id && !$s) {
    flash('error', 'Service not found.');
    redirect('admin/services/');
}
$icons = ['rocket', 'briefcase', 'cart', 'code', 'phone', 'bot', 'zap', 'puzzle', 'sparkles', 'search', 'megaphone', 'palette', 'refresh', 'wrench', 'compass', 'chat', 'globe', 'chart', 'shield', 'pen', 'grad', 'heart', 'star'];
$errors = [];

if (is_post()) {
    admin_post_guard('manager');
    $in = static fn(string $k, int $max = 400) => trim(mb_substr((string)($_POST[$k] ?? ''), 0, $max));
    $int = static function (string $k): ?int { $v = preg_replace('/\D/', '', (string)($_POST[$k] ?? '')); return $v === '' ? null : (int)$v; };
    $data = [
        'title' => single_line($in('title', 150)),
        'slug' => slugify($in('slug', 80) ?: $in('title', 80)),
        'category' => array_key_exists($_POST['category'] ?? '', SERVICE_CATEGORIES) ? $_POST['category'] : 'other',
        'icon' => in_array($_POST['icon'] ?? '', $icons, true) ? $_POST['icon'] : 'sparkles',
        'tagline' => single_line($in('tagline', 190)) ?: null,
        'summary' => single_line($in('summary', 400)) ?: null,
        'description' => $in('description', 6000) ?: null,
        'price_from' => $int('price_from'),
        'price_to' => $int('price_to'),
        'price_suffix' => $in('price_suffix', 30) ?: null,
        'price_label' => $in('price_label', 80) ?: null,
        'price_usd' => $in('price_usd', 40) ?: null,
        'timeline' => $in('timeline', 80) ?: null,
        'features' => json_encode(lines_to_list($in('features', 4000)), JSON_UNESCAPED_UNICODE),
        'ideal_for' => json_encode(lines_to_list($in('ideal_for', 4000)), JSON_UNESCAPED_UNICODE),
        'not_included' => json_encode(lines_to_list($in('not_included', 4000)), JSON_UNESCAPED_UNICODE),
        'deliverables' => json_encode(lines_to_list($in('deliverables', 8000), ['title', 'desc']), JSON_UNESCAPED_UNICODE),
        'process' => json_encode(lines_to_list($in('process', 8000), ['stage', 'work', 'input', 'receive']), JSON_UNESCAPED_UNICODE),
        'faqs' => json_encode(lines_to_list($in('faqs', 12000), ['q', 'a']), JSON_UNESCAPED_UNICODE),
        'keywords' => $in('keywords', 400) ?: null,
        'overview' => $in('overview', 12000) ?: null,
        'benefits' => json_encode(lines_to_list($in('benefits', 8000), ['title', 'desc']), JSON_UNESCAPED_UNICODE),
        'packages' => json_encode(array_map(static fn($r) => ['name' => $r['name'], 'price' => $r['price'], 'note' => $r['note'], 'features' => array_values(array_filter(array_map('trim', explode(';', $r['features']))))], lines_to_list($in('packages', 8000), ['name', 'price', 'note', 'features'])), JSON_UNESCAPED_UNICODE),
        'tech_stack' => json_encode(array_values(array_filter(array_map('trim', explode(',', $in('tech_stack', 600))))), JSON_UNESCAPED_UNICODE),
        'is_buyable' => !empty($_POST['is_buyable']) ? 1 : 0,
        'buy_price' => $int('buy_price'),
        'buy_label' => single_line($in('buy_label', 80)) ?: null,
        'is_featured' => !empty($_POST['is_featured']) ? 1 : 0,
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        'sort_order' => (int)($_POST['sort_order'] ?? 100),
        'updated_at' => now(),
    ];
    if ($data['title'] === '') $errors[] = 'Title is required.';
    if ($data['is_buyable'] && (int)$data['buy_price'] < 1) $errors[] = 'Set an online booking price (₹) or turn off online booking.';
    if (db_val('SELECT id FROM services WHERE slug = ? AND id <> ?', [$data['slug'], $id])) $errors[] = 'That URL slug is already used.';
    if (!$errors) {
        if ($s) {
            db_update('services', $data, 'id = ?', [$id]);
            audit('service.update', 'service', $id);
        } else {
            $id = db_insert('services', $data + ['created_at' => now()]);
            audit('service.create', 'service', $id);
        }
        flash('success', 'Service saved. Page, pricing card, brochure and AI knowledge are updated.');
        redirect('admin/services/edit/?id=' . $id);
    }
    $s = array_merge($s ?? [], $data);
}
$v = static fn(string $k) => e((string)($s[$k] ?? ''));
admin_start($s ? 'Edit: ' . $s['title'] : 'New service', 'services');
?>
<a href="<?= e(url('admin/services/')) ?>" class="text-xs text-white/40 hover:text-white">← Services</a>
<?php foreach ($errors as $er): ?><p class="mt-3 rounded-2xl border border-red-400/30 bg-red-400/10 px-4 py-3 text-sm text-red-200"><?= e($er) ?></p><?php endforeach; ?>
<form method="post" class="mt-3 grid gap-4 xl:grid-cols-3">
  <?= csrf_field() ?>
  <div class="space-y-4 xl:col-span-2">
    <div class="a-card grid gap-4 sm:grid-cols-2">
      <div class="sm:col-span-2"><label class="a-label">Title</label><input class="a-input" name="title" value="<?= $v('title') ?>" required></div>
      <div><label class="a-label">URL slug</label><input class="a-input" name="slug" value="<?= $v('slug') ?>" placeholder="auto from title"></div>
      <div><label class="a-label">Category</label><select class="a-input" name="category"><?php foreach (SERVICE_CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>" <?= ($s['category'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="sm:col-span-2"><label class="a-label">Tagline</label><input class="a-input" name="tagline" value="<?= $v('tagline') ?>"></div>
      <div class="sm:col-span-2"><label class="a-label">Summary (cards, SEO, AI)</label><textarea class="a-input" name="summary" rows="2"><?= $v('summary') ?></textarea></div>
      <div class="sm:col-span-2"><label class="a-label">Description (service page & brochure)</label><textarea class="a-input min-h-[120px]" name="description"><?= $v('description') ?></textarea></div>
    </div>
    <div class="a-card grid gap-4">
      <h3 class="font-semibold">Detailed explanation</h3>
      <div><label class="a-label">“What is this service?” — overview (blank line between paragraphs)</label><textarea class="a-input min-h-[200px]" name="overview"><?= $v('overview') ?></textarea></div>
      <div><label class="a-label">How it helps the business — <code>Title | Description</code> per line (6 recommended)</label><textarea class="a-input min-h-[140px] font-mono text-xs" name="benefits"><?= e(list_to_lines($s['benefits'] ?? null, ['title', 'desc'])) ?></textarea></div>
      <div><label class="a-label">Packages — <code>Name | Price | Short note | feature 1; feature 2; feature 3</code> (2nd line is highlighted)</label><textarea class="a-input min-h-[120px] font-mono text-xs" name="packages"><?= e(implode("\n", array_map(static fn($p) => implode(' | ', [$p['name'] ?? '', $p['price'] ?? '', $p['note'] ?? '', implode('; ', (array)($p['features'] ?? []))]), json_list($s['packages'] ?? null)))) ?></textarea></div>
      <div><label class="a-label">Technology (comma separated)</label><input class="a-input" name="tech_stack" value="<?= e(implode(', ', json_list($s['tech_stack'] ?? null))) ?>"></div>
    </div>
    <div class="a-card grid gap-4 sm:grid-cols-2">
      <div><label class="a-label">Key features — one per line</label><textarea class="a-input min-h-[120px]" name="features"><?= e(list_to_lines($s['features'] ?? null)) ?></textarea></div>
      <div><label class="a-label">Ideal for — one per line</label><textarea class="a-input min-h-[120px]" name="ideal_for"><?= e(list_to_lines($s['ideal_for'] ?? null)) ?></textarea></div>
      <div class="sm:col-span-2"><label class="a-label">Deliverables — <code>Title | Description</code> per line (6 recommended)</label><textarea class="a-input min-h-[140px] font-mono text-xs" name="deliverables"><?= e(list_to_lines($s['deliverables'] ?? null, ['title', 'desc'])) ?></textarea></div>
      <div class="sm:col-span-2"><label class="a-label">Process — <code>Stage | The work | Your input | You receive</code></label><textarea class="a-input min-h-[110px] font-mono text-xs" name="process"><?= e(list_to_lines($s['process'] ?? null, ['stage', 'work', 'input', 'receive'])) ?></textarea></div>
      <div class="sm:col-span-2"><label class="a-label">FAQs — <code>Question | Answer</code></label><textarea class="a-input min-h-[140px] font-mono text-xs" name="faqs"><?= e(list_to_lines($s['faqs'] ?? null, ['q', 'a'])) ?></textarea></div>
      <div><label class="a-label">Not included — one per line</label><textarea class="a-input min-h-[90px]" name="not_included"><?= e(list_to_lines($s['not_included'] ?? null)) ?></textarea></div>
      <div><label class="a-label">AI keywords (comma separated)</label><textarea class="a-input min-h-[90px]" name="keywords"><?= $v('keywords') ?></textarea></div>
    </div>
  </div>
  <div class="space-y-4">
    <div class="a-card space-y-3">
      <h3 class="font-semibold">Pricing (always shown as negotiable)</h3>
      <div class="grid grid-cols-2 gap-3"><div><label class="a-label">From ₹</label><input class="a-input" name="price_from" value="<?= $v('price_from') ?>" inputmode="numeric"></div><div><label class="a-label">To ₹ (range)</label><input class="a-input" name="price_to" value="<?= $v('price_to') ?>" inputmode="numeric"></div></div>
      <div class="grid grid-cols-2 gap-3"><div><label class="a-label">Suffix</label><input class="a-input" name="price_suffix" value="<?= $v('price_suffix') ?>" placeholder="+ or /month"></div><div><label class="a-label">USD label</label><input class="a-input" name="price_usd" value="<?= $v('price_usd') ?>" placeholder="$199 one-time"></div></div>
      <div><label class="a-label">Label when no price</label><input class="a-input" name="price_label" value="<?= $v('price_label') ?>" placeholder="Custom quote"></div>
      <div><label class="a-label">Typical timeline</label><input class="a-input" name="timeline" value="<?= $v('timeline') ?>"></div>
    </div>
    <div class="a-card space-y-3">
      <h3 class="font-semibold">Online booking (Razorpay)</h3>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_buyable" value="1" <?= !empty($s['is_buyable']) ? 'checked' : '' ?> class="accent-[#4ADE80]"> Allow booking & paying online</label>
      <div><label class="a-label">Booking price ₹</label><input class="a-input" name="buy_price" value="<?= $v('buy_price') ?>" inputmode="numeric"></div>
      <div><label class="a-label">Button label</label><input class="a-input" name="buy_label" value="<?= $v('buy_label') ?>" placeholder="Book now"></div>
      <p class="text-xs text-white/35">Shown as a subtle secondary option — the page stays informative, not a shop.</p>
    </div>
    <div class="a-card space-y-3">
      <h3 class="font-semibold">Display</h3>
      <div><label class="a-label">Icon</label><select class="a-input" name="icon"><?php foreach ($icons as $ic): ?><option <?= ($s['icon'] ?? '') === $ic ? 'selected' : '' ?>><?= e($ic) ?></option><?php endforeach; ?></select></div>
      <div><label class="a-label">Sort order</label><input class="a-input" name="sort_order" value="<?= e((string)($s['sort_order'] ?? 100)) ?>" inputmode="numeric"></div>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" <?= !empty($s['is_featured']) ? 'checked' : '' ?> class="accent-[#4ADE80]"> Featured on homepage</label>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= !isset($s['is_active']) || (int)$s['is_active'] ? 'checked' : '' ?> class="accent-[#4ADE80]"> Published</label>
      <?php if (can('manager')): ?><button class="a-btn w-full" type="submit">Save service</button><?php endif; ?>
      <?php if ($s && !empty($s['slug'])): ?><div class="flex gap-2"><a class="a-btn-ghost flex-1" target="_blank" href="<?= e(url('services/' . $s['slug'] . '/')) ?>">View page</a><a class="a-btn-ghost flex-1" target="_blank" href="<?= e(url('brochure/' . $s['slug'] . '/')) ?>">Brochure</a></div><?php endif; ?>
    </div>
  </div>
</form>
<?php if ($s && can('owner') && $id): ?>
<form method="post" action="<?= e(url('admin/services/')) ?>" class="mt-4" data-confirm="Delete this service permanently?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>"><button class="a-btn-danger">Delete service</button></form>
<?php endif; ?>
<?php admin_end();

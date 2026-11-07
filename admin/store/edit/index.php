<?php
declare(strict_types=1);
require dirname(__DIR__, 3) . '/app/admin_boot.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$p = $id ? db_row('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$p) {
    flash('error', 'Product not found.');
    redirect('admin/store/');
}
$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
$errors = [];
$warnings = [];

/** "64M" → bytes */
function ini_bytes(string $v): int
{
    $v = trim($v);
    $n = (int)$v;
    return match (strtolower(substr($v, -1))) { 'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n };
}
$maxUpload = min(ini_bytes((string)ini_get('upload_max_filesize')), ini_bytes((string)ini_get('post_max_size')) ?: PHP_INT_MAX);

if (is_post()) {
    if ($ajax && !csrf_valid()) {
        json_out(['ok' => false, 'errors' => ['Your session expired or the upload was larger than the server allows (' . ini_get('post_max_size') . '). Refresh the page and try again.']], 419);
    }
    admin_post_guard('manager');
    $in = static fn(string $k, int $max = 400) => trim(mb_substr(str_replace("\r", '', (string)($_POST[$k] ?? '')), 0, $max));
    $rupees = static function (string $k): ?int { $v = preg_replace('/[^0-9.]/', '', (string)($_POST[$k] ?? '')); return $v === '' || (float)$v <= 0 ? null : (int)round((float)$v); };
    $cents = static function (string $k): ?int { $v = preg_replace('/[^0-9.]/', '', (string)($_POST[$k] ?? '')); return $v === '' || (float)$v <= 0 ? null : (int)round((float)$v * 100); };
    $faqs = array_values(array_filter(lines_to_list($in('faqs', 12000), ['q', 'a']), static fn($f) => $f['q'] !== '' && $f['a'] !== ''));

    $data = [
        'title' => single_line($in('title', 190)),
        'slug' => slugify($in('slug', 120) ?: $in('title', 120)),
        'subtitle' => single_line($in('subtitle', 255)) ?: null,
        'category' => single_line($in('category', 80)) ?: null,
        'tags' => implode(', ', array_slice(array_values(array_filter(array_map('trim', explode(',', single_line($in('tags', 400)))))), 0, 12)) ?: null,
        'description' => $in('description', 6000) ?: null,
        'highlights' => json_encode(lines_to_list($in('highlights', 4000)), JSON_UNESCAPED_UNICODE),
        'contents' => json_encode(lines_to_list($in('contents', 8000)), JSON_UNESCAPED_UNICODE),
        'audience' => json_encode(lines_to_list($in('audience', 3000)), JSON_UNESCAPED_UNICODE),
        'faqs' => json_encode($faqs, JSON_UNESCAPED_UNICODE),
        'seo_description' => single_line($in('seo_description', 300)) ?: null,
        'price_inr' => $rupees('price_inr'),
        'compare_inr' => $rupees('compare_inr'),
        'price_usd_cents' => $cents('price_usd'),
        'compare_usd_cents' => $cents('compare_usd'),
        'preview_pages' => max(1, min(12, (int)($_POST['preview_pages'] ?? 4))),
        'is_featured' => !empty($_POST['is_featured']) ? 1 : 0,
        'sort_order' => (int)($_POST['sort_order'] ?? 100),
        'updated_at' => now(),
    ];
    $wantStatus = array_key_exists($_POST['status'] ?? '', STORE_STATUSES) ? $_POST['status'] : 'draft';
    if (!empty($_POST['ai_used'])) {
        $data['ai_generated_at'] = now();
    }
    if ((int)($_POST['page_count'] ?? 0) > 0) {
        $data['page_count'] = min(100000, (int)$_POST['page_count']);
    }
    $pdfText = (string)($_POST['pdf_text'] ?? '');
    if (trim($pdfText) !== '') {
        $data['pdf_text'] = mb_substr(preg_replace('/[\x00-\x08\x0B\x0D-\x1F\x7F]/u', '', $pdfText) ?? '', 0, 200000);
    }

    // PDF upload (private storage).
    $newPdf = null;
    if (!empty($_FILES['pdf']) && ($_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $r = store_save_pdf($_FILES['pdf']);
        if (is_string($r)) {
            $errors[] = $r;
        } else {
            $newPdf = $r;
            $data['file_path'] = $r['path'];
            $data['file_name'] = $r['name'];
            $data['file_size'] = $r['size'];
            if (empty($data['page_count'])) {
                $data['page_count'] = $r['pages'] ?: null;
            }
            if ($data['title'] === '') {
                $data['title'] = mb_substr(ucwords(trim(preg_replace('/[_\-]+/', ' ', pathinfo($r['name'], PATHINFO_FILENAME)) ?? '')), 0, 190) ?: 'Untitled notes';
                $data['slug'] = slugify($in('slug', 120) ?: $data['title']);
            }
        }
    }

    // Preview images rendered in the browser.
    $newPreviews = [];
    foreach (files_list('previews', 12) as $i => $f) {
        if ($img = store_save_image($f, 'p' . ($i + 1), 1400, 80)) {
            $newPreviews[] = $img + ['locked' => false];
        }
    }
    if ($newPreviews) {
        foreach (files_list('teasers', 3) as $i => $f) {
            if ($img = store_save_image($f, 'lock' . ($i + 1), 700, 60)) {
                $newPreviews[] = $img + ['locked' => true];
            }
        }
    }
    $oldPreviewSet = null;
    if ($newPreviews) {
        $data['preview_images'] = json_encode($newPreviews, JSON_UNESCAPED_SLASHES);
        $oldPreviewSet = $p;
    } elseif ($newPdf) {
        $data['preview_images'] = '[]';
        $oldPreviewSet = $p;
        $warnings[] = 'The PDF was saved but preview pages could not be generated in your browser. Open the product and click “Re-render previews”.';
    }

    // Custom cover.
    $oldCover = null;
    if (!empty($_FILES['cover']) && ($_FILES['cover']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ($img = store_save_image($_FILES['cover'], 'cover', 1200, 85)) {
            $data['cover_image'] = $img['src'];
            $oldCover = $p['cover_image'] ?? null;
        } else {
            $errors[] = 'Cover: upload a PNG, JPG or WebP image under 6 MB.';
        }
    } elseif (!empty($_POST['remove_cover'])) {
        $data['cover_image'] = null;
        $oldCover = $p['cover_image'] ?? null;
    }

    if ($data['title'] === '') {
        $errors[] = 'Add a title (or upload a PDF and let AI write it).';
    }
    if (!$p && !$newPdf) {
        $errors[] = 'Upload the PDF for this product.';
    }
    if (db_val('SELECT id FROM products WHERE slug = ? AND id <> ?', [$data['slug'], $id])) {
        $data['slug'] = mb_substr($data['slug'], 0, 110) . '-' . substr(random_token(2), 0, 4);
    }
    if ($data['compare_inr'] !== null && $data['price_inr'] !== null && $data['compare_inr'] <= $data['price_inr']) {
        $data['compare_inr'] = null;
        $warnings[] = 'The ₹ “original price” must be higher than the selling price — it was removed.';
    }
    if ($data['compare_usd_cents'] !== null && $data['price_usd_cents'] !== null && $data['compare_usd_cents'] <= $data['price_usd_cents']) {
        $data['compare_usd_cents'] = null;
        $warnings[] = 'The $ “original price” must be higher than the selling price — it was removed.';
    }
    if ($data['price_usd_cents'] !== null && $data['price_usd_cents'] < 50) {
        $errors[] = 'USD price must be at least $0.50 (Razorpay minimum).';
    }

    if ($errors) {
        // Uploaded files of a failed save are discarded.
        if ($newPdf && ($f = store_pdf_path($newPdf['path']))) {
            @unlink($f);
        }
        foreach ($newPreviews as $img) {
            store_delete_image($img['src']);
        }
        if (isset($data['cover_image']) && $data['cover_image'] !== ($p['cover_image'] ?? null)) {
            store_delete_image($data['cover_image']);
        }
        if ($ajax) {
            json_out(['ok' => false, 'errors' => $errors], 422);
        }
    } else {
        $merged = array_merge($p ?? [], $data);
        $data['status'] = $wantStatus;
        if ($wantStatus === 'published' && (!store_pdf_path($merged['file_path'] ?? null) || !product_currencies($merged))) {
            $data['status'] = 'draft';
            $warnings[] = 'Saved as draft — add at least one price (₹ or $) before publishing.';
        }
        if ($p) {
            db_update('products', $data, 'id = ?', [$id]);
            audit('product.update', 'product', $id);
            if ($newPdf && ($old = store_pdf_path($p['file_path']))) {
                @unlink($old);
            }
        } else {
            $id = db_insert('products', $data + ['created_at' => now()]);
            audit('product.create', 'product', $id);
        }
        if ($oldPreviewSet) {
            store_delete_previews($oldPreviewSet);
        }
        store_delete_image($oldCover);
        foreach ($warnings as $w) {
            flash('error', $w);
        }
        flash('success', $data['status'] === 'published' ? 'Saved & published — the product page is live.' : 'Draft saved. Review the page, then set status to Published.');
        if ($ajax) {
            json_out(['ok' => true, 'redirect' => url('admin/store/edit/?id=' . $id)]);
        }
        redirect('admin/store/edit/?id=' . $id);
    }
    $p = array_merge($p ?? [], $data, ['status' => $wantStatus]);
}

$v = static fn(string $k) => e((string)($p[$k] ?? ''));
$usd = static fn(?int $c) => $c ? rtrim(rtrim(number_format($c / 100, 2, '.', ''), '0'), '.') : '';
$previews = $p ? product_previews($p) : [];
$hasFile = $p && store_pdf_path($p['file_path'] ?? null);
$orders = $p && !empty($p['id']) ? db_all("SELECT order_no, customer_name, customer_email, amount_paise, currency, download_count, paid_at FROM orders WHERE product_id = ? AND status = 'paid' ORDER BY id DESC LIMIT 8", [(int)$p['id']]) : [];
admin_start($p && !empty($p['id']) ? 'Edit: ' . $p['title'] : 'New product', 'store');
?>
<a href="<?= e(url('admin/store/')) ?>" class="text-xs text-white/40 hover:text-white">← Store</a>
<?php foreach ($errors as $er): ?><p class="mt-3 rounded-2xl border border-red-400/30 bg-red-400/10 px-4 py-3 text-sm text-red-200"><?= e($er) ?></p><?php endforeach; ?>
<div id="form-errors" class="mt-3 hidden rounded-2xl border border-red-400/30 bg-red-400/10 px-4 py-3 text-sm text-red-200" role="alert"></div>

<form id="product-form" method="post" enctype="multipart/form-data" class="mt-3 grid gap-4 xl:grid-cols-3"
      data-id="<?= (int)($p['id'] ?? 0) ?>" data-has-file="<?= $hasFile ? '1' : '0' ?>" data-max-bytes="<?= (int)$maxUpload ?>"
      data-file-url="<?= $hasFile ? e(url('admin/store/file/?inline=1&id=' . (int)$p['id'])) : '' ?>" data-ai-url="<?= e(url('admin/store/ai/')) ?>"
      data-pdfjs="<?= e(url('assets/vendor/pdfjs/')) ?>" data-watermark="<?= e(setting('site_name', 'Devs Arun') . ' · preview') ?>" data-ai-ready="<?= ai_configured() ? '1' : '0' ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="page_count" id="page-count" value="">
  <input type="hidden" name="ai_used" id="ai-used" value="">
  <textarea name="pdf_text" id="pdf-text" class="hidden" aria-hidden="true" tabindex="-1"></textarea>

  <div class="space-y-4 xl:col-span-2">
    <!-- 1. PDF -->
    <div class="a-card">
      <div class="flex items-center justify-between gap-3"><h3 class="font-semibold">1 · PDF file</h3><?php if ($hasFile): ?><a class="text-xs text-neo-green" href="<?= e(url('admin/store/file/?id=' . (int)$p['id'])) ?>">Download original</a><?php endif; ?></div>
      <label id="pdf-drop" class="mt-3 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-white/15 bg-white/[0.02] px-4 py-8 text-center transition hover:border-neo-green/40">
        <span class="text-neo-green"><?= icon('file', 'h-8 w-8') ?></span>
        <span class="text-sm font-medium"><?= $hasFile ? 'Replace PDF' : 'Choose or drop your PDF' ?></span>
        <span class="text-xs text-white/40">Max <?= e(ini_get('upload_max_filesize')) ?> · the file stays private — buyers get it only after paying</span>
        <input id="pdf-input" type="file" name="pdf" accept="application/pdf,.pdf" class="sr-only">
      </label>
      <p id="pdf-status" class="mt-3 text-sm text-white/55"><?php if ($hasFile): ?>Current file: <strong class="text-white"><?= e($p['file_name']) ?></strong> · <?= (int)$p['page_count'] ?> pages · <?= e(number_format((int)$p['file_size'] / 1048576, 1)) ?> MB<?php else: ?>No PDF yet.<?php endif; ?></p>
      <div id="pdf-progress" class="mt-2 hidden h-1.5 overflow-hidden rounded-full bg-white/[0.06]"><div class="h-full w-0 rounded-full bg-neo-green transition-all"></div></div>

      <div class="mt-5 flex flex-wrap items-end gap-3">
        <div><label class="a-label" for="preview-pages">Free preview pages</label><input id="preview-pages" class="a-input w-28" type="number" min="1" max="12" name="preview_pages" value="<?= (int)($p['preview_pages'] ?? 4) ?>"></div>
        <button type="button" id="rerender-btn" class="a-btn-ghost <?= $hasFile ? '' : 'hidden' ?>">Re-render previews</button>
        <p class="text-xs text-white/40">Pages are rendered in your browser with a light “preview” watermark; the next 2 pages are shown blurred &amp; locked.</p>
      </div>
      <div id="preview-grid" class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-7">
        <?php foreach ($previews as $img): ?><div class="relative overflow-hidden rounded-lg border border-white/10 bg-white"><img src="<?= e(url($img['src'])) ?>" alt="" class="aspect-[210/297] w-full object-cover object-top"><?php if (!empty($img['locked'])): ?><span class="absolute inset-x-0 bottom-0 bg-black/70 py-0.5 text-center text-[10px]">locked</span><?php endif; ?></div><?php endforeach; ?>
      </div>
    </div>

    <!-- 2. AI -->
    <div class="a-card">
      <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="font-semibold">2 · AI writes the product page</h3><button type="button" id="ai-btn" class="a-btn" <?= ai_configured() ? '' : 'disabled' ?>>✨ Write with AI</button></div>
      <p class="mt-1 text-xs text-white/40">Reads the PDF and drafts the title, subtitle, description, highlights, contents, audience, FAQs, SEO text and a price suggestion. Everything stays editable. <?= ai_configured() ? 'Engine: ' . e(ai_engine_label()) : '<a class="text-neo-green" href="' . e(url('admin/settings/?tab=ai')) . '">Add a Groq / Cloudflare key first →</a>' ?></p>
      <div class="mt-3"><label class="a-label" for="ai-instructions">Instructions for AI (optional)</label><input id="ai-instructions" class="a-input" placeholder="e.g. Target B.Tech 3rd-year students, write in Hinglish, mention it's for semester exams"></div>
      <p id="ai-status" class="mt-3 hidden rounded-2xl bg-white/[0.03] px-4 py-3 text-sm"></p>
    </div>

    <!-- 3. Content -->
    <div class="a-card grid gap-4 sm:grid-cols-2">
      <h3 class="font-semibold sm:col-span-2">3 · Product page</h3>
      <div class="sm:col-span-2"><label class="a-label">Title</label><input class="a-input" name="title" value="<?= $v('title') ?>" maxlength="190"></div>
      <div class="sm:col-span-2"><label class="a-label">Subtitle — one-line benefit hook</label><input class="a-input" name="subtitle" value="<?= $v('subtitle') ?>" maxlength="255"></div>
      <div><label class="a-label">URL slug</label><input class="a-input" name="slug" value="<?= $v('slug') ?>" placeholder="auto from title"></div>
      <div><label class="a-label">Category</label><input class="a-input" name="category" value="<?= $v('category') ?>" placeholder="e.g. Computer Science"></div>
      <div class="sm:col-span-2"><label class="a-label">Description (blank line between paragraphs)</label><textarea class="a-input min-h-[170px]" name="description"><?= $v('description') ?></textarea></div>
      <div><label class="a-label">Highlights — one per line</label><textarea class="a-input min-h-[150px]" name="highlights"><?= e(list_to_lines($p['highlights'] ?? null)) ?></textarea></div>
      <div><label class="a-label">Who it's for — one per line</label><textarea class="a-input min-h-[150px]" name="audience"><?= e(list_to_lines($p['audience'] ?? null)) ?></textarea></div>
      <div class="sm:col-span-2"><label class="a-label">What's inside (chapters / topics) — one per line</label><textarea class="a-input min-h-[150px]" name="contents"><?= e(list_to_lines($p['contents'] ?? null)) ?></textarea></div>
      <div class="sm:col-span-2"><label class="a-label">FAQs — <code>Question | Answer</code> per line (delivery, payment &amp; refund FAQs are added automatically)</label><textarea class="a-input min-h-[130px] font-mono text-xs" name="faqs"><?= e(list_to_lines($p['faqs'] ?? null, ['q', 'a'])) ?></textarea></div>
      <div><label class="a-label">Search tags (comma separated)</label><input class="a-input" name="tags" value="<?= $v('tags') ?>"></div>
      <div><label class="a-label">SEO meta description</label><input class="a-input" name="seo_description" value="<?= $v('seo_description') ?>" maxlength="300"></div>
    </div>
  </div>

  <div class="space-y-4">
    <div class="a-card space-y-3">
      <h3 class="font-semibold">Pricing</h3>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="a-label">Price ₹ (INR)</label><input class="a-input" name="price_inr" value="<?= $v('price_inr') ?>" inputmode="numeric" placeholder="199"></div>
        <div><label class="a-label">Original ₹ (strike-through)</label><input class="a-input" name="compare_inr" value="<?= $v('compare_inr') ?>" inputmode="numeric" placeholder="499"></div>
        <div><label class="a-label">Price $ (USD)</label><input class="a-input" name="price_usd" value="<?= e($usd(isset($p['price_usd_cents']) ? (int)$p['price_usd_cents'] : null)) ?>" inputmode="decimal" placeholder="4.99"></div>
        <div><label class="a-label">Original $</label><input class="a-input" name="compare_usd" value="<?= e($usd(isset($p['compare_usd_cents']) ? (int)$p['compare_usd_cents'] : null)) ?>" inputmode="decimal" placeholder="9.99"></div>
      </div>
      <p id="ai-price-hint" class="hidden rounded-2xl bg-neo-green/[0.06] px-3 py-2 text-xs text-neo-green"></p>
      <p class="text-xs text-white/35">Leave a currency empty to not sell in it. Visitors see ₹ in India and $ elsewhere and can switch. <?= store_usd_enabled() ? '' : '<strong class="text-amber-200">USD selling is off in Store settings.</strong>' ?></p>
    </div>
    <div class="a-card space-y-3">
      <h3 class="font-semibold">Publish</h3>
      <div><label class="a-label">Status</label><select class="a-input" name="status"><?php foreach (STORE_STATUSES as $k => $l): ?><option value="<?= e($k) ?>" <?= ($p['status'] ?? 'draft') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div><label class="a-label">Sort order</label><input class="a-input" name="sort_order" value="<?= e((string)($p['sort_order'] ?? 100)) ?>" inputmode="numeric"></div>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" <?= !empty($p['is_featured']) ? 'checked' : '' ?> class="accent-[#4ADE80]"> Featured (“Bestseller” badge, shown first)</label>
      <?php if (can('manager')): ?><button class="a-btn w-full" type="submit" id="save-btn">Save product</button><?php endif; ?>
      <div id="upload-progress" class="hidden h-1.5 overflow-hidden rounded-full bg-white/[0.06]"><div class="h-full w-0 rounded-full bg-neo-green transition-all"></div></div>
      <?php if ($p && !empty($p['id']) && ($p['status'] ?? '') === 'published'): ?><a class="a-btn-ghost w-full" target="_blank" href="<?= e(url('store/' . $p['slug'] . '/')) ?>">View live page</a><?php endif; ?>
      <?php if ($p && !empty($p['ai_generated_at'])): ?><p class="text-xs text-white/35">AI draft written <?= e(time_ago($p['ai_generated_at'])) ?></p><?php endif; ?>
    </div>
    <div class="a-card space-y-3">
      <h3 class="font-semibold">Cover image (optional)</h3>
      <p class="text-xs text-white/40">By default the first PDF page is the cover. Upload a designed cover to override it.</p>
      <?php if (!empty($p['cover_image'])): ?><img src="<?= e(url($p['cover_image'])) ?>" alt="" class="w-28 rounded-lg border border-white/10"><label class="flex items-center gap-2 text-xs text-white/50"><input type="checkbox" name="remove_cover" value="1" class="accent-[#4ADE80]"> Remove custom cover</label><?php endif; ?>
      <input class="a-input" type="file" name="cover" accept="image/png,image/jpeg,image/webp">
    </div>
    <?php if ($p && !empty($p['id'])): ?>
    <div class="a-card">
      <h3 class="font-semibold">Sales</h3>
      <p class="mt-1 text-sm text-white/55"><?= (int)$p['sales_count'] ?> sold · <?= (int)$p['views'] ?> views</p>
      <ul class="mt-3 space-y-2 text-xs"><?php foreach ($orders as $o): ?><li class="flex justify-between gap-2"><span class="truncate"><?= e($o['customer_name']) ?><span class="block text-white/35"><?= e($o['customer_email']) ?> · <?= (int)$o['download_count'] ?> DL</span></span><span class="text-neo-green"><?= e(money((int)$o['amount_paise'], $o['currency'])) ?></span></li><?php endforeach; ?>
      <?php if (!$orders): ?><li class="text-white/35">No sales yet.</li><?php endif; ?></ul>
    </div>
    <?php endif; ?>
  </div>
</form>
<?php if ($p && !empty($p['id']) && can('owner')): ?>
<form method="post" action="<?= e(url('admin/store/')) ?>" class="mt-4" data-confirm="Delete this product, its PDF and preview images permanently?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="a-btn-danger">Delete product</button></form>
<?php endif; ?>
<script type="module" src="<?= e(asset('js/store-admin.js')) ?>"></script>
<?php admin_end();

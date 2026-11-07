<?php
declare(strict_types=1);

/*
 * Notes store: digital PDF products sold via Razorpay in INR and/or USD.
 *
 * - The original PDF is stored privately in storage/products/ (blocked from the web) and is only
 *   streamed by /download/ to a paid order.
 * - Preview pages are rendered to images in the admin's browser (PDF.js), watermarked, uploaded and
 *   re-encoded with GD into uploads/store/. Visitors never receive the PDF itself before paying.
 */

const STORE_FILE_DIR = 'products';          // inside STORAGE
const STORE_IMAGE_DIR = 'uploads/store';    // inside ROOT (public)
const STORE_STATUSES = ['draft' => 'Draft', 'published' => 'Published'];

function store_enabled(): bool
{
    return setting('store_enabled', '1') === '1';
}

function store_usd_enabled(): bool
{
    return setting('store_usd_enabled', '1') === '1';
}

function products_published(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        return $cache = db_all("SELECT * FROM products WHERE status = 'published' ORDER BY is_featured DESC, sort_order, id DESC");
    } catch (Throwable) {
        return $cache = []; // table not created yet (before the v3 upgrade ran)
    }
}

function product_by_slug(string $slug, bool $publishedOnly = true): ?array
{
    return db_row('SELECT * FROM products WHERE slug = ?' . ($publishedOnly ? " AND status = 'published'" : ''), [$slug]);
}

function product_has_inr(array $p): bool
{
    return (int)($p['price_inr'] ?? 0) > 0;
}

function product_has_usd(array $p): bool
{
    return store_usd_enabled() && (int)($p['price_usd_cents'] ?? 0) > 0;
}

function product_currencies(array $p): array
{
    return array_keys(array_filter(['INR' => product_has_inr($p), 'USD' => product_has_usd($p)]));
}

/** Amount in minor units (paise / cents) for a currency, or 0 when not sold in it. */
function product_amount(array $p, string $currency): int
{
    return match ($currency) {
        'USD' => product_has_usd($p) ? (int)$p['price_usd_cents'] : 0,
        default => product_has_inr($p) ? (int)$p['price_inr'] * 100 : 0,
    };
}

function product_compare_amount(array $p, string $currency): int
{
    $c = $currency === 'USD' ? (int)($p['compare_usd_cents'] ?? 0) : (int)($p['compare_inr'] ?? 0) * 100;
    return $c > product_amount($p, $currency) ? $c : 0;
}

function product_discount_pct(array $p, string $currency): int
{
    $price = product_amount($p, $currency);
    $cmp = product_compare_amount($p, $currency);
    return ($cmp > 0 && $price > 0) ? (int)floor((1 - $price / $cmp) * 100) : 0;
}

/** "₹199 / $4.99" */
function product_price_summary(array $p): string
{
    $parts = [];
    foreach (product_currencies($p) as $c) {
        $parts[] = money(product_amount($p, $c), $c) . (product_compare_amount($p, $c) ? ' (was ' . money(product_compare_amount($p, $c), $c) . ')' : '');
    }
    return $parts ? implode(' / ', $parts) : 'Price coming soon';
}

/** Visitor's display currency: ?cur= → cookie → Cloudflare country header → INR. (JS refines it by timezone on first visit.) */
function store_currency(): string
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $q = strtoupper((string)($_GET['cur'] ?? ''));
    if (in_array($q, ['INR', 'USD'], true)) {
        if (!headers_sent()) {
            setcookie('da_cur', $q, ['expires' => time() + 31536000, 'path' => BASE_PATH === '' ? '/' : BASE_PATH . '/', 'secure' => is_https(), 'samesite' => 'Lax']);
        }
        return $c = $q;
    }
    $ck = (string)($_COOKIE['da_cur'] ?? '');
    if (in_array($ck, ['INR', 'USD'], true)) {
        return $c = $ck;
    }
    $country = strtoupper((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
    if ($country !== '' && $country !== 'XX' && $country !== 'T1') {
        return $c = $country === 'IN' ? 'INR' : 'USD';
    }
    return $c = 'INR';
}

/** Currency to show for this product (visitor preference if the product supports it). */
function product_display_currency(array $p): string
{
    $cur = product_currencies($p);
    if (!$cur) {
        return 'INR';
    }
    return in_array(store_currency(), $cur, true) ? store_currency() : $cur[0];
}

/**
 * Price markup for both currencies. JS shows the one matching the visitor's currency
 * (elements carry data-cur-show). Products sold in only one currency always show it.
 */
function product_price_html(array $p, string $size = 'md'): string
{
    $cur = product_currencies($p);
    if (!$cur) {
        return '<span class="text-sm text-white/45">Price coming soon</span>';
    }
    $both = count($cur) > 1;
    $show = product_display_currency($p);
    [$big, $small] = match ($size) {
        'xl' => ['text-4xl sm:text-5xl', 'text-lg'],
        'lg' => ['text-3xl', 'text-base'],
        default => ['text-xl', 'text-sm'],
    };
    $html = '';
    foreach ($cur as $c) {
        $pct = product_discount_pct($p, $c);
        $cmp = product_compare_amount($p, $c);
        $html .= '<span class="' . ($both && $c !== $show ? 'hidden ' : '') . 'inline-flex flex-wrap items-baseline gap-x-2.5 gap-y-1"' . ($both ? ' data-cur-show="' . $c . '"' : '') . '>'
            . '<span class="' . $big . ' font-extrabold tracking-tight text-white">' . e(money(product_amount($p, $c), $c)) . '</span>'
            . ($cmp ? '<s class="' . $small . ' text-white/35" aria-label="Original price">' . e(money($cmp, $c)) . '</s>' : '')
            . ($pct >= 5 ? '<span class="rounded-full bg-neo-green/15 px-2 py-0.5 text-xs font-bold text-neo-green">' . $pct . '% off</span>' : '')
            . '</span>';
    }
    return $html;
}

function product_previews(array $p): array
{
    return array_values(array_filter(json_list($p['preview_images'] ?? null), static fn($i) => is_array($i) && !empty($i['src'])));
}

/** Relative path of the cover (custom cover, else the first preview page), or ''. */
function product_cover_rel(array $p): string
{
    if (!empty($p['cover_image'])) {
        return (string)$p['cover_image'];
    }
    foreach (product_previews($p) as $i) {
        if (empty($i['locked'])) {
            return (string)$i['src'];
        }
    }
    return '';
}

function product_cover_url(array $p): string
{
    $rel = product_cover_rel($p);
    return $rel !== '' ? url($rel) : '';
}

function product_tags(array $p): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string)($p['tags'] ?? '')))));
}

/** Product card used on the store, home, about and service pages. */
function product_card(array $p): void
{
    $href = url('store/' . $p['slug'] . '/');
    $cover = product_cover_url($p);
    $show = product_display_currency($p);
    $pct = 0;
    foreach (product_currencies($p) as $c) {
        $pct = max($pct, product_discount_pct($p, $c));
    }
    $free = count(array_filter(product_previews($p), static fn($i) => empty($i['locked'])));
    ?>
    <article class="card card-hover group relative flex h-full flex-col overflow-hidden p-0" data-product data-title="<?= e(mb_strtolower($p['title'] . ' ' . $p['subtitle'] . ' ' . $p['tags'])) ?>" data-cat="<?= e(slugify((string)$p['category'])) ?>">
      <a href="<?= e($href) ?>" class="relative block overflow-hidden bg-gradient-to-br from-white/[0.06] to-white/[0.01] p-5 pb-0" tabindex="-1" aria-hidden="true">
        <?php if ($pct >= 5): ?><span class="absolute left-4 top-4 z-10 rounded-full bg-neo-green px-2.5 py-1 text-[11px] font-bold text-neo-black"><?= $pct ?>% off</span><?php endif; ?>
        <?php if ((int)$p['is_featured']): ?><span class="absolute right-4 top-4 z-10 rounded-full bg-white/10 px-2.5 py-1 font-mono text-[10px] uppercase tracking-wider text-white/80 backdrop-blur">Bestseller</span><?php endif; ?>
        <div class="mx-auto aspect-[210/297] w-[78%] overflow-hidden rounded-t-xl bg-white shadow-2xl shadow-black/50 ring-1 ring-white/10 transition duration-500 group-hover:-translate-y-1.5">
          <?php if ($cover): ?><img src="<?= e($cover) ?>" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover object-top"><?php else: ?><div class="flex h-full items-center justify-center bg-neutral-100 text-neutral-400"><?= icon('file', 'h-10 w-10') ?></div><?php endif; ?>
        </div>
      </a>
      <div class="flex flex-1 flex-col p-5 sm:p-6">
        <div class="flex flex-wrap items-center gap-2 font-mono text-[10px] uppercase tracking-widest text-white/40">
          <?php if ($p['category']): ?><span><?= e($p['category']) ?></span><span>·</span><?php endif; ?>
          <span><?= $p['page_count'] ? (int)$p['page_count'] . ' pages' : 'PDF' ?></span>
          <?php if ($free): ?><span>·</span><span class="text-neo-green"><?= $free ?> free preview</span><?php endif; ?>
        </div>
        <h3 class="mt-2 text-lg font-semibold leading-snug tracking-tight"><a href="<?= e($href) ?>" class="after:absolute after:inset-0 hover:text-neo-green"><?= e($p['title']) ?></a></h3>
        <?php if ($p['subtitle']): ?><p class="mt-1.5 line-clamp-2 text-sm leading-relaxed text-white/55"><?= e($p['subtitle']) ?></p><?php endif; ?>
        <div class="mt-auto flex items-end justify-between gap-3 pt-5">
          <div><?= product_price_html($p, 'md') ?></div>
          <span class="inline-flex flex-none items-center gap-1 rounded-full bg-neo-green px-3.5 py-2 text-xs font-bold text-neo-black transition group-hover:bg-neo-green/90">View <?= icon('arrow', 'h-3.5 w-3.5') ?></span>
        </div>
      </div>
    </article>
    <?php
}

/** Store teaser block for the home / about / services pages. Renders nothing when there are no products. */
function store_section(string $kicker = 'store', string $title = 'Notes & resources <span class="text-neo-green">by me</span>', string $sub = '', int $limit = 3): void
{
    if (!store_enabled()) {
        return;
    }
    $items = array_slice(products_published(), 0, $limit);
    if (!$items) {
        return;
    }
    ?>
    <section class="mx-auto mt-28 max-w-6xl px-5 sm:px-6">
      <div class="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
        <?php section_head($kicker, $title, $sub ?: 'Exam-ready PDF notes. Preview pages for free, pay securely and download instantly.', 'left'); ?>
        <a href="<?= e(url('store/')) ?>" class="btn-ghost btn-sm flex-none" data-track="cta_click" data-label="store_section"><?= icon('cart', 'h-4 w-4') ?> Visit the store</a>
      </div>
      <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($items as $p) { echo '<div class="reveal">'; product_card($p); echo '</div>'; } ?></div>
    </section>
    <?php
}

/* ------------------------------------------------------------------
 * Files
 * ------------------------------------------------------------------ */

function store_pdf_path(?string $rel): ?string
{
    if (!$rel || !preg_match('#^products/[0-9]{6}_[a-f0-9]{32}\.pdf$#', $rel)) {
        return null;
    }
    $f = STORAGE . '/' . $rel;
    return is_file($f) ? $f : null;
}

/** Validate + store an uploaded PDF privately. Returns [path, name, size, pages] or an error string. */
function store_save_pdf(array $f): array|string
{
    $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return 'The PDF is larger than the server upload limit (' . ini_get('upload_max_filesize') . '). Increase upload_max_filesize / post_max_size in hPanel → PHP configuration.';
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return 'PDF upload failed. Please try again.';
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $head = (string)file_get_contents($f['tmp_name'], false, null, 0, 1024);
    if (!in_array($mime, ['application/pdf', 'application/x-pdf'], true) || !str_contains($head, '%PDF-')) {
        return 'Only PDF files can be uploaded.';
    }
    $dir = STORAGE . '/' . STORE_FILE_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return 'Could not create storage/products/. Check folder permissions.';
    }
    $rel = STORE_FILE_DIR . '/' . date('ymd') . '_' . random_token(16) . '.pdf';
    if (!move_uploaded_file($f['tmp_name'], STORAGE . '/' . $rel)) {
        return 'Could not save the PDF on the server.';
    }
    @chmod(STORAGE . '/' . $rel, 0640);
    $name = mb_substr(preg_replace('/[^\p{L}\p{N} ._()\-]+/u', '', basename((string)$f['name'])) ?: 'notes.pdf', 0, 190);
    return ['path' => $rel, 'name' => $name, 'size' => (int)filesize(STORAGE . '/' . $rel), 'pages' => store_pdf_count_pages(STORAGE . '/' . $rel)];
}

/** Rough page count without external tools (only used when the browser did not report one). */
function store_pdf_count_pages(string $file): int
{
    $data = (string)@file_get_contents($file, false, null, 0, 64 * 1024 * 1024);
    if (preg_match_all('#/Type\s*/Pages\b[^>]*?/Count\s+(\d+)#s', $data, $m)) {
        return (int)max($m[1]);
    }
    return (int)preg_match_all('#/Type\s*/Page\b(?!s)#', $data);
}

/** Re-encode an uploaded image (GD) into uploads/store/. Returns ['src','w','h'] or null. */
function store_save_image(array $f, string $prefix, int $maxSide = 1600, int $quality = 80): ?array
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || $f['size'] > 6 * 1024 * 1024) {
        return null;
    }
    $info = @getimagesize($f['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!$info || !in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true) || $info['mime'] !== $mime || $info[0] * $info[1] > 40_000_000) {
        return null;
    }
    $src = match ($mime) {
        'image/png' => @imagecreatefrompng($f['tmp_name']),
        'image/jpeg' => @imagecreatefromjpeg($f['tmp_name']),
        default => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($f['tmp_name']) : false,
    };
    if (!$src) {
        return null;
    }
    [$w, $h] = [imagesx($src), imagesy($src)];
    $scale = min(1, $maxSide / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $dir = ROOT . '/' . STORE_IMAGE_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $webp = function_exists('imagewebp') && !empty(gd_info()['WebP Support']);
    $file = preg_replace('/[^a-z0-9-]/', '', $prefix) . '-' . random_token(8) . ($webp ? '.webp' : '.jpg');
    $ok = $webp ? imagewebp($dst, $dir . '/' . $file, $quality) : imagejpeg($dst, $dir . '/' . $file, $quality);
    imagedestroy($src);
    imagedestroy($dst);
    return $ok ? ['src' => STORE_IMAGE_DIR . '/' . $file, 'w' => $nw, 'h' => $nh] : null;
}

/** Normalise $_FILES['x'][] (multiple) into a list of single-file arrays. */
function files_list(string $key, int $max = 20): array
{
    $f = $_FILES[$key] ?? null;
    if (!$f || !is_array($f['name'] ?? null)) {
        return $f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? [$f] : [];
    }
    $out = [];
    foreach (array_keys($f['name']) as $i) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = ['name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        if (count($out) >= $max) {
            break;
        }
    }
    return $out;
}

function store_delete_image(?string $rel): void
{
    if ($rel && preg_match('#^uploads/store/[a-z0-9-]+\.(webp|jpg)$#', $rel) && is_file(ROOT . '/' . $rel)) {
        @unlink(ROOT . '/' . $rel);
    }
}

function store_delete_previews(array $p): void
{
    foreach (product_previews($p) as $i) {
        store_delete_image($i['src']);
    }
}

/* ------------------------------------------------------------------
 * AI copywriting
 * ------------------------------------------------------------------ */

/** Text sample for the model: start of the document + evenly spaced pages, within a char budget. */
function pdf_text_sample(string $text, int $budget = 12000): string
{
    $pages = array_values(array_filter(array_map(static fn($t) => trim(preg_replace('/[ \t]+/', ' ', $t) ?? ''), explode("\f", $text)), static fn($t) => $t !== ''));
    if (!$pages) {
        return '';
    }
    $total = array_sum(array_map('mb_strlen', $pages));
    if ($total <= $budget) {
        return implode("\n\n", array_map(static fn($t, $i) => "[Page " . ($i + 1) . "]\n" . $t, $pages, array_keys($pages)));
    }
    $out = [];
    $used = 0;
    $n = count($pages);
    $pick = array_unique(array_merge(range(0, min(2, $n - 1)), array_map(static fn($k) => (int)floor($k * ($n - 1) / 11), range(1, 11))));
    $per = (int)floor($budget / max(1, count($pick)));
    foreach ($pick as $i) {
        $chunk = mb_substr($pages[$i], 0, $i < 3 ? $per * 2 : $per);
        if ($used + mb_strlen($chunk) > $budget) {
            break;
        }
        $out[] = "[Page " . ($i + 1) . "]\n" . $chunk;
        $used += mb_strlen($chunk);
    }
    return implode("\n\n", $out);
}

/**
 * Write the whole product page from the PDF text.
 * $ctx: file_name, page_count, title_hint, instructions
 */
function store_generate_copy(string $text, array $ctx): array
{
    $sample = pdf_text_sample($text, 12000);
    $owner = setting('owner_name', 'Arun');
    $system = "You are a senior conversion copywriter who writes product pages for digital study notes and PDF guides sold to students and professionals in India and worldwide. "
        . "Write persuasive, specific and HONEST copy based only on the document content provided: no fake reviews, no invented statistics, no guaranteed marks/results, no claims the notes do not support. "
        . "Lead with the reader's outcome, use concrete topic names from the notes, short sentences and plain words. "
        . "Reply with one JSON object only.";
    $schema = [
        'title' => 'Clear product title, max 70 characters, includes the main subject/exam keyword',
        'subtitle' => 'One-line benefit-driven hook, max 140 characters',
        'category' => 'Short category such as "Computer Science", "UPSC", "Class 12 Physics", "Programming"',
        'tags' => ['5-8 short search keywords'],
        'description' => '2-3 short paragraphs separated by a blank line: the problem, what these notes cover and why they help',
        'highlights' => ['5-7 benefit bullets, each max 90 characters, concrete'],
        'contents' => ['6-15 chapter / topic names actually covered, in order'],
        'audience' => ['3-5 bullets: who this is ideal for'],
        'faqs' => [['q' => 'question about the content, level, exam fit', 'a' => 'honest answer, max 220 characters']],
        'seo_description' => 'Meta description, 140-155 characters',
        'suggested_price_inr' => 'integer rupees, typical Indian price for this size/quality of notes (e.g. 49-499)',
        'suggested_price_usd' => 'number in USD for international buyers (e.g. 2.99-14.99)',
    ];
    $user = "Create the product page for these PDF notes by {$owner}.\n"
        . 'File name: ' . ($ctx['file_name'] ?? 'notes.pdf') . "\n"
        . 'Pages: ' . ((int)($ctx['page_count'] ?? 0) ?: 'unknown') . "\n"
        . (!empty($ctx['title_hint']) ? 'Current title from the owner (improve but keep the meaning): ' . $ctx['title_hint'] . "\n" : '')
        . (!empty($ctx['instructions']) ? 'Owner instructions (follow them): ' . $ctx['instructions'] . "\n" : '')
        . "Write in the same language as the notes (use simple English if the notes are English; Hindi notes → Hinglish/Hindi copy) unless the owner instructions say otherwise.\n"
        . "Write 4-6 FAQs about the content/level/exam fit only (delivery, payment and refund FAQs are added automatically).\n\n"
        . "JSON keys and what they mean:\n" . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n"
        . ($sample !== '' ? "DOCUMENT TEXT (sampled pages):\n" . $sample : "The PDF has no selectable text (it is probably scanned or image-based). Base the copy on the file name, page count and owner instructions, keep claims general and do not invent specific chapter names — return an empty contents array.");

    $r = ai_complete([['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]], ['json' => true, 'max_tokens' => 2200, 'temperature' => 0.6, 'timeout' => 70]);
    $d = ai_json_decode($r['text']);
    if (!$d) {
        throw new RuntimeException('The AI reply was not valid JSON. Please try again.');
    }
    $str = static fn($v, int $max) => mb_substr(trim(preg_replace('/\s+/u', ' ', is_scalar($v) ? (string)$v : '') ?? ''), 0, $max);
    $list = static function ($v, int $maxItems, int $maxLen) use ($str): array {
        if (is_string($v)) {
            $v = preg_split('/\r?\n|;\s*/', $v) ?: [];
        }
        return array_values(array_filter(array_map(static fn($x) => $str(is_array($x) ? implode(' ', $x) : $x, $maxLen), array_slice(is_array($v) ? $v : [], 0, $maxItems)), static fn($x) => $x !== ''));
    };
    $desc = is_array($d['description'] ?? null) ? implode("\n\n", $d['description']) : (string)($d['description'] ?? '');
    $desc = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $desc)) ?? '');
    $faqs = [];
    foreach (array_slice(is_array($d['faqs'] ?? null) ? $d['faqs'] : [], 0, 8) as $f) {
        $q = $str($f['q'] ?? $f['question'] ?? '', 200);
        $a = $str($f['a'] ?? $f['answer'] ?? '', 500);
        if ($q !== '' && $a !== '') {
            $faqs[] = ['q' => $q, 'a' => $a];
        }
    }
    $usd = (float)preg_replace('/[^0-9.]/', '', (string)($d['suggested_price_usd'] ?? '0'));
    return [
        'title' => $str($d['title'] ?? '', 190),
        'subtitle' => $str($d['subtitle'] ?? '', 255),
        'category' => $str($d['category'] ?? '', 80),
        'tags' => implode(', ', $list($d['tags'] ?? [], 10, 40)),
        'description' => mb_substr($desc, 0, 6000),
        'highlights' => $list($d['highlights'] ?? [], 8, 160),
        'contents' => $list($d['contents'] ?? [], 30, 160),
        'audience' => $list($d['audience'] ?? [], 6, 160),
        'faqs' => $faqs,
        'seo_description' => $str($d['seo_description'] ?? '', 300),
        'suggested_price_inr' => max(0, (int)preg_replace('/\D/', '', (string)($d['suggested_price_inr'] ?? '0'))),
        'suggested_price_usd' => $usd > 0 ? round($usd, 2) : 0,
        'engine' => $r['engine'],
    ];
}

/** Standard FAQs shown on every product page (delivery, payment, refunds). */
function store_standard_faqs(array $p): array
{
    $limit = (int)setting('store_download_limit', '10');
    return [
        ['How do I get the PDF after paying?', 'Right after a successful payment you are taken to your receipt with a Download button, and the same download link is emailed to you.' . ($limit > 0 ? " You can re-download it up to {$limit} times." : '')],
        ['Can I read it on my phone?', 'Yes. It is a standard PDF that opens on any phone, tablet or laptop — and you can print it.'],
        ['Is the payment secure?', 'Payments are processed by Razorpay (UPI, cards, netbanking, wallets' . (product_has_usd($p) ? ', international cards in USD' : '') . '). Card and UPI details never touch this website.'],
        ['What if the file does not open?', 'Email ' . setting('contact_email') . ' with your order number and I will fix it or refund you. Because it is a digital download, other refunds are not offered once the file is downloaded.'],
        ['Can I share the notes?', 'The notes are for your personal use. Please do not re-upload or resell them — that keeps the price low for everyone.'],
    ];
}

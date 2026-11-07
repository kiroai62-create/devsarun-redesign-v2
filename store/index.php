<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

if (!store_enabled()) {
    http_response_code(404);
    require ROOT . '/404.php';
    exit;
}
$slug = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['slug'] ?? ''));

/* ======================================================================
 * PRODUCT PAGE
 * ==================================================================== */
if ($slug !== '') {
    $p = product_by_slug($slug);
    if (!$p) {
        http_response_code(404);
        require ROOT . '/404.php';
        exit;
    }
    if (!is_bot() && !admin()) {
        db_exec('UPDATE products SET views = views + 1 WHERE id = ?', [(int)$p['id']]);
        track_event('product_view', $p['slug']);
    }
    $currencies = product_currencies($p);
    $show = product_display_currency($p);
    $previews = product_previews($p);
    $free = array_values(array_filter($previews, static fn($i) => empty($i['locked'])));
    $cover = product_cover_url($p);
    $gallery = $previews;
    if (!empty($p['cover_image'])) {
        array_unshift($gallery, ['src' => $p['cover_image'], 'locked' => false, 'cover' => true]);
    }
    $highlights = json_list($p['highlights']);
    $contents = json_list($p['contents']);
    $audience = json_list($p['audience']);
    $faqs = array_merge(json_list($p['faqs']), array_map(static fn($f) => ['q' => $f[0], 'a' => $f[1]], store_standard_faqs($p)));
    $paras = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', (string)($p['description'] ?: $p['subtitle'])) ?: [])));
    $pages = (int)$p['page_count'];
    $lockedPages = max(0, $pages - count($free));
    $sizeMb = $p['file_size'] ? number_format((int)$p['file_size'] / 1048576, 1) . ' MB' : '';
    $buyable = razorpay_enabled() && $currencies && store_pdf_path($p['file_path']);
    $all = products_published();
    $related = array_values(array_filter($all, static fn($x) => $x['id'] !== $p['id'] && $x['category'] === $p['category']));
    $related = array_slice(array_merge($related, array_values(array_filter($all, static fn($x) => $x['id'] !== $p['id'] && $x['category'] !== $p['category']))), 0, 3);
    $stats = site_stats();
    $owner = setting('owner_name', 'Arun');
    $priceLabel = static fn(string $c) => money(product_amount($p, $c), $c);

    $offers = array_map(static fn($c) => ['@type' => 'Offer', 'price' => number_format(product_amount($p, $c) / 100, 2, '.', ''), 'priceCurrency' => $c, 'availability' => 'https://schema.org/InStock', 'url' => abs_url('store/' . $p['slug'] . '/'), 'itemCondition' => 'https://schema.org/NewCondition'], $currencies);
    $schema = ['@context' => 'https://schema.org', '@graph' => array_values(array_filter([
        array_filter(['@type' => 'Product', 'name' => $p['title'], 'description' => $p['seo_description'] ?: $p['subtitle'], 'image' => $cover ? [abs_url(product_cover_rel($p))] : null, 'sku' => 'NOTE-' . $p['id'], 'category' => $p['category'],
            'brand' => ['@type' => 'Brand', 'name' => setting('site_name', 'Devs Arun')], 'offers' => count($offers) === 1 ? $offers[0] : $offers]),
        ['@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Store', 'item' => abs_url('store/')], ['@type' => 'ListItem', 'position' => 2, 'name' => $p['title'], 'item' => abs_url('store/' . $p['slug'] . '/')]]],
        $faqs ? ['@type' => 'FAQPage', 'mainEntity' => array_map(static fn($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $faqs)] : null,
    ]))];

    page_script('https://checkout.razorpay.com/v1/checkout.js');
    page_script(asset('js/store.js'));
    page_start(['title' => $p['title'], 'description' => $p['seo_description'] ?: ($p['subtitle'] ?: mb_substr((string)$p['description'], 0, 155)), 'active' => 'store', 'schema' => $schema, 'body_class' => 'has-actionbar',
        'image' => $cover ? abs_url(product_cover_rel($p)) : null]);
    $trust = [
        ['download', 'Instant download', 'Get the PDF right after payment'],
        ['lock', 'Secure checkout', 'Razorpay · UPI, cards, netbanking'],
        ['phone', 'Any device', 'Phone, tablet, laptop or print'],
        ['mail', 'Link on email', 'Re-download any time'],
    ];
    $buyBtn = static fn(string $label, string $cls, string $where) => $buyable
        ? '<button type="button" class="' . $cls . '" data-buy data-track="buy_click" data-label="' . e($where . ':' . $p['slug']) . '">' . icon('lock', 'h-4 w-4') . ' ' . $label . '</button>'
        : '<a href="' . e(url('contact/')) . '" class="' . $cls . '">' . icon('mail', 'h-4 w-4') . ' Ask to buy</a>';
    ?>
    <div id="product" data-slug="<?= e($p['slug']) ?>" data-currencies="<?= e(implode(',', $currencies)) ?>" data-title="<?= e($p['title']) ?>"
         data-price-inr="<?= e(product_has_inr($p) ? $priceLabel('INR') : '') ?>" data-price-usd="<?= e(product_has_usd($p) ? $priceLabel('USD') : '') ?>"></div>

    <!-- HERO -->
    <section class="relative overflow-hidden pt-28 sm:pt-36">
      <div class="grid-bg pointer-events-none absolute inset-0"></div>
      <div class="pointer-events-none absolute -top-40 left-0 h-[28rem] w-[40rem] rounded-full bg-neo-green/[0.07] blur-3xl"></div>
      <div class="relative mx-auto max-w-6xl px-5 sm:px-6">
        <nav class="mb-6 flex items-center gap-2 overflow-x-auto whitespace-nowrap text-sm text-white/40" aria-label="Breadcrumb"><a href="<?= e(url('')) ?>" class="hover:text-white">Home</a><span>/</span><a href="<?= e(url('store/')) ?>" class="hover:text-white">Store</a><span>/</span><span class="truncate text-white/70"><?= e($p['title']) ?></span></nav>
        <div class="grid gap-8 lg:grid-cols-[1fr_1fr] lg:gap-12">
          <!-- Gallery -->
          <div>
            <div class="card relative overflow-hidden bg-gradient-to-br from-white/[0.06] to-white/[0.01] p-5 sm:p-8">
              <button type="button" class="group relative mx-auto block w-full max-w-[420px]" data-viewer-open="0" data-track="product_preview" data-label="<?= e($p['slug']) ?>:main" aria-label="Open the free preview">
                <span class="block aspect-[210/297] overflow-hidden rounded-xl bg-white shadow-2xl shadow-black/60 ring-1 ring-white/10 transition duration-500 group-hover:-translate-y-1">
                  <?php if ($cover): ?><img src="<?= e($cover) ?>" alt="<?= e($p['title']) ?> — first page" class="h-full w-full object-cover object-top" fetchpriority="high"><?php else: ?><span class="flex h-full items-center justify-center text-neutral-400"><?= icon('file', 'h-14 w-14') ?></span><?php endif; ?>
                </span>
                <?php if ($free): ?><span class="absolute bottom-4 left-1/2 inline-flex -translate-x-1/2 items-center gap-2 whitespace-nowrap rounded-full bg-neo-black/85 px-4 py-2 text-sm font-semibold text-white shadow-xl backdrop-blur"><?= icon('search', 'h-4 w-4 text-neo-green') ?> Read <?= count($free) ?> pages free</span><?php endif; ?>
              </button>
            </div>
            <?php if (count($gallery) > 1): ?>
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1" aria-label="Preview pages">
              <?php foreach ($gallery as $i => $img): ?>
                <button type="button" class="relative h-24 w-[68px] flex-none overflow-hidden rounded-lg border border-white/10 bg-white transition hover:border-neo-green/60" data-viewer-open="<?= $i ?>" aria-label="<?= !empty($img['locked']) ? 'Locked page' : 'Preview page ' . ($i + 1) ?>">
                  <img src="<?= e(url($img['src'])) ?>" alt="" loading="lazy" class="h-full w-full object-cover object-top">
                  <?php if (!empty($img['locked'])): ?><span class="absolute inset-0 flex items-center justify-center bg-black/45 text-white"><?= icon('lock', 'h-4 w-4') ?></span><?php endif; ?>
                </button>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- Buy box -->
          <div>
            <div class="flex flex-wrap items-center gap-2 font-mono text-[11px] uppercase tracking-widest text-white/45">
              <?php if ($p['category']): ?><span class="rounded-full border border-white/10 px-3 py-1"><?= e($p['category']) ?></span><?php endif; ?>
              <span class="rounded-full border border-white/10 px-3 py-1">PDF<?= $pages ? ' · ' . $pages . ' pages' : '' ?></span>
              <?php if ((int)$p['is_featured']): ?><span class="rounded-full bg-neo-green/15 px-3 py-1 text-neo-green">Bestseller</span><?php endif; ?>
            </div>
            <h1 class="mt-4 text-balance text-3xl font-extrabold leading-[1.1] tracking-tight sm:text-5xl"><?= e($p['title']) ?></h1>
            <?php if ($p['subtitle']): ?><p class="mt-4 text-lg leading-relaxed text-white/65"><?= e($p['subtitle']) ?></p><?php endif; ?>
            <?php if ((int)$p['sales_count'] >= 5): ?><p class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/[0.05] px-3.5 py-1.5 text-sm text-white/75">🔥 <strong><?= (int)$p['sales_count'] ?>+</strong> learners already bought these notes</p><?php endif; ?>

            <div class="card mt-6 p-5 sm:p-6" id="buy-main">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div><?= product_price_html($p, 'xl') ?></div>
                <?php if (count($currencies) > 1): ?>
                  <div class="inline-flex rounded-full border border-white/10 p-1 text-xs font-semibold" role="group" aria-label="Currency">
                    <button type="button" data-set-cur="INR" aria-pressed="<?= $show === 'INR' ? 'true' : 'false' ?>" class="cur-btn rounded-full px-3 py-1.5 <?= $show === 'INR' ? 'is-active' : '' ?>">₹ INR</button>
                    <button type="button" data-set-cur="USD" aria-pressed="<?= $show === 'USD' ? 'true' : 'false' ?>" class="cur-btn rounded-full px-3 py-1.5 <?= $show === 'USD' ? 'is-active' : '' ?>">$ USD</button>
                  </div>
                <?php endif; ?>
              </div>
              <p class="mt-2 text-xs text-white/45">One-time payment · instant PDF download · no subscription</p>
              <div class="mt-5 grid gap-2.5">
                <?= $buyBtn('Buy now — instant download', 'btn-primary w-full !py-4 text-base', 'hero') ?>
                <?php if ($free): ?><button type="button" class="btn-ghost w-full" data-viewer-open="0" data-track="product_preview" data-label="<?= e($p['slug']) ?>:button"><?= icon('search', 'h-4 w-4') ?> Preview <?= count($free) ?> pages free</button><?php endif; ?>
              </div>
              <ul class="mt-5 grid grid-cols-2 gap-3">
                <?php foreach ($trust as [$ic, $t, $d]): ?><li class="flex gap-2.5"><span class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-xl bg-neo-green/10 text-neo-green"><?= icon($ic, 'h-4 w-4') ?></span><span class="text-xs leading-snug"><span class="block font-semibold text-white"><?= e($t) ?></span><span class="text-white/45"><?= e($d) ?></span></span></li><?php endforeach; ?>
              </ul>
              <p class="mt-4 border-t border-white/[0.06] pt-4 text-center text-[11px] text-white/40">UPI · Cards · NetBanking · Wallets<?= in_array('USD', $currencies, true) ? ' · International cards (USD)' : '' ?> — powered by Razorpay</p>
            </div>

            <?php if ($highlights): ?>
            <ul class="mt-6 space-y-2.5 text-[15px] text-white/75"><?php foreach (array_slice($highlights, 0, 4) as $h): ?><li class="flex gap-2.5"><span class="mt-0.5 text-neo-green"><?= icon('check', 'h-5 w-5') ?></span><?= e($h) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <button type="button" class="mt-6 inline-flex items-center gap-2 text-sm text-white/55 hover:text-neo-green" data-open-chat="<?= e('Tell me about the "' . $p['title'] . '" notes — what is inside and is it right for me?') ?>"><?= icon('bot', 'h-4 w-4') ?> Not sure? Ask the AI assistant about these notes</button>
          </div>
        </div>
      </div>
    </section>

    <?php if ($highlights): ?>
    <section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
      <?php section_head('what you get', 'Why these notes <span class="text-neo-green">help</span>', '', 'left'); ?>
      <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($highlights as $i => $h): ?><div class="card reveal flex gap-4 p-6"><span class="flex h-10 w-10 flex-none items-center justify-center rounded-2xl bg-neo-green/10 text-neo-green"><?= icon(['zap', 'check', 'star', 'chart', 'clock', 'shield', 'heart', 'sparkles'][$i % 8]) ?></span><p class="text-[15px] leading-relaxed text-white/75"><?= e($h) ?></p></div><?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
      <div class="grid gap-10 lg:grid-cols-[1.3fr_.7fr]">
        <div>
          <p class="kicker">// about these notes</p>
          <h2 class="mt-3 text-3xl font-bold tracking-tight">What this PDF <span class="text-neo-green">covers</span></h2>
          <div class="mt-6 space-y-5 text-[17px] leading-relaxed text-white/65"><?php foreach ($paras as $para): ?><p><?= nl2br(e($para)) ?></p><?php endforeach; ?></div>
          <?php if ($contents): ?>
            <h3 class="mt-10 text-xl font-semibold">What's inside</h3>
            <ol class="mt-5 grid gap-2.5 sm:grid-cols-2">
              <?php foreach ($contents as $i => $c): ?><li class="flex gap-3 rounded-2xl border border-white/[0.06] bg-white/[0.02] px-4 py-3 text-sm text-white/75"><span class="font-mono text-xs text-neo-green"><?= sprintf('%02d', $i + 1) ?></span><?= e($c) ?></li><?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </div>
        <div class="space-y-4">
          <?php if ($audience): ?>
          <div class="card p-6"><h3 class="font-semibold">Perfect for</h3><ul class="mt-4 space-y-3 text-sm text-white/70"><?php foreach ($audience as $a): ?><li class="flex gap-3"><span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-neo-green/10 text-neo-green"><?= icon('users', 'h-3.5 w-3.5') ?></span><?= e($a) ?></li><?php endforeach; ?></ul></div>
          <?php endif; ?>
          <div class="card p-6">
            <h3 class="font-semibold">Quick facts</h3>
            <dl class="mt-4 divide-y divide-white/[0.06] text-sm">
              <?php foreach (array_filter(['Format' => 'PDF (digital download)', 'Pages' => $pages ? (string)$pages : '', 'File size' => $sizeMb, 'Free preview' => $free ? count($free) . ' pages' : '', 'Updated' => date('M Y', strtotime($p['updated_at'] ?: $p['created_at'])), 'Delivery' => 'Instant, plus email link']) as $k => $val): ?>
                <div class="flex justify-between gap-4 py-2.5"><dt class="text-white/45"><?= e($k) ?></dt><dd class="text-right font-medium"><?= e($val) ?></dd></div>
              <?php endforeach; ?>
            </dl>
          </div>
        </div>
      </div>
    </section>

    <?php if ($previews): ?>
    <!-- LOOK INSIDE -->
    <section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
      <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end">
        <?php section_head('look inside', 'Read the first pages <span class="text-neo-green">before you buy</span>', 'Real pages from the PDF — what you see is what you get.', 'left'); ?>
        <button type="button" class="btn-ghost btn-sm flex-none" data-viewer-open="0" data-track="product_preview" data-label="<?= e($p['slug']) ?>:strip"><?= icon('search', 'h-4 w-4') ?> Open full preview</button>
      </div>
      <div class="mt-10 flex snap-x gap-4 overflow-x-auto pb-4">
        <?php foreach ($previews as $i => $img): $gi = $i + (!empty($p['cover_image']) ? 1 : 0); ?>
          <button type="button" class="group relative w-[240px] flex-none snap-start overflow-hidden rounded-2xl bg-white shadow-xl shadow-black/40 ring-1 ring-white/10 sm:w-[280px]" data-viewer-open="<?= $gi ?>" aria-label="<?= !empty($img['locked']) ? 'Locked page — buy to unlock' : 'Open page ' . ($i + 1) ?>">
            <img src="<?= e(url($img['src'])) ?>" alt="" loading="lazy" class="aspect-[210/297] w-full object-cover object-top transition duration-500 group-hover:scale-[1.02]">
            <?php if (!empty($img['locked'])): ?>
              <span class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-neo-black/55 p-4 text-center text-white backdrop-blur-[2px]"><span class="flex h-12 w-12 items-center justify-center rounded-full bg-neo-green text-neo-black"><?= icon('lock', 'h-5 w-5') ?></span><span class="text-sm font-semibold">+<?= $lockedPages ?: 'more' ?> pages</span><span class="text-xs text-white/70">Unlock with purchase</span></span>
            <?php else: ?>
              <span class="absolute left-3 top-3 rounded-full bg-neo-black/80 px-2.5 py-1 font-mono text-[10px] text-white">p. <?= $i + 1 ?></span>
            <?php endif; ?>
          </button>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- AUTHOR -->
    <section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
      <div class="card grid items-center gap-6 p-7 sm:grid-cols-[auto_1fr_auto] sm:p-9">
        <img src="<?= e(brand_logo_url()) ?>" alt="" class="h-16 w-16 rounded-2xl">
        <div>
          <p class="kicker">// made by</p>
          <h2 class="mt-2 text-2xl font-bold"><?= e($owner) ?> — <?= e(setting('site_name', 'Devs Arun')) ?></h2>
          <p class="mt-2 max-w-2xl text-sm leading-relaxed text-white/60">Full-stack developer from <?= e(setting('location', 'India')) ?> with <?= (int)$stats['clients'] ?>+ live client websites, <?= (int)$stats['apps'] ?> store-published apps and <?= (int)$stats['extensions'] ?> Chrome extensions. These notes are written the way I wish someone had explained it to me — clear, structured and exam-focused.</p>
        </div>
        <a href="<?= e(url('about/')) ?>" class="btn-ghost btn-sm">About me</a>
      </div>
    </section>

    <!-- FAQ -->
    <section class="mx-auto mt-24 max-w-3xl px-5 sm:px-6">
      <?php section_head('faq', 'Questions, <span class="text-neo-green">answered</span>'); ?>
      <div class="mt-10 space-y-3">
        <?php foreach ($faqs as $f): ?>
          <details class="card group px-6 py-5 [&_summary::-webkit-details-marker]:hidden"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium"><?= e($f['q'] ?? '') ?><span class="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-white/10 text-neo-green transition group-open:rotate-45">+</span></summary><p class="mt-3 text-sm leading-relaxed text-white/60"><?= e($f['a'] ?? '') ?></p></details>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- FINAL CTA -->
    <section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
      <div class="relative overflow-hidden rounded-[32px] border border-neo-green/20 bg-gradient-to-br from-neo-green/[0.10] via-white/[0.02] to-transparent px-6 py-12 text-center sm:px-12">
        <div class="pointer-events-none absolute -top-24 left-1/2 h-64 w-[36rem] -translate-x-1/2 rounded-full bg-neo-green/10 blur-3xl"></div>
        <h2 class="relative mx-auto max-w-2xl text-3xl font-bold tracking-tight sm:text-4xl">Get <span class="text-neo-green"><?= e($p['title']) ?></span> now</h2>
        <div class="relative mt-5 flex justify-center"><?= product_price_html($p, 'lg') ?></div>
        <div class="relative mx-auto mt-7 flex max-w-md flex-col gap-3 sm:flex-row sm:justify-center">
          <?= $buyBtn('Buy & download', 'btn-primary', 'final') ?>
          <?php if ($free): ?><button type="button" class="btn-ghost" data-viewer-open="0">Preview first</button><?php endif; ?>
        </div>
        <p class="relative mt-5 text-xs text-white/45">Secure Razorpay payment · instant download · file problem? I'll fix it or refund you.</p>
      </div>
    </section>

    <?php if ($related): ?>
    <section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
      <?php section_head('more notes', 'You may also <span class="text-neo-green">like</span>', '', 'left'); ?>
      <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($related as $r) product_card($r); ?></div>
    </section>
    <?php endif; ?>

    <!-- Desktop sticky bar -->
    <div id="sticky-buy" class="pointer-events-none fixed inset-x-0 top-[84px] z-40 hidden -translate-y-4 opacity-0 transition duration-300 md:block">
      <div class="mx-auto max-w-6xl px-6">
        <div class="pointer-events-auto flex items-center justify-between gap-4 rounded-full border border-white/10 bg-neo-black/90 py-2 pl-5 pr-2 shadow-2xl backdrop-blur-xl">
          <p class="truncate text-sm font-semibold"><?= e($p['title']) ?></p>
          <div class="flex flex-none items-center gap-3"><div class="[&_.text-xl]:text-lg"><?= product_price_html($p, 'md') ?></div><?= $buyBtn('Buy now', 'btn-primary btn-sm', 'sticky') ?></div>
        </div>
      </div>
    </div>
    <!-- Mobile action bar -->
    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-neo-black/95 p-3 backdrop-blur-xl md:hidden">
      <div class="flex items-center gap-3">
        <div class="min-w-0 flex-1 [&_.text-xl]:text-lg"><?= product_price_html($p, 'md') ?></div>
        <?= $buyBtn('Buy now', 'btn-primary !px-5 !py-3', 'mobilebar') ?>
      </div>
    </div>

    <!-- Preview viewer -->
    <div id="pv-modal" class="fixed inset-0 z-[85] hidden" role="dialog" aria-modal="true" aria-label="Free preview">
      <div class="absolute inset-0 bg-black/90 backdrop-blur-sm" data-pv-close></div>
      <div class="absolute inset-x-0 top-0 z-10 flex items-center justify-between gap-3 border-b border-white/10 bg-neo-black/90 px-4 py-3 backdrop-blur sm:px-6">
        <p class="truncate text-sm"><span class="font-semibold"><?= e($p['title']) ?></span> <span class="text-white/45">· free preview</span></p>
        <div class="flex flex-none items-center gap-2"><?= $buyBtn('Buy', 'btn-primary btn-sm', 'viewer_top') ?><button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-white/70 hover:bg-white/10" data-pv-close aria-label="Close preview"><?= icon('x', 'h-5 w-5') ?></button></div>
      </div>
      <div id="pv-scroll" class="relative h-full overflow-y-auto px-3 pb-16 pt-20 sm:px-6">
        <div class="mx-auto grid max-w-3xl gap-5">
          <?php foreach ($gallery as $i => $img): ?>
            <figure class="relative overflow-hidden rounded-xl bg-white shadow-2xl" id="pv-<?= $i ?>">
              <img src="<?= e(url($img['src'])) ?>" alt="<?= !empty($img['locked']) ? 'Locked page' : 'Preview page ' . ($i + 1) ?>" loading="lazy" class="w-full select-none" draggable="false">
              <?php if (!empty($img['locked'])): ?>
                <figcaption class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-neo-black/60 p-6 text-center text-white">
                  <span class="flex h-14 w-14 items-center justify-center rounded-full bg-neo-green text-neo-black"><?= icon('lock', 'h-6 w-6') ?></span>
                  <span class="text-xl font-bold">Unlock all <?= $pages ?: '' ?> pages</span>
                  <span class="max-w-sm text-sm text-white/75">You've seen the free preview. Get the complete PDF instantly.</span>
                  <?= $buyBtn('Buy now', 'btn-primary', 'viewer_lock') ?>
                </figcaption>
              <?php endif; ?>
            </figure>
          <?php endforeach; ?>
          <?php if (!array_filter($gallery, static fn($i) => !empty($i['locked']))): ?>
            <div class="rounded-2xl border border-neo-green/25 bg-neo-black p-6 text-center"><p class="text-lg font-bold">That's the free preview<?= $lockedPages ? ' — ' . $lockedPages . ' more pages inside' : '' ?></p><div class="mt-4"><?= $buyBtn('Get the full PDF', 'btn-primary', 'viewer_end') ?></div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Buy modal -->
    <?php if ($buyable): ?>
    <div id="buy-modal" class="fixed inset-0 z-[90] hidden" role="dialog" aria-modal="true" aria-labelledby="buy-title">
      <div class="absolute inset-0 bg-black/75 backdrop-blur-sm" data-buy-close></div>
      <div class="relative mx-auto flex min-h-full max-w-lg items-end justify-center sm:items-center sm:p-6">
        <div class="relative w-full rounded-t-[28px] border border-white/10 bg-[#0d0d0d] p-6 shadow-2xl sm:rounded-[28px] sm:p-8">
          <button type="button" class="absolute right-4 top-4 inline-flex h-10 w-10 items-center justify-center rounded-full text-white/60 hover:bg-white/5" data-buy-close aria-label="Close"><?= icon('x', 'h-5 w-5') ?></button>
          <div class="flex items-center gap-4 pr-10">
            <?php if ($cover): ?><img src="<?= e($cover) ?>" alt="" class="h-20 w-14 flex-none rounded-md object-cover object-top ring-1 ring-white/10"><?php endif; ?>
            <div class="min-w-0"><p id="buy-title" class="font-semibold leading-snug"><?= e($p['title']) ?></p><p class="mt-1 text-sm text-white/50">PDF<?= $pages ? ' · ' . $pages . ' pages' : '' ?> · instant download</p></div>
          </div>
          <form id="buy-form" class="mt-6 grid gap-4" novalidate>
            <div><label class="label" for="b-name">Your name</label><input id="b-name" class="field" name="name" required maxlength="120" autocomplete="name"></div>
            <div><label class="label" for="b-email">Email <span class="text-white/35">(the download link is sent here)</span></label><input id="b-email" class="field" type="email" name="email" required maxlength="190" autocomplete="email" inputmode="email"></div>
            <div><label class="label" for="b-phone">Phone / WhatsApp <span class="text-white/35">(optional)</span></label><input id="b-phone" class="field" type="tel" name="phone" maxlength="20" autocomplete="tel"></div>
            <p id="buy-error" class="hidden rounded-2xl border border-red-400/30 bg-red-400/10 px-4 py-3 text-sm text-red-200" role="alert"></p>
            <button type="submit" class="btn-primary w-full !py-4 text-base"><?= icon('lock', 'h-4 w-4') ?> <span id="buy-pay-label">Pay <?= e($priceLabel($show)) ?> securely</span></button>
            <p class="text-center text-xs text-white/40">By paying you agree to the <a class="text-neo-green hover:underline" href="<?= e(url('terms/')) ?>" target="_blank">terms</a> &amp; <a class="text-neo-green hover:underline" href="<?= e(url('refund-policy/')) ?>" target="_blank">refund policy</a>. Payments by Razorpay.</p>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <?php
    page_end();
    exit;
}

/* ======================================================================
 * STORE LISTING
 * ==================================================================== */
$products = products_published();
$cats = [];
foreach ($products as $x) {
    if ($x['category']) {
        $cats[slugify($x['category'])] = $x['category'];
    }
}
$hasUsd = (bool)array_filter($products, 'product_has_usd');
$hasInr = (bool)array_filter($products, 'product_has_inr');
$title = setting('store_title', 'Notes & study resources');
page_script(asset('js/store.js'));
page_start(['title' => $title, 'description' => setting('store_intro') ?: 'PDF notes by ' . setting('owner_name', 'Arun') . ' — free preview pages, secure Razorpay payment, instant download.', 'active' => 'store',
    'schema' => $products ? ['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => array_map(static fn($x, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => abs_url('store/' . $x['slug'] . '/'), 'name' => $x['title']], $products, array_keys($products))] : null]);
?>
<section class="relative overflow-hidden pt-32 sm:pt-40">
  <div class="grid-bg pointer-events-none absolute inset-0"></div>
  <div class="pointer-events-none absolute -top-40 left-1/2 h-[28rem] w-[56rem] -translate-x-1/2 rounded-full bg-neo-green/[0.08] blur-3xl"></div>
  <div class="relative mx-auto max-w-6xl px-5 text-center sm:px-6">
    <p class="kicker">// store</p>
    <h1 class="mx-auto mt-4 max-w-3xl text-balance text-4xl font-extrabold tracking-tight sm:text-6xl"><?= e($title) ?></h1>
    <p class="mx-auto mt-6 max-w-2xl text-lg text-white/60"><?= e(setting('store_intro')) ?></p>
    <div class="mt-8 flex flex-wrap justify-center gap-x-6 gap-y-3 text-sm text-white/55">
      <?php foreach (['Free preview pages', 'Instant PDF download', 'Secure Razorpay checkout', $hasUsd && $hasInr ? 'Pay in ₹ or $' : 'Works on any device'] as $t): ?><span class="flex items-center gap-2"><span class="text-neo-green"><?= icon('check', 'h-4 w-4') ?></span><?= e($t) ?></span><?php endforeach; ?>
    </div>
  </div>
</section>

<section class="mx-auto mt-14 max-w-6xl px-5 sm:px-6">
  <?php if ($products): ?>
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex flex-1 flex-wrap items-center gap-2" data-store-cats>
      <?php if (count($cats) > 1): ?><button type="button" class="chip is-active" data-cat-filter="">All <span class="text-white/35"><?= count($products) ?></span></button><?php foreach ($cats as $k => $label): ?><button type="button" class="chip" data-cat-filter="<?= e($k) ?>"><?= e($label) ?></button><?php endforeach; ?><?php endif; ?>
    </div>
    <div class="flex items-center gap-2">
      <label class="relative block sm:w-64"><span class="sr-only">Search notes</span><span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-white/35"><?= icon('search', 'h-4 w-4') ?></span><input type="search" class="field !py-2.5 pl-10" placeholder="Search notes…" data-store-search></label>
      <?php if ($hasUsd && $hasInr): ?><div class="inline-flex flex-none rounded-full border border-white/10 p-1 text-xs font-semibold" role="group" aria-label="Currency"><button type="button" data-set-cur="INR" class="cur-btn rounded-full px-3 py-1.5 <?= store_currency() === 'INR' ? 'is-active' : '' ?>">₹</button><button type="button" data-set-cur="USD" class="cur-btn rounded-full px-3 py-1.5 <?= store_currency() === 'USD' ? 'is-active' : '' ?>">$</button></div><?php endif; ?>
    </div>
  </div>
  <div id="product-grid" class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($products as $x) product_card($x); ?>
  </div>
  <p id="store-empty" class="mt-10 hidden text-center text-white/45">No notes match your search.</p>
  <?php else: ?>
  <div class="card mx-auto max-w-2xl p-10 text-center">
    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-neo-green/10 text-neo-green"><?= icon('file', 'h-7 w-7') ?></div>
    <h2 class="mt-5 text-2xl font-bold">New notes are on the way</h2>
    <p class="mt-3 text-white/60">The first PDFs are being prepared. Tell the AI assistant which topic you need — I'll prioritise it.</p>
    <button type="button" class="btn-primary mt-6" data-open-chat="I want notes on a topic — when will the store have them?"><?= icon('bot', 'h-4 w-4') ?> Request a topic</button>
  </div>
  <?php endif; ?>
</section>

<section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
  <div class="grid gap-4 md:grid-cols-3">
    <?php foreach ([['search', 'Preview before you pay', 'Every product shows real pages from the PDF, so you know exactly what you are buying.'], ['zap', 'Instant access', 'Pay with UPI, card or netbanking and download straight away — the link is emailed too.'], ['heart', 'Made by a developer who teaches', 'Structured, to-the-point notes written by ' . setting('owner_name', 'Arun') . ', not copy-pasted content.']] as [$ic, $t, $d]): ?>
      <div class="card p-6"><span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-neo-green/10 text-neo-green"><?= icon($ic) ?></span><h3 class="mt-5 font-semibold"><?= e($t) ?></h3><p class="mt-2 text-sm leading-relaxed text-white/55"><?= e($d) ?></p></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="mx-auto mt-24 max-w-3xl px-5 sm:px-6">
  <?php section_head('faq', 'Store <span class="text-neo-green">questions</span>'); ?>
  <div class="mt-10 space-y-3">
    <?php foreach (store_standard_faqs(['price_usd_cents' => $hasUsd ? 1 : 0]) as [$q, $a]): ?>
      <details class="card group px-6 py-5 [&_summary::-webkit-details-marker]:hidden"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium"><?= e($q) ?><span class="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-white/10 text-neo-green transition group-open:rotate-45">+</span></summary><p class="mt-3 text-sm leading-relaxed text-white/60"><?= e($a) ?></p></details>
    <?php endforeach; ?>
  </div>
</section>
<?php cta_band('Need custom notes, a website or an app?', 'I also build websites, apps and AI tools. Tell me what you need — every service price is negotiable.'); page_end();

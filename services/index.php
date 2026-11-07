<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$slug = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['slug'] ?? ''));

/* ======================================================================
 * SERVICE DETAIL
 * ==================================================================== */
if ($slug !== '') {
    $s = service_by_slug($slug);
    if (!$s) {
        http_response_code(404);
        require ROOT . '/404.php';
        exit;
    }
    track_event('service_view', $s['slug'], (int)$s['id']);
    $deliverables = json_list($s['deliverables']);
    $process = json_list($s['process']);
    $faqs = json_list($s['faqs']);
    $ideal = json_list($s['ideal_for']);
    $notIncluded = json_list($s['not_included']);
    $features = json_list($s['features']);
    $benefits = json_list($s['benefits'] ?? null);
    $packages = service_packages($s);
    $stack = json_list($s['tech_stack'] ?? null);
    $paras = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', (string)($s['overview'] ?: $s['description'] ?: $s['summary'])) ?: [])));
    $buyable = (int)$s['is_buyable'] && (int)$s['buy_price'] > 0;
    $related = array_slice(array_values(array_filter(services_active(), static fn($x) => $x['category'] === $s['category'] && $x['id'] !== $s['id'])), 0, 3);
    if (count($related) < 3) {
        $related = array_slice(array_merge($related, array_values(array_filter(services_active(), static fn($x) => (int)$x['is_featured'] && $x['id'] !== $s['id'] && !in_array($x, $related, true)))), 0, 3);
    }
    $brochure = url('brochure/' . $s['slug'] . '/');
    $short = preg_replace('/\s*[\/(].*$/', '', $s['title']);

    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => array_values(array_filter([
            ['@type' => 'Service', 'name' => $s['title'], 'description' => $s['summary'], 'serviceType' => $s['title'], 'areaServed' => 'Worldwide',
                'provider' => ['@type' => 'ProfessionalService', 'name' => setting('site_name'), 'url' => abs_url('')],
                'offers' => $s['price_from'] ? ['@type' => 'Offer', 'priceCurrency' => 'INR', 'price' => (int)$s['price_from'], 'description' => 'Starting price, negotiable'] : null],
            ['@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Services', 'item' => abs_url('services/')], ['@type' => 'ListItem', 'position' => 2, 'name' => $s['title'], 'item' => abs_url('services/' . $s['slug'] . '/')]]],
            $faqs ? ['@type' => 'FAQPage', 'mainEntity' => array_map(static fn($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $faqs)] : null,
        ])),
    ];
    $tabs = array_filter(['overview' => 'Overview', 'benefits' => $benefits ? 'Benefits' : null, 'packages' => $packages ? 'Packages' : null, 'deliverables' => $deliverables ? 'Deliverables' : null, 'process' => $process ? 'Process' : null, 'faq' => $faqs ? 'FAQ' : null, 'enquire' => 'Enquire']);

    page_start(['title' => $s['title'], 'description' => $s['summary'], 'active' => 'services', 'schema' => $schema, 'body_class' => 'has-actionbar']);
    ?>
    <!-- HERO -->
    <section class="relative overflow-hidden pt-32 sm:pt-40">
      <div class="grid-bg pointer-events-none absolute inset-0"></div>
      <div class="pointer-events-none absolute -top-40 right-0 h-[28rem] w-[40rem] rounded-full bg-neo-green/[0.07] blur-3xl"></div>
      <div class="relative mx-auto max-w-6xl px-5 sm:px-6">
        <nav class="mb-8 flex items-center gap-2 text-sm text-white/40" aria-label="Breadcrumb"><a href="<?= e(url('')) ?>" class="hover:text-white">Home</a><span>/</span><a href="<?= e(url('services/')) ?>" class="hover:text-white">Services</a><span>/</span><span class="text-white/70"><?= e($s['title']) ?></span></nav>
        <div class="grid gap-10 lg:grid-cols-[1.25fr_.75fr]">
          <div>
            <div class="flex items-center gap-3">
              <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-neo-green/10 text-neo-green"><?= icon($s['icon'], 'h-6 w-6') ?></span>
              <span class="rounded-full border border-white/10 px-3 py-1 font-mono text-[11px] uppercase tracking-widest text-white/50"><?= e(SERVICE_CATEGORIES[$s['category']] ?? 'Service') ?></span>
            </div>
            <h1 class="mt-6 text-balance text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-6xl"><?= e($s['title']) ?></h1>
            <p class="mt-4 text-xl text-neo-green/90"><?= e($s['tagline']) ?></p>
            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-white/60"><?= e($s['summary']) ?></p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
              <button type="button" class="btn-primary" <?= enquire_attrs($s, '', 'hero:' . $s['slug']) ?>><?= icon('chat', 'h-4 w-4') ?> Enquire now</button>
              <a href="<?= e($brochure) ?>" class="btn-ghost" data-track="brochure_view" data-label="<?= e($s['slug']) ?>"><?= icon('download', 'h-4 w-4') ?> Download brochure</a>
              <?php if ($buyable): ?><a href="<?= e(url('checkout/?service=' . $s['slug'])) ?>" class="btn-ghost" data-track="checkout_open" data-label="<?= e($s['slug']) ?>"><?= icon('lock', 'h-4 w-4') ?> Book online</a><?php endif; ?>
            </div>
            <dl class="mt-10 grid grid-cols-2 gap-px overflow-hidden rounded-3xl border border-white/[0.07] bg-white/[0.07] sm:grid-cols-4">
              <?php foreach ([[$s['price_from'] ? 'Starting from' : 'Pricing', price_text($s)], ['Timeline', $s['timeline'] ?: 'With quote'], ['Payment', 'Milestones · Razorpay'], ['Support', 'Post-launch included']] as [$k, $v]): ?>
                <div class="bg-neo-black px-5 py-4"><dt class="font-mono text-[10px] uppercase tracking-widest text-white/40"><?= e($k) ?></dt><dd class="mt-1.5 text-sm font-semibold text-white"><?= e($v) ?></dd></div>
              <?php endforeach; ?>
            </dl>
          </div>
          <aside class="card h-fit p-7 lg:sticky lg:top-28">
            <p class="font-mono text-[11px] uppercase tracking-widest text-white/40">At a glance</p>
            <p class="mt-3 text-4xl font-extrabold tracking-tight text-neo-green"><?= e(price_text($s)) ?></p>
            <?php if ($s['price_usd']): ?><p class="mt-1 text-sm text-white/50"><?= e($s['price_usd']) ?></p><?php endif; ?>
            <p class="mt-4 rounded-2xl bg-white/[0.04] px-4 py-3 text-sm text-white/60"><strong class="text-white">Every price is negotiable.</strong> The final quote depends on your scope — nothing is fixed.</p>
            <ul class="mt-5 space-y-2.5 text-sm text-white/70"><?php foreach ($features as $f): ?><li class="flex gap-2"><span class="mt-0.5 text-neo-green"><?= icon('check', 'h-4 w-4') ?></span><?= e($f) ?></li><?php endforeach; ?></ul>
            <div class="mt-6 grid gap-2.5">
              <button type="button" class="btn-primary" <?= enquire_attrs($s, '', 'sidebar:' . $s['slug']) ?>>Enquire about this service</button>
              <a href="<?= e(url('start-project/?service=' . $s['slug'])) ?>" class="btn-ghost" data-track="cta_click" data-label="service_brief:<?= e($s['slug']) ?>">Send a detailed brief</a>
            </div>
            <p class="mt-4 flex items-center justify-center gap-2 text-xs text-white/40"><?= icon('clock', 'h-3.5 w-3.5') ?> Reply within 24 hours</p>
          </aside>
        </div>
      </div>
    </section>

    <!-- SUB NAV -->
    <div class="sticky top-[84px] z-30 mt-16 hidden md:block">
      <div class="mx-auto max-w-6xl px-6">
        <nav class="flex items-center gap-1 overflow-x-auto rounded-full border border-white/[0.07] bg-neo-black/80 p-1.5 backdrop-blur-xl" aria-label="On this page">
          <?php foreach ($tabs as $id => $label): ?><a href="#<?= e($id) ?>" class="rounded-full px-4 py-2 text-sm text-white/60 transition hover:bg-white/[0.05] hover:text-white"><?= e($label) ?></a><?php endforeach; ?>
          <a href="<?= e($brochure) ?>" class="ml-auto inline-flex flex-none items-center gap-1.5 rounded-full px-4 py-2 text-sm text-neo-green hover:bg-neo-green/10" data-track="brochure_view" data-label="<?= e($s['slug']) ?>"><?= icon('download', 'h-4 w-4') ?> Brochure</a>
        </nav>
      </div>
    </div>

    <!-- OVERVIEW -->
    <section id="overview" class="mx-auto mt-16 max-w-6xl scroll-mt-40 px-5 sm:px-6">
      <div class="grid gap-10 lg:grid-cols-[1.3fr_.7fr]">
        <div>
          <p class="kicker">// overview</p>
          <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">What is <span class="text-neo-green"><?= e($short) ?></span>?</h2>
          <div class="mt-6 space-y-5 text-[17px] leading-relaxed text-white/65"><?php foreach ($paras as $p): ?><p><?= e($p) ?></p><?php endforeach; ?></div>
        </div>
        <div class="space-y-4">
          <?php if ($ideal): ?>
          <div class="card p-6"><h3 class="font-semibold">Ideal for</h3><ul class="mt-4 space-y-3 text-sm text-white/70"><?php foreach ($ideal as $i): ?><li class="flex gap-3"><span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-neo-green/10 text-neo-green"><?= icon('users', 'h-3.5 w-3.5') ?></span><?= e($i) ?></li><?php endforeach; ?></ul></div>
          <?php endif; ?>
          <?php if ($stack): ?>
          <div class="card p-6"><h3 class="font-semibold">Technology</h3><div class="mt-4 flex flex-wrap gap-2"><?php foreach ($stack as $t): ?><span class="rounded-full border border-white/10 bg-white/[0.03] px-3 py-1.5 text-xs text-white/70"><?= e($t) ?></span><?php endforeach; ?></div></div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <?php if ($benefits): ?>
    <!-- BENEFITS -->
    <section id="benefits" class="mx-auto mt-24 max-w-6xl scroll-mt-40 px-5 sm:px-6">
      <?php section_head('business impact', 'How it helps your <span class="text-neo-green">website & business</span>', 'The practical outcomes you can expect — explained without jargon.', 'left'); ?>
      <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($benefits as $i => $b): ?>
          <div class="card reveal p-6"><div class="flex items-center justify-between"><span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-neo-green/10 text-neo-green"><?= icon(['chart', 'zap', 'users', 'shield', 'globe', 'star'][$i % 6]) ?></span><span class="font-mono text-sm text-white/20"><?= sprintf('%02d', $i + 1) ?></span></div><h3 class="mt-5 font-semibold"><?= e($b['title'] ?? '') ?></h3><p class="mt-2 text-sm leading-relaxed text-white/55"><?= e($b['desc'] ?? '') ?></p></div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($packages): ?>
    <!-- PACKAGES -->
    <section id="packages" class="mx-auto mt-24 max-w-6xl scroll-mt-40 px-5 sm:px-6">
      <?php section_head('packages & pricing', 'Choose a <span class="text-neo-green">starting point</span>', 'Transparent starting prices. Mix, match or downsize — every package is negotiable and can be tailored to your budget.'); ?>
      <div class="mt-12 grid gap-4 <?= count($packages) >= 3 ? 'lg:grid-cols-3' : (count($packages) === 2 ? 'md:grid-cols-2' : 'mx-auto max-w-md') ?>">
        <?php foreach ($packages as $i => $p): $hl = count($packages) >= 3 && $i === 1; ?>
          <div class="card relative flex flex-col p-7 <?= $hl ? 'border-neo-green/40 bg-neo-green/[0.04] shadow-[0_30px_80px_-30px_rgba(74,222,128,0.25)]' : '' ?>">
            <?php if ($hl): ?><span class="absolute -top-3 left-7 rounded-full bg-neo-green px-3 py-1 font-mono text-[10px] font-bold uppercase tracking-wider text-neo-black">Most chosen</span><?php endif; ?>
            <p class="font-semibold"><?= e($p['name']) ?></p>
            <p class="mt-1 text-sm text-white/45"><?= e($p['note'] ?? '') ?></p>
            <p class="mt-5 text-3xl font-extrabold tracking-tight <?= $hl ? 'text-neo-green' : 'text-white' ?>"><?= e($p['price'] ?? '') ?></p>
            <p class="mt-1 text-xs text-white/35">starting price · negotiable</p>
            <ul class="mt-6 flex-1 space-y-2.5 text-sm text-white/70"><?php foreach ((array)($p['features'] ?? []) as $f): ?><li class="flex gap-2"><span class="mt-0.5 text-neo-green"><?= icon('check', 'h-4 w-4') ?></span><?= e($f) ?></li><?php endforeach; ?></ul>
            <div class="mt-7 grid gap-2">
              <button type="button" class="<?= $hl ? 'btn-primary' : 'btn-ghost' ?> btn-sm w-full" <?= enquire_attrs($s, $p['name'], 'package:' . $s['slug'] . ':' . $p['name']) ?>>Enquire about <?= e($p['name']) ?></button>
              <?php if ($buyable && $i === 0): ?><a href="<?= e(url('checkout/?service=' . $s['slug'])) ?>" class="text-center text-xs text-white/45 hover:text-neo-green" data-track="checkout_open" data-label="<?= e($s['slug']) ?>"><?= icon('lock', 'mr-1 inline h-3 w-3') ?>or book online · <?= e(inr((int)$s['buy_price'])) ?></a><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="mt-6 flex flex-col items-start justify-between gap-4 rounded-[28px] border border-dashed border-white/15 p-6 sm:flex-row sm:items-center">
        <p class="text-sm text-white/60"><strong class="text-white">Need something in between?</strong> Packages are only starting points. Tell me your budget and I'll shape the right scope — no pressure.</p>
        <button type="button" class="btn-ghost btn-sm flex-none" <?= enquire_attrs($s, '', 'custom_scope:' . $s['slug']) ?>>Request custom scope</button>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($deliverables): ?>
    <section id="deliverables" class="mx-auto mt-24 max-w-6xl scroll-mt-40 px-5 sm:px-6">
      <?php section_head('what you get', 'Deliverables, <span class="text-neo-green">clearly listed</span>', '', 'left'); ?>
      <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($deliverables as $i => $d): ?>
          <div class="card reveal p-6"><span class="font-mono text-sm text-neo-green"><?= sprintf('%02d', $i + 1) ?></span><h3 class="mt-3 font-semibold"><?= e($d['title'] ?? '') ?></h3><p class="mt-2 text-sm leading-relaxed text-white/55"><?= e($d['desc'] ?? '') ?></p></div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($process): ?>
    <section id="process" class="mx-auto mt-24 max-w-6xl scroll-mt-40 px-5 sm:px-6">
      <?php section_head('process', 'A simple, <span class="text-neo-green">reviewable</span> process', '', 'left'); ?>
      <div class="card mt-10 overflow-x-auto p-0">
        <table class="w-full min-w-[640px] text-left text-sm">
          <thead><tr class="border-b border-white/[0.06] font-mono text-[11px] uppercase tracking-wider text-white/40"><th class="px-6 py-4">Stage</th><th class="px-6 py-4">The work</th><th class="px-6 py-4">Your input</th><th class="px-6 py-4">You receive</th></tr></thead>
          <tbody><?php foreach ($process as $p): ?><tr class="border-b border-white/[0.04] last:border-0"><td class="px-6 py-4 font-semibold text-white"><?= e($p['stage'] ?? '') ?></td><td class="px-6 py-4 text-white/65"><?= e($p['work'] ?? '') ?></td><td class="px-6 py-4 text-white/65"><?= e($p['input'] ?? '') ?></td><td class="px-6 py-4 text-neo-green/90"><?= e($p['receive'] ?? '') ?></td></tr><?php endforeach; ?></tbody>
        </table>
      </div>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div class="card p-7"><p class="kicker">// commitment</p><h3 class="mt-3 text-xl font-semibold">What I commit to</h3><p class="mt-3 text-sm leading-relaxed text-white/60">Reviewable work at every milestone, agreed scope, timing and access, a transparent negotiable quote, and post-launch support. Extra work is always approved by you first — no surprise scope expansion.</p></div>
        <div class="card p-7"><p class="font-mono text-xs uppercase tracking-[.25em] text-white/40">// outside scope</p><h3 class="mt-3 text-xl font-semibold">Not included</h3><ul class="mt-3 space-y-2 text-sm text-white/60"><?php foreach ($notIncluded ?: ['Anything outside the agreed scope is quoted separately.'] as $n): ?><li class="flex gap-2"><span class="text-white/30">—</span><?= e($n) ?></li><?php endforeach; ?></ul></div>
      </div>
    </section>
    <?php endif; ?>

    <!-- BROCHURE -->
    <section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
      <div class="grid items-center gap-8 overflow-hidden rounded-[32px] border border-white/[0.07] bg-gradient-to-br from-violet-500/[0.08] via-white/[0.02] to-neo-green/[0.07] p-8 sm:p-10 lg:grid-cols-[1fr_.8fr]">
        <div>
          <p class="kicker">// brochure</p>
          <h2 class="mt-3 text-3xl font-bold tracking-tight">Download the <?= e($s['title']) ?> brochure</h2>
          <p class="mt-3 text-white/60">A presentation-style PDF with the overview, business benefits, packages, deliverables and process — easy to share with your partner or team.</p>
          <div class="mt-7 flex flex-col gap-3 sm:flex-row">
            <a href="<?= e($brochure) ?>" class="btn-primary" data-track="brochure_view" data-label="<?= e($s['slug']) ?>"><?= icon('download', 'h-4 w-4') ?> View & download PDF</a>
            <button type="button" class="btn-ghost" <?= enquire_attrs($s, '', 'brochure_band:' . $s['slug']) ?>>Enquire instead</button>
          </div>
        </div>
        <a href="<?= e($brochure) ?>" class="group relative block aspect-video overflow-hidden rounded-2xl bg-[#F7F7F7] p-6 shadow-2xl shadow-black/40 transition hover:-translate-y-1" aria-label="Open brochure">
          <span class="absolute -right-10 -top-10 h-36 w-36 rounded-full bg-gradient-to-br from-fuchsia-400 to-violet-300 opacity-70"></span>
          <span class="absolute -bottom-12 right-10 h-32 w-32 rounded-t-full bg-gradient-to-b from-violet-300 to-violet-100"></span>
          <span class="absolute left-6 top-4 h-10 w-10 rounded-full bg-gradient-to-br from-yellow-200 to-yellow-400"></span>
          <span class="relative mt-10 block font-mono text-[10px] tracking-[.2em] text-neutral-500"><?= e(strtoupper(setting('site_name', 'Devs Arun'))) ?> / BRIEFING</span>
          <span class="relative mt-2 block max-w-[70%] text-2xl font-extrabold leading-tight text-neutral-800"><?= e(strtoupper($s['title'])) ?></span>
          <span class="relative mt-2 block max-w-[65%] text-xs text-neutral-600"><?= e($s['tagline']) ?></span>
        </a>
      </div>
    </section>

    <?php if ($faqs): ?>
    <section id="faq" class="mx-auto mt-24 max-w-3xl scroll-mt-40 px-5 sm:px-6">
      <?php section_head('faq', 'Questions about <span class="text-neo-green">' . e($short) . '</span>'); ?>
      <div class="mt-10 space-y-3">
        <?php foreach ($faqs as $f): ?>
          <details class="card group px-6 py-5 [&_summary::-webkit-details-marker]:hidden"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium"><?= e($f['q'] ?? '') ?><span class="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-white/10 text-neo-green transition group-open:rotate-45">+</span></summary><p class="mt-3 text-sm leading-relaxed text-white/60"><?= e($f['a'] ?? '') ?></p></details>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- INLINE ENQUIRY -->
    <section id="enquire" class="mx-auto mt-24 max-w-6xl scroll-mt-40 px-5 sm:px-6">
      <div class="grid overflow-hidden rounded-[32px] border border-neo-green/15 lg:grid-cols-[.8fr_1.2fr]">
        <div class="relative bg-gradient-to-br from-neo-green/[0.12] via-white/[0.02] to-transparent p-8 sm:p-10">
          <p class="kicker">// enquire</p>
          <h2 class="mt-3 text-3xl font-bold tracking-tight">Talk to me about <?= e($short) ?></h2>
          <p class="mt-4 text-white/60">Get a free consultation and an honest, negotiable quote within 24 hours.</p>
          <ul class="mt-8 space-y-4 text-sm text-white/70">
            <li class="flex gap-3"><span class="text-neo-green"><?= icon('clock') ?></span>Personal reply within 24 hours</li>
            <li class="flex gap-3"><span class="text-neo-green"><?= icon('shield') ?></span>Your details stay private — no spam</li>
            <li class="flex gap-3"><span class="text-neo-green"><?= icon('heart') ?></span>Every price is negotiable</li>
            <li class="flex gap-3"><span class="text-neo-green"><?= icon('lock') ?></span>Secure Razorpay milestone payments</li>
          </ul>
          <?php if ($wa = preg_replace('/\D+/', '', setting('whatsapp'))): ?>
            <a href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Hi Arun, I want to know more about ' . $s['title'] . '.') ?>" target="_blank" rel="noopener" class="btn-ghost btn-sm mt-8" data-track="whatsapp_click" data-label="service:<?= e($s['slug']) ?>"><?= icon('whatsapp', 'h-4 w-4') ?> Prefer WhatsApp?</a>
          <?php endif; ?>
        </div>
        <form class="grid gap-4 bg-white/[0.02] p-8 sm:grid-cols-2 sm:p-10" data-ajax="lead/">
          <input type="hidden" name="source" value="enquiry">
          <input type="hidden" name="service" value="<?= e($s['slug']) ?>">
          <input type="text" name="website_hp" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
          <div data-hide-on-success><label class="label" for="ie-name">Full name</label><input id="ie-name" class="field" name="name" required maxlength="120" autocomplete="name"></div>
          <div data-hide-on-success><label class="label" for="ie-email">Email</label><input id="ie-email" class="field" type="email" name="email" required maxlength="190" autocomplete="email"></div>
          <div data-hide-on-success><label class="label" for="ie-phone">Phone / WhatsApp</label><input id="ie-phone" class="field" type="tel" name="phone" required maxlength="20" autocomplete="tel"></div>
          <div data-hide-on-success><label class="label" for="ie-pkg">Package</label><select id="ie-pkg" class="field" name="package"><option value="">Not sure yet</option><?php foreach ($packages as $p): ?><option value="<?= e($p['name'] . ' — ' . $p['price']) ?>"><?= e($p['name'] . ' — ' . $p['price']) ?></option><?php endforeach; ?></select></div>
          <div data-hide-on-success class="sm:col-span-2"><label class="label" for="ie-msg">Message (optional)</label><textarea id="ie-msg" class="field min-h-[110px]" name="message" maxlength="3000" placeholder="Your business, goal, timeline…"></textarea></div>
          <div data-hide-on-success class="sm:col-span-2"><button type="submit" class="btn-primary w-full sm:w-auto">Send enquiry <?= icon('send', 'h-4 w-4') ?></button></div>
          <div data-success class="hidden rounded-3xl border border-neo-green/30 bg-neo-green/10 p-6 text-center sm:col-span-2"><p class="text-lg font-semibold text-neo-green">Enquiry received ✅</p><p class="mt-1 text-sm text-white/70">Reference <strong data-ref></strong>. I'll reply within 24 hours.</p></div>
        </form>
      </div>
    </section>

    <?php if ($related): ?>
    <section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
      <?php section_head('related', 'You might also <span class="text-neo-green">need</span>', '', 'left'); ?>
      <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($related as $r) service_card($r); ?></div>
    </section>
    <?php endif; ?>

    <!-- Mobile action bar -->
    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-neo-black/90 p-3 backdrop-blur-xl md:hidden">
      <div class="grid grid-cols-[auto_1fr] gap-2">
        <a href="<?= e($brochure) ?>" class="inline-flex h-12 w-12 items-center justify-center rounded-full border border-white/10 text-white/70" aria-label="Brochure" data-track="brochure_view" data-label="<?= e($s['slug']) ?>"><?= icon('download', 'h-5 w-5') ?></a>
        <button type="button" class="btn-primary !py-3" <?= enquire_attrs($s, '', 'mobilebar:' . $s['slug']) ?>>Enquire · <?= e(price_text($s)) ?></button>
      </div>
    </div>
    <?php
    page_end();
    exit;
}

/* ======================================================================
 * SERVICES LISTING
 * ==================================================================== */
page_start(['title' => 'Services', 'description' => 'Websites, ecommerce, web apps, Android apps, AI agents, automation, Chrome extensions, SEO and AEO/GEO — explained in detail with packages, brochures and negotiable pricing.', 'active' => 'services']);
$all = services_active();
$byCat = [];
foreach ($all as $s) {
    $byCat[$s['category']][] = $s;
}
$catIntro = [
    'websites' => ['Websites & design', 'Your online storefront — from a one-day landing page to a complete store, designed to build trust and bring enquiries.'],
    'apps' => ['Apps & software', 'Custom web apps, Android apps and Chrome extensions — software built around how your business works.'],
    'ai' => ['AI & automation', 'Assistants and workflows that answer customers, capture leads and remove repetitive work — 24×7.'],
    'growth' => ['Growth & visibility', 'Get found on Google and in AI search (ChatGPT, Gemini, AI Overviews), then turn visitors into customers.'],
    'other' => ['Support & consulting', 'Keep everything running smoothly, explore new ideas, or start with a free consultation.'],
];
$guide = [
    ['I need to get online quickly', 'landing-page'], ['I want a complete company website', 'business-website'], ['I want to sell products online', 'ecommerce-store'],
    ['I need software for my operations', 'web-app'], ['I want an app on Play Store', 'android-app'], ['I get too many repeat questions', 'ai-agent'],
    ['I want to appear in ChatGPT / AI search', 'aeo-geo-starter'], ['I want more Google traffic', 'seo'], ['My website looks old', 'website-redesign'],
];
$slugs = array_column($all, null, 'slug');
?>
<section class="relative overflow-hidden pt-32 sm:pt-40">
  <div class="grid-bg pointer-events-none absolute inset-0"></div>
  <div class="pointer-events-none absolute -top-40 left-1/2 h-[28rem] w-[56rem] -translate-x-1/2 rounded-full bg-neo-green/[0.07] blur-3xl"></div>
  <div class="relative mx-auto max-w-6xl px-5 text-center sm:px-6">
    <p class="kicker">// services</p>
    <h1 class="mx-auto mt-4 max-w-3xl text-balance text-4xl font-extrabold tracking-tight sm:text-6xl">Everything your business needs to <span class="text-neo-green">grow online</span></h1>
    <p class="mx-auto mt-6 max-w-2xl text-lg text-white/60">Each service is explained in plain language — what it is, how it helps your business, packages, deliverables and process. Download any brochure or enquire in one click. Every price is negotiable.</p>
    <div class="mt-8 flex flex-wrap justify-center gap-2">
      <?php foreach ($catIntro as $k => [$t]): if (!empty($byCat[$k])): ?><a href="#cat-<?= e($k) ?>" class="chip"><?= e($t) ?> <span class="text-white/35"><?= count($byCat[$k]) ?></span></a><?php endif; endforeach; ?>
      <a href="#brochures" class="chip"><?= icon('download', 'h-3.5 w-3.5') ?> Brochure library</a>
    </div>
  </div>
</section>

<!-- Guide -->
<section class="mx-auto mt-16 max-w-6xl px-5 sm:px-6">
  <div class="card p-6 sm:p-8">
    <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end"><div><p class="kicker">// quick guide</p><h2 class="mt-2 text-2xl font-bold tracking-tight">Not sure where to start?</h2></div><p class="text-sm text-white/45">Pick the sentence that sounds like you.</p></div>
    <div class="mt-6 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($guide as [$need, $sl]): if (!isset($slugs[$sl])) continue; ?>
        <a href="<?= e(url('services/' . $sl . '/')) ?>" class="group flex items-center justify-between gap-3 rounded-2xl border border-white/[0.06] px-4 py-3.5 text-sm transition hover:border-neo-green/30 hover:bg-white/[0.03]"><span><span class="block text-white/80">“<?= e($need) ?>”</span><span class="mt-0.5 block text-xs text-neo-green"><?= e($slugs[$sl]['title']) ?></span></span><span class="text-white/30 transition group-hover:translate-x-0.5 group-hover:text-neo-green"><?= icon('arrow', 'h-4 w-4') ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php foreach ($catIntro as $k => [$t, $d]): if (empty($byCat[$k])) continue; ?>
<section id="cat-<?= e($k) ?>" class="mx-auto mt-24 max-w-6xl scroll-mt-28 px-5 sm:px-6">
  <div class="flex flex-col justify-between gap-4 border-b border-white/[0.06] pb-6 sm:flex-row sm:items-end">
    <div class="max-w-2xl"><p class="kicker">// <?= e(strtolower($t)) ?></p><h2 class="mt-3 text-3xl font-bold tracking-tight"><?= e($t) ?></h2><p class="mt-3 text-white/55"><?= e($d) ?></p></div>
    <p class="font-mono text-xs text-white/30"><?= count($byCat[$k]) ?> service<?= count($byCat[$k]) === 1 ? '' : 's' ?></p>
  </div>
  <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"><?php foreach ($byCat[$k] as $s) service_card($s); ?></div>
</section>
<?php endforeach; ?>

<!-- Brochure library -->
<section id="brochures" class="mx-auto mt-24 max-w-6xl scroll-mt-28 px-5 sm:px-6">
  <?php section_head('brochure library', 'Download any <span class="text-neo-green">brochure</span>', 'Presentation-style PDFs you can save, print or share with your team.'); ?>
  <div class="card mt-10 divide-y divide-white/[0.05] p-0">
    <?php foreach ($all as $s): ?>
      <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
        <div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/[0.04] text-neo-green"><?= icon('file', 'h-5 w-5') ?></span><div><p class="font-medium"><?= e($s['title']) ?> — brochure</p><p class="text-xs text-white/40">PDF · <?= e(price_text($s)) ?> · negotiable</p></div></div>
        <div class="flex gap-2"><button type="button" class="a-btn-ghost" <?= enquire_attrs($s, '', 'library:' . $s['slug']) ?>>Enquire</button><a href="<?= e(url('brochure/' . $s['slug'] . '/')) ?>" class="a-btn" data-track="brochure_view" data-label="<?= e($s['slug']) ?>"><?= icon('download', 'h-4 w-4') ?> Download</a></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php cta_band('Not sure which service fits?', 'Tell me about your business and goal. I will suggest the right-size scope for your budget — free.'); page_end();

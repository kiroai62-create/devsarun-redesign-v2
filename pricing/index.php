<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$all = services_active();
page_start(['title' => 'Pricing', 'description' => 'Transparent, negotiable pricing for landing pages, websites, ecommerce, web apps, Android apps, Chrome extensions, AI agents and AEO/GEO. Starting from ₹3,500.', 'active' => 'pricing']);
?>
<section class="relative overflow-hidden pt-32 sm:pt-40">
  <div class="grid-bg pointer-events-none absolute inset-0"></div>
  <div class="relative mx-auto max-w-6xl px-5 text-center sm:px-6">
    <p class="kicker">// pricing</p>
    <h1 class="mx-auto mt-4 max-w-3xl text-balance text-4xl font-extrabold tracking-tight sm:text-6xl">Simple, honest pricing <span class="text-neo-green">for serious work</span></h1>
    <p class="mx-auto mt-6 max-w-2xl text-lg text-white/60">Every price below is a starting point. The final quote depends on your exact scope — and <strong class="text-white">all pricing is negotiable</strong>. 🤝</p>
    <div class="mt-8 flex flex-wrap justify-center gap-2">
      <?php foreach (['⚡ Fast delivery', '💬 Free consultation', '🔧 Post-delivery support', '🇮🇳 INR pricing', '🌍 USD quotes available'] as $b): ?><span class="rounded-full border border-white/10 bg-white/[0.03] px-4 py-2 text-sm text-white/70"><?= e($b) ?></span><?php endforeach; ?>
    </div>
  </div>
</section>

<section class="mx-auto mt-16 max-w-6xl px-5 sm:px-6">
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($all as $s): $features = json_list($s['features']); ?>
      <div class="card relative flex flex-col p-7 <?= $s['slug'] === 'business-website' ? 'border-neo-green/40 bg-neo-green/[0.04]' : '' ?>">
        <?php if ($s['slug'] === 'business-website'): ?><span class="absolute -top-3 left-7 rounded-full bg-neo-green px-3 py-1 font-mono text-[10px] font-bold uppercase tracking-wider text-neo-black">Most popular</span><?php endif; ?>
        <div class="flex items-center gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-neo-green/10 text-neo-green"><?= icon($s['icon']) ?></span><div><h3 class="font-semibold"><?= e($s['title']) ?></h3><p class="text-xs text-white/45"><?= e($s['tagline']) ?></p></div></div>
        <p class="mt-6 font-mono text-[10px] uppercase tracking-widest text-white/40"><?= $s['price_from'] ? ($s['price_to'] ? 'Range' : 'Starting from') : 'Quote' ?></p>
        <p class="mt-1 text-3xl font-extrabold tracking-tight text-neo-green"><?= e(price_text($s)) ?></p>
        <p class="mt-1 text-xs text-white/40"><?= $s['price_usd'] ? e($s['price_usd']) . ' · ' : '' ?>depends on scope · negotiable</p>
        <ul class="mt-6 flex-1 space-y-2.5 text-sm text-white/65">
          <?php foreach ($features as $f): ?><li class="flex gap-2"><span class="mt-0.5 text-neo-green"><?= icon('check', 'h-4 w-4') ?></span><?= e($f) ?></li><?php endforeach; ?>
        </ul>
        <div class="mt-7 grid grid-cols-2 gap-2">
          <button type="button" class="<?= $s['slug'] === 'business-website' ? 'btn-primary' : 'btn-ghost' ?> btn-sm col-span-2" <?= enquire_attrs($s, '', 'pricing:' . $s['slug']) ?>>Enquire now</button>
          <a href="<?= e(url('services/' . $s['slug'] . '/')) ?>" class="rounded-full py-2 text-center text-xs text-white/50 hover:text-white">Details</a>
          <a href="<?= e(url('brochure/' . $s['slug'] . '/')) ?>" class="rounded-full py-2 text-center text-xs text-white/50 hover:text-white">Brochure PDF</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-6 flex flex-col items-start justify-between gap-5 rounded-[28px] border border-dashed border-neo-green/30 bg-white/[0.02] p-7 sm:flex-row sm:items-center">
    <div><h3 class="text-lg font-semibold">💬 Tight budget? <span class="text-neo-green">No problem.</span></h3><p class="mt-1 max-w-2xl text-sm text-white/60">There is a smart scope for every budget — if a full website doesn't fit, start with a killer landing page. Honest advice, always. Every price here is negotiable.</p></div>
    <a href="<?= e(url('start-project/')) ?>" class="btn-primary flex-none">Let's talk</a>
  </div>
</section>

<section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
  <div class="grid gap-4 md:grid-cols-3">
    <?php foreach ([['Quality over cheap', 'I never compromise on quality — pricing is fair, not rock-bottom.'], ['Transparent quotes', 'The quote you get is the price you pay. No hidden costs added midway.'], ['Negotiable, always', 'Genuine budget constraint? Let\'s talk and find the best solution.']] as [$t, $d]): ?>
      <div class="card border-l-2 border-l-neo-green p-6"><h3 class="font-semibold"><?= e($t) ?></h3><p class="mt-2 text-sm text-white/60"><?= e($d) ?></p></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="mx-auto mt-24 max-w-3xl px-5 sm:px-6">
  <?php section_head('faq', 'Pricing <span class="text-neo-green">questions</span>'); ?>
  <div class="mt-10 space-y-3">
    <?php foreach ([['Are the prices fixed?', 'No — all prices are “starting from” or a range, and every price is negotiable.'], ['When do I get an exact quote?', 'After a short requirement call or your project brief — usually within 24 hours, in writing.'], ['How does payment work?', 'Milestone based: an advance to start and the rest on delivery. Pay online securely via Razorpay — UPI, cards or netbanking.'], ['Can I pay in USD?', 'International clients can request a USD quote. Online payments are processed in INR via Razorpay.'], ['Do I get post-delivery support?', 'Yes — initial support after launch is included. Maintenance plans are available too.']] as [$q, $a]): ?>
      <details class="card group px-6 py-5 [&_summary::-webkit-details-marker]:hidden"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium"><?= e($q) ?><span class="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-white/10 text-neo-green transition group-open:rotate-45">+</span></summary><p class="mt-3 text-sm text-white/60"><?= e($a) ?></p></details>
    <?php endforeach; ?>
  </div>
</section>
<?php cta_band('Ready to build something?', 'Tell me your idea, I\'ll understand the requirement and give you an honest, negotiable quote. No pressure, no spam.'); page_end();

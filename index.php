<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

$stats = site_stats();
$featured = array_values(array_filter(services_active(), static fn($s) => (int)$s['is_featured']));
$work = projects_active(null, true);
$testimonials = testimonials_active();
$aeo = service_by_slug('aeo-geo-starter');

page_start(['active' => 'home']);
?>
<!-- HERO -->
<section class="relative overflow-hidden pt-32 sm:pt-40">
  <div class="grid-bg pointer-events-none absolute inset-0"></div>
  <div class="pointer-events-none absolute -top-40 left-1/2 h-[30rem] w-[60rem] -translate-x-1/2 rounded-full bg-neo-green/[0.09] blur-3xl"></div>
  <div class="relative mx-auto grid max-w-6xl items-center gap-14 px-5 sm:px-6 lg:grid-cols-[1.15fr_.85fr]">
    <div>
      <a href="<?= e(url('start-project/')) ?>" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] py-1.5 pl-2 pr-4 text-xs text-white/70 transition hover:border-neo-green/30">
        <span class="rounded-full bg-neo-green/15 px-2 py-0.5 font-mono text-[10px] font-semibold uppercase text-neo-green">Status</span>
        <?= e(setting('availability', 'Accepting new projects')) ?> <?= icon('arrow', 'h-3.5 w-3.5') ?>
      </a>
      <h1 class="mt-6 text-balance text-[2.6rem] font-extrabold leading-[1.05] tracking-tight sm:text-6xl lg:text-[4.1rem]">
        Websites, apps &amp; AI that <span class="text-neo-green">bring you clients.</span>
      </h1>
      <p class="mt-6 max-w-xl text-lg leading-relaxed text-white/60">
        I'm <?= e(setting('owner_name', 'Arun')) ?> — a full-stack developer building fast websites, web apps, Android apps, Chrome extensions, AI agents and AEO/GEO for businesses in India and worldwide. Premium quality, fast delivery, honest pricing.
      </p>
      <div class="mt-9 flex flex-col gap-3 sm:flex-row">
        <a href="<?= e(url('start-project/')) ?>" class="btn-primary" data-track="cta_click" data-label="hero_start">Start a project <?= icon('arrow', 'h-4 w-4') ?></a>
        <a href="<?= e(url('work/')) ?>" class="btn-ghost">See my work</a>
      </div>
      <div class="mt-10 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-white/50">
        <span class="flex items-center gap-2"><span class="text-neo-green"><?= icon('check', 'h-4 w-4') ?></span>Free consultation</span>
        <span class="flex items-center gap-2"><span class="text-neo-green"><?= icon('check', 'h-4 w-4') ?></span>Every price negotiable</span>
        <span class="flex items-center gap-2"><span class="text-neo-green"><?= icon('check', 'h-4 w-4') ?></span>Secure Razorpay payments</span>
      </div>
    </div>

    <div class="relative">
      <div class="card overflow-hidden p-0 shadow-2xl shadow-black/50">
        <div class="flex items-center gap-2 border-b border-white/[0.06] px-5 py-4">
          <span class="h-3 w-3 rounded-full bg-red-400/80"></span><span class="h-3 w-3 rounded-full bg-yellow-400/80"></span><span class="h-3 w-3 rounded-full bg-neo-green"></span>
          <span class="ml-3 font-mono text-xs text-white/35">devsarun.io/stack.js</span>
        </div>
        <pre class="overflow-x-auto px-6 pb-12 pt-6 font-mono text-[13px] leading-7 text-white/70 sm:text-sm"><span class="text-white/40">const</span> <span class="text-neo-green">devsArun</span> = {
  frontend: <span class="text-neo-green">"HTML, Tailwind, JS"</span>,
  backend:  <span class="text-neo-green">"PHP, Python, Node"</span>,
  mobile:   <span class="text-neo-green">"Flutter"</span>,
  ai:       <span class="text-neo-green">"GPT, Gemini, Groq"</span>,
  shipped:  <span class="text-neo-green">"Play · Amazon · Chrome"</span>,
  pricing:  <span class="text-neo-green">"negotiable 🤝"</span>,
};</pre>
      </div>
      <div class="absolute -bottom-7 right-6 hidden rounded-3xl border border-white/10 bg-neo-black/90 px-5 py-4 shadow-xl backdrop-blur sm:block">
        <p class="font-mono text-[10px] uppercase tracking-widest text-white/40">Live on stores</p>
        <p class="mt-1 text-sm font-semibold"><?= (int)$stats['apps'] ?> apps · <?= (int)$stats['extensions'] ?> extensions</p>
      </div>
    </div>
  </div>

  <!-- stats -->
  <div class="relative mx-auto mt-20 max-w-6xl px-5 sm:px-6">
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
      <?php foreach ([[$stats['clients'] . '+', 'Live client websites'], [(string)$stats['apps'], 'Apps on Play & Amazon'], [(string)$stats['extensions'], 'Chrome extensions'], ['24h', 'Typical response time']] as [$n, $l]): ?>
        <div class="card px-5 py-6 text-center"><p class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl"><?= e($n) ?></p><p class="mt-1.5 text-sm text-white/50"><?= e($l) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- SERVICES -->
<section class="mx-auto mt-28 max-w-6xl px-5 sm:px-6">
  <?php section_head('what i do', 'Every service your business <span class="text-neo-green">needs online</span>', 'From a one-day landing page to AI agents and custom software. Each service comes with a clear brochure you can download.'); ?>
  <div class="mt-14 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach (array_slice($featured, 0, 6) as $s) { echo '<div class="reveal">'; service_card($s); echo '</div>'; } ?>
  </div>
  <div class="mt-10 text-center"><a href="<?= e(url('services/')) ?>" class="btn-ghost">View all <?= count(services_active()) ?> services <?= icon('arrow', 'h-4 w-4') ?></a></div>
</section>

<?php if ($aeo): ?>
<!-- AEO / GEO -->
<section class="mx-auto mt-28 max-w-6xl px-5 sm:px-6">
  <div class="relative overflow-hidden rounded-[32px] border border-white/[0.07] bg-gradient-to-br from-violet-500/[0.10] via-white/[0.02] to-neo-green/[0.08] p-8 sm:p-12">
    <div class="pointer-events-none absolute -right-20 -top-20 h-72 w-72 rounded-full bg-pink-500/20 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-24 left-10 h-72 w-72 rounded-full bg-blue-500/15 blur-3xl"></div>
    <div class="relative grid items-center gap-10 lg:grid-cols-2">
      <div>
        <span class="rounded-full bg-white/10 px-3 py-1 font-mono text-[11px] uppercase tracking-widest text-white/80">New service</span>
        <h2 class="mt-5 text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">AEO + GEO.<br>Get found.<br>Get chosen.</h2>
        <p class="mt-5 max-w-md text-white/65">Customers now ask ChatGPT, Gemini and Google AI “Who offers this service in my city?”. I help your pages become clear, citable answers — with honest expectations and no fake guarantees.</p>
        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
          <a href="<?= e(url('aeo-geo/')) ?>" class="btn-primary">Explore AEO + GEO <?= icon('arrow', 'h-4 w-4') ?></a>
          <a href="<?= e(url('brochure/aeo-geo-starter/')) ?>" class="btn-ghost" data-track="brochure_view" data-label="aeo-geo-starter"><?= icon('download', 'h-4 w-4') ?> Proposal PDF</a>
        </div>
      </div>
      <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
        <?php foreach ([['01 / Ask', 'A customer asks AI for a service or recommendation.'], ['02 / Appear', 'Your content may be cited or your business mentioned.'], ['03 / Enquire', 'Interested users visit, call or enquire.']] as [$t, $d]): ?>
          <div class="rounded-3xl border border-white/10 bg-neo-black/50 p-5 backdrop-blur"><p class="text-lg font-bold"><?= e($t) ?></p><p class="mt-2 text-sm text-white/55"><?= e($d) ?></p></div>
        <?php endforeach; ?>
        <div class="rounded-3xl border border-neo-green/20 bg-neo-black/50 p-5 sm:col-span-3 lg:col-span-1 xl:col-span-3"><p class="font-mono text-xs uppercase tracking-widest text-white/40">Starter · one-time</p><p class="mt-1 text-3xl font-extrabold text-neo-green"><?= e($aeo['price_usd'] ?: price_text($aeo)) ?></p><p class="text-xs text-white/40">≈ <?= e(price_text($aeo)) ?> · negotiable</p></div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- WORK -->
<section class="mx-auto mt-28 max-w-6xl px-5 sm:px-6">
  <div class="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
    <?php section_head('selected work', 'Shipped, live and <span class="text-neo-green">used by real people</span>', 'Client websites, Play Store & Amazon apps, AI Chrome extensions and tools.', 'left'); ?>
    <a href="<?= e(url('work/')) ?>" class="btn-ghost btn-sm flex-none">All <?= (int)$stats['total'] ?> projects <?= icon('arrow', 'h-4 w-4') ?></a>
  </div>
  <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach (array_slice($work, 0, 9) as $p) { echo '<div class="reveal">'; project_card($p); echo '</div>'; } ?>
  </div>
</section>

<!-- PROCESS -->
<section class="mx-auto mt-28 max-w-6xl px-5 sm:px-6">
  <?php section_head('process', 'Simple, transparent <span class="text-neo-green">process</span>', 'You always know what is happening, what it costs and what comes next.'); ?>
  <div class="mt-14 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ([['01', 'Share your brief', 'Fill the 2-minute project form or chat with the AI assistant.', 'pen'], ['02', 'Free call + quote', 'We discuss scope. You get a written, negotiable quote within 24h.', 'chat'], ['03', 'Build with updates', 'Weekly previews. Track status anytime with your reference ID.', 'code'], ['04', 'Launch & support', 'Go live, walkthrough, and post-launch support included.', 'rocket']] as [$n, $t, $d, $ic]): ?>
      <div class="card reveal p-6">
        <div class="flex items-center justify-between"><span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-neo-green/10 text-neo-green"><?= icon($ic) ?></span><span class="font-mono text-sm text-white/25"><?= $n ?></span></div>
        <h3 class="mt-5 font-semibold"><?= e($t) ?></h3><p class="mt-2 text-sm leading-relaxed text-white/55"><?= e($d) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- WHY -->
<section class="mx-auto mt-28 max-w-6xl px-5 sm:px-6">
  <div class="grid gap-4 lg:grid-cols-3">
    <div class="card p-8 lg:col-span-1">
      <p class="kicker">// promise</p>
      <h2 class="mt-3 text-3xl font-bold tracking-tight">Quality first. <span class="text-neo-green">Price you can talk about.</span></h2>
      <p class="mt-4 text-white/55">No inflated agency rates, no hidden charges. If a full website doesn't fit your budget, we start smart — and scale later.</p>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:col-span-2">
      <?php foreach ([['shield', 'Secure by default', 'Prepared queries, CSRF protection, hashed passwords, HTTPS and safe uploads in every build.'], ['zap', 'Fast delivery', 'Landing pages in days, not weeks. Milestones for bigger projects.'], ['chart', 'Built to convert', 'Clear CTAs, enquiry tracking, SEO and AEO-ready structure.'], ['users', 'Direct developer access', 'You talk to the person who writes the code — on WhatsApp, email or call.']] as [$ic, $t, $d]): ?>
        <div class="card p-6"><span class="text-neo-green"><?= icon($ic, 'h-6 w-6') ?></span><h3 class="mt-4 font-semibold"><?= e($t) ?></h3><p class="mt-2 text-sm leading-relaxed text-white/55"><?= e($d) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($testimonials): ?>
<section class="mx-auto mt-28 max-w-6xl px-5 sm:px-6">
  <?php section_head('clients', 'What clients <span class="text-neo-green">say</span>'); ?>
  <div class="mt-12 grid gap-4 md:grid-cols-3">
    <?php foreach ($testimonials as $t): ?>
      <figure class="card p-6"><div class="flex gap-0.5 text-neo-green"><?= str_repeat(icon('star', 'h-4 w-4'), max(1, min(5, (int)$t['rating']))) ?></div><blockquote class="mt-4 text-sm leading-relaxed text-white/70">“<?= e($t['quote']) ?>”</blockquote><figcaption class="mt-5 text-sm"><span class="font-semibold"><?= e($t['name']) ?></span><span class="block text-white/40"><?= e($t['role']) ?></span></figcaption></figure>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- FAQ -->
<section class="mx-auto mt-28 max-w-3xl px-5 sm:px-6">
  <?php section_head('faq', 'Common <span class="text-neo-green">questions</span>'); ?>
  <div class="mt-12 space-y-3">
    <?php foreach ([
        ['Are the prices fixed?', 'No — every price is a starting point and fully negotiable. Share your budget and requirement; we will shape the right scope.'],
        ['How fast can you deliver?', 'Landing pages in 1–3 days, business websites in about a week, apps and custom software in planned milestones.'],
        ['Can I pay online?', 'Yes. Some services can be booked directly on their page, and every negotiated quote gets a secure Razorpay payment link (UPI, cards, netbanking).'],
        ['Do you work with clients outside India?', 'Yes — NRI and international clients are welcome. Quotes can be shared in USD.'],
        ['How do I track my project request?', 'After you submit a brief you get a reference ID. Use it with your email on the Track page to see live status updates.'],
    ] as [$q, $a]): ?>
      <details class="card group px-6 py-5 [&_summary::-webkit-details-marker]:hidden">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium"><?= e($q) ?><span class="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-white/10 text-neo-green transition group-open:rotate-45">+</span></summary>
        <p class="mt-3 text-sm leading-relaxed text-white/60"><?= e($a) ?></p>
      </details>
    <?php endforeach; ?>
  </div>
</section>

<?php cta_band(); page_end();

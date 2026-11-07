<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$stats = site_stats();
page_start(['title' => 'About', 'description' => 'Arun — full-stack developer from India building websites, apps, Chrome extensions, AI agents and AEO/GEO for businesses worldwide.', 'active' => 'about']);
$skills = [
    'Frontend' => [['HTML5 / CSS3 / Tailwind', 95], ['JavaScript', 90], ['Responsive & UI design', 92]],
    'Backend' => [['PHP', 88], ['MySQL', 90], ['Python', 85]],
    'Apps & AI' => [['Flutter (Android)', 82], ['Chrome Extensions (MV3)', 92], ['LLM APIs — GPT / Gemini / Groq', 88]],
];
?>
<section class="relative overflow-hidden pt-32 sm:pt-40">
  <div class="grid-bg pointer-events-none absolute inset-0"></div>
  <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-5 sm:px-6 lg:grid-cols-2">
    <div>
      <p class="kicker">// about</p>
      <h1 class="mt-4 text-4xl font-extrabold tracking-tight sm:text-6xl">Hi, I'm <?= e(setting('owner_name', 'Arun')) ?>. <span class="text-neo-green">I build things that work.</span></h1>
      <p class="mt-6 text-lg leading-relaxed text-white/60">A full-stack developer based in <?= e(setting('location', 'India')) ?>. Over 3+ years I've gone from simple HTML pages to production websites for schools, coaching institutes, clinics and IT companies; apps on Google Play and the Amazon Appstore; and AI-powered Chrome extensions used every day.</p>
      <p class="mt-4 text-lg leading-relaxed text-white/60">I work directly with business owners — no middlemen, no inflated agency pricing. You talk to the person who writes the code, and every price is negotiable.</p>
      <div class="mt-8 flex flex-col gap-3 sm:flex-row"><a href="<?= e(url('start-project/')) ?>" class="btn-primary">Work with me</a><a href="<?= e(url('work/')) ?>" class="btn-ghost">See projects</a></div>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <?php foreach ([['3+', 'Years experience'], ['50+', 'Projects delivered'], [$stats['clients'] . '+', 'Live client sites'], [(string)($stats['apps'] + $stats['extensions']), 'Store-published apps & extensions']] as [$n, $l]): ?>
        <div class="card p-6"><p class="text-4xl font-extrabold text-neo-green"><?= e($n) ?></p><p class="mt-2 text-sm text-white/55"><?= e($l) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
  <?php section_head('expertise', 'Technical <span class="text-neo-green">expertise</span>', '', 'left'); ?>
  <div class="mt-10 grid gap-4 md:grid-cols-3">
    <?php foreach ($skills as $group => $items): ?>
      <div class="card p-6">
        <h3 class="font-semibold text-neo-green"><?= e($group) ?></h3>
        <div class="mt-5 space-y-5">
          <?php foreach ($items as [$name, $pct]): ?>
            <div><div class="mb-2 flex justify-between text-sm"><span class="text-white/70"><?= e($name) ?></span><span class="font-mono text-xs text-white/40"><?= (int)$pct ?>%</span></div><div class="h-1.5 rounded-full bg-white/[0.06]"><div class="h-full rounded-full bg-neo-green" style="width:<?= (int)$pct ?>%"></div></div></div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
  <?php section_head('values', 'How I <span class="text-neo-green">work</span>', '', 'left'); ?>
  <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ([['Honest pricing', 'Starting prices, not traps. Everything negotiable, nothing hidden.'], ['Fast & reliable', 'Clear milestones, weekly previews, on-time delivery.'], ['Security first', 'Safe code by default — your data and your customers\' data matter.'], ['Long-term partner', 'Support after launch, plus help growing with SEO and AEO/GEO.']] as [$t, $d]): ?>
      <div class="card p-6"><h3 class="font-semibold"><?= e($t) ?></h3><p class="mt-2 text-sm text-white/55"><?= e($d) ?></p></div>
    <?php endforeach; ?>
  </div>
</section>
<?php cta_band(); page_end();

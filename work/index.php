<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$projects = projects_active();
$counts = [];
foreach ($projects as $p) {
    $counts[$p['type']] = ($counts[$p['type']] ?? 0) + 1;
}
page_start(['title' => 'Work & Portfolio', 'description' => 'Live client websites, Google Play and Amazon Appstore apps, AI Chrome extensions and web tools built by Devs Arun.', 'active' => 'work']);
?>
<section class="relative overflow-hidden pt-32 sm:pt-40">
  <div class="grid-bg pointer-events-none absolute inset-0"></div>
  <div class="relative mx-auto max-w-6xl px-5 text-center sm:px-6">
    <p class="kicker">// work</p>
    <h1 class="mx-auto mt-4 max-w-3xl text-balance text-4xl font-extrabold tracking-tight sm:text-6xl"><?= count($projects) ?> projects. <span class="text-neo-green">All live.</span></h1>
    <p class="mx-auto mt-6 max-w-2xl text-lg text-white/60">Client websites in production, apps on Google Play and the Amazon Appstore, AI-powered Chrome extensions and developer tools.</p>
    <div class="mx-auto mt-10 grid max-w-3xl grid-cols-2 gap-3 sm:grid-cols-4">
      <?php foreach (['client' => 'Websites', 'android' => 'Play Store', 'amazon' => 'Amazon apps', 'extension' => 'Extensions'] as $k => $l): ?>
        <div class="card px-4 py-5"><p class="text-3xl font-extrabold"><?= (int)($counts[$k] ?? 0) ?></p><p class="mt-1 text-xs text-white/50"><?= e($l) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="mx-auto mt-16 max-w-6xl px-5 sm:px-6">
  <div class="sticky top-24 z-30 -mx-2 flex gap-2 overflow-x-auto rounded-full px-2 py-2 sm:justify-center" data-filter-group="#work-grid">
    <button type="button" class="chip is-active flex-none bg-neo-black" data-filter="all">All <span class="text-white/35"><?= count($projects) ?></span></button>
    <?php foreach (PROJECT_TYPES as $k => $l): if (!empty($counts[$k])): ?>
      <button type="button" class="chip flex-none bg-neo-black" data-filter="<?= e($k) ?>"><?= e($l) ?> <span class="text-white/35"><?= (int)$counts[$k] ?></span></button>
    <?php endif; endforeach; ?>
  </div>
  <div id="work-grid" class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($projects as $p) project_card($p); ?>
  </div>
</section>

<section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
  <div class="grid gap-4 md:grid-cols-3">
    <?php foreach ([['Phase 1 · Learning', 'HTML, CSS and small tools — learning responsive design fundamentals.'], ['Phase 2 · Extensions & tools', 'Chrome extensions and web tools used by real people every day.'], ['Phase 3 · Client work', '18+ production websites for education, healthcare, astrology, IT and more.'], ['Phase 4 · Apps', 'Tunnl on Google Play and six kids & utility apps on the Amazon Appstore.'], ['Phase 5 · AI products', 'InboxSwift, Aptis, Kognit, QuickGlance and AI course builders.'], ['Now · AEO / GEO', 'Helping businesses get found and chosen in AI search.']] as $i => [$t, $d]): ?>
      <div class="card p-6 <?= $i === 5 ? 'border-neo-green/25' : '' ?>"><p class="font-mono text-xs text-neo-green"><?= e($t) ?></p><p class="mt-3 text-sm leading-relaxed text-white/60"><?= e($d) ?></p></div>
    <?php endforeach; ?>
  </div>
</section>
<?php cta_band('Want something like this?', 'Tell me what you want to build — website, app, extension or AI tool. Honest, negotiable quote within 24 hours.'); page_end();

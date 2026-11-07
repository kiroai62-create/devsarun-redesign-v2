<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$s = service_by_slug('aeo-geo-starter');
$schema = $s ? ['@context' => 'https://schema.org', '@type' => 'Service', 'name' => 'AEO + GEO Starter', 'description' => $s['summary'], 'offers' => ['@type' => 'Offer', 'price' => '199', 'priceCurrency' => 'USD']] : null;
page_start(['title' => 'AEO + GEO — Get found in AI search', 'description' => 'Answer Engine Optimization and Generative Engine Optimization: help customers find and choose your business in ChatGPT, Gemini and Google AI. Starter from $199, negotiable.', 'active' => 'aeo-geo', 'schema' => $schema]);
$card = 'rounded-[28px] border border-white/[0.07] bg-white/[0.025] p-7';
?>
<section class="relative overflow-hidden pt-32 sm:pt-40">
  <div class="pointer-events-none absolute -left-32 top-20 h-96 w-96 rounded-full bg-pink-500/15 blur-3xl"></div>
  <div class="pointer-events-none absolute -right-20 top-0 h-[28rem] w-[28rem] rounded-full bg-violet-500/15 blur-3xl"></div>
  <div class="pointer-events-none absolute bottom-0 left-1/2 h-72 w-[40rem] -translate-x-1/2 rounded-full bg-blue-500/10 blur-3xl"></div>
  <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-5 sm:px-6 lg:grid-cols-[1.2fr_.8fr]">
    <div>
      <p class="kicker">// ai search visibility</p>
      <h1 class="mt-5 text-5xl font-extrabold leading-[1.02] tracking-tight sm:text-7xl">AEO + GEO<br>Get found.<br><span class="text-neo-green">Get chosen.</span></h1>
      <p class="mt-7 max-w-xl text-lg leading-relaxed text-white/65">Help customers find your services through AI search — ChatGPT, Gemini, Perplexity and Google AI Overviews — and take the next step.</p>
      <div class="mt-9 flex flex-col gap-3 sm:flex-row">
        <a href="#free-check" class="btn-primary" data-track="cta_click" data-label="aeo_free_check">Get a free visibility check</a>
        <a href="<?= e(url('brochure/aeo-geo-starter/')) ?>" class="btn-ghost" data-track="brochure_view" data-label="aeo-geo-starter"><?= icon('download', 'h-4 w-4') ?> Download proposal (PDF)</a>
      </div>
    </div>
    <div class="<?= $card ?> relative">
      <p class="font-mono text-xs uppercase tracking-widest text-white/40">Your next lead?</p>
      <div class="mt-5 rounded-3xl bg-white/[0.04] p-5 text-sm text-white/80">💬 “Who offers this service in my city?”</div>
      <div class="mt-3 ml-8 rounded-3xl bg-neo-green/10 p-5 text-sm text-white/80"><span class="text-neo-green">AI:</span> Based on trusted sources, <strong class="text-white">Your Business</strong> offers this service locally — their page explains pricing and how to book…</div>
      <p class="mt-5 text-sm leading-relaxed text-white/55">If your page is found and selected, its content may be cited — and your business may enter their shortlist.</p>
    </div>
  </div>
</section>

<section class="mx-auto mt-28 max-w-6xl px-5 sm:px-6">
  <?php section_head('two sides', 'Your content. Your services. <span class="text-neo-green">New visibility.</span>'); ?>
  <div class="mt-12 grid gap-4 md:grid-cols-2">
    <div class="<?= $card ?>"><span class="flex h-14 w-14 items-center justify-center rounded-full bg-blue-500 text-white"><?= icon('sparkles', 'h-7 w-7') ?></span><h3 class="mt-6 text-2xl font-bold">AEO: your content</h3><p class="mt-3 text-white/60">Answer Engine Optimization. Make your website content easier to use as a clear answer to customer questions.</p></div>
    <div class="<?= $card ?>"><span class="flex h-14 w-14 items-center justify-center rounded-full bg-blue-500 text-white"><?= icon('chart', 'h-7 w-7') ?></span><h3 class="mt-6 text-2xl font-bold">GEO: your business</h3><p class="mt-3 text-white/60">Generative Engine Optimization. Help AI search understand your services and expertise. Relevant pages may be linked or your business mentioned.</p></div>
  </div>
</section>

<section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
  <div class="grid gap-4 md:grid-cols-3">
    <?php foreach ([['01 / Ask', 'A potential customer asks AI for a service, solution or business recommendation.'], ['02 / Appear', 'Your website content may be cited or linked; your services may be mentioned.'], ['03 / Enquire', 'Interested users may visit, call or enquire. Leads are possible — not guaranteed.']] as [$t, $d]): ?>
      <div class="<?= $card ?>"><p class="text-4xl font-extrabold tracking-tight"><?= e($t) ?></p><p class="mt-4 text-white/60"><?= e($d) ?></p></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
  <div class="<?= $card ?> grid gap-10 md:grid-cols-2">
    <div><p class="font-mono text-xs uppercase tracking-widest text-red-300/70">Before: generic claims</p><p class="mt-4 text-xl text-white/80">“We offer the best AC repair. Great service. Great prices. Call us today.”</p><p class="mt-3 text-sm text-white/45">Little detail to support a decision.</p></div>
    <div><p class="font-mono text-xs uppercase tracking-widest text-neo-green">After: answer + CTA</p><p class="mt-4 text-xl text-white/80">“Do you repair AC in Austin? We serve Austin homes. Request an assessment to discuss repair options and pricing.”</p><p class="mt-3 text-sm text-white/45">Illustrative; verify facts.</p></div>
  </div>
</section>

<section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
  <?php section_head('what we improve', 'Improve discovery. <span class="text-neo-green">Make enquiring easy.</span>'); ?>
  <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ([['Site access', 'Review indexing, crawler access and internal links.'], ['Service clarity', 'Explain your services, target customer and service area.'], ['Useful answers', 'Answer questions customers ask before choosing a provider.'], ['Business evidence', 'Use verified facts, experience and authentic proof.'], ['Page structure', 'Improve headings and relevant, valid structured data.'], ['Enquiry path', 'Give interested visitors a clear call, quote or enquiry option.']] as [$t, $d]): ?>
      <div class="<?= $card ?>"><h3 class="text-lg font-bold uppercase tracking-tight"><?= e($t) ?></h3><p class="mt-2 text-white/60"><?= e($d) ?></p></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
  <?php section_head('use cases', 'How AI discovery could <span class="text-neo-green">become a lead</span>'); ?>
  <div class="mt-12 grid gap-4 md:grid-cols-2">
    <?php foreach ([['Local services', '“Who repairs AC in Austin?”', 'A relevant service page may lead to a visit, call or quote request.'], ['B2B & software', '“Which tool fits our team?”', 'Useful use-case pages may lead to product enquiries or demo requests.'], ['Professional services', '“Who can help with this problem?”', 'Clear expertise and service details may support consultation enquiries.'], ['Product businesses', '“Which product fits my needs?”', 'Helpful product information may support visits, questions or purchases.']] as [$t, $q, $d]): ?>
      <div class="<?= $card ?>"><h3 class="text-lg font-bold uppercase"><?= e($t) ?></h3><p class="mt-3 text-white/80"><?= e($q) ?></p><p class="mt-1 text-white/55"><?= e($d) ?></p></div>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($s): ?>
<section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6" id="starter">
  <div class="relative overflow-hidden rounded-[32px] border border-neo-green/20 bg-gradient-to-br from-neo-green/[0.08] to-transparent p-8 sm:p-12">
    <div class="grid gap-10 lg:grid-cols-[.8fr_1.2fr]">
      <div>
        <p class="font-mono text-xs uppercase tracking-widest text-white/50">AEO + GEO Starter / one-time</p>
        <p class="mt-3 text-7xl font-extrabold tracking-tight">$199</p>
        <p class="mt-2 text-white/50">≈ <?= e(price_text($s)) ?> · negotiable</p>
        <p class="mt-6 text-lg font-semibold">One website. A focused start.</p>
        <div class="mt-7 grid gap-2.5">
          <a href="<?= e(url('start-project/?service=aeo-geo-starter')) ?>" class="btn-primary">Start with AEO + GEO</a>
          <?php if ((int)$s['is_buyable']): ?><a href="<?= e(url('checkout/?service=aeo-geo-starter')) ?>" class="btn-ghost" data-track="checkout_open" data-label="aeo-geo-starter"><?= icon('lock', 'h-4 w-4') ?> Book & pay online</a><?php endif; ?>
        </div>
      </div>
      <div class="grid gap-3 sm:grid-cols-2">
        <?php foreach (json_list($s['deliverables']) as $i => $d): ?>
          <div class="rounded-3xl border border-white/[0.07] bg-neo-black/40 p-5"><p class="text-2xl font-extrabold text-neo-green"><?= sprintf('%02d', $i + 1) ?></p><p class="mt-2 font-bold uppercase"><?= e($d['title']) ?></p><p class="mt-1 text-sm text-white/55"><?= e($d['desc']) ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
  <?php section_head('process', 'A simple, <span class="text-neo-green">reviewable</span> process'); ?>
  <div class="card mt-10 overflow-x-auto p-0">
    <table class="w-full min-w-[640px] text-left text-sm">
      <thead><tr class="border-b border-white/[0.06] font-mono text-[11px] uppercase tracking-wider text-white/40"><th class="px-6 py-4">Stage</th><th class="px-6 py-4">The work</th><th class="px-6 py-4">Your input</th><th class="px-6 py-4">You receive</th></tr></thead>
      <tbody><?php foreach (json_list($s['process']) as $p): ?><tr class="border-b border-white/[0.04] last:border-0"><td class="px-6 py-4 font-semibold"><?= e($p['stage']) ?></td><td class="px-6 py-4 text-white/65"><?= e($p['work']) ?></td><td class="px-6 py-4 text-white/65"><?= e($p['input']) ?></td><td class="px-6 py-4 text-neo-green/90"><?= e($p['receive']) ?></td></tr><?php endforeach; ?></tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<section class="mx-auto mt-24 max-w-6xl px-5 sm:px-6">
  <?php section_head('honesty', 'Measure signals. <span class="text-neo-green">Not promises.</span>'); ?>
  <div class="mt-12 grid gap-4 md:grid-cols-2">
    <?php foreach ([['Search activity', 'Review impressions and clicks in Search Console. Google AI traffic is included in overall Web reporting.'], ['Identifiable referrals', 'Track visits from AI sources when your analytics can identify them. Attribution is incomplete.'], ['Enquiries & conversions', 'Track business outcomes in your analytics. A change does not prove AEO caused it.'], ['Honest expectations', 'Set a baseline first. Ongoing monitoring and paid tools are outside the $199 starter scope.']] as [$t, $d]): ?>
      <div class="<?= $card ?>"><h3 class="text-lg font-bold uppercase"><?= e($t) ?></h3><p class="mt-2 text-white/60"><?= e($d) ?></p></div>
    <?php endforeach; ?>
  </div>
  <div class="mt-4 grid gap-4 md:grid-cols-2">
    <div class="<?= $card ?>"><h3 class="text-lg font-bold uppercase">What we commit to</h3><p class="mt-2 text-white/60">Reviewable improvements. One website, two existing pages. Agreed access, timing and scope. One revision round.</p></div>
    <div class="<?= $card ?>"><h3 class="text-lg font-bold uppercase">No guarantees</h3><p class="mt-2 text-white/60">No guaranteed AI mentions, rankings, leads or revenue. Excludes new articles, major coding fixes, PR, paid tools and ongoing monitoring.</p></div>
  </div>
  <div class="mt-4 <?= $card ?>">
    <h3 class="text-lg font-bold uppercase">Reference notes & important limits</h3>
    <ul class="mt-3 grid gap-3 text-sm text-white/60 md:grid-cols-2">
      <li><a class="text-neo-green hover:underline" href="https://developers.google.com/search/docs/appearance/ai-features" target="_blank" rel="noopener">Google Search Central — AI features and your website</a>: SEO foundations, eligibility and measurement.</li>
      <li><a class="text-neo-green hover:underline" href="https://platform.openai.com/docs/bots" target="_blank" rel="noopener">Overview of OpenAI crawlers</a>: search access and model-training permissions are separate controls.</li>
      <li>Google requires no special AI schema or AI text file for AI Overviews or AI Mode.</li>
      <li>No client results or performance figures are claimed. Terms overlap; there is no separate AEO/GEO ranking switch.</li>
    </ul>
  </div>
</section>

<section class="mx-auto mt-24 max-w-3xl px-5 sm:px-6" id="free-check">
  <div class="card p-7 sm:p-10">
    <h2 class="text-4xl font-extrabold tracking-tight sm:text-5xl">Be findable.</h2>
    <p class="mt-4 text-white/60">Want your services considered in AI search? Share your website URL, main service and target customer — I'll send a free quick visibility check by email.</p>
    <form class="mt-8 grid gap-4 sm:grid-cols-2" data-ajax="lead/">
      <input type="hidden" name="source" value="audit">
      <input type="hidden" name="service" value="aeo-geo-starter">
      <input type="text" name="website_hp" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div data-hide-on-success class="sm:col-span-2"><label class="label" for="a-url">Website URL</label><input id="a-url" class="field" type="url" name="existing_website" placeholder="https://yourbusiness.com" required></div>
      <div data-hide-on-success><label class="label" for="a-svc">Main service</label><input id="a-svc" class="field" name="project_title" placeholder="e.g. Home tuition in Patna" required maxlength="190"></div>
      <div data-hide-on-success><label class="label" for="a-cust">Target customer</label><input id="a-cust" class="field" name="description" placeholder="e.g. Parents of class 6–12 students" required maxlength="2000"></div>
      <div data-hide-on-success><label class="label" for="a-name">Your name</label><input id="a-name" class="field" name="name" required maxlength="120"></div>
      <div data-hide-on-success><label class="label" for="a-email">Email</label><input id="a-email" class="field" type="email" name="email" required maxlength="190"></div>
      <div data-hide-on-success class="sm:col-span-2"><button class="btn-primary w-full" type="submit">Get my free visibility check</button><p class="mt-3 text-center text-xs text-white/40">No spam. Start with a focused $199 website improvement only if it makes sense.</p></div>
      <div data-success class="hidden rounded-3xl border border-neo-green/30 bg-neo-green/10 p-6 text-center sm:col-span-2"><p class="text-lg font-semibold text-neo-green">Request received ✅</p><p class="mt-1 text-sm text-white/70">Reference <strong data-ref></strong>. You'll get your visibility check by email within 24–48 hours.</p></div>
    </form>
  </div>
</section>
<?php page_end();

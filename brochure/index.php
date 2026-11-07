<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

/*
 * /brochure/{slug}/ — presentation-style brochure (16:9 slides) generated from the
 * service data. "Download PDF" renders the deck client-side; print is the fallback.
 */
$slug = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['slug'] ?? ''));
$s = $slug ? service_by_slug($slug) : null;
if (!$s) {
    http_response_code(404);
    require ROOT . '/404.php';
    exit;
}
track_pageview();
track_event('brochure_view', $s['slug'], (int)$s['id']);

$site = setting('site_name', 'Devs Arun');
$deliverables = array_slice(json_list($s['deliverables']), 0, 6);
$process = array_slice(json_list($s['process']), 0, 4);
$faqs = array_slice(json_list($s['faqs']), 0, 4);
$ideal = array_slice(json_list($s['ideal_for']), 0, 4);
$notIncluded = json_list($s['not_included']);
$features = json_list($s['features']);
$benefits = array_slice(json_list($s['benefits'] ?? null), 0, 6);
$packages = array_slice(service_packages($s), 0, 3);
$overview = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', (string)($s['overview'] ?: $s['description'] ?: $s['summary'])) ?: [])));
$short = strtoupper(preg_replace('/\s*[\/(].*$/', '', $s['title']));
$isAeo = $s['slug'] === 'aeo-geo-starter';
$price = $s['price_usd'] && $isAeo ? '$199' : price_text($s);
$priceSub = $isAeo ? 'AEO + GEO STARTER  /  ONE-TIME USD  ·  ≈ ' . price_text($s) : strtoupper($s['title']) . '  /  ' . ($s['price_from'] ? 'STARTING PRICE' : 'PRICING') . '  /  NEGOTIABLE';
$titleWords = explode(' ', strtoupper($s['title']));
$tagline = strtoupper(rtrim((string)$s['tagline'], '.'));
$wa = setting('whatsapp');
$deco = static fn(string $v) => '<div class="deco deco-' . $v . '" aria-hidden="true"><i class="b1"></i><i class="b2"></i><i class="b3"></i><i class="b4"></i><i class="arc"></i></div>';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($s['title']) ?> — Brochure · <?= e($site) ?></title>
<meta name="description" content="<?= e($s['summary']) ?>">
<meta name="robots" content="noindex">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="<?= e(brand_favicon_url()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/brochure.css')) ?>">
</head>
<body data-base="<?= e(BASE_PATH) ?>" data-slug="<?= e($s['slug']) ?>" data-sid="<?= (int)$s['id'] ?>">
<div class="toolbar">
  <a href="<?= e(url('services/' . $s['slug'] . '/')) ?>" class="tb-btn">Back to service</a>
  <span class="tb-title"><?= e($s['title']) ?> · brochure</span>
  <div class="tb-actions">
    <a href="<?= e(url('services/' . $s['slug'] . '/#enquire')) ?>" class="tb-btn">Enquire</a>
    <button type="button" class="tb-btn" id="print-btn">Print</button>
    <button type="button" class="tb-btn tb-primary" id="pdf-btn">Download PDF</button>
  </div>
</div>

<main class="deck" id="deck">

  <!-- COVER -->
  <section class="slide">
    <?= $deco('a') ?>
    <div class="pad center-v">
      <p class="eyebrow"><?= e(strtoupper($site)) ?> / SERVICE BRIEFING</p>
      <h1 class="mega"><?= $isAeo ? 'AEO + GEO<br>GET FOUND.<br>GET CHOSEN.' : e(implode(' ', $titleWords)) . '<br><span class="soft">' . e($tagline) . '</span>' ?></h1>
      <p class="lead w60"><?= e($s['summary']) ?></p>
    </div>
  </section>

<?php if ($isAeo): ?>
  <section class="slide">
    <?= $deco('b') ?>
    <div class="pad split">
      <div>
        <h2 class="big">YOUR NEXT<br>LEAD?</h2>
        <p class="lead">A customer asks AI: “Who offers this service in my city?”</p>
        <p class="lead">If your page is found and selected, its content may be cited—and your business may enter their shortlist.</p>
      </div>
      <div class="arch"><div class="chat-q">💬 “Who offers this service in my city?”</div><div class="chat-a"><b>AI:</b> Based on trusted sources, <b>Your Business</b> offers this service locally…</div></div>
    </div>
  </section>

  <section class="slide">
    <?= $deco('c') ?>
    <div class="pad">
      <h2 class="h">YOUR CONTENT. YOUR SERVICES. NEW VISIBILITY.</h2>
      <div class="cols2 mt">
        <div><span class="dot-ic">✦</span><h3>AEO: YOUR CONTENT</h3><p>Answer Engine Optimization. Make your website content easier to use as a clear answer to customer questions.</p></div>
        <div><span class="dot-ic">↗</span><h3>GEO: YOUR BUSINESS</h3><p>Generative Engine Optimization. Help AI search understand your services and expertise. Relevant pages may be linked or your business mentioned.</p></div>
      </div>
    </div>
  </section>

  <section class="slide">
    <?= $deco('d') ?>
    <div class="pad steps">
      <div><h2 class="step">01 / ASK</h2><p>A potential customer asks AI for a service, solution or business recommendation.</p></div>
      <div><h2 class="step">02 / APPEAR</h2><p>Your website content may be cited or linked; your services may be mentioned.</p></div>
      <div><h2 class="step">03 / ENQUIRE</h2><p>Interested users may visit, call or enquire. Leads are possible—not guaranteed.</p></div>
    </div>
  </section>

  <section class="slide">
    <?= $deco('e') ?>
    <div class="pad">
      <h2 class="h">TURN DISCOVERY INTO ENQUIRY OPPORTUNITIES</h2>
      <div class="cols2 grid4 mt">
        <div><h3>YOUR WEBSITE CONTENT</h3><p>Helpful pages may be cited or linked as sources in search-grounded AI answers.</p></div>
        <div><h3>YOUR SERVICES</h3><p>Your business may be mentioned when its services match the customer’s question.</p></div>
        <div><h3>RELEVANT WEBSITE VISITS</h3><p>Source links can bring interested users to your service pages to learn more.</p></div>
        <div><h3>A NEW ROUTE TO LEADS</h3><p>Clear offers and enquiry options help visitors take action. Traffic and leads are not guaranteed.</p></div>
      </div>
    </div>
  </section>

  <section class="slide">
    <?= $deco('c') ?>
    <div class="pad">
      <h2 class="h">BETTER ANSWERS. A CLEAR NEXT STEP.</h2>
      <div class="cols2 mt">
        <div><span class="dot-ic">✦</span><h3>BEFORE: GENERIC CLAIMS</h3><p>“We offer the best AC repair. Great service. Great prices. Call us today.”<br><br>Little detail to support a decision.</p></div>
        <div><span class="dot-ic">↗</span><h3>AFTER: ANSWER + CTA</h3><p>“Do you repair AC in Austin? We serve Austin homes. Request an assessment to discuss repair options and pricing.”<br>Illustrative; verify facts.</p></div>
      </div>
    </div>
  </section>

  <section class="slide">
    <?= $deco('f') ?>
    <div class="pad">
      <h2 class="h">IMPROVE DISCOVERY. MAKE ENQUIRING EASY.</h2>
      <div class="cols3 mt">
        <?php foreach ([['SITE ACCESS', 'Review indexing, crawler access and internal links.'], ['SERVICE CLARITY', 'Explain your services, target customer and service area.'], ['USEFUL ANSWERS', 'Answer questions customers ask before choosing a provider.'], ['BUSINESS EVIDENCE', 'Use verified facts, experience and authentic proof.'], ['PAGE STRUCTURE', 'Improve headings and relevant, valid structured data.'], ['ENQUIRY PATH', 'Give interested visitors a clear call, quote or enquiry option.']] as [$t, $d]): ?>
          <div><h3><?= e($t) ?></h3><p><?= e($d) ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="slide">
    <?= $deco('e') ?>
    <div class="pad">
      <h2 class="h">HOW AI DISCOVERY COULD BECOME A LEAD</h2>
      <div class="cols2 grid4 mt">
        <div><h3>LOCAL SERVICES</h3><p>“Who repairs AC in Austin?”<br>A relevant service page may lead to a visit, call or quote request.</p></div>
        <div><h3>B2B &amp; SOFTWARE</h3><p>“Which tool fits our team?”<br>Useful use-case pages may lead to product enquiries or demo requests.</p></div>
        <div><h3>PROFESSIONAL SERVICES</h3><p>“Who can help with this problem?”<br>Clear expertise and service details may support consultation enquiries.</p></div>
        <div><h3>PRODUCT BUSINESSES</h3><p>“Which product fits my needs?”<br>Helpful product information may support visits, questions or purchases.</p></div>
      </div>
    </div>
  </section>
<?php else: ?>
  <!-- WHAT IS -->
  <section class="slide">
    <?= $deco('b') ?>
    <div class="pad split">
      <div>
        <h2 class="big">WHAT IS<br><?= e($short) ?>?</h2>
        <?php foreach (array_slice($overview, 0, 2) as $para): ?><p class="lead small"><?= e($para) ?></p><?php endforeach; ?>
      </div>
      <div class="arch arch-list">
        <?php foreach (array_slice($features, 0, 4) as $f): ?><div class="tick"><?= e($f) ?></div><?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php if ($benefits): ?>
  <section class="slide">
    <?= $deco('f') ?>
    <div class="pad">
      <h2 class="h">HOW IT HELPS YOUR BUSINESS.</h2>
      <div class="cols3 mt">
        <?php foreach ($benefits as $b): ?><div><h3><?= e(strtoupper($b['title'] ?? '')) ?></h3><p><?= e($b['desc'] ?? '') ?></p></div><?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($ideal): ?>
  <section class="slide">
    <?= $deco('e') ?>
    <div class="pad">
      <h2 class="h">WHO IT’S FOR</h2>
      <div class="cols2 grid4 mt">
        <?php foreach ($ideal as $i => $x): ?><div><h3><?= sprintf('%02d', $i + 1) ?> / <?= e(strtoupper($x)) ?></h3><p>A focused <?= e(strtolower($s['title'])) ?> designed around how these customers find, compare and contact you.</p></div><?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>
<?php endif; ?>

  <?php if (!$isAeo): ?>
  <!-- PRICE -->
  <section class="slide">
    <?= $deco('g') ?>
    <div class="pad center">
      <p class="price"><?= e($price) ?></p>
      <p class="price-sub"><?= e($priceSub) ?></p>
      <?php if ($s['timeline']): ?><p class="price-sub small">TYPICAL TIMELINE · <?= e(strtoupper($s['timeline'])) ?></p><?php endif; ?>
    </div>
  </section>
  <?php else: ?>
  <section class="slide">
    <?= $deco('g') ?>
    <div class="pad center"><p class="price">$199</p><p class="price-sub"><?= e($priceSub) ?></p></div>
  </section>
  <?php endif; ?>

  <?php if (count($packages) > 1): ?>
  <section class="slide">
    <?= $deco('i') ?>
    <div class="pad">
      <h2 class="h">PACKAGES. CHOOSE A STARTING POINT.</h2>
      <div class="pk mt">
        <?php foreach ($packages as $i => $p): ?>
          <div class="pk-card<?= $i === 1 ? ' pk-hl' : '' ?>">
            <p class="pk-name"><?= e(strtoupper($p['name'])) ?></p>
            <p class="pk-price"><?= e($p['price'] ?? '') ?></p>
            <p class="pk-note"><?= e($p['note'] ?? '') ?></p>
            <ul><?php foreach (array_slice((array)($p['features'] ?? []), 0, 5) as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="pk-foot">All prices are starting points and negotiable.</p>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($deliverables): ?>
  <section class="slide">
    <?= $deco('h') ?>
    <div class="pad">
      <h2 class="h"><?= $isAeo ? 'ONE WEBSITE. A FOCUSED START.' : 'WHAT YOU GET.' ?></h2>
      <div class="cols3 nums mt">
        <?php foreach ($deliverables as $i => $d): ?><div><p class="num"><?= sprintf('%02d', $i + 1) ?></p><h3><?= e(strtoupper($d['title'] ?? '')) ?></h3><?php if (!$isAeo): ?><p><?= e($d['desc'] ?? '') ?></p><?php endif; ?></div><?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($process): ?>
  <section class="slide">
    <?= $deco('i') ?>
    <div class="pad">
      <h2 class="h">A SIMPLE, REVIEWABLE PROCESS</h2>
      <table class="tbl mt">
        <thead><tr><th>STAGE</th><th>THE WORK</th><th>YOUR INPUT</th><th>YOU RECEIVE</th></tr></thead>
        <tbody><?php foreach ($process as $p): ?><tr><td><b><?= e(strtoupper($p['stage'] ?? '')) ?></b></td><td><?= e($p['work'] ?? '') ?></td><td><?= e($p['input'] ?? '') ?></td><td><?= e($p['receive'] ?? '') ?></td></tr><?php endforeach; ?></tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($isAeo): ?>
  <section class="slide">
    <?= $deco('e') ?>
    <div class="pad">
      <h2 class="h">MEASURE SIGNALS. NOT PROMISES.</h2>
      <div class="cols2 grid4 mt">
        <div><h3>SEARCH ACTIVITY</h3><p>Review impressions and clicks in Search Console. Google AI traffic is included in overall Web reporting.</p></div>
        <div><h3>IDENTIFIABLE REFERRALS</h3><p>Track visits from AI sources when your analytics can identify them. Attribution is incomplete.</p></div>
        <div><h3>ENQUIRIES &amp; CONVERSIONS</h3><p>Track business outcomes in your analytics. A change does not prove AEO caused it.</p></div>
        <div><h3>HONEST EXPECTATIONS</h3><p>Set a baseline first. Ongoing monitoring and paid tools are outside the $199 starter scope.</p></div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- SCOPE -->
  <section class="slide">
    <?= $deco('c') ?>
    <div class="pad">
      <h2 class="h">CLEAR SCOPE. NO BLACK BOX.</h2>
      <div class="cols2 mt">
        <div><span class="dot-ic">✦</span><h3>WHAT WE COMMIT TO</h3><p><?= $isAeo ? 'Reviewable improvements.<br>One website, two existing pages.<br>Agreed access, timing and scope.<br>One revision round.' : 'Reviewable work at every milestone.<br>Agreed scope, timing and access.<br>Transparent, negotiable quote.<br>Post-launch support.' ?></p></div>
        <div><span class="dot-ic">↗</span><h3><?= $isAeo ? 'NO GUARANTEES' : 'NOT INCLUDED' ?></h3><p><?= $notIncluded ? implode('<br>', array_map('e', $notIncluded)) : 'Anything outside the agreed scope is quoted separately and approved by you first.' ?></p></div>
      </div>
    </div>
  </section>

  <?php if ($isAeo): ?>
  <section class="slide">
    <?= $deco('e') ?>
    <div class="pad">
      <h2 class="h">REFERENCE NOTES &amp; IMPORTANT LIMITS</h2>
      <div class="cols2 grid4 mt">
        <div><h3>GOOGLE SEARCH CENTRAL</h3><p><u>AI features and your website.</u><br>SEO foundations, eligibility and measurement.</p></div>
        <div><h3>OPENAI CRAWLERS</h3><p><u>Overview of OpenAI crawlers.</u><br>Search access and model-training permissions are separate controls.</p></div>
        <div><h3>NO SPECIAL AI SHORTCUT</h3><p>Google requires no special AI schema or AI text file for AI Overviews or AI Mode.</p></div>
        <div><h3>ILLUSTRATIVE EXAMPLES</h3><p>No client results or performance figures are claimed. Terms overlap; there is no separate AEO/GEO ranking switch.</p></div>
      </div>
    </div>
  </section>
  <?php elseif ($faqs): ?>
  <section class="slide">
    <?= $deco('e') ?>
    <div class="pad">
      <h2 class="h">COMMON QUESTIONS</h2>
      <div class="cols2 grid4 mt">
        <?php foreach ($faqs as $f): ?><div><h3><?= e(strtoupper($f['q'] ?? '')) ?></h3><p><?= e($f['a'] ?? '') ?></p></div><?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- CTA -->
  <section class="slide">
    <?= $deco('j') ?>
    <div class="pad center-v">
      <h2 class="mega"><?= $isAeo ? 'Be findable.' : 'Let’s build it.' ?></h2>
      <p class="lead w55"><?= $isAeo ? 'Want your services considered in AI search? Reply with your website URL, main service and target customer.<br><br>Start with a focused $199 website improvement.' : 'Share your idea in a 2-minute brief and get an honest, negotiable quote within 24 hours. Every price is negotiable.' ?></p>
      <div class="contact">
        <p><b><?= e(abs_url('start-project/?service=' . $s['slug'])) ?></b></p>
        <p><?= e(setting('contact_email')) ?><?= $wa ? ' · WhatsApp ' . e($wa) : '' ?></p>
      </div>
      <p class="foot"><?= e(strtoupper($s['title'])) ?> / <?= e(strtoupper($site)) ?> BRIEFING</p>
    </div>
  </section>
</main>
<p class="hint">Tip: “Download PDF” builds a landscape PDF of all slides. If it doesn't start, use Print → Save as PDF.</p>
<script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.2/dist/html2pdf.bundle.min.js" defer></script>
<script src="<?= e(asset('js/brochure.js')) ?>" defer></script>
</body>
</html>

<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$pre = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['service'] ?? ''));
$services = services_active();
$features = ['Admin panel', 'User login / accounts', 'Payment gateway', 'Booking / appointments', 'Blog / news', 'WhatsApp integration', 'AI chatbot', 'Email automation', 'Multi-language', 'SEO setup', 'Analytics dashboard', 'Mobile app', 'Course / LMS', 'Product catalogue', 'Custom API / integration'];
$budgets = ['Under ₹5,000', '₹5,000 – ₹15,000', '₹15,000 – ₹30,000', '₹30,000 – ₹60,000', '₹60,000 – ₹1,00,000', '₹1,00,000+', 'USD budget', 'Not sure — suggest me'];
$timelines = ['ASAP (this week)', '2–4 weeks', '1–2 months', 'Flexible'];

page_script(asset('js/brief.js'));
page_start(['title' => 'Start a project', 'description' => 'Share your project brief in 2 minutes and get an honest, negotiable quote within 24 hours.', 'active' => 'start']);
?>
<section class="relative overflow-hidden pt-32 sm:pt-36">
  <div class="grid-bg pointer-events-none absolute inset-0"></div>
  <div class="relative mx-auto max-w-3xl px-5 sm:px-6">
    <div class="text-center">
      <p class="kicker">// project brief</p>
      <h1 class="mt-4 text-4xl font-extrabold tracking-tight sm:text-5xl">Let's build something <span class="text-neo-green">great</span></h1>
      <p class="mx-auto mt-4 max-w-xl text-white/60">Takes about 2 minutes. You'll get a reference ID, a confirmation email and a negotiable quote within 24 hours.</p>
    </div>

    <form id="brief" class="card mt-10 p-6 sm:p-9" enctype="multipart/form-data" novalidate>
      <input type="text" name="website_hp" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
      <input type="hidden" name="source" value="brief">

      <div class="mb-8">
        <div class="flex items-center justify-between text-xs text-white/45"><span id="step-label">Step 1 of 4 · Service</span><span id="step-pct">25%</span></div>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/[0.06]"><div id="step-bar" class="h-full w-1/4 rounded-full bg-neo-green transition-all duration-500"></div></div>
      </div>

      <!-- STEP 1 -->
      <fieldset class="step" data-step="1" data-title="Service">
        <legend class="text-xl font-semibold">What do you need?</legend>
        <p class="mt-1 text-sm text-white/50">Pick the closest match — you can explain details next.</p>
        <div class="mt-6 grid gap-3 sm:grid-cols-2">
          <?php foreach ($services as $s): ?>
            <label class="block">
              <input class="chip-input sr-only" type="radio" name="service" value="<?= e($s['slug']) ?>" <?= $pre === $s['slug'] ? 'checked' : '' ?> required>
              <span class="option-card">
                <span class="flex items-center gap-3"><span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-neo-green/10 text-neo-green"><?= icon($s['icon']) ?></span><span><span class="block font-medium"><?= e($s['title']) ?></span><span class="block text-xs text-white/45"><?= e(price_text($s)) ?> · negotiable</span></span></span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <!-- STEP 2 -->
      <fieldset class="step hidden" data-step="2" data-title="Project">
        <legend class="text-xl font-semibold">Tell me about the project</legend>
        <div class="mt-6 grid gap-5">
          <div><label class="label" for="b-title">Project / business name *</label><input id="b-title" class="field" name="project_title" maxlength="190" required placeholder="e.g. Sharma Coaching Classes website"></div>
          <div><label class="label" for="b-desc">What should it do? *</label><textarea id="b-desc" class="field min-h-[140px]" name="description" maxlength="5000" required placeholder="Goals, pages/screens, who it's for, anything you have in mind…"></textarea><p class="mt-1.5 text-xs text-white/35">Minimum 20 characters. Hindi / Hinglish is fine.</p></div>
          <div>
            <span class="label">Features you might need</span>
            <div class="flex flex-wrap gap-2">
              <?php foreach ($features as $i => $f): ?><label><input class="chip-input sr-only" type="checkbox" name="features[]" value="<?= e($f) ?>"><span class="chip"><?= e($f) ?></span></label><?php endforeach; ?>
            </div>
          </div>
          <div class="grid gap-5 sm:grid-cols-2">
            <div><label class="label" for="b-site">Existing website (optional)</label><input id="b-site" class="field" type="url" name="existing_website" maxlength="500" placeholder="https://"></div>
            <div><label class="label" for="b-ref">Reference sites you like (optional)</label><input id="b-ref" class="field" name="reference_links" maxlength="1000" placeholder="links, comma separated"></div>
          </div>
          <div><label class="label" for="b-file">Attach a file (optional)</label><input id="b-file" class="field file:mr-4 file:rounded-full file:border-0 file:bg-neo-green/10 file:px-4 file:py-2 file:text-sm file:text-neo-green" type="file" name="attachment" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.webp,.txt,.zip"><p class="mt-1.5 text-xs text-white/35">PDF, DOC, images, TXT or ZIP · max 8 MB</p></div>
        </div>
      </fieldset>

      <!-- STEP 3 -->
      <fieldset class="step hidden" data-step="3" data-title="Budget">
        <legend class="text-xl font-semibold">Budget & timeline</legend>
        <p class="mt-1 text-sm text-white/50">Just a range — <strong class="text-white">every price is negotiable</strong> and there's a smart scope for every budget.</p>
        <div class="mt-6"><span class="label">Budget range *</span><div class="flex flex-wrap gap-2"><?php foreach ($budgets as $b): ?><label><input class="chip-input sr-only" type="radio" name="budget" value="<?= e($b) ?>" required><span class="chip"><?= e($b) ?></span></label><?php endforeach; ?></div></div>
        <div class="mt-6"><span class="label">When do you need it? *</span><div class="flex flex-wrap gap-2"><?php foreach ($timelines as $t): ?><label><input class="chip-input sr-only" type="radio" name="timeline" value="<?= e($t) ?>" required><span class="chip"><?= e($t) ?></span></label><?php endforeach; ?></div></div>
        <div class="mt-6 max-w-xs"><label class="label" for="b-start">Preferred start date (optional)</label><input id="b-start" class="field [color-scheme:dark]" type="date" name="start_date" min="<?= date('Y-m-d') ?>"></div>
      </fieldset>

      <!-- STEP 4 -->
      <fieldset class="step hidden" data-step="4" data-title="Contact">
        <legend class="text-xl font-semibold">Where should I send the quote?</legend>
        <div class="mt-6 grid gap-5 sm:grid-cols-2">
          <div><label class="label" for="b-name">Full name *</label><input id="b-name" class="field" name="name" maxlength="120" required autocomplete="name"></div>
          <div><label class="label" for="b-email">Email *</label><input id="b-email" class="field" type="email" name="email" maxlength="190" required autocomplete="email"></div>
          <div class="sm:col-span-2 grid grid-cols-[minmax(0,.9fr)_minmax(0,1.6fr)] gap-3">
            <div><label class="label" for="b-cc">Country</label><select id="b-cc" class="field" name="country_code"><?php foreach (country_codes() as $c => $l): ?><option value="<?= e($c) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
            <div><label class="label" for="b-phone">Phone / WhatsApp *</label><input id="b-phone" class="field" type="tel" name="phone" maxlength="20" required autocomplete="tel" placeholder="98765 43210"></div>
          </div>
          <div><label class="label" for="b-company">Company (optional)</label><input id="b-company" class="field" name="company" maxlength="150" autocomplete="organization"></div>
          <div><label class="label" for="b-heard">How did you find me?</label><select id="b-heard" class="field" name="heard_from"><option value="">Select</option><?php foreach (['Google search', 'ChatGPT / AI search', 'Instagram', 'LinkedIn', 'Referral', 'Play Store / Chrome Store', 'Other'] as $h): ?><option><?= e($h) ?></option><?php endforeach; ?></select></div>
          <div class="sm:col-span-2"><span class="label">Preferred contact</span><div class="flex flex-wrap gap-2"><?php foreach (['WhatsApp', 'Phone call', 'Email', 'Google Meet'] as $i => $pc): ?><label><input class="chip-input sr-only" type="radio" name="preferred_contact" value="<?= e($pc) ?>" <?= $i === 0 ? 'checked' : '' ?>><span class="chip"><?= e($pc) ?></span></label><?php endforeach; ?></div></div>
          <div class="sm:col-span-2"><label class="label" for="b-time">Best time to call (optional)</label><input id="b-time" class="field" name="call_time" maxlength="60" placeholder="e.g. Weekdays after 6 PM"></div>
          <label class="flex items-start gap-3 text-sm text-white/60 sm:col-span-2"><input type="checkbox" name="consent" value="1" required class="mt-1 h-4 w-4 accent-[#4ADE80]"> I agree to be contacted about this project. My details are kept private (<a class="text-neo-green hover:underline" href="<?= e(url('privacy/')) ?>" target="_blank">privacy policy</a>).</label>
        </div>
      </fieldset>

      <p id="brief-error" class="mt-6 hidden rounded-2xl border border-red-400/30 bg-red-400/10 px-4 py-3 text-sm text-red-200" role="alert"></p>

      <div id="brief-nav" class="mt-8 flex items-center justify-between gap-3">
        <button type="button" id="b-back" class="btn-ghost btn-sm invisible">Back</button>
        <button type="button" id="b-next" class="btn-primary">Continue <?= icon('arrow', 'h-4 w-4') ?></button>
        <button type="submit" id="b-submit" class="btn-primary hidden">Send my brief <?= icon('send', 'h-4 w-4') ?></button>
      </div>

      <div id="brief-done" class="hidden py-6 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-neo-green/15 text-neo-green"><?= icon('check', 'h-8 w-8') ?></div>
        <h2 class="mt-6 text-2xl font-bold">Brief received — thank you!</h2>
        <p class="mt-2 text-white/60">Your reference ID</p>
        <p id="brief-ref" class="mt-2 font-mono text-3xl font-bold tracking-wider text-neo-green"></p>
        <p class="mx-auto mt-4 max-w-md text-sm text-white/55">A confirmation is on its way to your email. I'll reply with questions or a negotiable quote within 24 hours.</p>
        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
          <a id="brief-track" href="<?= e(url('track/')) ?>" class="btn-primary">Track my request</a>
          <?php if ($wa = preg_replace('/\D+/', '', setting('whatsapp'))): ?><a id="brief-wa" data-wa="<?= e($wa) ?>" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" class="btn-ghost" data-track="whatsapp_click" data-label="brief_done"><?= icon('whatsapp', 'h-4 w-4') ?> Say hi on WhatsApp</a><?php endif; ?>
        </div>
      </div>
    </form>

    <div class="mt-6 grid gap-3 text-center text-sm text-white/50 sm:grid-cols-3">
      <p class="flex items-center justify-center gap-2"><?= icon('lock', 'h-4 w-4 text-neo-green') ?> Private & secure</p>
      <p class="flex items-center justify-center gap-2"><?= icon('clock', 'h-4 w-4 text-neo-green') ?> Reply within 24h</p>
      <p class="flex items-center justify-center gap-2"><?= icon('heart', 'h-4 w-4 text-neo-green') ?> Every price negotiable</p>
    </div>
  </div>
</section>
<?php page_end();

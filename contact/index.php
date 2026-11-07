<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$wa = preg_replace('/\D+/', '', setting('whatsapp'));
page_start(['title' => 'Contact', 'description' => 'Contact Devs Arun for websites, apps, AI agents and AEO/GEO. Reply within 24 hours. Every price negotiable.', 'active' => 'contact']);
?>
<section class="relative overflow-hidden pt-32 sm:pt-40">
  <div class="grid-bg pointer-events-none absolute inset-0"></div>
  <div class="relative mx-auto max-w-6xl px-5 sm:px-6">
    <div class="max-w-2xl"><p class="kicker">// contact</p><h1 class="mt-4 text-4xl font-extrabold tracking-tight sm:text-6xl">Let's <span class="text-neo-green">talk.</span></h1><p class="mt-5 text-lg text-white/60">Quick question or a big idea — message me. For a detailed quote, the <a class="text-neo-green hover:underline" href="<?= e(url('start-project/')) ?>">project brief</a> is fastest.</p></div>
    <div class="mt-12 grid gap-4 lg:grid-cols-[1.2fr_.8fr]">
      <form class="card grid gap-5 p-6 sm:grid-cols-2 sm:p-9" data-ajax="lead/">
        <input type="hidden" name="source" value="contact">
        <input type="text" name="website_hp" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div data-hide-on-success><label class="label" for="c-name">Name</label><input id="c-name" class="field" name="name" required maxlength="120" autocomplete="name"></div>
        <div data-hide-on-success><label class="label" for="c-email">Email</label><input id="c-email" class="field" type="email" name="email" required maxlength="190" autocomplete="email"></div>
        <div data-hide-on-success><label class="label" for="c-phone">Phone / WhatsApp (optional)</label><input id="c-phone" class="field" type="tel" name="phone" maxlength="20" autocomplete="tel"></div>
        <div data-hide-on-success><label class="label" for="c-sub">Subject</label><input id="c-sub" class="field" name="subject" maxlength="190" required placeholder="Project discussion"></div>
        <div data-hide-on-success class="sm:col-span-2"><label class="label" for="c-msg">Message</label><textarea id="c-msg" class="field min-h-[160px]" name="message" required maxlength="5000" placeholder="Tell me what you need…"></textarea></div>
        <div data-hide-on-success class="sm:col-span-2"><button type="submit" class="btn-primary w-full sm:w-auto">Send message <?= icon('send', 'h-4 w-4') ?></button></div>
        <div data-success class="hidden rounded-3xl border border-neo-green/30 bg-neo-green/10 p-6 text-center sm:col-span-2"><p class="text-lg font-semibold text-neo-green">Message sent ✅</p><p class="mt-1 text-sm text-white/70">Reference <strong data-ref></strong>. I'll reply within 24 hours.</p></div>
      </form>
      <div class="grid gap-4">
        <div class="card p-6">
          <h2 class="font-semibold">Quick information</h2>
          <ul class="mt-5 space-y-5 text-sm">
            <li class="flex gap-4"><span class="flex h-11 w-11 flex-none items-center justify-center rounded-2xl bg-neo-green/10 text-neo-green"><?= icon('mail') ?></span><span><span class="block text-white/45">Email</span><a class="break-all font-medium hover:text-neo-green" href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></span></li>
            <?php if ($wa): ?><li class="flex gap-4"><span class="flex h-11 w-11 flex-none items-center justify-center rounded-2xl bg-neo-green/10 text-neo-green"><?= icon('whatsapp') ?></span><span><span class="block text-white/45">WhatsApp</span><a class="font-medium hover:text-neo-green" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" data-track="whatsapp_click" data-label="contact"><?= e(setting('whatsapp')) ?></a></span></li><?php endif; ?>
            <li class="flex gap-4"><span class="flex h-11 w-11 flex-none items-center justify-center rounded-2xl bg-neo-green/10 text-neo-green"><?= icon('pin') ?></span><span><span class="block text-white/45">Location</span><span class="font-medium"><?= e(setting('location')) ?></span></span></li>
            <li class="flex gap-4"><span class="flex h-11 w-11 flex-none items-center justify-center rounded-2xl bg-neo-green/10 text-neo-green"><?= icon('clock') ?></span><span><span class="block text-white/45">Response time</span><span class="font-medium"><?= e(setting('response_time', 'Within 24 hours')) ?></span></span></li>
          </ul>
        </div>
        <div class="card p-6">
          <h2 class="font-semibold">Prefer to chat?</h2>
          <p class="mt-2 text-sm text-white/55">My AI assistant knows every service and price — ask anything, any time.</p>
          <button type="button" class="btn-ghost btn-sm mt-4" data-open-chat><?= icon('bot', 'h-4 w-4') ?> Open AI assistant</button>
        </div>
      </div>
    </div>
  </div>
</section>
<?php page_end();

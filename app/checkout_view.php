<?php
declare(strict_types=1);

/**
 * Shared checkout UI for service bookings (/checkout/) and negotiated quotes (/pay/).
 * $item = ['type' => 'service'|'quote', 'key' => slug|token, 'title', 'desc', 'amount' (INR int), 'lines' => [], 'prefill' => []]
 */
function render_checkout(array $item): void
{
    page_script('https://checkout.razorpay.com/v1/checkout.js');
    page_script(asset('js/checkout.js'));
    page_start(['title' => 'Secure checkout — ' . $item['title'], 'noindex' => true]);
    $pre = $item['prefill'] ?? [];
    ?>
    <section class="pt-32 sm:pt-40">
      <div class="mx-auto grid max-w-5xl gap-6 px-5 sm:px-6 lg:grid-cols-[1fr_.9fr]">
        <div class="card p-7 sm:p-9">
          <p class="kicker">// <?= $item['type'] === 'quote' ? 'your quote' : 'book online' ?></p>
          <h1 class="mt-3 text-3xl font-extrabold tracking-tight"><?= e($item['title']) ?></h1>
          <?php if (!empty($item['desc'])): ?><p class="mt-3 leading-relaxed text-white/60"><?= nl2br(e($item['desc'])) ?></p><?php endif; ?>
          <?php if (!empty($item['lines'])): ?>
            <ul class="mt-6 space-y-2.5 text-sm text-white/70"><?php foreach ($item['lines'] as $l): ?><li class="flex gap-2"><span class="text-neo-green"><?= icon('check', 'h-4 w-4') ?></span><?= e($l) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <div class="mt-8 rounded-3xl border border-white/[0.07] bg-white/[0.02] p-5 text-sm text-white/60">
            <p class="font-medium text-white">How this works</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5">
              <li>Pay securely via Razorpay (UPI, cards, netbanking, wallets).</li>
              <li>You get an instant receipt by email.</li>
              <li>I contact you within 24 hours to collect details and kick off.</li>
            </ol>
            <?php if ($item['type'] === 'service'): ?><p class="mt-3">Want a different scope or price? <a class="text-neo-green hover:underline" href="<?= e(url('start-project/?service=' . $item['key'])) ?>">Send a brief instead</a> — every price is negotiable.</p><?php endif; ?>
          </div>
        </div>

        <div class="card h-fit p-7 sm:p-9">
          <div class="flex items-end justify-between border-b border-white/[0.06] pb-5">
            <span class="text-sm text-white/50">Amount payable</span>
            <span class="text-3xl font-extrabold text-neo-green"><?= e(inr($item['amount'])) ?></span>
          </div>
          <?php if (!razorpay_enabled()): ?>
            <div class="mt-6 rounded-2xl border border-amber-400/30 bg-amber-400/10 p-4 text-sm text-amber-100">Online payments are being set up right now. Please <a class="font-semibold underline" href="<?= e(url('start-project/')) ?>">send your brief</a> or message on WhatsApp — I'll share a payment link.</div>
          <?php else: ?>
          <form id="checkout" class="mt-6 grid gap-4" data-type="<?= e($item['type']) ?>" data-key="<?= e($item['key']) ?>" novalidate>
            <div><label class="label" for="c-name">Full name</label><input id="c-name" class="field" name="name" maxlength="120" required value="<?= e($pre['name'] ?? '') ?>" autocomplete="name"></div>
            <div><label class="label" for="c-email">Email</label><input id="c-email" class="field" type="email" name="email" maxlength="190" required value="<?= e($pre['email'] ?? '') ?>" autocomplete="email"></div>
            <div><label class="label" for="c-phone">Phone / WhatsApp</label><input id="c-phone" class="field" type="tel" name="phone" maxlength="20" required value="<?= e($pre['phone'] ?? '') ?>" autocomplete="tel"></div>
            <div><label class="label" for="c-notes">Notes (optional)</label><textarea id="c-notes" class="field min-h-[90px]" name="notes" maxlength="1000" placeholder="Website URL, deadline, anything I should know"></textarea></div>
            <label class="flex items-start gap-3 text-xs text-white/55"><input type="checkbox" name="agree" value="1" required class="mt-0.5 h-4 w-4 accent-[#4ADE80]"> I agree to the <a class="text-neo-green hover:underline" href="<?= e(url('terms/')) ?>" target="_blank">terms</a> and <a class="text-neo-green hover:underline" href="<?= e(url('refund-policy/')) ?>" target="_blank">refund policy</a>.</label>
            <p id="checkout-error" class="hidden rounded-2xl border border-red-400/30 bg-red-400/10 px-4 py-3 text-sm text-red-200" role="alert"></p>
            <button type="submit" class="btn-primary w-full"><?= icon('lock', 'h-4 w-4') ?> Pay <?= e(inr($item['amount'])) ?> securely</button>
            <p class="text-center text-xs text-white/35">Payments processed by Razorpay. Card details never touch this website.</p>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </section>
    <?php
    page_end();
}

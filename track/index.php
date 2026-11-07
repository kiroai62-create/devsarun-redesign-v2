<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

/* /track/ — clients check request status with reference ID + email. */
$ref = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string)($_REQUEST['ref'] ?? '')));
$email = strtolower(trim((string)($_REQUEST['email'] ?? '')));
$lead = null;
$error = '';
$updates = [];
$quotes = [];

if ($ref !== '' && $email !== '') {
    if (rate_limited('track', 20, 600)) {
        $error = 'Too many lookups. Please try again in a few minutes.';
    } else {
        $row = db_row('SELECT * FROM leads WHERE ref = ? LIMIT 1', [$ref]);
        if ($row && hash_equals(strtolower($row['email']), $email)) {
            $lead = $row;
            $updates = db_all('SELECT type, body, created_at FROM lead_activities WHERE lead_id = ? AND is_public = 1 ORDER BY id DESC', [$lead['id']]);
            $quotes = db_all("SELECT token, title, amount_paise, status, valid_until FROM quotes WHERE lead_id = ? AND status IN ('sent','paid') ORDER BY id DESC", [$lead['id']]);
            track_event('track_lookup', $ref);
        } else {
            $error = 'No request found for that reference ID and email.';
        }
    }
}
page_start(['title' => 'Track your request', 'noindex' => true]);
$flow = ['new', 'contacted', 'proposal', 'in_progress', 'won'];
?>
<section class="pt-32 sm:pt-40">
  <div class="mx-auto max-w-3xl px-5 sm:px-6">
    <div class="text-center"><p class="kicker">// track</p><h1 class="mt-4 text-4xl font-extrabold tracking-tight sm:text-5xl">Track your <span class="text-neo-green">request</span></h1><p class="mt-4 text-white/60">Enter the reference ID from your confirmation email.</p></div>
    <form method="get" class="card mt-10 grid gap-4 p-6 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
      <div><label class="label" for="t-ref">Reference ID</label><input id="t-ref" class="field font-mono uppercase" name="ref" value="<?= e($ref) ?>" placeholder="DA-26XXXXXX" required maxlength="20"></div>
      <div><label class="label" for="t-email">Email</label><input id="t-email" class="field" type="email" name="email" value="<?= e($email) ?>" required maxlength="190"></div>
      <button class="btn-primary" type="submit">Check</button>
    </form>
    <?php if ($error): ?><p class="mt-6 rounded-2xl border border-red-400/30 bg-red-400/10 px-5 py-4 text-sm text-red-200"><?= e($error) ?></p><?php endif; ?>

    <?php if ($lead): $statusIdx = array_search($lead['status'], $flow, true); ?>
      <div class="card mt-6 p-6 sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div><p class="font-mono text-sm text-neo-green"><?= e($lead['ref']) ?></p><h2 class="mt-1 text-xl font-semibold"><?= e($lead['project_title'] ?: $lead['service_label'] ?: 'Your request') ?></h2><p class="text-sm text-white/45">Submitted <?= e(date('d M Y', strtotime($lead['created_at']))) ?></p></div>
          <?= status_badge($lead['status']) ?>
        </div>
        <p class="mt-6 rounded-2xl bg-white/[0.04] px-5 py-4 text-white/80"><?= e(LEAD_STATUS_PUBLIC[$lead['status']] ?? 'In review') ?></p>
        <?php if ($statusIdx !== false): ?>
        <ol class="mt-8 grid grid-cols-5 gap-2 text-center text-[11px] text-white/45">
          <?php foreach (['Received', 'Contacted', 'Quote', 'In progress', 'Done'] as $i => $label): ?>
            <li><div class="mx-auto h-1.5 rounded-full <?= $i <= $statusIdx ? 'bg-neo-green' : 'bg-white/10' ?>"></div><span class="mt-2 block <?= $i <= $statusIdx ? 'text-white/80' : '' ?>"><?= e($label) ?></span></li>
          <?php endforeach; ?>
        </ol>
        <?php endif; ?>

        <?php foreach ($quotes as $q): ?>
          <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-neo-green/25 bg-neo-green/[0.06] p-5">
            <div><p class="text-sm text-white/55">Quote</p><p class="font-semibold"><?= e($q['title']) ?> · <span class="text-neo-green"><?= e(inr($q['amount_paise'] / 100)) ?></span></p></div>
            <?php if ($q['status'] === 'paid'): ?><?= status_badge('paid') ?><?php else: ?><a href="<?= e(url('pay/' . $q['token'] . '/')) ?>" class="btn-primary btn-sm">Pay securely</a><?php endif; ?>
          </div>
        <?php endforeach; ?>

        <?php if ($updates): ?>
          <h3 class="mt-8 font-semibold">Updates</h3>
          <ol class="mt-4 space-y-4 border-l border-white/10 pl-5">
            <?php foreach ($updates as $u): ?><li class="relative"><span class="absolute -left-[25px] top-1.5 h-2.5 w-2.5 rounded-full bg-neo-green"></span><p class="text-sm text-white/80"><?= nl2br(e($u['body'])) ?></p><p class="mt-1 text-xs text-white/35"><?= e(date('d M Y, h:i A', strtotime($u['created_at']))) ?></p></li><?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php page_end();

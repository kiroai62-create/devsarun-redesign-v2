<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/admin_boot.php';
require_admin();

$v30 = (int)db_val('SELECT COUNT(DISTINCT visitor_id) FROM page_views WHERE created_at >= (NOW() - INTERVAL 30 DAY)');
$vToday = (int)db_val('SELECT COUNT(DISTINCT visitor_id) FROM page_views WHERE created_at >= CURDATE()');
$pv30 = (int)db_val('SELECT COUNT(*) FROM page_views WHERE created_at >= (NOW() - INTERVAL 30 DAY)');
$leadsAll = (int)db_val('SELECT COUNT(*) FROM leads');
$leads30 = (int)db_val('SELECT COUNT(*) FROM leads WHERE created_at >= (NOW() - INTERVAL 30 DAY)');
$leadsNew = (int)db_val("SELECT COUNT(*) FROM leads WHERE status = 'new'");
$won = (int)db_val("SELECT COUNT(*) FROM leads WHERE status = 'won'");
$revenueAll = (int)db_val("SELECT COALESCE(SUM(amount_paise),0) FROM orders WHERE status = 'paid'") / 100;
$revenue30 = (int)db_val("SELECT COALESCE(SUM(amount_paise),0) FROM orders WHERE status = 'paid' AND paid_at >= (NOW() - INTERVAL 30 DAY)") / 100;
$pipeline = (int)db_val("SELECT COALESCE(SUM(value_estimate),0) FROM leads WHERE status NOT IN ('won','lost','spam')");
$chats30 = (int)db_val('SELECT COUNT(*) FROM chat_sessions WHERE created_at >= (NOW() - INTERVAL 30 DAY)');
$brochures30 = (int)db_val("SELECT COUNT(*) FROM events WHERE name = 'brochure_download' AND created_at >= (NOW() - INTERVAL 30 DAY)");
$formStarts30 = (int)db_val("SELECT COUNT(DISTINCT visitor_id) FROM events WHERE name = 'form_start' AND created_at >= (NOW() - INTERVAL 30 DAY)");
$conv = $v30 > 0 ? round($leads30 / $v30 * 100, 1) : 0;

// 30-day series
$days = [];
for ($i = 29; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i day"))] = ['v' => 0, 'l' => 0];
}
foreach (db_all('SELECT DATE(created_at) d, COUNT(DISTINCT visitor_id) c FROM page_views WHERE created_at >= (CURDATE() - INTERVAL 29 DAY) GROUP BY d') as $r) {
    if (isset($days[$r['d']])) $days[$r['d']]['v'] = (int)$r['c'];
}
foreach (db_all('SELECT DATE(created_at) d, COUNT(*) c FROM leads WHERE created_at >= (CURDATE() - INTERVAL 29 DAY) GROUP BY d') as $r) {
    if (isset($days[$r['d']])) $days[$r['d']]['l'] = (int)$r['c'];
}
$lineCfg = ['type' => 'line', 'data' => ['labels' => array_map(static fn($d) => date('d M', strtotime($d)), array_keys($days)), 'datasets' => [
    ['label' => 'Visitors', 'data' => array_column($days, 'v')], ['label' => 'Leads', 'data' => array_column($days, 'l'), 'yAxisID' => 'y1'],
]], 'options' => ['scales' => ['y' => ['beginAtZero' => true], 'y1' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['display' => false]]]]];

$bySource = db_all('SELECT source, COUNT(*) c FROM leads GROUP BY source ORDER BY c DESC');
$srcCfg = ['type' => 'doughnut', 'data' => ['labels' => array_map(static fn($r) => ucfirst($r['source']), $bySource), 'datasets' => [['data' => array_map('intval', array_column($bySource, 'c'))]]], 'options' => ['cutout' => '68%']];
$byService = db_all("SELECT COALESCE(service_label,'Other') s, COUNT(*) c FROM leads GROUP BY s ORDER BY c DESC LIMIT 8");
$svcCfg = ['type' => 'bar', 'data' => ['labels' => array_column($byService, 's'), 'datasets' => [['label' => 'Leads', 'data' => array_map('intval', array_column($byService, 'c'))]]], 'options' => ['indexAxis' => 'y']];

$recent = db_all('SELECT id, ref, name, email, service_label, budget, status, source, created_at FROM leads ORDER BY id DESC LIMIT 8');
$followUps = db_all("SELECT id, ref, name, follow_up_at, status FROM leads WHERE follow_up_at IS NOT NULL AND follow_up_at <= CURDATE() AND status NOT IN ('won','lost','spam') ORDER BY follow_up_at LIMIT 8");
$payments = db_all("SELECT order_no, item_title, amount_paise, customer_name, paid_at FROM orders WHERE status = 'paid' ORDER BY paid_at DESC LIMIT 5");

$setupWarnings = [];
if (!smtp_configured()) $setupWarnings[] = ['SMTP email is not configured — client confirmations use PHP mail() fallback.', 'admin/settings/?tab=email'];
if (!razorpay_enabled()) $setupWarnings[] = ['Razorpay is disabled — online booking & quote payments are off.', 'admin/settings/?tab=payments'];
if (!ai_configured()) $setupWarnings[] = ['AI provider key not set — the assistant is using the built-in rules engine.', 'admin/settings/?tab=ai'];

admin_start('Dashboard', 'dashboard');
?>
<?php if ($setupWarnings): ?>
  <div class="mb-6 grid gap-2">
    <?php foreach ($setupWarnings as [$msg, $href]): ?><a href="<?= e(url($href)) ?>" class="flex items-center justify-between gap-3 rounded-2xl border border-amber-400/20 bg-amber-400/[0.06] px-4 py-3 text-sm text-amber-100 hover:border-amber-400/40"><span>⚠️ <?= e($msg) ?></span><span class="text-xs text-amber-200/70">Configure →</span></a><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
  <?= admin_stat('Visitors (30d)', number_format($v30), $vToday . ' today · ' . number_format($pv30) . ' page views', 'users') ?>
  <?= admin_stat('Leads (30d)', (string)$leads30, $leadsNew . ' new · ' . $leadsAll . ' all time', 'pen') ?>
  <?= admin_stat('Conversion', $conv . '%', 'visitors → leads (30d)', 'chart') ?>
  <?= admin_stat('Revenue (30d)', inr($revenue30), inr($revenueAll) . ' all time', 'lock') ?>
  <?= admin_stat('Open pipeline', inr($pipeline), 'estimated value of open leads', 'briefcase') ?>
  <?= admin_stat('Won deals', (string)$won, 'leads marked won', 'star') ?>
  <?= admin_stat('AI chats (30d)', (string)$chats30, 'conversations started', 'bot') ?>
  <?= admin_stat('Brochure downloads', (string)$brochures30, 'last 30 days', 'download') ?>
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-3">
  <div class="a-card xl:col-span-2"><div class="flex items-center justify-between"><h2 class="font-semibold">Visitors vs leads · 30 days</h2><a class="text-xs text-neo-green" href="<?= e(url('admin/analytics/')) ?>">Analytics →</a></div><div class="mt-4 h-72"><canvas data-chart="c-line"></canvas></div><script type="application/json" id="c-line"><?= json_encode($lineCfg, JSON_HEX_TAG) ?></script></div>
  <div class="a-card">
    <h2 class="font-semibold">Funnel · 30 days</h2>
    <?php $funnel = [['Visitors', $v30], ['Started a form', $formStarts30], ['Became leads', $leads30], ['Won', (int)db_val("SELECT COUNT(*) FROM leads WHERE status='won' AND created_at >= (NOW() - INTERVAL 30 DAY)")]]; $max = max(1, $v30); ?>
    <div class="mt-5 space-y-4">
      <?php foreach ($funnel as [$l, $n]): ?><div><div class="mb-1.5 flex justify-between text-sm"><span class="text-white/60"><?= e($l) ?></span><span class="font-semibold"><?= number_format($n) ?></span></div><div class="h-2 rounded-full bg-white/[0.05]"><div class="h-full rounded-full bg-neo-green" style="width:<?= max(2, min(100, round($n / $max * 100))) ?>%"></div></div></div><?php endforeach; ?>
    </div>
  </div>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
  <div class="a-card"><h2 class="font-semibold">Leads by source</h2><div class="mt-4 h-60"><?php if ($bySource): ?><canvas data-chart="c-src"></canvas><?php else: ?><p class="text-sm text-white/40">No leads yet.</p><?php endif; ?></div><script type="application/json" id="c-src"><?= json_encode($srcCfg, JSON_HEX_TAG) ?></script></div>
  <div class="a-card"><h2 class="font-semibold">Leads by service</h2><div class="mt-4 h-60"><?php if ($byService): ?><canvas data-chart="c-svc"></canvas><?php else: ?><p class="text-sm text-white/40">No leads yet.</p><?php endif; ?></div><script type="application/json" id="c-svc"><?= json_encode($svcCfg, JSON_HEX_TAG) ?></script></div>
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-3">
  <div class="a-card overflow-x-auto p-0 xl:col-span-2">
    <div class="flex items-center justify-between px-5 pt-5"><h2 class="font-semibold">Latest leads</h2><a class="text-xs text-neo-green" href="<?= e(url('admin/leads/')) ?>">All leads →</a></div>
    <table class="a-table mt-3"><thead><tr><th>Lead</th><th>Service</th><th>Budget</th><th>Status</th><th>When</th></tr></thead><tbody>
      <?php foreach ($recent as $l): ?><tr><td><a class="font-medium text-white hover:text-neo-green" href="<?= e(url('admin/leads/view/?id=' . $l['id'])) ?>"><?= e($l['name']) ?></a><span class="block font-mono text-[11px] text-white/35"><?= e($l['ref']) ?> · <?= e($l['source']) ?></span></td><td><?= e($l['service_label'] ?: '—') ?></td><td><?= e($l['budget'] ?: '—') ?></td><td><?= status_badge($l['status']) ?></td><td class="whitespace-nowrap text-white/45"><?= e(time_ago($l['created_at'])) ?></td></tr><?php endforeach; ?>
      <?php if (!$recent): ?><tr><td colspan="5" class="py-8 text-center text-white/40">No leads yet — share your site and they'll show up here.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
  <div class="grid gap-4">
    <div class="a-card"><h2 class="font-semibold">Follow-ups due</h2>
      <ul class="mt-3 space-y-2 text-sm"><?php foreach ($followUps as $f): ?><li class="flex items-center justify-between gap-2"><a class="hover:text-neo-green" href="<?= e(url('admin/leads/view/?id=' . $f['id'])) ?>"><?= e($f['name']) ?> <span class="font-mono text-[11px] text-white/35"><?= e($f['ref']) ?></span></a><span class="text-xs <?= $f['follow_up_at'] < date('Y-m-d') ? 'text-red-300' : 'text-amber-200' ?>"><?= e(date('d M', strtotime($f['follow_up_at']))) ?></span></li><?php endforeach; ?>
      <?php if (!$followUps): ?><li class="text-white/40">Nothing due. 🎉</li><?php endif; ?></ul></div>
    <div class="a-card"><h2 class="font-semibold">Recent payments</h2>
      <ul class="mt-3 space-y-2 text-sm"><?php foreach ($payments as $p): ?><li class="flex justify-between gap-2"><span class="truncate"><?= e($p['customer_name']) ?> <span class="block truncate text-xs text-white/40"><?= e($p['item_title']) ?></span></span><span class="font-semibold text-neo-green"><?= e(inr($p['amount_paise'] / 100)) ?></span></li><?php endforeach; ?>
      <?php if (!$payments): ?><li class="text-white/40">No payments yet.</li><?php endif; ?></ul></div>
  </div>
</div>
<?php admin_end(['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js']);

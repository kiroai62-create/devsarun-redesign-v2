<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/admin_boot.php';
require APP . '/lead_filters.php';
require_admin();

[$where, $params] = lead_filters();
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 25;
$total = (int)db_val("SELECT COUNT(*) FROM leads WHERE $where", $params);
$rows = db_all("SELECT * FROM leads WHERE $where ORDER BY FIELD(priority,'high','normal','low'), id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
$counts = [];
foreach (db_all('SELECT status, COUNT(*) c FROM leads GROUP BY status') as $r) {
    $counts[$r['status']] = (int)$r['c'];
}
$q = array_filter(['q' => $_GET['q'] ?? '', 'status' => $_GET['status'] ?? '', 'source' => $_GET['source'] ?? '', 'service' => $_GET['service'] ?? '', 'from' => $_GET['from'] ?? '', 'to' => $_GET['to'] ?? '']);
admin_start('Leads & CRM', 'leads');
?>
<div class="flex gap-2 overflow-x-auto pb-2">
  <a href="?" class="chip flex-none <?= empty($_GET['status']) ? 'is-active' : '' ?>">All <span class="text-white/35"><?= array_sum($counts) ?></span></a>
  <a href="?status=open" class="chip flex-none <?= ($_GET['status'] ?? '') === 'open' ? 'is-active' : '' ?>">Open</a>
  <?php foreach (LEAD_STATUSES as $s): ?><a href="?status=<?= e($s) ?>" class="chip flex-none <?= ($_GET['status'] ?? '') === $s ? 'is-active' : '' ?>"><?= e(ucwords(str_replace('_', ' ', $s))) ?> <span class="text-white/35"><?= (int)($counts[$s] ?? 0) ?></span></a><?php endforeach; ?>
</div>

<form method="get" class="a-card mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
  <?php if (!empty($_GET['status'])): ?><input type="hidden" name="status" value="<?= e((string)$_GET['status']) ?>"><?php endif; ?>
  <input class="a-input lg:col-span-2" type="search" name="q" value="<?= e((string)($_GET['q'] ?? '')) ?>" placeholder="Name, email, phone, ref, company…">
  <select class="a-input" name="source"><option value="">All sources</option><?php foreach (['brief', 'enquiry', 'contact', 'chat', 'audit', 'checkout'] as $s): ?><option value="<?= e($s) ?>" <?= ($_GET['source'] ?? '') === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select>
  <select class="a-input" name="service"><option value="">All services</option><?php foreach (db_all('SELECT id, title FROM services ORDER BY sort_order') as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)($_GET['service'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?></option><?php endforeach; ?></select>
  <input class="a-input [color-scheme:dark]" type="date" name="from" value="<?= e((string)($_GET['from'] ?? '')) ?>" title="From">
  <div class="flex gap-2"><input class="a-input [color-scheme:dark]" type="date" name="to" value="<?= e((string)($_GET['to'] ?? '')) ?>" title="To"><button class="a-btn" type="submit">Filter</button></div>
</form>

<div class="mt-3 flex items-center justify-between">
  <p class="text-sm text-white/45"><?= $total ?> lead<?= $total === 1 ? '' : 's' ?></p>
  <a class="a-btn-ghost" href="<?= e(url('admin/leads/export/?' . http_build_query($q))) ?>"><?= icon('download', 'h-4 w-4') ?> Export CSV</a>
</div>

<div class="a-card mt-3 overflow-x-auto p-0">
  <table class="a-table">
    <thead><tr><th>Lead</th><th>Contact</th><th>Service / project</th><th>Budget</th><th>Status</th><th>Source</th><th>Received</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $l): ?>
      <tr>
        <td><a href="<?= e(url('admin/leads/view/?id=' . $l['id'])) ?>" class="font-medium text-white hover:text-neo-green"><?= e($l['name']) ?></a><?php if ($l['priority'] === 'high'): ?> <span class="ml-1 rounded-full bg-red-400/15 px-1.5 py-0.5 text-[10px] text-red-300">HIGH</span><?php endif; ?><span class="block font-mono text-[11px] text-white/35"><?= e($l['ref']) ?></span></td>
        <td class="text-xs"><span class="block"><?= e($l['email']) ?></span><span class="text-white/45"><?= e(trim(($l['country_code'] ?? '') . ' ' . ($l['phone'] ?? ''))) ?></span></td>
        <td><span class="block"><?= e($l['service_label'] ?: '—') ?></span><span class="block max-w-[220px] truncate text-xs text-white/40"><?= e($l['project_title']) ?></span></td>
        <td class="whitespace-nowrap"><?= e($l['budget'] ?: '—') ?><?php if ($l['value_estimate']): ?><span class="block text-xs text-neo-green"><?= e(inr((int)$l['value_estimate'])) ?></span><?php endif; ?></td>
        <td><?= status_badge($l['status']) ?><?php if ($l['follow_up_at']): ?><span class="mt-1 block text-[11px] <?= $l['follow_up_at'] <= date('Y-m-d') ? 'text-amber-200' : 'text-white/35' ?>">↻ <?= e(date('d M', strtotime($l['follow_up_at']))) ?></span><?php endif; ?></td>
        <td class="text-xs text-white/55"><?= e(ucfirst($l['source'])) ?><?php if ($l['utm_source'] || $l['referrer']): ?><span class="block text-white/35"><?= e($l['utm_source'] ?: $l['referrer']) ?></span><?php endif; ?></td>
        <td class="whitespace-nowrap text-xs text-white/45"><?= e(date('d M Y', strtotime($l['created_at']))) ?><span class="block"><?= e(time_ago($l['created_at'])) ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="py-12 text-center text-white/40">No leads match these filters.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?= paginate($total, $per, $page, $q) ?>
<?php admin_end();

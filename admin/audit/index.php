<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/admin_boot.php';
require_admin('manager');

$page = max(1, (int)($_GET['page'] ?? 1));
$per = 50;
$total = (int)db_val('SELECT COUNT(*) FROM audit_log');
$rows = db_all("SELECT a.*, u.name, u.email FROM audit_log a LEFT JOIN admin_users u ON u.id = a.admin_id ORDER BY a.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
admin_start('Audit log', 'audit');
?>
<p class="text-sm text-white/45">Every sensitive admin action is recorded (who, what, when). IPs are stored as one-way hashes.</p>
<div class="a-card mt-4 overflow-x-auto p-0">
  <table class="a-table"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Target</th><th>Details</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td class="whitespace-nowrap text-xs text-white/50"><?= e(date('d M Y, h:i:s A', strtotime($r['created_at']))) ?></td><td><?= e($r['name'] ?: 'System / visitor') ?><span class="block text-xs text-white/35"><?= e($r['email']) ?></span></td><td class="font-mono text-xs text-neo-green"><?= e($r['action']) ?></td><td class="text-xs"><?= e($r['entity']) ?><?= $r['entity_id'] ? ' #' . (int)$r['entity_id'] : '' ?></td><td class="max-w-xs truncate font-mono text-[11px] text-white/40"><?= e((string)$r['meta']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="py-10 text-center text-white/40">Nothing yet.</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?= paginate($total, $per, $page) ?>
<?php admin_end();

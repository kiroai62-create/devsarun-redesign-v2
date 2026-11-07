<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/admin_boot.php';
require_admin();

$page = max(1, (int)($_GET['page'] ?? 1));
$per = 30;
$onlyLeads = !empty($_GET['leads']);
$where = $onlyLeads ? 'WHERE cs.lead_id IS NOT NULL' : '';
$total = (int)db_val("SELECT COUNT(*) FROM chat_sessions cs $where");
$rows = db_all("SELECT cs.*, (SELECT content FROM chat_messages m WHERE m.session_id = cs.id AND m.role = 'user' ORDER BY m.id LIMIT 1) first_msg, l.ref lead_ref FROM chat_sessions cs LEFT JOIN leads l ON l.id = cs.lead_id $where ORDER BY cs.last_at DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$engineStats = db_all('SELECT COALESCE(engine, "—") engine, COUNT(*) c FROM chat_sessions GROUP BY engine');
admin_start('AI chats', 'chats');
?>
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
  <?= admin_stat('Conversations', (string)$total, 'all time', 'chat') ?>
  <?= admin_stat('Leads captured', (string)db_val('SELECT COUNT(*) FROM chat_sessions WHERE lead_id IS NOT NULL'), 'shared email / phone', 'users') ?>
  <?= admin_stat('Messages (30d)', (string)db_val('SELECT COUNT(*) FROM chat_messages WHERE created_at >= (NOW() - INTERVAL 30 DAY)'), '', 'send') ?>
  <?= admin_stat('Engine', ai_configured() ? ucfirst(setting('ai_provider')) : 'Rules', implode(' · ', array_map(static fn($r) => $r['engine'] . ': ' . $r['c'], $engineStats)), 'bot') ?>
</div>
<div class="mt-4 flex gap-2"><a href="?" class="chip <?= !$onlyLeads ? 'is-active' : '' ?>">All</a><a href="?leads=1" class="chip <?= $onlyLeads ? 'is-active' : '' ?>">With lead</a><a href="<?= e(url('admin/settings/?tab=ai')) ?>" class="a-btn-ghost ml-auto">Train / configure AI →</a></div>
<div class="a-card mt-3 overflow-x-auto p-0">
  <table class="a-table"><thead><tr><th>Visitor</th><th>First message</th><th>Messages</th><th>Page</th><th>Last active</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><a class="font-medium text-white hover:text-neo-green" href="<?= e(url('admin/chats/view/?id=' . $r['id'])) ?>"><?= e($r['name'] ?: ($r['email'] ?: 'Anonymous #' . $r['id'])) ?></a><?php if ($r['lead_ref']): ?><span class="block font-mono text-[11px] text-neo-green"><?= e($r['lead_ref']) ?></span><?php endif; ?><?php if ($r['phone']): ?><span class="block text-xs text-white/40"><?= e($r['phone']) ?></span><?php endif; ?></td>
      <td class="max-w-md"><span class="line-clamp-2"><?= e(mb_substr((string)$r['first_msg'], 0, 160)) ?></span></td>
      <td><?= (int)$r['messages_count'] ?></td>
      <td class="text-xs text-white/45"><?= e($r['page'] ?: '—') ?></td>
      <td class="whitespace-nowrap text-xs text-white/45"><?= e(time_ago($r['last_at'])) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="py-12 text-center text-white/40">No conversations yet.</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?= paginate($total, $per, $page, $onlyLeads ? ['leads' => 1] : []) ?>
<?php admin_end();

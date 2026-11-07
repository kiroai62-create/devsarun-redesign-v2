<?php
declare(strict_types=1);
require dirname(__DIR__, 3) . '/app/admin_boot.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$s = db_row('SELECT cs.*, l.ref lead_ref FROM chat_sessions cs LEFT JOIN leads l ON l.id = cs.lead_id WHERE cs.id = ?', [$id]);
if (!$s) {
    flash('error', 'Conversation not found.');
    redirect('admin/chats/');
}
if (is_post() && ($_POST['action'] ?? '') === 'delete') {
    admin_post_guard('owner');
    db_exec('DELETE FROM chat_messages WHERE session_id = ?', [$id]);
    db_exec('DELETE FROM chat_sessions WHERE id = ?', [$id]);
    audit('chat.delete', 'chat', $id);
    flash('success', 'Conversation deleted.');
    redirect('admin/chats/');
}
$msgs = db_all('SELECT role, content, created_at FROM chat_messages WHERE session_id = ? ORDER BY id', [$id]);
admin_start('Conversation #' . $id, 'chats');
?>
<a href="<?= e(url('admin/chats/')) ?>" class="text-xs text-white/40 hover:text-white">← All chats</a>
<div class="mt-3 grid gap-4 lg:grid-cols-[1fr_300px]">
  <div class="a-card flex flex-col gap-3">
    <?php foreach ($msgs as $m): ?>
      <div class="<?= $m['role'] === 'user' ? 'msg msg-user' : 'msg msg-bot' ?>"><?= $m['role'] === 'user' ? e($m['content']) : chat_format($m['content']) ?><span class="mt-1 block text-[10px] opacity-50"><?= e(date('d M, h:i A', strtotime($m['created_at']))) ?></span></div>
    <?php endforeach; ?>
  </div>
  <div class="space-y-4">
    <div class="a-card text-sm">
      <h3 class="font-semibold">Visitor</h3>
      <dl class="mt-3 space-y-2 text-white/70">
        <div><dt class="text-xs text-white/40">Name</dt><dd><?= e($s['name'] ?: '—') ?></dd></div>
        <div><dt class="text-xs text-white/40">Email</dt><dd><?= e($s['email'] ?: '—') ?></dd></div>
        <div><dt class="text-xs text-white/40">Phone</dt><dd><?= e($s['phone'] ?: '—') ?></dd></div>
        <div><dt class="text-xs text-white/40">Started on</dt><dd><?= e($s['page'] ?: '—') ?> · <?= e(date('d M Y, h:i A', strtotime($s['created_at']))) ?></dd></div>
        <div><dt class="text-xs text-white/40">Engine</dt><dd><?= e($s['engine'] ?: '—') ?></dd></div>
      </dl>
      <?php if ($s['lead_id']): ?><a class="a-btn mt-4 w-full" href="<?= e(url('admin/leads/view/?id=' . $s['lead_id'])) ?>">Open lead <?= e($s['lead_ref']) ?></a><?php endif; ?>
    </div>
    <?php if (can('owner')): ?><form method="post" data-confirm="Delete this conversation?" class="a-card"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="a-btn-danger w-full">Delete conversation</button></form><?php endif; ?>
  </div>
</div>
<?php admin_end();

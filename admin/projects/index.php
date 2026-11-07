<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/admin_boot.php';
require_admin();

if (is_post()) {
    admin_post_guard('manager');
    $id = (int)($_POST['id'] ?? 0);
    $a = (string)($_POST['action'] ?? '');
    if ($a === 'toggle') {
        db_exec('UPDATE projects SET is_active = 1 - is_active WHERE id = ?', [$id]);
    } elseif ($a === 'feature') {
        db_exec('UPDATE projects SET is_featured = 1 - is_featured WHERE id = ?', [$id]);
    } elseif ($a === 'delete' && can('owner')) {
        db_exec('DELETE FROM projects WHERE id = ?', [$id]);
        flash('success', 'Project deleted.');
    }
    audit('project.' . $a, 'project', $id);
    redirect('admin/projects/' . (isset($_GET['type']) ? '?type=' . urlencode((string)$_GET['type']) : ''));
}
$type = array_key_exists($_GET['type'] ?? '', PROJECT_TYPES) ? $_GET['type'] : '';
$rows = $type ? db_all('SELECT * FROM projects WHERE type = ? ORDER BY sort_order, id DESC', [$type]) : db_all('SELECT * FROM projects ORDER BY type, sort_order, id DESC');
$clicks = [];
foreach (db_all("SELECT label, COUNT(*) c FROM events WHERE name = 'outbound_click' GROUP BY label") as $r) {
    $clicks[$r['label']] = (int)$r['c'];
}
admin_start('Portfolio', 'projects');
?>
<div class="flex flex-wrap items-center justify-between gap-3">
  <div class="flex flex-wrap gap-2"><a href="?" class="chip <?= $type === '' ? 'is-active' : '' ?>">All</a><?php foreach (PROJECT_TYPES as $k => $l): ?><a href="?type=<?= e($k) ?>" class="chip <?= $type === $k ? 'is-active' : '' ?>"><?= e($l) ?></a><?php endforeach; ?></div>
  <?php if (can('manager')): ?><a href="<?= e(url('admin/projects/edit/')) ?>" class="a-btn">+ Add project</a><?php endif; ?>
</div>
<div class="a-card mt-4 overflow-x-auto p-0">
  <table class="a-table"><thead><tr><th>Project</th><th>Type</th><th>Link</th><th>Clicks</th><th>Featured</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $p): ?>
    <tr>
      <td><a class="font-medium text-white hover:text-neo-green" href="<?= e(url('admin/projects/edit/?id=' . $p['id'])) ?>"><?= e($p['title']) ?></a><span class="block text-xs text-white/35"><?= e($p['subtitle']) ?></span></td>
      <td class="text-xs"><?= e(PROJECT_TYPES[$p['type']] ?? $p['type']) ?></td>
      <td class="max-w-[220px] truncate text-xs"><?php if ($p['url']): ?><a class="text-neo-green hover:underline" href="<?= e($p['url']) ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://(www\.)?#', '', $p['url'])) ?></a><?php else: ?>—<?php endif; ?></td>
      <td><?= (int)($clicks[$p['title']] ?? 0) ?></td>
      <td><?php if (can('manager')): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="feature"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="text-lg <?= (int)$p['is_featured'] ? 'text-neo-green' : 'text-white/20' ?>" title="Toggle featured">★</button></form><?php endif; ?></td>
      <td><?= (int)$p['is_active'] ? status_badge('sent') : status_badge('draft') ?></td>
      <td class="text-right"><?php if (can('manager')): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="a-btn-ghost !py-1.5 text-xs"><?= (int)$p['is_active'] ? 'Hide' : 'Show' ?></button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php admin_end();

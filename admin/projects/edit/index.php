<?php
declare(strict_types=1);
require dirname(__DIR__, 3) . '/app/admin_boot.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$p = $id ? db_row('SELECT * FROM projects WHERE id = ?', [$id]) : null;
$icons = ['globe', 'grad', 'star', 'users', 'code', 'briefcase', 'heart', 'bot', 'file', 'wrench', 'play', 'puzzle', 'clock', 'palette', 'rocket', 'mail', 'sparkles', 'search', 'shield', 'download', 'chart'];
$errors = [];
if (is_post()) {
    admin_post_guard('manager');
    $url = trim((string)($_POST['url'] ?? ''));
    $data = [
        'type' => array_key_exists($_POST['type'] ?? '', PROJECT_TYPES) ? $_POST['type'] : 'client',
        'title' => single_line(mb_substr((string)($_POST['title'] ?? ''), 0, 150)),
        'subtitle' => single_line(mb_substr((string)($_POST['subtitle'] ?? ''), 0, 190)) ?: null,
        'description' => trim(mb_substr((string)($_POST['description'] ?? ''), 0, 2000)) ?: null,
        'url' => $url ?: null,
        'tags' => json_encode(array_values(array_filter(array_map('trim', explode(',', mb_substr((string)($_POST['tags'] ?? ''), 0, 300))))), JSON_UNESCAPED_UNICODE),
        'icon' => in_array($_POST['icon'] ?? '', $icons, true) ? $_POST['icon'] : 'globe',
        'metric' => single_line(mb_substr((string)($_POST['metric'] ?? ''), 0, 80)) ?: null,
        'is_featured' => !empty($_POST['is_featured']) ? 1 : 0,
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        'sort_order' => (int)($_POST['sort_order'] ?? 100),
    ];
    if ($data['title'] === '') $errors[] = 'Title is required.';
    if ($url !== '' && !valid_url($url)) $errors[] = 'Link must be a full http(s) URL.';
    if (!$errors) {
        if ($p) {
            db_update('projects', $data, 'id = ?', [$id]);
        } else {
            $id = db_insert('projects', $data + ['created_at' => now()]);
        }
        audit('project.save', 'project', $id);
        flash('success', 'Project saved.');
        redirect('admin/projects/');
    }
    $p = array_merge($p ?? [], $data);
}
$v = static fn(string $k) => e((string)($p[$k] ?? ''));
admin_start($p && $id ? 'Edit project' : 'New project', 'projects');
?>
<a href="<?= e(url('admin/projects/')) ?>" class="text-xs text-white/40 hover:text-white">← Portfolio</a>
<?php foreach ($errors as $er): ?><p class="mt-3 rounded-2xl border border-red-400/30 bg-red-400/10 px-4 py-3 text-sm text-red-200"><?= e($er) ?></p><?php endforeach; ?>
<form method="post" class="a-card mt-3 grid max-w-3xl gap-4 sm:grid-cols-2">
  <?= csrf_field() ?>
  <div><label class="a-label">Type</label><select class="a-input" name="type"><?php foreach (PROJECT_TYPES as $k => $l): ?><option value="<?= e($k) ?>" <?= ($p['type'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div><label class="a-label">Icon</label><select class="a-input" name="icon"><?php foreach ($icons as $ic): ?><option <?= ($p['icon'] ?? '') === $ic ? 'selected' : '' ?>><?= e($ic) ?></option><?php endforeach; ?></select></div>
  <div class="sm:col-span-2"><label class="a-label">Title</label><input class="a-input" name="title" value="<?= $v('title') ?>" required></div>
  <div><label class="a-label">Subtitle</label><input class="a-input" name="subtitle" value="<?= $v('subtitle') ?>" placeholder="e.g. Google Play · Live"></div>
  <div><label class="a-label">Metric badge</label><input class="a-input" name="metric" value="<?= $v('metric') ?>" placeholder="e.g. ★ 5.0 · 16 users"></div>
  <div class="sm:col-span-2"><label class="a-label">Description</label><textarea class="a-input min-h-[100px]" name="description"><?= $v('description') ?></textarea></div>
  <div class="sm:col-span-2"><label class="a-label">Link (website / store URL)</label><input class="a-input" name="url" value="<?= $v('url') ?>" placeholder="https://"></div>
  <div><label class="a-label">Tags (comma separated)</label><input class="a-input" name="tags" value="<?= e(implode(', ', json_list($p['tags'] ?? null))) ?>"></div>
  <div><label class="a-label">Sort order</label><input class="a-input" name="sort_order" value="<?= e((string)($p['sort_order'] ?? 100)) ?>"></div>
  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" <?= !empty($p['is_featured']) ? 'checked' : '' ?> class="accent-[#4ADE80]"> Featured on homepage</label>
  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= !isset($p['is_active']) || (int)$p['is_active'] ? 'checked' : '' ?> class="accent-[#4ADE80]"> Visible</label>
  <div class="sm:col-span-2 flex justify-between"><button class="a-btn" type="submit">Save project</button></div>
</form>
<?php if ($p && $id && can('owner')): ?><form method="post" action="<?= e(url('admin/projects/')) ?>" class="mt-4" data-confirm="Delete this project?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>"><button class="a-btn-danger">Delete project</button></form><?php endif; ?>
<?php admin_end();

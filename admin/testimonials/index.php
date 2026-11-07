<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/admin_boot.php';
require_admin();

if (is_post()) {
    admin_post_guard('manager');
    $a = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($a === 'save') {
        $data = ['name' => single_line(mb_substr((string)$_POST['name'], 0, 120)), 'role' => single_line(mb_substr((string)($_POST['role'] ?? ''), 0, 150)) ?: null, 'quote' => trim(mb_substr((string)$_POST['quote'], 0, 1500)), 'rating' => max(1, min(5, (int)($_POST['rating'] ?? 5))), 'is_active' => !empty($_POST['is_active']) ? 1 : 0, 'sort_order' => (int)($_POST['sort_order'] ?? 100)];
        if ($data['name'] && $data['quote']) {
            $id ? db_update('testimonials', $data, 'id = ?', [$id]) : db_insert('testimonials', $data + ['created_at' => now()]);
            flash('success', 'Testimonial saved.');
        } else {
            flash('error', 'Name and quote are required.');
        }
    } elseif ($a === 'delete') {
        db_exec('DELETE FROM testimonials WHERE id = ?', [$id]);
        flash('success', 'Deleted.');
    }
    audit('testimonial.' . $a, 'testimonial', $id);
    redirect('admin/testimonials/');
}
$rows = db_all('SELECT * FROM testimonials ORDER BY sort_order, id DESC');
$edit = isset($_GET['id']) ? db_row('SELECT * FROM testimonials WHERE id = ?', [(int)$_GET['id']]) : null;
admin_start('Testimonials', 'testimonials');
?>
<p class="text-sm text-white/45">Real client reviews only — the homepage section appears automatically once you add one.</p>
<div class="mt-4 grid gap-4 xl:grid-cols-[1fr_380px]">
  <div class="grid gap-3 md:grid-cols-2">
    <?php foreach ($rows as $t): ?>
      <div class="a-card"><div class="flex justify-between"><p class="font-semibold"><?= e($t['name']) ?></p><span class="text-neo-green"><?= str_repeat('★', (int)$t['rating']) ?></span></div><p class="text-xs text-white/40"><?= e($t['role']) ?> <?= (int)$t['is_active'] ? '' : '· hidden' ?></p><p class="mt-3 text-sm text-white/70">“<?= e($t['quote']) ?>”</p>
        <?php if (can('manager')): ?><div class="mt-4 flex gap-2"><a class="a-btn-ghost !py-1.5 text-xs" href="?id=<?= (int)$t['id'] ?>">Edit</a><form method="post" data-confirm="Delete?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="a-btn-ghost !py-1.5 text-xs">Delete</button></form></div><?php endif; ?></div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="a-card text-sm text-white/40 md:col-span-2">No testimonials yet.</div><?php endif; ?>
  </div>
  <?php if (can('manager')): ?>
  <form method="post" class="a-card h-fit space-y-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <h3 class="font-semibold"><?= $edit ? 'Edit testimonial' : 'Add testimonial' ?></h3>
    <input class="a-input" name="name" placeholder="Client name" required value="<?= e($edit['name'] ?? '') ?>">
    <input class="a-input" name="role" placeholder="Role / company" value="<?= e($edit['role'] ?? '') ?>">
    <textarea class="a-input min-h-[120px]" name="quote" placeholder="What they said" required><?= e($edit['quote'] ?? '') ?></textarea>
    <div class="grid grid-cols-2 gap-3"><select class="a-input" name="rating"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= (int)($edit['rating'] ?? 5) === $i ? 'selected' : '' ?>><?= $i ?> ★</option><?php endfor; ?></select><input class="a-input" name="sort_order" value="<?= e((string)($edit['sort_order'] ?? 100)) ?>" placeholder="Sort"></div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= !isset($edit['is_active']) || (int)$edit['is_active'] ? 'checked' : '' ?> class="accent-[#4ADE80]"> Visible</label>
    <button class="a-btn w-full">Save</button>
  </form>
  <?php endif; ?>
</div>
<?php admin_end();

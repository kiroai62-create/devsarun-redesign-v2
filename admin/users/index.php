<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/admin_boot.php';
$me = require_admin();

if (is_post()) {
    $a = (string)($_POST['action'] ?? '');
    csrf_check();
    if ($a === 'password') {
        $row = db_row('SELECT password_hash FROM admin_users WHERE id = ?', [$me['id']]);
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify((string)($_POST['current'] ?? ''), $row['password_hash'])) {
            flash('error', 'Current password is incorrect.');
        } elseif (!password_strong($new) || $new !== ($_POST['confirm'] ?? '')) {
            flash('error', 'New password must be 10+ characters with letters and numbers, and both fields must match.');
        } else {
            db_update('admin_users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$me['id']]);
            session_regenerate_id(true);
            audit('auth.password_change', 'admin_user', (int)$me['id']);
            flash('success', 'Password changed.');
        }
    } elseif (can('owner')) {
        $id = (int)($_POST['id'] ?? 0);
        if ($a === 'create') {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $pass = (string)($_POST['password'] ?? '');
            $role = array_key_exists($_POST['role'] ?? '', ROLES) ? $_POST['role'] : 'viewer';
            if (!valid_email($email) || !password_strong($pass) || trim((string)$_POST['name']) === '') {
                flash('error', 'Name, valid email and a strong password (10+ chars, letters + numbers) are required.');
            } elseif (db_val('SELECT id FROM admin_users WHERE email = ?', [$email])) {
                flash('error', 'A user with that email already exists.');
            } else {
                $nid = db_insert('admin_users', ['name' => single_line(mb_substr((string)$_POST['name'], 0, 120)), 'email' => $email, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'role' => $role, 'is_active' => 1, 'created_at' => now()]);
                audit('user.create', 'admin_user', $nid, ['role' => $role]);
                flash('success', 'Team member added.');
            }
        } elseif ($id && $id !== (int)$me['id']) {
            if ($a === 'role' && array_key_exists($_POST['role'] ?? '', ROLES)) {
                db_update('admin_users', ['role' => $_POST['role']], 'id = ?', [$id]);
                audit('user.role', 'admin_user', $id, ['role' => $_POST['role']]);
            } elseif ($a === 'toggle') {
                db_exec('UPDATE admin_users SET is_active = 1 - is_active WHERE id = ?', [$id]);
                audit('user.toggle', 'admin_user', $id);
            } elseif ($a === 'reset' && password_strong((string)($_POST['password'] ?? ''))) {
                db_update('admin_users', ['password_hash' => password_hash((string)$_POST['password'], PASSWORD_DEFAULT)], 'id = ?', [$id]);
                audit('user.reset_password', 'admin_user', $id);
                flash('success', 'Password reset.');
            }
        }
    }
    redirect('admin/users/');
}
$users = db_all('SELECT id, name, email, role, is_active, last_login_at, created_at FROM admin_users ORDER BY id');
$logins = db_all('SELECT email, success, created_at FROM login_attempts ORDER BY id DESC LIMIT 15');
admin_start('Team & account', 'users');
?>
<div class="grid gap-4 xl:grid-cols-[1fr_380px]">
  <div class="space-y-4">
    <div class="a-card overflow-x-auto p-0">
      <table class="a-table"><thead><tr><th>Member</th><th>Role</th><th>Last login</th><th>Status</th><?php if (can('owner')): ?><th></th><?php endif; ?></tr></thead><tbody>
      <?php foreach ($users as $u): ?>
        <tr><td><span class="font-medium text-white"><?= e($u['name']) ?></span><?= (int)$u['id'] === (int)$me['id'] ? ' <span class="text-xs text-neo-green">(you)</span>' : '' ?><span class="block text-xs text-white/40"><?= e($u['email']) ?></span></td>
          <td><?php if (can('owner') && (int)$u['id'] !== (int)$me['id']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><select class="a-input !w-auto !py-1.5" name="role" data-autosubmit><?php foreach (array_keys(ROLES) as $r): ?><option <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></form><?php else: ?><?= e($u['role']) ?><?php endif; ?></td>
          <td class="text-xs text-white/45"><?= e(time_ago($u['last_login_at'])) ?></td>
          <td><?= (int)$u['is_active'] ? status_badge('sent') : status_badge('cancelled') ?></td>
          <?php if (can('owner')): ?><td class="whitespace-nowrap text-right"><?php if ((int)$u['id'] !== (int)$me['id']): ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="a-btn-ghost !py-1.5 text-xs"><?= (int)$u['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
            <form method="post" class="mt-1 inline-flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input class="a-input !w-32 !py-1.5 text-xs" type="password" name="password" placeholder="New password" minlength="10"><button class="a-btn-ghost !py-1.5 text-xs">Reset</button></form>
          <?php endif; ?></td><?php endif; ?></tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
    <div class="a-card text-sm"><h3 class="font-semibold">Recent sign-in attempts</h3>
      <ul class="mt-3 divide-y divide-white/[0.05]"><?php foreach ($logins as $l): ?><li class="flex justify-between py-2"><span><?= e($l['email']) ?></span><span class="<?= (int)$l['success'] ? 'text-neo-green' : 'text-red-300' ?>"><?= (int)$l['success'] ? 'success' : 'failed' ?> · <?= e(time_ago($l['created_at'])) ?></span></li><?php endforeach; ?></ul></div>
    <div class="a-card text-sm text-white/55"><h3 class="font-semibold text-white">Roles</h3><p class="mt-2"><strong class="text-white">Owner</strong> — everything incl. settings, users, deleting. <strong class="text-white">Manager</strong> — leads, emails, quotes, services, portfolio. <strong class="text-white">Viewer</strong> — read-only dashboards.</p></div>
  </div>
  <div class="space-y-4">
    <form method="post" class="a-card space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <h3 class="font-semibold">Change my password</h3>
      <input class="a-input" type="password" name="current" placeholder="Current password" required autocomplete="current-password">
      <input class="a-input" type="password" name="new" placeholder="New password (10+ chars)" required minlength="10" autocomplete="new-password">
      <input class="a-input" type="password" name="confirm" placeholder="Confirm new password" required minlength="10" autocomplete="new-password">
      <button class="a-btn w-full">Update password</button></form>
    <?php if (can('owner')): ?>
    <form method="post" class="a-card space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="create">
      <h3 class="font-semibold">Add team member</h3>
      <input class="a-input" name="name" placeholder="Name" required>
      <input class="a-input" type="email" name="email" placeholder="Email" required>
      <input class="a-input" type="password" name="password" placeholder="Temporary password (10+ chars)" required minlength="10" autocomplete="new-password">
      <select class="a-input" name="role"><option value="viewer">Viewer (read-only)</option><option value="manager">Manager</option><option value="owner">Owner</option></select>
      <button class="a-btn w-full">Add member</button></form>
    <?php endif; ?>
  </div>
</div>
<?php admin_end();

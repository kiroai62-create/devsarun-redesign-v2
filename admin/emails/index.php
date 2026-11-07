<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/admin_boot.php';
require_admin();

if (is_post()) {
    admin_post_guard('manager');
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'send') {
        $subjectTpl = single_line(mb_substr((string)($_POST['subject'] ?? ''), 0, 200));
        $bodyTpl = mb_substr((string)($_POST['body'] ?? ''), 0, 20000);
        $segment = (string)($_POST['segment'] ?? 'single');
        if ($subjectTpl === '' || trim($bodyTpl) === '') {
            flash('error', 'Subject and message are required.');
            redirect('admin/emails/');
        }
        $recipients = [];
        if ($segment === 'single') {
            $to = strtolower(trim((string)($_POST['to'] ?? '')));
            if (!valid_email($to)) {
                flash('error', 'Enter a valid recipient email.');
                redirect('admin/emails/');
            }
            $lead = db_row('SELECT id, name, ref, service_label, email FROM leads WHERE email = ? ORDER BY id DESC LIMIT 1', [$to]);
            $recipients[] = $lead ?: ['id' => null, 'name' => '', 'ref' => '', 'service_label' => '', 'email' => $to];
        } elseif (in_array($segment, LEAD_STATUSES, true) || $segment === 'open') {
            $sql = $segment === 'open' ? "status NOT IN ('won','lost','spam')" : 'status = ?';
            $recipients = db_all("SELECT MAX(id) id, MAX(name) name, MAX(ref) ref, MAX(service_label) service_label, email FROM leads WHERE $sql AND email NOT LIKE '%@chat.local' GROUP BY email LIMIT 200", $segment === 'open' ? [] : [$segment]);
        }
        $sent = 0;
        $failed = 0;
        foreach ($recipients as $r) {
            $vars = ['name' => $r['name'] ?: 'there', 'ref' => $r['ref'], 'service' => $r['service_label'] ?: 'your project', 'email' => $r['email']];
            $subject = render_template_text($subjectTpl, $vars);
            [$ok] = send_mail($r['email'], $subject, email_layout($subject, '<p>' . nl2br(render_template_string($bodyTpl, $vars)) . '</p>'), ['kind' => $segment === 'single' ? 'manual' : 'campaign', 'lead_id' => $r['id'] ?? null, 'reply_to' => setting('contact_email')]);
            $ok ? $sent++ : $failed++;
            if (!empty($r['id'])) {
                lead_activity((int)$r['id'], 'email', 'Email sent: ' . $subject . ($ok ? '' : ' (failed)'));
            }
        }
        audit('email.send', 'email', null, ['segment' => $segment, 'sent' => $sent, 'failed' => $failed]);
        flash($failed ? 'error' : 'success', "Sent: $sent" . ($failed ? " · Failed: $failed (see log below)" : ''));
    } elseif ($action === 'template_save') {
        $data = ['name' => single_line(mb_substr((string)$_POST['name'], 0, 120)), 'subject' => single_line(mb_substr((string)$_POST['t_subject'], 0, 250)), 'body' => mb_substr((string)$_POST['t_body'], 0, 20000)];
        if ($data['name'] && $data['subject'] && $data['body']) {
            if ($tid = (int)($_POST['id'] ?? 0)) {
                db_update('email_templates', $data, 'id = ?', [$tid]);
            } else {
                db_insert('email_templates', $data + ['created_at' => now()]);
            }
            flash('success', 'Template saved.');
        }
    } elseif ($action === 'template_delete') {
        db_exec('DELETE FROM email_templates WHERE id = ?', [(int)$_POST['id']]);
        flash('success', 'Template deleted.');
    }
    redirect('admin/emails/');
}

$templates = db_all('SELECT * FROM email_templates ORDER BY name');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 30;
$total = (int)db_val('SELECT COUNT(*) FROM email_log');
$log = db_all("SELECT e.*, l.ref FROM email_log e LEFT JOIN leads l ON l.id = e.lead_id ORDER BY e.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$editT = isset($_GET['t']) ? db_row('SELECT * FROM email_templates WHERE id = ?', [(int)$_GET['t']]) : null;
admin_start('Email center', 'emails');
?>
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
  <?= admin_stat('Sent (30d)', (string)db_val("SELECT COUNT(*) FROM email_log WHERE status='sent' AND created_at >= (NOW() - INTERVAL 30 DAY)"), '', 'mail') ?>
  <?= admin_stat('Failed (30d)', (string)db_val("SELECT COUNT(*) FROM email_log WHERE status='failed' AND created_at >= (NOW() - INTERVAL 30 DAY)"), '', 'x') ?>
  <?= admin_stat('Transport', smtp_configured() ? 'SMTP' : 'PHP mail()', smtp_configured() ? setting('smtp_host') : 'configure SMTP in Settings', 'send') ?>
  <?= admin_stat('Templates', (string)count($templates), '', 'file') ?>
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-[1.3fr_1fr]">
  <?php if (can('manager')): ?>
  <form method="post" class="a-card space-y-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="send">
    <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-semibold">Compose</h3>
      <select class="a-input w-auto" data-template-select><option value="">Insert template…</option><?php foreach ($templates as $t): ?><option value="<?= (int)$t['id'] ?>" data-subject="<?= e($t['subject']) ?>" data-body="<?= e($t['body']) ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
    <div class="grid gap-3 sm:grid-cols-[180px_1fr]">
      <select class="a-input" name="segment"><option value="single">One recipient</option><option value="open">All open leads</option><?php foreach (LEAD_STATUSES as $s): if ($s !== 'spam'): ?><option value="<?= e($s) ?>">Leads: <?= e(ucwords(str_replace('_', ' ', $s))) ?></option><?php endif; endforeach; ?></select>
      <input class="a-input" type="email" name="to" placeholder="client@example.com (for one recipient)" value="<?= e((string)($_GET['to'] ?? '')) ?>">
    </div>
    <input class="a-input" name="subject" placeholder="Subject" required maxlength="200">
    <textarea class="a-input min-h-[200px]" name="body" placeholder="Message… placeholders: {{name}} {{ref}} {{service}} {{email}}" required></textarea>
    <div class="flex items-center justify-between gap-3"><p class="text-xs text-white/35">Segments send individually (max 200), personalised, and are logged per lead.</p><button class="a-btn" type="submit" data-confirm="Send this email now?"><?= icon('send', 'h-4 w-4') ?> Send</button></div>
  </form>
  <?php endif; ?>

  <div class="a-card">
    <h3 class="font-semibold">Templates</h3>
    <ul class="mt-3 divide-y divide-white/[0.05] text-sm">
      <?php foreach ($templates as $t): ?><li class="flex items-center justify-between gap-2 py-2.5"><a class="hover:text-neo-green" href="?t=<?= (int)$t['id'] ?>"><?= e($t['name']) ?><span class="block text-xs text-white/35"><?= e($t['subject']) ?></span></a>
        <?php if (can('manager')): ?><form method="post" data-confirm="Delete template?"><?= csrf_field() ?><input type="hidden" name="action" value="template_delete"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="text-xs text-white/35 hover:text-red-300">Delete</button></form><?php endif; ?></li><?php endforeach; ?>
    </ul>
    <?php if (can('manager')): ?>
    <form method="post" class="mt-4 space-y-2 border-t border-white/[0.06] pt-4">
      <?= csrf_field() ?><input type="hidden" name="action" value="template_save"><input type="hidden" name="id" value="<?= (int)($editT['id'] ?? 0) ?>">
      <p class="text-xs text-white/45"><?= $editT ? 'Edit template' : 'New template' ?></p>
      <input class="a-input" name="name" placeholder="Template name" required value="<?= e($editT['name'] ?? '') ?>">
      <input class="a-input" name="t_subject" placeholder="Subject" required value="<?= e($editT['subject'] ?? '') ?>">
      <textarea class="a-input min-h-[120px]" name="t_body" placeholder="Body with {{name}}, {{service}}…" required><?= e($editT['body'] ?? '') ?></textarea>
      <button class="a-btn-ghost w-full">Save template</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="a-card mt-4 overflow-x-auto p-0">
  <h3 class="px-5 pt-5 font-semibold">Email log</h3>
  <table class="a-table mt-3"><thead><tr><th>To</th><th>Subject</th><th>Type</th><th>Status</th><th>When</th></tr></thead><tbody>
  <?php foreach ($log as $m): ?>
    <tr><td><?= e($m['to_email']) ?><?php if ($m['ref']): ?><a class="block font-mono text-[11px] text-neo-green" href="<?= e(url('admin/leads/view/?id=' . $m['lead_id'])) ?>"><?= e($m['ref']) ?></a><?php endif; ?></td>
      <td class="max-w-md"><details><summary class="cursor-pointer"><?= e($m['subject']) ?></summary><div class="mt-2 max-h-60 overflow-auto whitespace-pre-wrap rounded-xl bg-white/[0.03] p-3 text-xs text-white/60"><?= e(trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/tr|\/h1)\s*\/?>/i', "\n", (string)$m['body_html']) ?? ''), ENT_QUOTES))) ?></div></details><?php if ($m['error']): ?><span class="mt-1 block text-xs text-red-300"><?= e($m['error']) ?></span><?php endif; ?></td>
      <td class="text-xs text-white/50"><?= e($m['kind']) ?></td><td><?= status_badge($m['status']) ?></td><td class="whitespace-nowrap text-xs text-white/45"><?= e(time_ago($m['created_at'])) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$log): ?><tr><td colspan="5" class="py-10 text-center text-white/40">No emails sent yet.</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?= paginate($total, $per, $page) ?>
<?php admin_end();

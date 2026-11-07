<?php
declare(strict_types=1);
require dirname(__DIR__, 3) . '/app/admin_boot.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$lead = db_row('SELECT * FROM leads WHERE id = ?', [$id]);
if (!$lead) {
    flash('error', 'Lead not found.');
    redirect('admin/leads/');
}
$self = 'admin/leads/view/?id=' . $id;
$vars = ['name' => $lead['name'], 'ref' => $lead['ref'], 'service' => $lead['service_label'] ?: 'your project', 'email' => $lead['email']];

if (is_post()) {
    $action = (string)($_POST['action'] ?? '');
    admin_post_guard($action === 'delete' ? 'owner' : 'manager');

    if ($action === 'update') {
        $status = in_array($_POST['status'] ?? '', LEAD_STATUSES, true) ? $_POST['status'] : $lead['status'];
        $priority = in_array($_POST['priority'] ?? '', ['low', 'normal', 'high'], true) ? $_POST['priority'] : 'normal';
        $value = (int)preg_replace('/\D/', '', (string)($_POST['value_estimate'] ?? ''));
        $follow = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['follow_up_at'] ?? '')) ? $_POST['follow_up_at'] : null;
        db_update('leads', ['status' => $status, 'priority' => $priority, 'value_estimate' => $value ?: null, 'follow_up_at' => $follow, 'tags' => mb_substr(trim((string)($_POST['tags'] ?? '')), 0, 255) ?: null, 'updated_at' => now()], 'id = ?', [$id]);
        if ($status !== $lead['status']) {
            lead_activity($id, 'status', LEAD_STATUS_PUBLIC[$status] ?? ucfirst($status), !in_array($status, ['spam', 'lost'], true));
            audit('lead.status', 'lead', $id, ['from' => $lead['status'], 'to' => $status]);
            if (!empty($_POST['notify']) && valid_email($lead['email'])) {
                $body = '<p>Hi ' . e($lead['name']) . ',</p><p>Your request <strong>' . e($lead['ref']) . '</strong> has a new status:</p><p style="font-size:17px"><strong>' . e(LEAD_STATUS_PUBLIC[$status] ?? $status) . '</strong></p>';
                send_mail($lead['email'], 'Update on your request ' . $lead['ref'], email_layout('Project update', $body, 'Track your request', abs_url('track/?ref=' . $lead['ref'] . '&email=' . rawurlencode($lead['email']))), ['kind' => 'status_update', 'lead_id' => $id]);
            }
        }
        flash('success', 'Lead updated.');
    } elseif ($action === 'note') {
        $body = trim(mb_substr((string)($_POST['body'] ?? ''), 0, 5000));
        if ($body !== '') {
            $public = !empty($_POST['is_public']);
            lead_activity($id, 'note', $body, $public);
            if ($public && !empty($_POST['notify'])) {
                send_mail($lead['email'], 'New update on ' . $lead['ref'], email_layout('Project update', '<p>Hi ' . e($lead['name']) . ',</p><p>' . nl2br(e($body)) . '</p>', 'View all updates', abs_url('track/?ref=' . $lead['ref'] . '&email=' . rawurlencode($lead['email']))), ['kind' => 'status_update', 'lead_id' => $id]);
            }
            flash('success', $public ? 'Update posted (visible on the client tracking page).' : 'Private note added.');
        }
    } elseif ($action === 'email') {
        $subject = single_line(mb_substr(render_template_text((string)($_POST['subject'] ?? ''), $vars), 0, 200));
        $bodyRaw = (string)($_POST['body'] ?? '');
        if ($subject === '' || trim($bodyRaw) === '') {
            flash('error', 'Subject and message are required.');
        } else {
            $bodyHtml = nl2br(render_template_string(mb_substr($bodyRaw, 0, 20000), $vars));
            [$ok, $err] = send_mail($lead['email'], $subject, email_layout($subject, '<p>' . $bodyHtml . '</p>'), ['kind' => 'manual', 'lead_id' => $id, 'reply_to' => setting('contact_email')]);
            lead_activity($id, 'email', 'Email sent: ' . $subject . ($ok ? '' : ' (FAILED: ' . $err . ')'));
            if ($ok && $lead['status'] === 'new') {
                db_update('leads', ['status' => 'contacted', 'updated_at' => now()], 'id = ?', [$id]);
            }
            audit('lead.email', 'lead', $id, ['ok' => $ok]);
            flash($ok ? 'success' : 'error', $ok ? 'Email sent to ' . $lead['email'] : 'Email failed: ' . $err);
        }
    } elseif ($action === 'quote') {
        $title = single_line(mb_substr((string)($_POST['title'] ?? ''), 0, 190));
        $amount = (float)str_replace(',', '', (string)($_POST['amount'] ?? '0'));
        $valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['valid_until'] ?? '')) ? $_POST['valid_until'] : null;
        if ($title === '' || $amount < 1) {
            flash('error', 'Quote title and amount are required.');
        } else {
            $token = random_token(20);
            $qid = db_insert('quotes', ['token' => $token, 'lead_id' => $id, 'title' => $title, 'description' => mb_substr(trim((string)($_POST['description'] ?? '')), 0, 5000) ?: null, 'amount_paise' => (int)round($amount * 100), 'status' => 'sent', 'valid_until' => $valid, 'created_by' => admin()['id'], 'created_at' => now()]);
            $link = abs_url('pay/' . $token . '/');
            lead_activity($id, 'quote', 'Quote shared: ' . $title . ' — ' . inr($amount), true);
            db_update('leads', ['status' => in_array($lead['status'], ['won'], true) ? $lead['status'] : 'proposal', 'value_estimate' => (int)round($amount), 'updated_at' => now()], 'id = ?', [$id]);
            audit('quote.create', 'quote', $qid, ['amount' => $amount]);
            if (!empty($_POST['send_email'])) {
                $body = '<p>Hi ' . e($lead['name']) . ',</p><p>As discussed, here is your quote:</p><p style="font-size:17px"><strong>' . e($title) . '</strong><br>Amount: <strong>' . e(inr($amount)) . '</strong></p>'
                    . ($_POST['description'] ? '<p>' . nl2br(e((string)$_POST['description'])) . '</p>' : '') . '<p>You can pay securely online using the button below (UPI, cards, netbanking). Want to adjust scope or price? Just reply — it\'s negotiable.</p>';
                [$ok] = send_mail($lead['email'], 'Your quote: ' . $title, email_layout('Your quote is ready', $body, 'View & pay securely', $link), ['kind' => 'quote', 'lead_id' => $id]);
                flash($ok ? 'success' : 'error', $ok ? 'Quote created and emailed.' : 'Quote created, but the email failed. Copy the link below.');
            } else {
                flash('success', 'Quote created. Share the payment link with the client.');
            }
        }
    } elseif ($action === 'delete') {
        if ($lead['attachment_path'] && is_file(STORAGE . '/uploads/' . $lead['attachment_path'])) {
            @unlink(STORAGE . '/uploads/' . $lead['attachment_path']);
        }
        db_exec('DELETE FROM lead_activities WHERE lead_id = ?', [$id]);
        db_exec('DELETE FROM leads WHERE id = ?', [$id]);
        audit('lead.delete', 'lead', $id, ['ref' => $lead['ref'], 'email' => $lead['email']]);
        flash('success', 'Lead deleted.');
        redirect('admin/leads/');
    }
    redirect($self);
}

$activities = db_all('SELECT a.*, u.name admin_name FROM lead_activities a LEFT JOIN admin_users u ON u.id = a.admin_id WHERE a.lead_id = ? ORDER BY a.id DESC', [$id]);
$quotes = db_all('SELECT * FROM quotes WHERE lead_id = ? ORDER BY id DESC', [$id]);
$orders = db_all('SELECT * FROM orders WHERE lead_id = ? ORDER BY id DESC', [$id]);
$emails = db_all('SELECT id, subject, status, kind, created_at FROM email_log WHERE lead_id = ? ORDER BY id DESC LIMIT 20', [$id]);
$chat = db_row('SELECT id, messages_count FROM chat_sessions WHERE lead_id = ? LIMIT 1', [$id]);
$templates = db_all('SELECT * FROM email_templates ORDER BY name');
$features = json_list($lead['features']);
$wa = preg_replace('/\D+/', '', ($lead['country_code'] ?? '') . ($lead['phone'] ?? ''));

admin_start('Lead ' . $lead['ref'], 'leads');
?>
<div class="flex flex-wrap items-start justify-between gap-4">
  <div>
    <a href="<?= e(url('admin/leads/')) ?>" class="text-xs text-white/40 hover:text-white">← All leads</a>
    <h2 class="mt-2 text-2xl font-bold tracking-tight"><?= e($lead['name']) ?> <?= status_badge($lead['status']) ?></h2>
    <p class="mt-1 text-sm text-white/50"><span class="font-mono"><?= e($lead['ref']) ?></span> · <?= e(ucfirst($lead['source'])) ?> · <?= e(date('d M Y, h:i A', strtotime($lead['created_at']))) ?> (<?= e(time_ago($lead['created_at'])) ?>)</p>
  </div>
  <div class="flex flex-wrap gap-2">
    <a class="a-btn-ghost" href="mailto:<?= e($lead['email']) ?>"><?= icon('mail', 'h-4 w-4') ?> Email</a>
    <?php if ($wa): ?><a class="a-btn-ghost" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Hi ' . $lead['name'] . ', this is Arun regarding your request ' . $lead['ref'] . '.') ?>"><?= icon('whatsapp', 'h-4 w-4') ?> WhatsApp</a><a class="a-btn-ghost" href="tel:<?= e('+' . $wa) ?>"><?= icon('phone', 'h-4 w-4') ?> Call</a><?php endif; ?>
  </div>
</div>

<div class="mt-6 grid gap-4 xl:grid-cols-3">
  <div class="space-y-4 xl:col-span-2">
    <div class="a-card">
      <h3 class="font-semibold"><?= e($lead['project_title'] ?: 'Request') ?></h3>
      <p class="mt-1 text-sm text-neo-green"><?= e($lead['service_label'] ?: '—') ?></p>
      <div class="mt-4 whitespace-pre-wrap rounded-2xl bg-white/[0.03] p-4 text-sm leading-relaxed text-white/80"><?= e($lead['description'] ?: '—') ?></div>
      <?php if ($features): ?><div class="mt-4 flex flex-wrap gap-1.5"><?php foreach ($features as $f): ?><span class="rounded-full bg-neo-green/10 px-2.5 py-1 text-xs text-neo-green"><?= e($f) ?></span><?php endforeach; ?></div><?php endif; ?>
      <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
        <?php foreach (['Email' => $lead['email'], 'Phone' => trim(($lead['country_code'] ?? '') . ' ' . ($lead['phone'] ?? '')), 'Company' => $lead['company'], 'Budget' => $lead['budget'], 'Timeline' => $lead['timeline'], 'Start date' => $lead['start_date'], 'Preferred contact' => $lead['preferred_contact'], 'Best time to call' => $lead['call_time'], 'Heard from' => $lead['heard_from'], 'Existing website' => $lead['existing_website'], 'References' => $lead['reference_links']] as $k => $v): if ($v): ?>
          <div><dt class="text-xs text-white/40"><?= e($k) ?></dt><dd class="mt-0.5 break-words text-white/85"><?php if (preg_match('#^https?://#', (string)$v)): ?><a class="text-neo-green hover:underline" href="<?= e($v) ?>" target="_blank" rel="noopener noreferrer"><?= e($v) ?></a><?php else: ?><?= e($v) ?><?php endif; ?></dd></div>
        <?php endif; endforeach; ?>
      </dl>
      <?php if ($lead['attachment_path']): ?><a class="a-btn-ghost mt-5" href="<?= e(url('admin/leads/file/?id=' . $id)) ?>"><?= icon('file', 'h-4 w-4') ?> <?= e($lead['attachment_name']) ?></a><?php endif; ?>
      <?php if ($chat): ?><a class="a-btn-ghost mt-5" href="<?= e(url('admin/chats/view/?id=' . $chat['id'])) ?>"><?= icon('bot', 'h-4 w-4') ?> View AI chat (<?= (int)$chat['messages_count'] ?> messages)</a><?php endif; ?>
    </div>

    <?php if (can('manager')): ?>
    <form method="post" class="a-card">
      <?= csrf_field() ?><input type="hidden" name="action" value="email">
      <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="font-semibold">Email this client</h3>
        <select class="a-input w-auto" data-template-select><option value="">Insert template…</option><?php foreach ($templates as $t): ?><option value="<?= (int)$t['id'] ?>" data-subject="<?= e($t['subject']) ?>" data-body="<?= e($t['body']) ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
      <input class="a-input mt-4" name="subject" placeholder="Subject" maxlength="200" required>
      <textarea class="a-input mt-3 min-h-[160px]" name="body" placeholder="Write your message… placeholders: {{name}} {{ref}} {{service}}" required></textarea>
      <div class="mt-3 flex items-center justify-between gap-3"><p class="text-xs text-white/35">Sent via <?= smtp_configured() ? 'SMTP (' . e(setting('smtp_host')) . ')' : 'PHP mail() — configure SMTP in Settings' ?> · logged in Email center</p><button class="a-btn" type="submit"><?= icon('send', 'h-4 w-4') ?> Send email</button></div>
    </form>

    <form method="post" class="a-card">
      <?= csrf_field() ?><input type="hidden" name="action" value="quote">
      <h3 class="font-semibold">Create quote + payment link</h3>
      <p class="mt-1 text-xs text-white/40">Negotiated price → secure Razorpay link the client can pay from email or the tracking page.<?= razorpay_enabled() ? '' : ' ⚠️ Razorpay is disabled in Settings.' ?></p>
      <div class="mt-4 grid gap-3 sm:grid-cols-3">
        <input class="a-input sm:col-span-2" name="title" placeholder="e.g. Business website — 6 pages + admin" required maxlength="190" value="<?= e($lead['service_label'] && $lead['service_label'] !== 'From AI chat' ? $lead['service_label'] : '') ?>">
        <input class="a-input" name="amount" inputmode="decimal" placeholder="Amount ₹" required>
        <textarea class="a-input sm:col-span-2" name="description" placeholder="Scope summary (optional)"></textarea>
        <div><label class="a-label">Valid until</label><input class="a-input [color-scheme:dark]" type="date" name="valid_until" value="<?= e(date('Y-m-d', strtotime('+14 days'))) ?>"></div>
      </div>
      <div class="mt-3 flex items-center justify-between"><label class="flex items-center gap-2 text-sm text-white/60"><input type="checkbox" name="send_email" value="1" checked class="accent-[#4ADE80]"> Email quote to client</label><button class="a-btn" type="submit">Create quote</button></div>
      <?php foreach ($quotes as $q): $link = abs_url('pay/' . $q['token'] . '/'); ?>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-white/[0.03] px-4 py-3 text-sm"><span><?= e($q['title']) ?> · <strong class="text-neo-green"><?= e(inr($q['amount_paise'] / 100)) ?></strong> <?= status_badge($q['status']) ?></span><button type="button" class="a-btn-ghost !py-1.5 text-xs" data-copy="<?= e($link) ?>">Copy pay link</button></div>
      <?php endforeach; ?>
    </form>
    <?php endif; ?>

    <div class="a-card">
      <h3 class="font-semibold">Activity timeline</h3>
      <?php if (can('manager')): ?>
      <form method="post" class="mt-4">
        <?= csrf_field() ?><input type="hidden" name="action" value="note">
        <textarea class="a-input min-h-[80px]" name="body" placeholder="Add a note or a client-visible update…" required></textarea>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3"><div class="flex flex-wrap gap-4 text-sm text-white/60"><label class="flex items-center gap-2"><input type="checkbox" name="is_public" value="1" class="accent-[#4ADE80]"> Show on client tracking page</label><label class="flex items-center gap-2"><input type="checkbox" name="notify" value="1" class="accent-[#4ADE80]"> Also email client</label></div><button class="a-btn-ghost" type="submit">Add</button></div>
      </form>
      <?php endif; ?>
      <ol class="mt-5 space-y-4 border-l border-white/10 pl-5">
        <?php foreach ($activities as $a): ?>
          <li class="relative"><span class="absolute -left-[25px] top-1.5 h-2.5 w-2.5 rounded-full <?= $a['is_public'] ? 'bg-neo-green' : 'bg-white/30' ?>"></span>
            <p class="text-sm text-white/85"><?= nl2br(e($a['body'])) ?></p>
            <p class="mt-1 text-xs text-white/35"><?= e(ucfirst($a['type'])) ?> · <?= e($a['admin_name'] ?: 'System') ?> · <?= e(date('d M Y, h:i A', strtotime($a['created_at']))) ?><?= $a['is_public'] ? ' · <span class="text-neo-green">client-visible</span>' : '' ?></p></li>
        <?php endforeach; ?>
      </ol>
    </div>
  </div>

  <div class="space-y-4">
    <form method="post" class="a-card space-y-3">
      <?= csrf_field() ?><input type="hidden" name="action" value="update">
      <h3 class="font-semibold">Pipeline</h3>
      <div><label class="a-label">Status</label><select class="a-input" name="status"><?php foreach (LEAD_STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $lead['status'] === $s ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $s))) ?></option><?php endforeach; ?></select></div>
      <div><label class="a-label">Priority</label><select class="a-input" name="priority"><?php foreach (['high', 'normal', 'low'] as $p): ?><option value="<?= $p ?>" <?= $lead['priority'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option><?php endforeach; ?></select></div>
      <div><label class="a-label">Deal value (₹)</label><input class="a-input" name="value_estimate" inputmode="numeric" value="<?= e((string)($lead['value_estimate'] ?? '')) ?>"></div>
      <div><label class="a-label">Follow-up date</label><input class="a-input [color-scheme:dark]" type="date" name="follow_up_at" value="<?= e((string)($lead['follow_up_at'] ?? '')) ?>"></div>
      <div><label class="a-label">Tags</label><input class="a-input" name="tags" value="<?= e((string)($lead['tags'] ?? '')) ?>" placeholder="hot, referral, nri"></div>
      <label class="flex items-center gap-2 text-sm text-white/60"><input type="checkbox" name="notify" value="1" class="accent-[#4ADE80]"> Email client if status changes</label>
      <?php if (can('manager')): ?><button class="a-btn w-full" type="submit">Save</button><?php endif; ?>
    </form>

    <div class="a-card text-sm">
      <h3 class="font-semibold">Attribution</h3>
      <dl class="mt-3 space-y-2 text-white/70">
        <div class="flex justify-between gap-3"><dt class="text-white/40">Landing page</dt><dd class="truncate"><?= e($lead['landing'] ?: '—') ?></dd></div>
        <div class="flex justify-between gap-3"><dt class="text-white/40">Referrer</dt><dd class="truncate"><?= e($lead['referrer'] ?: 'Direct') ?></dd></div>
        <div class="flex justify-between gap-3"><dt class="text-white/40">UTM</dt><dd class="truncate"><?= e(implode(' / ', array_filter([$lead['utm_source'], $lead['utm_medium'], $lead['utm_campaign']])) ?: '—') ?></dd></div>
        <div class="flex justify-between gap-3"><dt class="text-white/40">Device</dt><dd class="max-w-[60%] truncate" title="<?= e($lead['user_agent']) ?>"><?= e($lead['user_agent'] ? (preg_match('/mobile|android|iphone/i', $lead['user_agent']) ? 'Mobile' : 'Desktop') : '—') ?></dd></div>
      </dl>
    </div>

    <?php if ($orders): ?>
    <div class="a-card text-sm"><h3 class="font-semibold">Payments</h3><?php foreach ($orders as $o): ?><div class="mt-3 flex justify-between"><span><?= e($o['order_no']) ?><span class="block text-xs text-white/40"><?= e($o['item_title']) ?></span></span><span class="text-right"><?= e(inr($o['amount_paise'] / 100)) ?><span class="block"><?= status_badge($o['status']) ?></span></span></div><?php endforeach; ?></div>
    <?php endif; ?>

    <div class="a-card text-sm"><h3 class="font-semibold">Emails</h3>
      <?php foreach ($emails as $em): ?><div class="mt-3 flex justify-between gap-2"><span class="truncate"><?= e($em['subject']) ?><span class="block text-xs text-white/35"><?= e($em['kind']) ?> · <?= e(time_ago($em['created_at'])) ?></span></span><?= status_badge($em['status']) ?></div><?php endforeach; ?>
      <?php if (!$emails): ?><p class="mt-2 text-white/40">No emails yet.</p><?php endif; ?>
    </div>

    <?php if (can('owner')): ?>
    <form method="post" data-confirm="Delete this lead permanently? This cannot be undone." class="a-card"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="a-btn-danger w-full" type="submit">Delete lead</button></form>
    <?php endif; ?>
  </div>
</div>
<?php admin_end();

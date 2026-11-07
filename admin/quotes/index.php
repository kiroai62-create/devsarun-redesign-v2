<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/admin_boot.php';
require_admin();

if (is_post()) {
    admin_post_guard('manager');
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create') {
        $title = single_line(mb_substr((string)($_POST['title'] ?? ''), 0, 190));
        $amount = (float)str_replace(',', '', (string)($_POST['amount'] ?? '0'));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $name = single_line(mb_substr((string)($_POST['name'] ?? ''), 0, 120));
        if ($title === '' || $amount < 1 || !valid_email($email) || $name === '') {
            flash('error', 'Name, valid email, title and amount are required.');
        } else {
            // Attach to an existing lead with this email, or create one.
            $lead = db_row('SELECT id FROM leads WHERE email = ? ORDER BY id DESC LIMIT 1', [$email]);
            $leadId = $lead ? (int)$lead['id'] : db_insert('leads', ['ref' => lead_ref(), 'source' => 'contact', 'service_label' => $title, 'project_title' => $title, 'description' => 'Quote created manually by admin.', 'features' => '[]', 'name' => $name, 'email' => $email, 'phone' => mb_substr((string)($_POST['phone'] ?? ''), 0, 20) ?: null, 'status' => 'proposal', 'priority' => 'normal', 'value_estimate' => (int)$amount, 'created_at' => now()]);
            $token = random_token(20);
            $valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['valid_until'] ?? '')) ? $_POST['valid_until'] : null;
            $qid = db_insert('quotes', ['token' => $token, 'lead_id' => $leadId, 'title' => $title, 'description' => mb_substr(trim((string)($_POST['description'] ?? '')), 0, 5000) ?: null, 'amount_paise' => (int)round($amount * 100), 'status' => 'sent', 'valid_until' => $valid, 'created_by' => admin()['id'], 'created_at' => now()]);
            lead_activity($leadId, 'quote', 'Quote shared: ' . $title . ' — ' . inr($amount), true);
            audit('quote.create', 'quote', $qid);
            if (!empty($_POST['send_email'])) {
                $link = abs_url('pay/' . $token . '/');
                [$ok] = send_mail($email, 'Your quote: ' . $title, email_layout('Your quote is ready', '<p>Hi ' . e($name) . ',</p><p><strong>' . e($title) . '</strong> — <strong>' . e(inr($amount)) . '</strong></p>' . (!empty($_POST['description']) ? '<p>' . nl2br(e((string)$_POST['description'])) . '</p>' : '') . '<p>Pay securely online with the button below. Want changes? Just reply — it\'s negotiable.</p>', 'View & pay securely', $link), ['kind' => 'quote', 'lead_id' => $leadId]);
                flash($ok ? 'success' : 'error', $ok ? 'Quote created and emailed.' : 'Quote created, email failed — copy the link.');
            } else {
                flash('success', 'Quote created.');
            }
        }
    } elseif ($action === 'cancel') {
        db_update('quotes', ['status' => 'cancelled'], 'id = ? AND status = ?', [(int)$_POST['id'], 'sent']);
        audit('quote.cancel', 'quote', (int)$_POST['id']);
        flash('success', 'Quote cancelled — the payment link no longer works.');
    }
    redirect('admin/quotes/');
}
$rows = db_all('SELECT q.*, l.name, l.email, l.ref FROM quotes q LEFT JOIN leads l ON l.id = q.lead_id ORDER BY q.id DESC LIMIT 200');
admin_start('Quotes & payment links', 'quotes');
?>
<div class="grid gap-4 xl:grid-cols-[1fr_380px]">
  <div class="a-card overflow-x-auto p-0">
    <table class="a-table"><thead><tr><th>Quote</th><th>Client</th><th>Amount</th><th>Status</th><th>Valid</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $q): $link = abs_url('pay/' . $q['token'] . '/'); ?>
      <tr>
        <td class="max-w-[240px]"><?= e($q['title']) ?><span class="block text-xs text-white/35"><?= e(date('d M Y', strtotime($q['created_at']))) ?></span></td>
        <td><?php if ($q['lead_id']): ?><a class="hover:text-neo-green" href="<?= e(url('admin/leads/view/?id=' . $q['lead_id'])) ?>"><?= e($q['name']) ?></a><span class="block text-xs text-white/40"><?= e($q['email']) ?></span><?php else: ?>—<?php endif; ?></td>
        <td class="whitespace-nowrap font-semibold text-neo-green"><?= e(inr($q['amount_paise'] / 100)) ?></td>
        <td><?= status_badge($q['status']) ?></td>
        <td class="whitespace-nowrap text-xs text-white/45"><?= $q['valid_until'] ? e(date('d M Y', strtotime($q['valid_until']))) : '—' ?></td>
        <td class="whitespace-nowrap"><?php if ($q['status'] === 'sent'): ?><button type="button" class="a-btn-ghost !py-1.5 text-xs" data-copy="<?= e($link) ?>">Copy link</button>
          <form method="post" class="inline" data-confirm="Cancel this quote? The link will stop working."><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$q['id'] ?>"><button class="a-btn-ghost !py-1.5 text-xs">Cancel</button></form><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="py-12 text-center text-white/40">No quotes yet. Create one here or from a lead.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
  <?php if (can('manager')): ?>
  <form method="post" class="a-card h-fit space-y-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <h3 class="font-semibold">New quote / payment link</h3>
    <p class="text-xs text-white/40">For negotiated deals. The client pays the exact amount via Razorpay.</p>
    <input class="a-input" name="name" placeholder="Client name" required maxlength="120">
    <input class="a-input" type="email" name="email" placeholder="Client email" required maxlength="190">
    <input class="a-input" name="phone" placeholder="Phone (optional)" maxlength="20">
    <input class="a-input" name="title" placeholder="What is this for?" required maxlength="190">
    <input class="a-input" name="amount" inputmode="decimal" placeholder="Amount in ₹" required>
    <textarea class="a-input" name="description" placeholder="Scope summary (optional)"></textarea>
    <div><label class="a-label">Valid until</label><input class="a-input [color-scheme:dark]" type="date" name="valid_until" value="<?= e(date('Y-m-d', strtotime('+14 days'))) ?>"></div>
    <label class="flex items-center gap-2 text-sm text-white/60"><input type="checkbox" name="send_email" value="1" checked class="accent-[#4ADE80]"> Email the link to the client</label>
    <button class="a-btn w-full">Create quote</button>
  </form>
  <?php endif; ?>
</div>
<?php admin_end();

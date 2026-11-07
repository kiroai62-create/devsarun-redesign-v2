<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* POST /api/chat/ — AI assistant. Actions: send | history */
if (!is_post()) {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}
csrf_check(true);

$action = input('action', 20);
$token = preg_replace('/[^a-f0-9]/', '', input('token', 64));
$session = $token !== '' ? db_row('SELECT * FROM chat_sessions WHERE token = ?', [$token]) : null;

if ($action === 'history') {
    if (!$session) {
        json_out(['ok' => true, 'messages' => []]);
    }
    $rows = db_all('SELECT role, content FROM chat_messages WHERE session_id = ? ORDER BY id DESC LIMIT 40', [$session['id']]);
    $out = array_map(static fn($r) => ['role' => $r['role'] === 'user' ? 'user' : 'bot', 'html' => $r['role'] === 'user' ? e($r['content']) : chat_format($r['content'])], array_reverse($rows));
    json_out(['ok' => true, 'messages' => $out]);
}

if ($action !== 'send') {
    json_out(['ok' => false, 'error' => 'Unknown action'], 400);
}
if (setting('ai_enabled', '1') !== '1') {
    json_out(['ok' => false, 'error' => 'The assistant is offline right now. Please use the contact form or WhatsApp.'], 503);
}

$message = trim(input('message', 800));
if ($message === '') {
    json_out(['ok' => false, 'error' => 'Please type a message.'], 422);
}
if (rate_limited('chat', 40, 600)) {
    json_out(['ok' => false, 'error' => 'You are sending messages too quickly. Please wait a minute.'], 429);
}

if (!$session) {
    $token = random_token(24);
    $sid = db_insert('chat_sessions', [
        'token' => $token, 'visitor_id' => visitor_id(), 'page' => mb_substr(input('page', 255), 0, 255),
        'ip_hash' => ip_hash(), 'created_at' => now(), 'last_at' => now(),
    ]);
    $session = db_row('SELECT * FROM chat_sessions WHERE id = ?', [$sid]);
}
$sid = (int)$session['id'];
if ((int)$session['messages_count'] > 200) {
    json_out(['ok' => false, 'error' => 'This conversation is very long. Please use the project brief form so nothing gets lost.'], 429);
}

db_insert('chat_messages', ['session_id' => $sid, 'role' => 'user', 'content' => $message, 'created_at' => now()]);
track_event('chat_message', mb_substr($message, 0, 120));

// Lead capture from chat: detect email / phone.
$updates = [];
if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $message, $m) && valid_email(strtolower($m[0]))) {
    $updates['email'] = strtolower($m[0]);
}
if (preg_match('/(\+?\d[\d\s\-]{8,16}\d)/', $message, $m) && valid_phone(trim($m[1]))) {
    $updates['phone'] = mb_substr(trim($m[1]), 0, 30);
}
if (preg_match('/\b(?:my name is|i am|i\'m|mera naam|naam)\s+([a-z\x{0900}-\x{097F}][a-z\x{0900}-\x{097F} ]{1,40})/iu', $message, $m)) {
    $updates['name'] = mb_substr(ucwords(trim(preg_split('/\s+(?:and|aur|,)/i', $m[1])[0])), 0, 120);
}
if ($updates) {
    db_update('chat_sessions', $updates, 'id = ?', [$sid]);
    $session = array_merge($session, $updates);
    $contact = $session['email'] ?? null;
    if (!$session['lead_id'] && ($contact || !empty($session['phone']))) {
        $transcript = implode("\n", array_map(static fn($r) => ($r['role'] === 'user' ? 'Visitor: ' : 'AI: ') . $r['content'], array_reverse(db_all('SELECT role, content FROM chat_messages WHERE session_id = ? ORDER BY id DESC LIMIT 12', [$sid]))));
        $attr = attribution();
        $ref = lead_ref();
        $leadId = db_insert('leads', [
            'ref' => $ref, 'source' => 'chat', 'service_label' => 'From AI chat', 'project_title' => 'AI chat enquiry',
            'description' => mb_substr($transcript, 0, 5000), 'features' => '[]',
            'name' => $session['name'] ?: 'Chat visitor', 'email' => $contact ?: 'unknown@chat.local', 'phone' => $session['phone'] ?? null,
            'status' => 'new', 'priority' => 'normal', 'landing' => $attr['landing'] ?: null, 'referrer' => $attr['referrer'] ?: null,
            'utm_source' => $attr['utm_source'] ?: null, 'ip_hash' => ip_hash(), 'user_agent' => user_agent(), 'created_at' => now(),
        ]);
        db_update('chat_sessions', ['lead_id' => $leadId], 'id = ?', [$sid]);
        lead_activity($leadId, 'created', 'Lead captured by the AI assistant', false);
        $notify = setting('notify_email', setting('contact_email'));
        if ($notify && valid_email($notify)) {
            send_mail($notify, "💬 New chat lead {$ref}", email_layout('New lead from AI chat', '<p>Contact: <strong>' . e(($contact ?: '') . ' ' . ($session['phone'] ?? '')) . '</strong></p><p style="white-space:pre-wrap">' . nl2br(e($transcript)) . '</p>', 'Open lead', abs_url('admin/leads/view/?id=' . $leadId)), ['kind' => 'admin_notify', 'lead_id' => $leadId]);
        }
    } elseif ($session['lead_id']) {
        $leadUpd = array_intersect_key($updates, ['email' => 1, 'phone' => 1, 'name' => 1]);
        if ($leadUpd) {
            db_update('leads', $leadUpd + ['updated_at' => now()], 'id = ?', [$session['lead_id']]);
        }
    }
}

$history = array_map(static fn($r) => ['role' => $r['role'] === 'user' ? 'user' : 'assistant', 'content' => $r['content']], array_reverse(db_all('SELECT role, content FROM chat_messages WHERE session_id = ? ORDER BY id DESC LIMIT 14', [$sid])));
$reply = ai_reply($history);
$text = mb_substr($reply['text'], 0, 4000);

db_insert('chat_messages', ['session_id' => $sid, 'role' => 'assistant', 'content' => $text, 'created_at' => now()]);
db_exec('UPDATE chat_sessions SET messages_count = messages_count + 2, last_at = NOW(), engine = ? WHERE id = ?', [$reply['engine'], $sid]);

// Contextual quick replies.
$low = mb_strtolower($message . ' ' . $text);
$chips = ['Start a project', 'Is the price negotiable?'];
if (!$session['email'] && !$session['phone'] && (int)$session['messages_count'] >= 2) {
    array_unshift($chips, 'Share my WhatsApp number');
}
if (str_contains($low, 'aeo') || str_contains($low, 'geo')) {
    $chips[] = 'What do I get for $199?';
} elseif (str_contains($low, 'website')) {
    $chips[] = 'How long does a website take?';
} else {
    $chips[] = 'Show me your work';
}

json_out(['ok' => true, 'token' => $token, 'html' => chat_format($text), 'chips' => array_slice(array_values(array_unique($chips)), 0, 4)]);

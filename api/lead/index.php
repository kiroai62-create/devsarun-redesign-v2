<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/*
 * POST /api/lead/  — project brief (multipart), contact form and AEO audit requests.
 */
if (!is_post()) {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}
csrf_check(true);

// Honeypot: bots fill hidden fields. Pretend success.
if (input('website_hp') !== '') {
    json_out(['ok' => true, 'ref' => lead_ref()]);
}
if (rate_limited('lead', 6, 600)) {
    json_out(['ok' => false, 'error' => 'Too many submissions. Please wait a few minutes or message on WhatsApp.'], 429);
}

$source = input('source', 20, 'brief');
if (!in_array($source, ['brief', 'contact', 'audit', 'enquiry'], true)) {
    $source = 'brief';
}

$name = single_line(input('name', 120));
$email = strtolower(input('email', 190));
$phone = input('phone', 20);
$cc = input('country_code', 8, '+91');
$title = single_line(input('project_title', 190) ?: input('subject', 190));
$desc = input('description', 5000) ?: input('message', 5000);
$website = input('existing_website', 500);
$errors = [];

if (mb_strlen($name) < 2) $errors[] = 'Please enter your name.';
if (!valid_email($email)) $errors[] = 'Please enter a valid email address.';
if ($phone !== '' && !valid_phone($phone)) $errors[] = 'Please enter a valid phone number.';
if ($cc !== '' && !preg_match('/^\+\d{1,4}$/', $cc)) $cc = '';
if ($website !== '' && !valid_url($website)) $errors[] = 'Please enter a valid website URL (https://…).';

if ($source === 'brief') {
    if ($phone === '') $errors[] = 'Please enter your phone / WhatsApp number.';
    if ($title === '') $errors[] = 'Please enter a project name.';
    if (mb_strlen($desc) < 20) $errors[] = 'Please describe the project in at least 20 characters.';
    if (input('consent') !== '1') $errors[] = 'Please accept to be contacted about this project.';
} elseif ($source === 'contact') {
    if (mb_strlen($desc) < 10) $errors[] = 'Message must be at least 10 characters.';
} elseif ($source === 'enquiry') {
    if ($phone === '') $errors[] = 'Please enter your phone / WhatsApp number.';
} else { // audit
    if ($website === '') $errors[] = 'Please enter your website URL.';
}
if ($errors) {
    json_out(['ok' => false, 'error' => $errors[0], 'errors' => $errors], 422);
}

$service = null;
if (($slug = preg_replace('/[^a-z0-9-]/', '', input('service', 80))) !== '') {
    $service = service_by_slug($slug);
}

// Optional attachment (stored outside the public web root, served only to admins).
$attachPath = null;
$attachName = null;
if (!empty($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['attachment'];
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        json_out(['ok' => false, 'error' => 'File upload failed. Try a smaller file.'], 422);
    }
    if ($f['size'] > 8 * 1024 * 1024) {
        json_out(['ok' => false, 'error' => 'The attachment must be smaller than 8 MB.'], 422);
    }
    $allowed = [
        'pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'webp' => ['image/webp'],
        'txt' => ['text/plain'], 'zip' => ['application/zip', 'application/x-zip-compressed'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    ];
    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
    if (!isset($allowed[$ext]) || !in_array($mime, $allowed[$ext], true)) {
        json_out(['ok' => false, 'error' => 'File type not allowed. Use PDF, DOC, images, TXT or ZIP.'], 422);
    }
    $dir = STORAGE . '/uploads/briefs';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        json_out(['ok' => false, 'error' => 'Upload storage unavailable.'], 500);
    }
    $stored = date('Ym') . '_' . random_token(16) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $stored)) {
        json_out(['ok' => false, 'error' => 'Could not save the file.'], 500);
    }
    $attachPath = 'briefs/' . $stored;
    $attachName = mb_substr(preg_replace('/[^\w.\- ]+/u', '_', (string)$f['name']) ?? 'file', 0, 190);
}

$package = single_line(input('package', 80));
if ($source === 'enquiry') {
    $title = $title ?: trim('Enquiry: ' . ($service['title'] ?? 'General') . ($package ? ' — ' . $package : ''));
    $desc = trim(($package ? "Interested package: {$package}\n\n" : '') . ($desc ?: 'Requested a call back / details.'));
}

$startDate = input('start_date', 10);
$startDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) ? $startDate : null;
$attr = attribution();

$data = [
    'source' => $source,
    'service_id' => $service['id'] ?? null,
    'service_label' => $service['title'] ?? ($source === 'contact' ? 'General enquiry' : null),
    'project_title' => $title ?: null,
    'description' => $desc ?: null,
    'features' => json_encode(input_array('features'), JSON_UNESCAPED_UNICODE),
    'budget' => single_line(input('budget', 60)) ?: null,
    'timeline' => single_line(input('timeline', 60)) ?: null,
    'start_date' => $startDate,
    'existing_website' => $website ?: null,
    'reference_links' => input('reference_links', 1000) ?: null,
    'name' => $name,
    'email' => $email,
    'phone' => $phone ?: null,
    'country_code' => $cc ?: null,
    'company' => single_line(input('company', 150)) ?: null,
    'preferred_contact' => single_line(input('preferred_contact', 20)) ?: null,
    'call_time' => single_line(input('call_time', 60)) ?: null,
    'heard_from' => single_line(input('heard_from', 60)) ?: null,
    'attachment_path' => $attachPath,
    'attachment_name' => $attachName,
    'status' => 'new',
    'priority' => in_array(input('timeline', 60), ['ASAP (this week)'], true) ? 'high' : 'normal',
    'landing' => $attr['landing'] ?: null,
    'referrer' => $attr['referrer'] ?: null,
    'utm_source' => $attr['utm_source'] ?: null,
    'utm_medium' => $attr['utm_medium'] ?: null,
    'utm_campaign' => $attr['utm_campaign'] ?: null,
    'ip_hash' => ip_hash(),
    'user_agent' => user_agent(),
    'created_at' => now(),
];

$ref = '';
for ($try = 0; $try < 5; $try++) {
    $ref = lead_ref();
    try {
        $leadId = db_insert('leads', ['ref' => $ref] + $data);
        break;
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000' || $try === 4) {
            throw $e;
        }
    }
}
$labels = ['brief' => 'Project brief submitted', 'contact' => 'Contact form message', 'audit' => 'Free AEO/GEO visibility check requested', 'enquiry' => 'Service enquiry' . ($service ? ' — ' . $service['title'] : '') . ($package ? ' (' . $package . ')' : '')];
lead_activity($leadId, 'created', $labels[$source], true);
track_event(['brief' => 'form_submit', 'contact' => 'contact_submit', 'audit' => 'audit_request', 'enquiry' => 'enquiry_submit'][$source], $ref, $service ? (int)$service['id'] : null);

// Emails (failures are logged, never block the user).
$trackUrl = abs_url('track/?ref=' . $ref . '&email=' . rawurlencode($email));
$svcLabel = $service['title'] ?? 'your project';
$clientBody = '<p>Hi ' . e($name) . ',</p><p>Thanks for reaching out! I have received your ' . e($source === 'audit' ? 'AEO/GEO visibility check request' : ($source === 'contact' ? 'message' : ($source === 'enquiry' ? 'enquiry about ' . $svcLabel . ($package ? ' (' . $package . ')' : '') : 'project brief for ' . $svcLabel))) . '.</p>'
    . '<p>Your reference ID is <strong style="font-family:monospace;font-size:16px">' . e($ref) . '</strong>.</p>'
    . '<p>I will get back to you within 24 hours. And remember — every price is negotiable, so feel free to share your budget.</p>';
send_mail($email, "We received your request — {$ref}", email_layout('Request received ✅', $clientBody, 'Track your request', $trackUrl), ['kind' => 'lead_ack', 'lead_id' => $leadId]);

$notify = setting('notify_email', setting('contact_email'));
if ($notify && valid_email($notify)) {
    $rows = '';
    foreach (['Source' => $source, 'Service' => $svcLabel, 'Package' => $package, 'Project' => $title, 'Budget' => $data['budget'], 'Timeline' => $data['timeline'], 'Name' => $name, 'Email' => $email, 'Phone' => trim(($cc ?: '') . ' ' . $phone), 'Company' => $data['company'], 'Website' => $website, 'Preferred' => $data['preferred_contact']] as $k => $v) {
        if ($v) {
            $rows .= '<tr><td style="padding:4px 12px 4px 0;color:#888">' . e($k) . '</td><td style="padding:4px 0"><strong>' . e((string)$v) . '</strong></td></tr>';
        }
    }
    $adminBody = '<table>' . $rows . '</table><p style="margin-top:16px;white-space:pre-wrap">' . nl2br(e($desc)) . '</p>';
    send_mail($notify, "🔥 New lead {$ref}: {$svcLabel} — {$name}", email_layout('New lead: ' . $ref, $adminBody, 'Open in admin', abs_url('admin/leads/view/?id=' . $leadId)), ['kind' => 'admin_notify', 'lead_id' => $leadId, 'reply_to' => $email]);
}

json_out(['ok' => true, 'ref' => $ref, 'message' => 'Thank you! Your reference is ' . $ref]);

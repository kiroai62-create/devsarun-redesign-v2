<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/admin_boot.php';
require_admin('manager');

$tabs = ['general' => 'General', 'branding' => 'Branding & icon', 'email' => 'Email / SMTP', 'payments' => 'Razorpay', 'ai' => 'AI assistant', 'seo' => 'SEO'];
$tab = array_key_exists($_GET['tab'] ?? '', $tabs) ? $_GET['tab'] : 'general';
$fields = [
    'general' => ['site_name', 'owner_name', 'site_tagline', 'contact_email', 'notify_email', 'whatsapp', 'location', 'response_time', 'availability', 'github_url', 'instagram_url', 'twitter_url', 'threads_url'],
    'email' => ['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'smtp_pass', 'mail_from', 'mail_from_name'],
    'payments' => ['razorpay_enabled', 'razorpay_key_id', 'razorpay_key_secret', 'razorpay_webhook_secret'],
    'ai' => ['ai_enabled', 'ai_provider', 'ai_model', 'ai_base_url', 'ai_api_key', 'ai_greeting', 'ai_knowledge'],
    'seo' => ['seo_title', 'seo_description'],
];
$test = null;

/** Save an uploaded brand image after re-encoding it with GD (strips any embedded payload). */
function save_brand_image(array $f, string $name, int $maxSide): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || $f['size'] > 2 * 1024 * 1024) {
        return null;
    }
    $info = @getimagesize($f['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!$info || !in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true) || $info['mime'] !== $mime) {
        return null;
    }
    $src = match ($mime) { 'image/png' => @imagecreatefrompng($f['tmp_name']), 'image/jpeg' => @imagecreatefromjpeg($f['tmp_name']), default => @imagecreatefromwebp($f['tmp_name']) };
    if (!$src) {
        return null;
    }
    [$w, $h] = [imagesx($src), imagesy($src)];
    $scale = min(1, $maxSide / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $dir = ROOT . '/uploads/brand';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $name . '-' . substr(random_token(6), 0, 10) . '.png';
    $ok = imagepng($dst, $dir . '/' . $file, 6);
    imagedestroy($src);
    imagedestroy($dst);
    return $ok ? 'uploads/brand/' . $file : null;
}

if (is_post()) {
    admin_post_guard('owner');
    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'save' && isset($fields[$tab])) {
        foreach ($fields[$tab] as $k) {
            if (in_array($k, ['razorpay_enabled', 'ai_enabled'], true)) {
                setting_set($k, !empty($_POST[$k]) ? '1' : '0');
                continue;
            }
            $val = trim((string)($_POST[$k] ?? ''));
            if (in_array($k, SECRET_SETTINGS, true) && $val === '') {
                continue; // keep existing secret
            }
            $max = in_array($k, ['ai_knowledge'], true) ? 12000 : 500;
            setting_set($k, mb_substr($val, 0, $max));
        }
        if (!empty($_POST['clear_secret']) && in_array($_POST['clear_secret'], SECRET_SETTINGS, true)) {
            setting_set((string)$_POST['clear_secret'], '');
        }
        audit('settings.save', 'settings', null, ['tab' => $tab]);
        flash('success', $tabs[$tab] . ' settings saved.');
    } elseif ($action === 'brand') {
        foreach (['logo' => 512, 'favicon' => 256] as $k => $size) {
            if (!empty($_FILES[$k]) && $_FILES[$k]['error'] !== UPLOAD_ERR_NO_FILE) {
                $path = save_brand_image($_FILES[$k], $k, $size);
                if ($path) {
                    $old = setting($k . '_path');
                    if ($old && str_starts_with($old, 'uploads/brand/') && is_file(ROOT . '/' . $old)) {
                        @unlink(ROOT . '/' . $old);
                    }
                    setting_set($k . '_path', $path);
                    flash('success', ucfirst($k) . ' updated.');
                } else {
                    flash('error', ucfirst($k) . ': upload a PNG, JPG or WebP under 2 MB.');
                }
            }
        }
        foreach (['logo', 'favicon'] as $k) {
            if (!empty($_POST['reset_' . $k])) {
                setting_set($k . '_path', '');
                flash('success', ucfirst($k) . ' reset to the default icon.');
            }
        }
        audit('settings.branding', 'settings');
    } elseif ($action === 'test_email') {
        $to = strtolower(trim((string)($_POST['test_to'] ?? '')));
        if (valid_email($to)) {
            [$ok, $err] = send_mail($to, 'Test email from ' . setting('site_name', 'Devs Arun'), email_layout('SMTP is working ✅', '<p>This is a test email from your admin panel.</p>'), ['kind' => 'test']);
            flash($ok ? 'success' : 'error', $ok ? 'Test email sent to ' . $to : 'Failed: ' . $err);
        }
    } elseif ($action === 'test_razorpay') {
        try {
            razorpay_test_credentials();
            flash('success', 'Razorpay credentials are valid ✅ (' . razorpay_mode() . ' mode)');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
    } elseif ($action === 'test_ai') {
        $q = trim(mb_substr((string)($_POST['test_q'] ?? 'What are your prices?'), 0, 500));
        $res = ai_reply([['role' => 'user', 'content' => $q]]);
        $_SESSION['ai_test'] = ['q' => $q, 'a' => $res['text'], 'engine' => $res['engine']];
    }
    redirect('admin/settings/?tab=' . $tab);
}
if (isset($_SESSION['ai_test'])) {
    $test = $_SESSION['ai_test'];
    unset($_SESSION['ai_test']);
}

$field = static function (string $k, string $label, string $type = 'text', string $help = '', string $ph = ''): void {
    $secret = in_array($k, SECRET_SETTINGS, true);
    $val = $secret ? '' : setting($k);
    echo '<div><label class="a-label" for="f-' . e($k) . '">' . e($label) . '</label>';
    if ($type === 'textarea') {
        echo '<textarea id="f-' . e($k) . '" class="a-input min-h-[160px]" name="' . e($k) . '" placeholder="' . e($ph) . '">' . e($val) . '</textarea>';
    } else {
        echo '<input id="f-' . e($k) . '" class="a-input" type="' . ($secret ? 'password' : e($type)) . '" name="' . e($k) . '" value="' . e($val) . '" placeholder="' . e($secret && setting($k) !== '' ? '•••••••• saved (leave blank to keep)' : $ph) . '" autocomplete="off">';
    }
    if ($help) echo '<p class="mt-1 text-xs text-white/35">' . $help . '</p>';
    echo '</div>';
};
admin_start('Settings', 'settings');
?>
<div class="flex gap-2 overflow-x-auto pb-2"><?php foreach ($tabs as $k => $l): ?><a href="?tab=<?= e($k) ?>" class="chip flex-none <?= $tab === $k ? 'is-active' : '' ?>"><?= e($l) ?></a><?php endforeach; ?></div>
<?php if (!can('owner')): ?><p class="mt-3 rounded-2xl border border-amber-400/20 bg-amber-400/[0.06] px-4 py-3 text-sm text-amber-100">Only the owner can change settings.</p><?php endif; ?>

<?php if ($tab === 'branding'): ?>
  <form method="post" enctype="multipart/form-data" class="a-card mt-3 grid max-w-3xl gap-6 sm:grid-cols-2">
    <?= csrf_field() ?><input type="hidden" name="action" value="brand">
    <?php foreach (['logo' => 'Logo / app icon (square, 512px)', 'favicon' => 'Favicon (square, 256px)'] as $k => $l): ?>
      <div>
        <p class="a-label"><?= e($l) ?></p>
        <div class="flex items-center gap-4"><img src="<?= e($k === 'logo' ? brand_logo_url() : brand_favicon_url()) ?>" alt="" class="h-16 w-16 rounded-2xl border border-white/10 bg-white/[0.03] object-contain"><span class="text-xs text-white/40"><?= setting($k . '_path') ? 'Custom upload' : 'Default DevsArun icon' ?></span></div>
        <input class="a-input mt-3" type="file" name="<?= $k ?>" accept="image/png,image/jpeg,image/webp">
        <?php if (setting($k . '_path')): ?><label class="mt-2 flex items-center gap-2 text-xs text-white/50"><input type="checkbox" name="reset_<?= $k ?>" value="1" class="accent-[#4ADE80]"> Reset to default icon</label><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <p class="text-xs text-white/35 sm:col-span-2">Images are re-encoded to PNG on upload (safe, metadata stripped). The default icon is the green <code>&lt;A&gt;</code> mark in <code>assets/img/logo.svg</code>.</p>
    <?php if (can('owner')): ?><div><button class="a-btn">Save branding</button></div><?php endif; ?>
  </form>
<?php else: ?>
  <form method="post" class="a-card mt-3 grid max-w-3xl gap-4 sm:grid-cols-2">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <?php if ($tab === 'general'): ?>
      <?php $field('site_name', 'Site name'); $field('owner_name', 'Your name'); ?>
      <div class="sm:col-span-2"><?php $field('site_tagline', 'Tagline'); ?></div>
      <?php $field('contact_email', 'Public contact email', 'email'); $field('notify_email', 'Lead notification email', 'email', 'New leads, chats & payments are emailed here.'); ?>
      <?php $field('whatsapp', 'WhatsApp number', 'text', 'With country code, e.g. +91 76673 11203'); $field('location', 'Location'); ?>
      <?php $field('response_time', 'Response time'); $field('availability', 'Availability status'); ?>
      <?php $field('github_url', 'GitHub URL', 'url'); $field('instagram_url', 'Instagram URL', 'url'); $field('twitter_url', 'X / Twitter URL', 'url'); $field('threads_url', 'Threads URL', 'url'); ?>
    <?php elseif ($tab === 'email'): ?>
      <p class="sm:col-span-2 text-sm text-white/60">Mail engine: <strong class="text-neo-green"><?= e(mail_engine()) ?></strong></p>
      <div class="sm:col-span-2 rounded-2xl bg-white/[0.03] p-4 text-xs leading-relaxed text-white/50">Gmail: host <code>smtp.gmail.com</code>, port <code>587</code>, TLS, your Gmail + an <strong>App Password</strong>. Hostinger: <code>smtp.hostinger.com</code>, port <code>465</code>, SSL. The password is stored encrypted (AES-256-GCM).</div>
      <?php $field('smtp_host', 'SMTP host', 'text', '', 'smtp.gmail.com'); ?>
      <div class="grid grid-cols-2 gap-3"><?php $field('smtp_port', 'Port', 'number'); ?><div><label class="a-label">Encryption</label><select class="a-input" name="smtp_encryption"><?php foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None'] as $k => $l): ?><option value="<?= $k ?>" <?= setting('smtp_encryption', 'tls') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div></div>
      <?php $field('smtp_user', 'SMTP username'); $field('smtp_pass', 'SMTP password'); $field('mail_from', 'From email', 'email', 'Usually the same as the SMTP username.'); $field('mail_from_name', 'From name'); ?>
    <?php elseif ($tab === 'payments'): ?>
      <p class="sm:col-span-2 text-sm text-white/60">Payment engine: <strong class="text-neo-green"><?= e(razorpay_engine()) ?></strong> · Mode: <strong class="<?= razorpay_mode() === 'live' ? 'text-neo-green' : 'text-amber-200' ?>"><?= e(razorpay_mode()) ?></strong></p>
      <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="razorpay_enabled" value="1" <?= setting('razorpay_enabled') === '1' ? 'checked' : '' ?> class="accent-[#4ADE80]"> Enable online payments</label>
      <?php $field('razorpay_key_id', 'Key ID', 'text', 'rzp_test_… for testing, rzp_live_… for production'); $field('razorpay_key_secret', 'Key secret'); ?>
      <div class="sm:col-span-2"><?php $field('razorpay_webhook_secret', 'Webhook secret', 'text', 'Razorpay Dashboard → Settings → Webhooks. Events: <code>payment.captured</code>, <code>order.paid</code>, <code>payment.failed</code>.'); ?></div>
      <div class="sm:col-span-2 rounded-2xl bg-white/[0.03] p-4 text-sm"><p class="a-label">Webhook URL</p><div class="flex flex-wrap items-center gap-2"><code class="break-all text-neo-green"><?= e(abs_url('api/razorpay-webhook/')) ?></code><button type="button" class="a-btn-ghost !py-1 text-xs" data-copy="<?= e(abs_url('api/razorpay-webhook/')) ?>">Copy</button></div></div>
    <?php elseif ($tab === 'ai'): ?>
      <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="ai_enabled" value="1" <?= setting('ai_enabled', '1') === '1' ? 'checked' : '' ?> class="accent-[#4ADE80]"> Assistant enabled on the website</label>
      <div><label class="a-label">Provider</label><select class="a-input" name="ai_provider"><?php foreach (['gemini' => 'Google Gemini', 'openai' => 'OpenAI', 'compatible' => 'OpenAI-compatible (Groq, OpenRouter…)'] as $k => $l): ?><option value="<?= $k ?>" <?= setting('ai_provider', 'gemini') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <?php $field('ai_model', 'Model', 'text', 'e.g. gemini-2.0-flash · gpt-4o-mini · llama-3.3-70b-versatile'); ?>
      <?php $field('ai_api_key', 'API key', 'text', 'Encrypted at rest. Leave empty to use the built-in rules engine (free, offline).'); $field('ai_base_url', 'Base URL (compatible providers only)', 'url'); ?>
      <div class="sm:col-span-2"><?php $field('ai_greeting', 'Greeting message'); ?></div>
      <div class="sm:col-span-2"><?php $field('ai_knowledge', 'Extra training / knowledge', 'textarea', 'Facts, offers, policies, FAQs. Services, prices and the “every price is negotiable” rule are added automatically from the Services table.', "- Diwali offer: 10% off landing pages\n- We also provide hosting setup"); ?></div>
    <?php elseif ($tab === 'seo'): ?>
      <div class="sm:col-span-2"><?php $field('seo_title', 'Homepage title'); ?></div>
      <div class="sm:col-span-2"><?php $field('seo_description', 'Homepage meta description', 'textarea'); ?></div>
      <p class="text-xs text-white/40 sm:col-span-2">Sitemap: <a class="text-neo-green" href="<?= e(url('sitemap.xml')) ?>" target="_blank"><?= e(abs_url('sitemap.xml')) ?></a> — submit it in Google Search Console.</p>
    <?php endif; ?>
    <?php if (can('owner')): ?><div class="sm:col-span-2"><button class="a-btn" type="submit">Save <?= e($tabs[$tab]) ?></button></div><?php endif; ?>
  </form>

  <?php if ($tab === 'email' && can('owner')): ?>
    <form method="post" class="a-card mt-4 flex max-w-3xl flex-wrap items-end gap-3"><?= csrf_field() ?><input type="hidden" name="action" value="test_email"><div class="flex-1"><label class="a-label">Send a test email to</label><input class="a-input" type="email" name="test_to" value="<?= e(admin()['email']) ?>" required></div><button class="a-btn-ghost">Send test</button></form>
  <?php elseif ($tab === 'payments' && can('owner')): ?>
    <form method="post" class="a-card mt-4 flex max-w-3xl items-center justify-between gap-3"><?= csrf_field() ?><input type="hidden" name="action" value="test_razorpay"><p class="text-sm text-white/55">Check that the saved Key ID / Secret are accepted by Razorpay.</p><button class="a-btn-ghost">Test credentials</button></form>
  <?php elseif ($tab === 'ai'): ?>
    <form method="post" class="a-card mt-4 max-w-3xl"><?= csrf_field() ?><input type="hidden" name="action" value="test_ai">
      <label class="a-label">Test the assistant</label><div class="flex gap-2"><input class="a-input" name="test_q" value="<?= e($test['q'] ?? 'Website banane ka kitna price hai?') ?>"><?php if (can('owner')): ?><button class="a-btn-ghost flex-none">Ask</button><?php endif; ?></div>
      <?php if ($test): ?><div class="msg msg-bot mt-4 max-w-none"><?= chat_format($test['a']) ?><span class="mt-2 block text-[10px] text-white/40">engine: <?= e($test['engine']) ?></span></div><?php endif; ?>
    </form>
  <?php endif; ?>
<?php endif; ?>
<?php admin_end();

<?php
declare(strict_types=1);

/*
 * Dependency-free SMTP mailer (SSL / STARTTLS / plain, AUTH LOGIN).
 * Falls back to PHP mail() when SMTP is not configured.
 * Every send is logged to `email_log`.
 */

final class SmtpMailer
{
    /** @var resource|null */
    private $sock = null;
    private array $transcript = [];

    public function __construct(
        private string $host,
        private int $port,
        private string $encryption, // ssl | tls | none
        private string $user,
        private string $pass,
        private int $timeout = 20,
    ) {}

    public function send(string $from, string $fromName, array $to, string $subject, string $mime, string $headers): void
    {
        $remote = ($this->encryption === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $sock = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) {
            throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
        }
        $this->sock = $sock;
        stream_set_timeout($sock, $this->timeout);
        try {
            $this->expect(220);
            $ehloHost = preg_replace('/[^a-z0-9.\-]/i', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
            $this->cmd("EHLO $ehloHost", 250);
            if ($this->encryption === 'tls') {
                $this->cmd('STARTTLS', 220);
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new RuntimeException('STARTTLS negotiation failed');
                }
                $this->cmd("EHLO $ehloHost", 250);
            }
            if ($this->user !== '') {
                $this->cmd('AUTH LOGIN', 334);
                $this->cmd(base64_encode($this->user), 334);
                $this->cmd(base64_encode($this->pass), 235);
            }
            $this->cmd('MAIL FROM:<' . $from . '>', 250);
            foreach ($to as $rcpt) {
                $this->cmd('RCPT TO:<' . $rcpt . '>', [250, 251]);
            }
            $this->cmd('DATA', 354);
            $data = $headers . "\r\n" . $mime;
            $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], ["\n", "\n"], $data)) ?? $data;
            $data = str_replace("\n", "\r\n", $data);
            fwrite($sock, $data . "\r\n.\r\n");
            $this->expect(250);
            $this->cmd('QUIT', 221);
        } finally {
            if (is_resource($sock)) {
                fclose($sock);
            }
        }
    }

    private function cmd(string $line, int|array $expect): string
    {
        fwrite($this->sock, $line . "\r\n");
        return $this->expect($expect);
    }

    private function expect(int|array $codes): string
    {
        $codes = (array)$codes;
        $resp = '';
        while (($line = fgets($this->sock, 1024)) !== false) {
            $resp .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $code = (int)substr($resp, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP error: ' . trim($resp ?: 'no response (timeout)'));
        }
        return $resp;
    }
}

function mail_header_safe(string $s): string
{
    return trim(str_replace(["\r", "\n", "\0"], '', $s));
}

function mime_encode_header(string $s): string
{
    $s = mail_header_safe($s);
    return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

function smtp_configured(): bool
{
    return setting('smtp_host') !== '' && setting('mail_from') !== '';
}

/**
 * Send an email. Returns [bool ok, string error].
 * $opts: reply_to, lead_id, kind, cc (array)
 */
function send_mail(string $to, string $subject, string $html, array $opts = []): array
{
    $to = mail_header_safe($to);
    if (!valid_email($to)) {
        return [false, 'Invalid recipient'];
    }
    $from = mail_header_safe(setting('mail_from', setting('contact_email', 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))));
    $fromName = setting('mail_from_name', setting('site_name', 'Devs Arun'));
    $text = trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/h\d|\/li|\/tr)\s*\/?>/i', "\n", $html) ?? $html), ENT_QUOTES, 'UTF-8'));
    $boundary = 'b_' . random_token(12);
    $domain = substr(strrchr($from, '@') ?: '@localhost', 1);

    $headers = [
        'Date: ' . date('r'),
        'From: ' . mime_encode_header($fromName) . ' <' . $from . '>',
        'To: <' . $to . '>',
        'Subject: ' . mime_encode_header($subject),
        'Message-ID: <' . random_token(12) . '@' . $domain . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: DevsArun-CRM',
    ];
    $cc = array_values(array_filter(array_map('mail_header_safe', (array)($opts['cc'] ?? [])), 'valid_email'));
    if ($cc) {
        $headers[] = 'Cc: ' . implode(', ', $cc);
    }
    if (!empty($opts['reply_to']) && valid_email((string)$opts['reply_to'])) {
        $headers[] = 'Reply-To: <' . mail_header_safe((string)$opts['reply_to']) . '>';
    }
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text))
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--$boundary--\r\n";

    $ok = false;
    $err = '';
    try {
        if (smtp_configured() && class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            [$ok, $err] = send_via_phpmailer($from, $fromName, $to, $cc, $subject, $html, $text, $opts);
        } elseif (smtp_configured()) {
            $m = new SmtpMailer(setting('smtp_host'), (int)setting('smtp_port', '587'), setting('smtp_encryption', 'tls'), setting('smtp_user'), setting('smtp_pass'));
            $m->send($from, $fromName, array_merge([$to], $cc), $subject, $body, implode("\r\n", $headers) . "\r\n");
            $ok = true;
        } else {
            // Fallback to PHP mail() (headers without To/Subject — mail() adds them).
            $h = array_filter($headers, static fn($l) => !str_starts_with($l, 'To:') && !str_starts_with($l, 'Subject:'));
            $ok = @mail($to, mime_encode_header($subject), $body, implode("\r\n", $h));
            if (!$ok) {
                $err = 'SMTP not configured and PHP mail() failed. Configure SMTP in Admin → Settings.';
            }
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }

    try {
        db_insert('email_log', [
            'to_email' => $to,
            'subject' => mb_substr($subject, 0, 250),
            'body_html' => $html,
            'kind' => mb_substr((string)($opts['kind'] ?? 'manual'), 0, 40),
            'lead_id' => $opts['lead_id'] ?? null,
            'admin_id' => $_SESSION['admin_id'] ?? null,
            'status' => $ok ? 'sent' : 'failed',
            'error' => $ok ? null : mb_substr($err, 0, 500),
            'created_at' => now(),
        ]);
    } catch (Throwable $e) {
        error_log('email_log insert failed: ' . $e->getMessage());
    }
    return [$ok, $err];
}

/** Which mail engine is active (shown in admin). */
function mail_engine(): string
{
    if (!smtp_configured()) {
        return 'PHP mail() fallback';
    }
    return class_exists(\PHPMailer\PHPMailer\PHPMailer::class) ? 'PHPMailer ' . \PHPMailer\PHPMailer\PHPMailer::VERSION . ' (SMTP)' : 'Built-in SMTP client';
}

/**
 * Send with PHPMailer (vendor). Handles SSL/STARTTLS, AUTH PLAIN/LOGIN/CRAM-MD5, UTF-8 and proper MIME.
 * $opts: reply_to, attachments => [[path, name]]
 */
function send_via_phpmailer(string $from, string $fromName, string $to, array $cc, string $subject, string $html, string $text, array $opts): array
{
    $m = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $m->isSMTP();
        $m->Host = setting('smtp_host');
        $m->Port = (int)setting('smtp_port', '587');
        $enc = setting('smtp_encryption', 'tls');
        if ($enc === 'ssl') {
            $m->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls') {
            $m->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $m->SMTPSecure = '';
            $m->SMTPAutoTLS = false;
        }
        $user = setting('smtp_user');
        $m->SMTPAuth = $user !== '';
        $m->Username = $user;
        $m->Password = setting('smtp_pass');
        $m->Timeout = 20;
        $m->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $m->Encoding = 'base64';
        $m->XMailer = 'DevsArun-CRM';
        $m->setFrom($from, $fromName);
        $m->addAddress($to);
        foreach ($cc as $c) {
            $m->addCC($c);
        }
        if (!empty($opts['reply_to']) && valid_email((string)$opts['reply_to'])) {
            $m->addReplyTo((string)$opts['reply_to']);
        }
        foreach ((array)($opts['attachments'] ?? []) as [$path, $name]) {
            if (is_file($path)) {
                $m->addAttachment($path, $name);
            }
        }
        $m->isHTML(true);
        $m->Subject = $subject;
        $m->Body = $html;
        $m->AltBody = $text;
        $m->send();
        return [true, ''];
    } catch (\Throwable $e) {
        return [false, $m->ErrorInfo ?: $e->getMessage()];
    }
}

/** Branded email layout. $bodyHtml must already be escaped/safe. */
function email_layout(string $title, string $bodyHtml, ?string $ctaText = null, ?string $ctaUrl = null): string
{
    $site = e(setting('site_name', 'Devs Arun'));
    $cta = '';
    if ($ctaText && $ctaUrl) {
        $cta = '<p style="margin:28px 0 8px"><a href="' . e($ctaUrl) . '" style="background:#4ADE80;color:#0A0A0A;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:999px;display:inline-block">' . e($ctaText) . '</a></p>';
    }
    $contact = e(setting('contact_email', 'itsdevsarun@gmail.com'));
    $wa = e(setting('whatsapp', ''));
    return '<!doctype html><html><body style="margin:0;background:#f4f5f4;font-family:Inter,Segoe UI,Arial,sans-serif;color:#111">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 12px"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:20px;overflow:hidden;border:1px solid #e7e9e7">'
        . '<tr><td style="background:#0A0A0A;padding:22px 28px;color:#fff;font-weight:800;font-size:18px">&lt;<span style="color:#4ADE80">DA</span>/&gt; &nbsp;' . $site . '</td></tr>'
        . '<tr><td style="padding:32px 28px 8px"><h1 style="margin:0 0 16px;font-size:22px;line-height:1.3">' . e($title) . '</h1>'
        . '<div style="font-size:15px;line-height:1.65;color:#333">' . $bodyHtml . '</div>' . $cta . '</td></tr>'
        . '<tr><td style="padding:24px 28px 28px;font-size:12px;color:#888;border-top:1px solid #eee">'
        . $site . ' · ' . $contact . ($wa ? ' · WhatsApp ' . $wa : '') . '<br>Every price is negotiable — just reply to this email.</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Admin-written template → safe HTML (text is escaped, {{placeholders}} filled). */
function render_template_string(string $tpl, array $vars): string
{
    $safe = e($tpl);
    return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', static fn($m) => e($vars[$m[1]] ?? ''), $safe) ?? $safe;
}

/** Plain-text version for subjects (no HTML escaping; header-safe). */
function render_template_text(string $tpl, array $vars): string
{
    return mail_header_safe(preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', static fn($m) => (string)($vars[$m[1]] ?? ''), $tpl) ?? $tpl);
}

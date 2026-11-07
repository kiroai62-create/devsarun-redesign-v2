<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * Generic helpers: escaping, URLs, requests, security, formatting.
 * ------------------------------------------------------------------ */

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['CONFIG'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return BASE_PATH . '/' . ltrim($path, '/');
}

function abs_url(string $path = '/'): string
{
    $base = rtrim((string)config('base_url', ''), '/');
    if ($base === '') {
        $scheme = is_https() ? 'https' : 'http';
        $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_PATH;
    }
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? (string)filemtime($file) : '1';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function redirect(string $path, int $code = 302): never
{
    header('Location: ' . url($path), true, $code);
    exit;
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_body(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $raw = file_get_contents('php://input', false, null, 0, 1_000_000) ?: '';
    $data = json_decode($raw, true);
    return $cache = is_array($data) ? $data : [];
}

/** Read a request value (JSON body first, then POST, then GET), trimmed + length limited. */
function input(string $key, int $max = 2000, string $default = ''): string
{
    $src = json_body();
    $v = $src[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default;
    if (is_array($v)) {
        return $default;
    }
    $v = trim(str_replace("\0", '', (string)$v));
    return mb_substr($v, 0, $max);
}

function input_array(string $key, int $maxItems = 30, int $maxLen = 120): array
{
    $src = json_body();
    $v = $src[$key] ?? $_POST[$key] ?? [];
    if (!is_array($v)) {
        return [];
    }
    $out = [];
    foreach (array_slice($v, 0, $maxItems) as $item) {
        if (is_scalar($item)) {
            $s = mb_substr(trim((string)$item), 0, $maxLen);
            if ($s !== '') {
                $out[] = $s;
            }
        }
    }
    return array_values(array_unique($out));
}

function single_line(string $s): string
{
    return trim(preg_replace('/[\r\n\t]+/', ' ', $s) ?? '');
}

function valid_email(string $email): bool
{
    return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_phone(string $phone): bool
{
    return (bool)preg_match('/^[0-9+\-\s()]{6,20}$/', $phone);
}

function valid_url(string $u): bool
{
    return strlen($u) <= 500 && filter_var($u, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $u);
}

function random_token(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

function client_ip(): string
{
    // Only REMOTE_ADDR is trusted; proxies can be enabled via config.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (config('trust_proxy', false) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/** Privacy-friendly IP fingerprint (never store raw IPs). */
function ip_hash(): string
{
    return substr(hash_hmac('sha256', client_ip(), app_key()), 0, 32);
}

function user_agent(): string
{
    return mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/** Request path relative to the site root, without leading slash (e.g. "services/seo/"). */
function current_path(): string
{
    $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
        $path = substr($path, strlen(BASE_PATH));
    }
    return ltrim($path, '/');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-') ?: 'item';
}

/* ---------------- Money / formatting ---------------- */

function inr(int|float|null $n): string
{
    if ($n === null) {
        return '';
    }
    $n = (int)round($n);
    $s = (string)abs($n);
    if (strlen($s) > 3) {
        $last3 = substr($s, -3);
        $rest = substr($s, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $s = $rest . ',' . $last3;
    }
    return ($n < 0 ? '-' : '') . '₹' . $s;
}

function price_text(array $svc): string
{
    if ($svc['price_from'] === null || $svc['price_from'] === '' || (int)$svc['price_from'] === 0) {
        return (string)($svc['price_label'] ?: 'Custom quote');
    }
    $t = inr((int)$svc['price_from']);
    if (!empty($svc['price_to'])) {
        $t .= ' – ' . inr((int)$svc['price_to']);
    }
    return $t . ($svc['price_suffix'] ?? '');
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

function json_list(?string $json): array
{
    if (!$json) {
        return [];
    }
    $d = json_decode($json, true);
    return is_array($d) ? $d : [];
}

/* ---------------- Encryption for secrets at rest ---------------- */

function app_key(): string
{
    static $key = null;
    if ($key === null) {
        $raw = base64_decode((string)config('app_key', ''), true);
        $key = ($raw !== false && strlen($raw) >= 32) ? substr($raw, 0, 32) : hash('sha256', 'devsarun-fallback-key', true);
    }
    return $key;
}

function encrypt_str(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'v1:' . base64_encode($iv . $tag . $cipher);
}

function decrypt_str(string $enc): string
{
    if (!str_starts_with($enc, 'v1:')) {
        return $enc;
    }
    $raw = base64_decode(substr($enc, 3), true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? '' : $plain;
}

/* ---------------- Settings (key/value, secrets encrypted) ---------------- */

const SECRET_SETTINGS = ['smtp_pass', 'razorpay_key_secret', 'razorpay_webhook_secret', 'ai_api_key'];

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        try {
            foreach (db_all('SELECT k, v FROM settings') as $row) {
                $cache[$row['k']] = $row['v'];
            }
        } catch (Throwable) {
            $cache = [];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    if (!array_key_exists($key, $all) || $all[$key] === null || $all[$key] === '') {
        return $default;
    }
    $v = (string)$all[$key];
    return in_array($key, SECRET_SETTINGS, true) ? decrypt_str($v) : $v;
}

function setting_set(string $key, string $value): void
{
    $stored = in_array($key, SECRET_SETTINGS, true) ? encrypt_str($value) : $value;
    db_exec('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$key, $stored]);
    settings_all(true);
}

/* ---------------- CSRF ---------------- */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = random_token(32);
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? (json_body()['_csrf'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

function csrf_check(bool $json = false): void
{
    if (!csrf_valid()) {
        if ($json) {
            json_out(['ok' => false, 'error' => 'Your session expired. Please refresh the page and try again.'], 419);
        }
        http_response_code(419);
        exit('Session expired. Please go back, refresh and try again.');
    }
}

/* ---------------- Rate limiting (DB backed) ---------------- */

function rate_limited(string $bucket, int $max, int $seconds): bool
{
    $key = $bucket . ':' . ip_hash();
    // Opportunistic cleanup.
    if (random_int(1, 50) === 1) {
        db_exec('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)');
    }
    $count = (int)db_val('SELECT COUNT(*) FROM rate_limits WHERE k = ? AND created_at > (NOW() - INTERVAL ? SECOND)', [$key, $seconds]);
    if ($count >= $max) {
        return true;
    }
    db_exec('INSERT INTO rate_limits (k, created_at) VALUES (?, NOW())', [$key]);
    return false;
}

/* ---------------- Flash messages ---------------- */

function flash(string $type, string $msg): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/* ---------------- Misc ---------------- */

function lead_ref(): string
{
    return 'DA-' . date('y') . strtoupper(substr(random_token(4), 0, 6));
}

function status_badge(string $status): string
{
    $map = [
        'new' => 'bg-sky-400/10 text-sky-300 border-sky-400/20',
        'contacted' => 'bg-violet-400/10 text-violet-300 border-violet-400/20',
        'qualified' => 'bg-amber-400/10 text-amber-300 border-amber-400/20',
        'proposal' => 'bg-orange-400/10 text-orange-300 border-orange-400/20',
        'negotiation' => 'bg-pink-400/10 text-pink-300 border-pink-400/20',
        'in_progress' => 'bg-teal-400/10 text-teal-300 border-teal-400/20',
        'won' => 'bg-neo-green/10 text-neo-green border-neo-green/20',
        'paid' => 'bg-neo-green/10 text-neo-green border-neo-green/20',
        'sent' => 'bg-neo-green/10 text-neo-green border-neo-green/20',
        'lost' => 'bg-white/5 text-white/40 border-white/10',
        'cancelled' => 'bg-white/5 text-white/40 border-white/10',
        'spam' => 'bg-red-400/10 text-red-300 border-red-400/20',
        'failed' => 'bg-red-400/10 text-red-300 border-red-400/20',
        'created' => 'bg-white/5 text-white/60 border-white/10',
        'draft' => 'bg-white/5 text-white/60 border-white/10',
    ];
    $cls = $map[$status] ?? 'bg-white/5 text-white/60 border-white/10';
    return '<span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium ' . $cls . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

const LEAD_STATUSES = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'in_progress', 'won', 'lost', 'spam'];

const LEAD_STATUS_PUBLIC = [
    'new' => 'Received — we are reviewing your brief',
    'contacted' => 'We have reached out to you',
    'qualified' => 'Requirement understood',
    'proposal' => 'Proposal / quote shared',
    'negotiation' => 'Finalising scope & price',
    'in_progress' => 'Project in progress',
    'won' => 'Confirmed — let\'s build!',
    'lost' => 'Closed',
    'spam' => 'Closed',
];

/** Very small, safe markdown subset for chat replies: **bold**, [text](url), line breaks. */
function chat_format(string $text): string
{
    $html = e($text);
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html) ?? $html;
    $html = preg_replace_callback('/\[([^\]]{1,80})\]\(((?:https?:\/\/|\/)[^\s)]{1,300})\)/', static function ($m) {
        $href = html_entity_decode($m[2], ENT_QUOTES);
        if (!preg_match('#^(/|https?://)#', $href) || str_starts_with($href, '//') || preg_match('#^\s*javascript:#i', $href)) {
            return $m[1];
        }
        $ext = str_starts_with($href, 'http') ? ' target="_blank" rel="noopener"' : '';
        if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
            $href = url(ltrim($href, '/')); // sub-folder installs
        }
        return '<a href="' . e($href) . '"' . $ext . '>' . $m[1] . '</a>';
    }, $html) ?? $html;
    return nl2br($html);
}

function country_codes(): array
{
    return ['+91' => 'India (+91)', '+1' => 'USA / Canada (+1)', '+44' => 'UK (+44)', '+61' => 'Australia (+61)', '+971' => 'UAE (+971)',
        '+966' => 'Saudi Arabia (+966)', '+974' => 'Qatar (+974)', '+973' => 'Bahrain (+973)', '+965' => 'Kuwait (+965)', '+968' => 'Oman (+968)',
        '+65' => 'Singapore (+65)', '+60' => 'Malaysia (+60)', '+977' => 'Nepal (+977)', '+880' => 'Bangladesh (+880)', '+94' => 'Sri Lanka (+94)',
        '+49' => 'Germany (+49)', '+33' => 'France (+33)', '+31' => 'Netherlands (+31)', '+39' => 'Italy (+39)', '+34' => 'Spain (+34)',
        '+27' => 'South Africa (+27)', '+234' => 'Nigeria (+234)', '+254' => 'Kenya (+254)', '+81' => 'Japan (+81)', '+64' => 'New Zealand (+64)'];
}

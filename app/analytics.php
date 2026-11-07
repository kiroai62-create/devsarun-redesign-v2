<?php
declare(strict_types=1);

/* First-party, privacy-friendly analytics (no third-party trackers). */

const TRACKABLE_EVENTS = [
    'cta_click', 'brochure_view', 'brochure_download', 'chat_open', 'chat_message', 'form_start', 'form_step',
    'form_submit', 'contact_submit', 'checkout_open', 'payment_success', 'payment_failed', 'whatsapp_click',
    'outbound_click', 'service_view', 'audit_request', 'track_lookup', 'quote_view', 'enquiry_submit', 'enquire_open',
];

function is_bot(): bool
{
    $ua = strtolower(user_agent());
    return $ua === '' || (bool)preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse|curl|wget|python|monitor/i', $ua);
}

function visitor_id(): string
{
    $v = $_COOKIE['da_vid'] ?? '';
    if (!is_string($v) || !preg_match('/^[a-f0-9]{24}$/', $v)) {
        $v = random_token(12);
        if (!headers_sent()) {
            setcookie('da_vid', $v, ['expires' => time() + 31536000, 'path' => BASE_PATH === '' ? '/' : BASE_PATH . '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
        }
        $_COOKIE['da_vid'] = $v;
    }
    return $v;
}

function device_type(): string
{
    $ua = user_agent();
    if (preg_match('/tablet|ipad/i', $ua)) return 'tablet';
    if (preg_match('/mobile|android|iphone/i', $ua)) return 'mobile';
    return 'desktop';
}

function browser_name(): string
{
    $ua = user_agent();
    return match (true) {
        str_contains($ua, 'Edg/') => 'Edge',
        str_contains($ua, 'OPR/') => 'Opera',
        str_contains($ua, 'SamsungBrowser') => 'Samsung',
        str_contains($ua, 'Chrome/') => 'Chrome',
        str_contains($ua, 'Firefox/') => 'Firefox',
        str_contains($ua, 'Safari/') => 'Safari',
        default => 'Other',
    };
}

function capture_attribution(): void
{
    if (!isset($_SESSION['attr'])) {
        $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $refHost = $ref ? (string)parse_url($ref, PHP_URL_HOST) : '';
        if ($refHost === ($_SERVER['HTTP_HOST'] ?? '')) {
            $refHost = '';
        }
        $_SESSION['attr'] = [
            'landing' => mb_substr((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), 0, 255),
            'referrer' => mb_substr($refHost, 0, 120),
            'utm_source' => mb_substr((string)($_GET['utm_source'] ?? ''), 0, 80),
            'utm_medium' => mb_substr((string)($_GET['utm_medium'] ?? ''), 0, 80),
            'utm_campaign' => mb_substr((string)($_GET['utm_campaign'] ?? ''), 0, 80),
        ];
    }
}

function attribution(): array
{
    return $_SESSION['attr'] ?? ['landing' => '', 'referrer' => '', 'utm_source' => '', 'utm_medium' => '', 'utm_campaign' => ''];
}

function track_pageview(): void
{
    if (is_bot() || admin()) {
        return;
    }
    capture_attribution();
    try {
        $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $refHost = $ref ? (string)parse_url($ref, PHP_URL_HOST) : '';
        db_insert('page_views', [
            'visitor_id' => visitor_id(),
            'session_id' => substr(hash('sha256', session_id()), 0, 32),
            'path' => mb_substr((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), 0, 255),
            'referrer' => $refHost === ($_SERVER['HTTP_HOST'] ?? '') ? '' : mb_substr($refHost, 0, 120),
            'utm_source' => mb_substr((string)($_GET['utm_source'] ?? ''), 0, 80) ?: null,
            'device' => device_type(),
            'browser' => browser_name(),
            'created_at' => now(),
        ]);
    } catch (Throwable $e) {
        error_log('pageview failed: ' . $e->getMessage());
    }
}

function track_event(string $name, string $label = '', ?int $serviceId = null): void
{
    if (!in_array($name, TRACKABLE_EVENTS, true)) {
        return;
    }
    try {
        db_insert('events', [
            'name' => $name,
            'label' => mb_substr($label, 0, 190),
            'service_id' => $serviceId,
            'visitor_id' => visitor_id(),
            'path' => mb_substr((string)parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH), 0, 255),
            'created_at' => now(),
        ]);
    } catch (Throwable $e) {
        error_log('event failed: ' . $e->getMessage());
    }
}

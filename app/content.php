<?php
declare(strict_types=1);

/* Content queries shared by public pages and the admin. */

const SERVICE_CATEGORIES = [
    'websites' => 'Websites',
    'apps' => 'Apps & Software',
    'ai' => 'AI & Automation',
    'growth' => 'Growth & Visibility',
    'other' => 'Other',
];

const PROJECT_TYPES = [
    'client' => 'Client Websites',
    'android' => 'Play Store',
    'amazon' => 'Amazon Appstore',
    'extension' => 'Chrome Extensions',
    'tool' => 'Web Tools',
];

function services_active(): array
{
    static $cache = null;
    return $cache ??= db_all('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order, id');
}

function service_by_slug(string $slug): ?array
{
    return db_row('SELECT * FROM services WHERE slug = ? AND is_active = 1', [$slug]);
}

function service_by_id(int $id): ?array
{
    return db_row('SELECT * FROM services WHERE id = ?', [$id]);
}

function projects_active(?string $type = null, bool $featuredOnly = false): array
{
    $sql = 'SELECT * FROM projects WHERE is_active = 1';
    $p = [];
    if ($type) {
        $sql .= ' AND type = ?';
        $p[] = $type;
    }
    if ($featuredOnly) {
        $sql .= ' AND is_featured = 1';
    }
    return db_all($sql . ' ORDER BY sort_order, id DESC', $p);
}

function testimonials_active(): array
{
    return db_all('SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order, id DESC LIMIT 12');
}

function lead_activity(int $leadId, string $type, string $body, bool $public = false): void
{
    db_insert('lead_activities', [
        'lead_id' => $leadId,
        'admin_id' => $_SESSION['admin_id'] ?? null,
        'type' => mb_substr($type, 0, 30),
        'body' => $body,
        'is_public' => $public ? 1 : 0,
        'created_at' => now(),
    ]);
}

function site_stats(): array
{
    static $s = null;
    if ($s !== null) {
        return $s;
    }
    $counts = [];
    foreach (db_all('SELECT type, COUNT(*) c FROM projects WHERE is_active = 1 GROUP BY type') as $r) {
        $counts[$r['type']] = (int)$r['c'];
    }
    return $s = [
        'clients' => $counts['client'] ?? 0,
        'apps' => ($counts['android'] ?? 0) + ($counts['amazon'] ?? 0),
        'extensions' => $counts['extension'] ?? 0,
        'tools' => $counts['tool'] ?? 0,
        'total' => array_sum($counts),
    ];
}

/** Icon set (inline SVG paths, stroke style). */
function icon(string $name, string $class = 'h-5 w-5'): string
{
    $paths = [
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'rocket' => '<path d="M5 15c-1.5 1.5-2 5-2 5s3.5-.5 5-2m-3-3 4 4m-4-4c0-4 3-9 9-11 0 0 1 6-4 11-1 1-2 1.5-3 2l-2-2Zm8-6a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/>',
        'cart' => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.5 12h11L21 7H6.2"/>',
        'code' => '<path d="m8 8-4 4 4 4m8-8 4 4-4 4m-2-12-4 16"/>',
        'phone' => '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/>',
        'bot' => '<rect x="4" y="8" width="16" height="12" rx="4"/><path d="M12 4v4M9 13h.01M15 13h.01M9 17h6"/>',
        'zap' => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
        'puzzle' => '<path d="M10 3a2 2 0 1 1 4 0v2h4a1 1 0 0 1 1 1v4h-2a2 2 0 1 0 0 4h2v4a1 1 0 0 1-1 1h-4v-2a2 2 0 1 0-4 0v2H6a1 1 0 0 1-1-1v-4h2a2 2 0 1 0 0-4H5V6a1 1 0 0 1 1-1h4V3Z"/>',
        'sparkles' => '<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8L12 3Zm7 11 .9 2.1L22 17l-2.1.9L19 20l-.9-2.1L16 17l2.1-.9L19 14Z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/>',
        'pen' => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/>',
        'wrench' => '<path d="M14.7 6.3a4 4 0 0 0 5 5L21 13l-8 8-3-3 8-8-1.3-1.3a4 4 0 0 1-5-5L13 2l1.7 4.3Z"/>',
        'refresh' => '<path d="M21 12a9 9 0 0 1-15.5 6.3L3 16m0-4a9 9 0 0 1 15.5-6.3L21 8M21 3v5h-5M3 21v-5h5"/>',
        'compass' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/>',
        'megaphone' => '<path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1Zm13-3a5 5 0 0 1 0 8M19 5a9 9 0 0 1 0 14"/>',
        'palette' => '<path d="M12 3a9 9 0 1 0 0 18c1 0 1.5-.7 1.5-1.5 0-1.2-1-1.5-1-2.5s.8-1.5 2-1.5H17a4 4 0 0 0 4-4c0-4.7-4-8.5-9-8.5Z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="15" cy="7" r="1"/>',
        'check' => '<path d="m5 12 5 5L20 7"/>',
        'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'external' => '<path d="M14 4h6v6m0-6-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'download' => '<path d="M12 3v12m-5-5 5 5 5-5M4 21h16"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'whatsapp' => '<path d="M20.5 3.5A11 11 0 0 0 3.2 17.3L2 22l4.8-1.2A11 11 0 0 0 20.5 3.5ZM12 20a8 8 0 0 1-4.1-1.1l-.3-.2-2.9.7.8-2.8-.2-.3A8 8 0 1 1 12 20Zm4.4-6c-.2-.1-1.4-.7-1.6-.8s-.4-.1-.5.1l-.7.9c-.1.2-.3.2-.5.1a6.6 6.6 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.7-1.7c-.2-.5-.4-.4-.5-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.1 5.1 0 0 0 1.1 2.7 11.6 11.6 0 0 0 4.4 3.9c1.6.7 2.3.8 3.1.6a2.7 2.7 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .2-1.3c-.1-.1-.2-.2-.4-.3Z" fill="currentColor" stroke="none"/>',
        'chat' => '<path d="M21 12a8 8 0 0 1-11.8 7L3 21l2-6.2A8 8 0 1 1 21 12Z"/>',
        'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'pin' => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-4-6"/>',
        'star' => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z"/>',
        'chrome' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/><path d="M12 8.5h8.5M8.97 13.75 4.7 6.4M15.03 13.75 10.8 21"/>',
        'play' => '<path d="m5 3 15 9-15 9V3Z"/>',
        'amazon' => '<path d="M4 15c4.5 3 11.5 3 16 0M17 17l3-2 .5 3.5"/><path d="M8 5h8v6H8z"/>',
        'tool' => '<path d="M4 6h16M4 12h10M4 18h7"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4 20-7Z"/>',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'file' => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8l-5-5Z"/><path d="M14 3v5h5"/>',
        'github' => '<path d="M9 19c-4.3 1.4-4.3-2.5-6-3m12 5v-3.5c0-1 .1-1.4-.5-2 2.8-.3 5.5-1.4 5.5-6a4.6 4.6 0 0 0-1.3-3.2 4.2 4.2 0 0 0-.1-3.2s-1.1-.3-3.5 1.3a12.3 12.3 0 0 0-6.2 0C6.5 2.8 5.4 3.1 5.4 3.1a4.2 4.2 0 0 0-.1 3.2A4.6 4.6 0 0 0 4 9.5c0 4.6 2.7 5.7 5.5 6-.6.6-.6 1.2-.5 2V21"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>',
        'twitter' => '<path d="M4 4l16 16M20 4 4 20" />',
        'threads' => '<path d="M16.5 11.5c-.5-3-2.5-4.5-5-4.5-3 0-5 2.2-5 5.5S8.5 18 12 18c3 0 5.5-1.8 5.5-4.5 0-2.3-2-3.5-4.5-3.5-2 0-3.5 1-3.5 2.6 0 1.4 1.2 2.2 2.6 2.2 2.4 0 3.4-2 3.4-5.3"/>',
        'heart' => '<path d="M12 20s-7-4.4-9-9a4.8 4.8 0 0 1 9-3 4.8 4.8 0 0 1 9 3c-2 4.6-9 9-9 9Z"/>',
        'grad' => '<path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11v5c3 2 9 2 12 0v-5M22 9v6"/>',
    ];
    $p = $paths[$name] ?? $paths['sparkles'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

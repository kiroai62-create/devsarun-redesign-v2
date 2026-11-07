<?php
declare(strict_types=1);

/* Admin layout + small helpers. */

function admin_nav(): array
{
    $newLeads = (int)db_val("SELECT COUNT(*) FROM leads WHERE status = 'new'");
    return [
        ['dashboard', 'Dashboard', 'admin/', 'chart', 0],
        ['leads', 'Leads & CRM', 'admin/leads/', 'users', $newLeads],
        ['chats', 'AI chats', 'admin/chats/', 'bot', 0],
        ['orders', 'Payments', 'admin/orders/', 'lock', 0],
        ['quotes', 'Quotes & links', 'admin/quotes/', 'file', 0],
        ['emails', 'Email center', 'admin/emails/', 'mail', 0],
        ['analytics', 'Analytics', 'admin/analytics/', 'search', 0],
        ['services', 'Services', 'admin/services/', 'sparkles', 0],
        ['projects', 'Portfolio', 'admin/projects/', 'briefcase', 0],
        ['testimonials', 'Testimonials', 'admin/testimonials/', 'star', 0],
        ['settings', 'Settings', 'admin/settings/', 'wrench', 0],
        ['users', 'Team & account', 'admin/users/', 'shield', 0],
        ['audit', 'Audit log', 'admin/audit/', 'clock', 0],
    ];
}

function admin_start(string $title, string $active = ''): void
{
    $u = admin();
    $site = setting('site_name', 'Devs Arun');
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($title) ?> · Admin · <?= e($site) ?></title>
<link rel="icon" href="<?= e(brand_favicon_url()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="bg-neo-black font-sans text-white antialiased" data-base="<?= e(BASE_PATH) ?>">
<div class="flex min-h-screen">
  <aside id="a-side" class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-white/[0.06] bg-[#0c0c0c] lg:flex">
    <a href="<?= e(url('admin/')) ?>" class="flex items-center gap-2.5 px-5 py-5"><img src="<?= e(brand_logo_url()) ?>" class="h-9 w-9 rounded-xl" alt=""><span><span class="block text-sm font-bold"><?= e($site) ?></span><span class="block font-mono text-[10px] uppercase tracking-widest text-neo-green">admin</span></span></a>
    <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 pb-6">
      <?php foreach (admin_nav() as [$key, $label, $href, $ic, $badge]): ?>
        <a href="<?= e(url($href)) ?>" class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm transition <?= $active === $key ? 'bg-neo-green/10 text-neo-green' : 'text-white/60 hover:bg-white/[0.03] hover:text-white' ?>">
          <?= icon($ic, 'h-4 w-4') ?><span class="flex-1"><?= e($label) ?></span>
          <?php if ($badge): ?><span class="rounded-full bg-neo-green px-2 py-0.5 text-[10px] font-bold text-neo-black"><?= (int)$badge ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="border-t border-white/[0.06] p-4 text-xs text-white/45">
      <p class="truncate font-medium text-white/80"><?= e($u['name'] ?? '') ?></p>
      <p class="truncate"><?= e($u['email'] ?? '') ?> · <?= e($u['role'] ?? '') ?></p>
      <div class="mt-3 flex gap-2"><a class="a-btn-ghost !px-3 !py-1.5 text-xs" href="<?= e(url('')) ?>" target="_blank">View site</a><form method="post" action="<?= e(url('admin/logout/')) ?>"><?= csrf_field() ?><button class="a-btn-ghost !px-3 !py-1.5 text-xs" type="submit">Log out</button></form></div>
    </div>
  </aside>
  <div id="a-overlay" class="fixed inset-0 z-30 hidden bg-black/70 lg:hidden"></div>
  <div class="min-w-0 flex-1 lg:pl-64">
    <header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-white/[0.06] bg-neo-black/85 px-4 py-3 backdrop-blur sm:px-8">
      <div class="flex items-center gap-3"><button type="button" id="a-menu" class="inline-flex h-10 w-10 items-center justify-center rounded-full hover:bg-white/5 lg:hidden" aria-label="Menu"><?= icon('menu') ?></button><h1 class="text-lg font-semibold tracking-tight"><?= e($title) ?></h1></div>
      <form method="get" action="<?= e(url('admin/leads/')) ?>" class="hidden sm:block"><input class="a-input w-64" type="search" name="q" placeholder="Search leads, email, ref…"></form>
    </header>
    <main class="px-4 py-6 sm:px-8">
      <?php foreach (flashes() as $f): ?>
        <div class="mb-4 rounded-2xl border px-4 py-3 text-sm <?= $f['type'] === 'error' ? 'border-red-400/30 bg-red-400/10 text-red-200' : 'border-neo-green/30 bg-neo-green/10 text-neo-green' ?>"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
<?php
}

function admin_end(array $scripts = []): void
{
    ?>
    </main>
  </div>
</div>
<?php foreach ($scripts as $s): ?><script src="<?= e($s) ?>" defer></script><?php endforeach; ?>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
<?php
}

function admin_stat(string $label, string $value, string $sub = '', string $ic = 'chart'): string
{
    return '<div class="a-card"><div class="flex items-center justify-between"><p class="text-xs text-white/45">' . e($label) . '</p><span class="text-neo-green">' . icon($ic, 'h-4 w-4') . '</span></div><p class="mt-2 text-2xl font-bold tracking-tight">' . e($value) . '</p>' . ($sub ? '<p class="mt-1 text-xs text-white/40">' . e($sub) . '</p>' : '') . '</div>';
}

function paginate(int $total, int $perPage, int $page, array $query = []): string
{
    $pages = (int)ceil($total / $perPage);
    if ($pages <= 1) {
        return '';
    }
    $html = '<div class="mt-4 flex flex-wrap items-center gap-1.5 text-sm">';
    for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++) {
        $q = http_build_query(array_merge($query, ['page' => $i]));
        $html .= '<a href="?' . e($q) . '" class="rounded-full px-3 py-1.5 ' . ($i === $page ? 'bg-neo-green text-neo-black' : 'border border-white/10 text-white/60 hover:text-white') . '">' . $i . '</a>';
    }
    return $html . '<span class="ml-2 text-xs text-white/35">' . $total . ' total</span></div>';
}

/** Lines textarea → list. "a | b" pipes → assoc rows using $keys. */
function lines_to_list(string $text, array $keys = []): array
{
    $out = [];
    foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (!$keys) {
            $out[] = mb_substr($line, 0, 300);
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $row = [];
        foreach ($keys as $i => $k) {
            $row[$k] = mb_substr($parts[$i] ?? '', 0, 600);
        }
        $out[] = $row;
    }
    return $out;
}

function list_to_lines(?string $json, array $keys = []): string
{
    $list = json_list($json);
    return implode("\n", array_map(static fn($r) => is_array($r) ? implode(' | ', array_map(static fn($k) => $r[$k] ?? '', $keys)) : (string)$r, $list));
}

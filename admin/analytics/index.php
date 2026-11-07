<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/admin_boot.php';
require_admin();

$range = in_array((int)($_GET['d'] ?? 30), [7, 30, 90, 365], true) ? (int)$_GET['d'] : 30;
$since = date('Y-m-d 00:00:00', strtotime('-' . ($range - 1) . ' days'));

$visitors = (int)db_val('SELECT COUNT(DISTINCT visitor_id) FROM page_views WHERE created_at >= ?', [$since]);
$sessions = (int)db_val('SELECT COUNT(DISTINCT session_id) FROM page_views WHERE created_at >= ?', [$since]);
$views = (int)db_val('SELECT COUNT(*) FROM page_views WHERE created_at >= ?', [$since]);
$leads = (int)db_val('SELECT COUNT(*) FROM leads WHERE created_at >= ?', [$since]);

$series = [];
for ($i = $range - 1; $i >= 0; $i--) {
    $series[date('Y-m-d', strtotime("-$i day"))] = ['v' => 0, 'p' => 0];
}
foreach (db_all('SELECT DATE(created_at) d, COUNT(DISTINCT visitor_id) v, COUNT(*) p FROM page_views WHERE created_at >= ? GROUP BY d', [$since]) as $r) {
    if (isset($series[$r['d']])) $series[$r['d']] = ['v' => (int)$r['v'], 'p' => (int)$r['p']];
}
$lineCfg = ['type' => 'line', 'data' => ['labels' => array_map(static fn($d) => date('d M', strtotime($d)), array_keys($series)), 'datasets' => [['label' => 'Visitors', 'data' => array_column($series, 'v')], ['label' => 'Page views', 'data' => array_column($series, 'p')]]], 'options' => ['scales' => ['y' => ['beginAtZero' => true]]]];

$top = static fn(string $sql) => db_all($sql, [$since]);
$pages = $top('SELECT path k, COUNT(*) c, COUNT(DISTINCT visitor_id) u FROM page_views WHERE created_at >= ? GROUP BY path ORDER BY c DESC LIMIT 15');
$refs = $top("SELECT COALESCE(NULLIF(referrer,''),'Direct / none') k, COUNT(DISTINCT session_id) c FROM page_views WHERE created_at >= ? GROUP BY k ORDER BY c DESC LIMIT 12");
$utm = $top("SELECT utm_source k, COUNT(DISTINCT session_id) c FROM page_views WHERE created_at >= ? AND utm_source IS NOT NULL GROUP BY k ORDER BY c DESC LIMIT 10");
$devices = $top('SELECT device k, COUNT(DISTINCT visitor_id) c FROM page_views WHERE created_at >= ? GROUP BY device ORDER BY c DESC');
$browsers = $top('SELECT browser k, COUNT(DISTINCT visitor_id) c FROM page_views WHERE created_at >= ? GROUP BY browser ORDER BY c DESC');
$events = $top('SELECT name k, COUNT(*) c, COUNT(DISTINCT visitor_id) u FROM events WHERE created_at >= ? GROUP BY name ORDER BY c DESC');
$svcViews = $top("SELECT label k, COUNT(*) c FROM events WHERE created_at >= ? AND name = 'service_view' GROUP BY label ORDER BY c DESC LIMIT 12");
$brochures = $top("SELECT label k, SUM(name='brochure_view') v, SUM(name='brochure_download') c FROM events WHERE created_at >= ? AND name IN ('brochure_view','brochure_download') GROUP BY label ORDER BY c DESC, v DESC LIMIT 12");
$ctas = $top("SELECT label k, COUNT(*) c FROM events WHERE created_at >= ? AND name IN ('cta_click','whatsapp_click','outbound_click','checkout_open') GROUP BY label ORDER BY c DESC LIMIT 15");
$devCfg = ['type' => 'doughnut', 'data' => ['labels' => array_map(static fn($r) => ucfirst((string)$r['k']), $devices), 'datasets' => [['data' => array_map('intval', array_column($devices, 'c'))]]], 'options' => ['cutout' => '65%']];

$table = static function (string $title, array $rows, array $cols): void {
    echo '<div class="a-card overflow-x-auto p-0"><h3 class="px-5 pt-5 font-semibold">' . e($title) . '</h3><table class="a-table mt-2"><thead><tr>';
    foreach ($cols as $c) echo '<th>' . e($c[0]) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr>';
        foreach ($cols as $c) echo '<td class="' . ($c[1] === 'k' ? 'max-w-[260px] truncate' : 'whitespace-nowrap') . '">' . e((string)($r[$c[1]] ?? '')) . '</td>';
        echo '</tr>';
    }
    if (!$rows) echo '<tr><td colspan="' . count($cols) . '" class="py-6 text-center text-white/35">No data yet</td></tr>';
    echo '</tbody></table></div>';
};

admin_start('Analytics', 'analytics');
?>
<div class="flex flex-wrap items-center justify-between gap-3">
  <div class="flex gap-2"><?php foreach ([7, 30, 90, 365] as $d): ?><a href="?d=<?= $d ?>" class="chip <?= $range === $d ? 'is-active' : '' ?>"><?= $d === 365 ? '1 year' : $d . ' days' ?></a><?php endforeach; ?></div>
  <p class="text-xs text-white/35">First-party analytics · bots & admins excluded · IPs never stored</p>
</div>
<div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
  <?= admin_stat('Unique visitors', number_format($visitors), '', 'users') ?>
  <?= admin_stat('Sessions', number_format($sessions), $sessions ? round($views / $sessions, 1) . ' pages / session' : '', 'refresh') ?>
  <?= admin_stat('Page views', number_format($views), '', 'globe') ?>
  <?= admin_stat('Leads', (string)$leads, $visitors ? round($leads / $visitors * 100, 2) . '% conversion' : '', 'pen') ?>
</div>
<div class="mt-4 grid gap-4 xl:grid-cols-3">
  <div class="a-card xl:col-span-2"><h3 class="font-semibold">Traffic</h3><div class="mt-4 h-72"><canvas data-chart="a-line"></canvas></div><script type="application/json" id="a-line"><?= json_encode($lineCfg, JSON_HEX_TAG) ?></script></div>
  <div class="a-card"><h3 class="font-semibold">Devices</h3><div class="mt-4 h-56"><canvas data-chart="a-dev"></canvas></div><script type="application/json" id="a-dev"><?= json_encode($devCfg, JSON_HEX_TAG) ?></script>
    <div class="mt-4 flex flex-wrap gap-2 text-xs text-white/55"><?php foreach ($browsers as $b): ?><span class="rounded-full bg-white/[0.04] px-2.5 py-1"><?= e($b['k']) ?> · <?= (int)$b['c'] ?></span><?php endforeach; ?></div></div>
</div>
<div class="mt-4 grid gap-4 lg:grid-cols-2">
  <?php $table('Top pages', $pages, [['Page', 'k'], ['Views', 'c'], ['Visitors', 'u']]); ?>
  <?php $table('Referrers', $refs, [['Source', 'k'], ['Sessions', 'c']]); ?>
  <?php $table('Visitor actions (events)', $events, [['Event', 'k'], ['Count', 'c'], ['Visitors', 'u']]); ?>
  <?php $table('Clicks — CTAs, WhatsApp, outbound', $ctas, [['Label', 'k'], ['Clicks', 'c']]); ?>
  <?php $table('Service page views', $svcViews, [['Service', 'k'], ['Views', 'c']]); ?>
  <?php $table('Brochures', $brochures, [['Service', 'k'], ['Opened', 'v'], ['Downloaded', 'c']]); ?>
  <?php $table('UTM campaigns', $utm, [['utm_source', 'k'], ['Sessions', 'c']]); ?>
</div>
<?php admin_end(['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js']);

<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

/* Served as /sitemap.xml via .htaccess */
header('Content-Type: application/xml; charset=utf-8');
$urls = ['', 'services/', 'work/', 'pricing/', 'aeo-geo/', 'about/', 'contact/', 'start-project/', 'privacy/', 'terms/', 'refund-policy/'];
foreach (services_active() as $s) {
    $urls[] = 'services/' . $s['slug'] . '/';
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url><loc>' . e(abs_url($u)) . '</loc><changefreq>weekly</changefreq><priority>' . ($u === '' ? '1.0' : '0.7') . "</priority></url>\n";
}
echo '</urlset>';

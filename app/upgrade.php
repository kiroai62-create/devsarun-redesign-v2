<?php
declare(strict_types=1);

/*
 * Idempotent schema + content upgrades. Runs automatically once per version
 * (from bootstrap, or from the installer on a fresh install).
 */

const SCHEMA_VERSION = 2;

function run_upgrades(PDO $pdo): void
{
    $current = (int)($pdo->query("SELECT v FROM settings WHERE k = 'schema_version'")->fetchColumn() ?: 1);
    if ($current >= SCHEMA_VERSION) {
        return;
    }

    // v2: detailed service content.
    $have = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['overview' => 'TEXT NULL', 'benefits' => 'TEXT NULL', 'packages' => 'TEXT NULL', 'tech_stack' => 'TEXT NULL'] as $col => $def) {
        if (!in_array($col, $have, true)) {
            $pdo->exec("ALTER TABLE services ADD COLUMN `$col` $def");
        }
    }
    $content = require dirname(__DIR__) . '/database/service_content.php';
    $st = $pdo->prepare("UPDATE services SET overview = ?, benefits = ?, packages = ?, tech_stack = ? WHERE slug = ? AND (overview IS NULL OR overview = '')");
    $j = static fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE);
    foreach ($content as $slug => $c) {
        $st->execute([
            $c['overview'],
            $j(array_map(static fn($b) => ['title' => $b[0], 'desc' => $b[1]], $c['benefits'])),
            $j(array_map(static fn($p) => ['name' => $p[0], 'price' => $p[1], 'note' => $p[2], 'features' => $p[3]], $c['packages'])),
            $j($c['tech_stack']),
            $slug,
        ]);
    }
    $pdo->prepare("INSERT INTO settings (k, v) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)")->execute([(string)SCHEMA_VERSION]);
}

<?php
declare(strict_types=1);

/*
 * Idempotent schema + content upgrades. Runs automatically once per version
 * (from bootstrap, or from the installer on a fresh install).
 */

const SCHEMA_VERSION = 3;

function run_upgrades(PDO $pdo): void
{
    $current = (int)($pdo->query("SELECT v FROM settings WHERE k = 'schema_version'")->fetchColumn() ?: 1);
    if ($current >= SCHEMA_VERSION) {
        return;
    }
    $columns = static function (string $table) use ($pdo): array {
        $st = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $st->execute([$table]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    };

    if ($current < 2) {
        // v2: detailed service content.
        $have = $columns('services');
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
    }

    if ($current < 3) {
        // v3: notes store (digital PDF products) + multi-currency orders + new AI providers.
        $pdo->exec(PRODUCTS_TABLE_SQL);
        $have = $columns('orders');
        foreach (['product_id' => 'INT UNSIGNED NULL AFTER quote_id', 'download_count' => 'INT UNSIGNED NOT NULL DEFAULT 0', 'last_download_at' => 'DATETIME NULL'] as $col => $def) {
            if (!in_array($col, $have, true)) {
                $pdo->exec("ALTER TABLE orders ADD COLUMN `$col` $def");
            }
        }
        $ins = $pdo->prepare('INSERT IGNORE INTO settings (k, v) VALUES (?, ?)');
        foreach ([
            'store_enabled' => '1',
            'store_usd_enabled' => '1',
            'store_download_limit' => '10',
            'store_title' => 'Notes & study resources',
            'store_intro' => 'Clear, exam-ready PDF notes made by Arun. Preview pages for free, pay securely with Razorpay and download instantly.',
            'ai_fallback' => '1',
            'groq_model' => 'openai/gpt-oss-120b',
            'cf_model' => '@cf/meta/llama-3.3-70b-instruct-fp8-fast',
        ] as $k => $v) {
            $ins->execute([$k, $v]);
        }
        // The old defaults pointed at models that have been retired (gemini-2.0-flash, llama-3.3-70b on Groq's free tier).
        $prov = (string)$pdo->query("SELECT v FROM settings WHERE k = 'ai_provider'")->fetchColumn();
        $key = (string)$pdo->query("SELECT v FROM settings WHERE k = 'ai_api_key'")->fetchColumn();
        if (in_array($prov, ['gemini', 'openai', 'compatible'], true)) {
            $ins->execute(['ai_other_provider', $prov]); // the existing single API key keeps working
        }
        if ($prov === 'gemini' && $key === '') {
            $pdo->exec("UPDATE settings SET v = 'groq' WHERE k = 'ai_provider'");
        }
        $pdo->exec("UPDATE settings SET v = 'gemini-2.5-flash' WHERE k = 'ai_model' AND v = 'gemini-2.0-flash'");
        $pdo->exec("UPDATE settings SET v = 'openai/gpt-oss-120b' WHERE k = 'ai_model' AND v IN ('llama-3.3-70b-versatile', 'llama-3.1-8b-instant')");
    }

    $pdo->prepare("INSERT INTO settings (k, v) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)")->execute([(string)SCHEMA_VERSION]);
}

const PRODUCTS_TABLE_SQL = "CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) NOT NULL UNIQUE,
  title VARCHAR(190) NOT NULL,
  subtitle VARCHAR(255) NULL,
  category VARCHAR(80) NULL,
  tags VARCHAR(400) NULL,
  description TEXT NULL,
  highlights TEXT NULL,
  contents TEXT NULL,
  audience TEXT NULL,
  faqs TEXT NULL,
  seo_description VARCHAR(300) NULL,
  price_inr INT UNSIGNED NULL,
  compare_inr INT UNSIGNED NULL,
  price_usd_cents INT UNSIGNED NULL,
  compare_usd_cents INT UNSIGNED NULL,
  file_path VARCHAR(255) NULL,
  file_name VARCHAR(190) NULL,
  file_size BIGINT UNSIGNED NULL,
  page_count INT UNSIGNED NULL,
  pdf_text MEDIUMTEXT NULL,
  cover_image VARCHAR(255) NULL,
  preview_images TEXT NULL,
  preview_pages TINYINT UNSIGNED NOT NULL DEFAULT 4,
  status VARCHAR(12) NOT NULL DEFAULT 'draft',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 100,
  sales_count INT UNSIGNED NOT NULL DEFAULT 0,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  ai_generated_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  KEY idx_status (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

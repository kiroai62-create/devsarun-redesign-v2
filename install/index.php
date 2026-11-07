<?php
declare(strict_types=1);

/*
 * One-time web installer.
 * - Creates tables, seeds content and the owner admin account
 * - Writes app/config.php (with a random encryption key)
 * - Locks itself: once app/config.php exists this page refuses to run.
 */

$root = dirname(__DIR__);
$configFile = $root . '/app/config.php';
header('X-Robots-Tag: noindex');
header('X-Frame-Options: DENY');

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

if (is_file($configFile)) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:system-ui;background:#0A0A0A;color:#fff;display:grid;place-items:center;min-height:100vh"><div style="text-align:center"><h1>Already installed ✅</h1><p style="color:#aaa">The installer is locked. Delete <code>app/config.php</code> only if you really want to reinstall.</p><p><a style="color:#4ADE80" href="../admin/login/">Go to admin login →</a></p></div>';
    exit;
}

$errors = [];
$done = false;
$in = static fn(string $k, string $d = '') => trim((string)($_POST[$k] ?? $d));

$guessUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php')), '/\\');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $db = ['host' => $in('db_host', 'localhost'), 'port' => (int)$in('db_port', '3306'), 'name' => $in('db_name'), 'user' => $in('db_user'), 'pass' => (string)($_POST['db_pass'] ?? '')];
    $baseUrl = rtrim($in('base_url', $guessUrl), '/');
    $adminName = $in('admin_name');
    $adminEmail = strtolower($in('admin_email'));
    $adminPass = (string)($_POST['admin_pass'] ?? '');

    if ($db['name'] === '' || $db['user'] === '') $errors[] = 'Database name and user are required.';
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid admin email.';
    if (strlen($adminPass) < 10 || !preg_match('/[A-Za-z]/', $adminPass) || !preg_match('/\d/', $adminPass)) $errors[] = 'Admin password must be at least 10 characters with letters and numbers.';
    if ($adminName === '') $errors[] = 'Admin name is required.';
    if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) $errors[] = 'Enter a valid site URL.';
    if (!is_writable($root . '/app')) $errors[] = 'The app/ folder must be writable so config.php can be created.';

    if (!$errors) {
        try {
            $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']), $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $schema = (string)file_get_contents($root . '/database/schema.sql');
            foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $schema)))) as $stmt) {
                $pdo->exec($stmt);
            }
            $seed = require $root . '/database/seed.php';
            $now = date('Y-m-d H:i:s');
            $pdo->beginTransaction();

            if ((int)$pdo->query('SELECT COUNT(*) FROM services')->fetchColumn() === 0) {
                $st = $pdo->prepare('INSERT INTO services (slug,title,category,icon,tagline,summary,description,price_from,price_to,price_suffix,price_label,price_usd,timeline,features,deliverables,process,faqs,ideal_for,not_included,keywords,is_buyable,buy_price,buy_label,is_featured,is_active,sort_order,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?)');
                foreach ($seed['services'] as $s) {
                    $deliv = array_map(static fn($d) => ['title' => $d[0], 'desc' => $d[1]], $s['deliverables']);
                    $proc = array_map(static fn($p) => ['stage' => $p[0], 'work' => $p[1], 'input' => $p[2], 'receive' => $p[3]], $s['process']);
                    $faqs = array_map(static fn($f) => ['q' => $f[0], 'a' => $f[1]], $s['faqs']);
                    $j = static fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE);
                    $st->execute([$s['slug'], $s['title'], $s['category'], $s['icon'], $s['tagline'], $s['summary'], $s['description'], $s['price_from'], $s['price_to'], $s['price_suffix'], $s['price_label'], $s['price_usd'], $s['timeline'], $j($s['features']), $j($deliv), $j($proc), $j($faqs), $j($s['ideal_for']), $j($s['not_included']), $s['keywords'], $s['is_buyable'], $s['buy_price'], $s['buy_label'], $s['is_featured'], $s['sort_order'], $now]);
                }
            }
            if ((int)$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn() === 0) {
                $st = $pdo->prepare('INSERT INTO projects (type,title,subtitle,description,url,tags,icon,metric,is_featured,is_active,sort_order,created_at) VALUES (?,?,?,?,?,?,?,?,?,1,?,?)');
                foreach ($seed['projects'] as $i => $p) {
                    $st->execute([$p['type'], $p['title'], $p['subtitle'], $p['desc'], $p['url'], json_encode($p['tags'], JSON_UNESCAPED_UNICODE), $p['icon'], $p['metric'] ?: null, $p['featured'], ($i + 1) * 10, $now]);
                }
            }
            if ((int)$pdo->query('SELECT COUNT(*) FROM email_templates')->fetchColumn() === 0) {
                $st = $pdo->prepare('INSERT INTO email_templates (name,subject,body,created_at) VALUES (?,?,?,?)');
                foreach ($seed['templates'] as $t) {
                    $st->execute([$t[0], $t[1], $t[2], $now]);
                }
            }
            $st = $pdo->prepare('INSERT IGNORE INTO settings (k, v) VALUES (?, ?)');
            foreach ($seed['settings'] as $k => $v) {
                $st->execute([$k, $v]);
            }
            $exists = $pdo->prepare('SELECT id FROM admin_users WHERE email = ?');
            $exists->execute([$adminEmail]);
            if ($exists->fetchColumn()) {
                $pdo->prepare('UPDATE admin_users SET password_hash = ?, role = "owner", is_active = 1 WHERE email = ?')->execute([password_hash($adminPass, PASSWORD_DEFAULT), $adminEmail]);
            } else {
                $pdo->prepare('INSERT INTO admin_users (name,email,password_hash,role,is_active,created_at) VALUES (?,?,?,"owner",1,?)')->execute([$adminName, $adminEmail, password_hash($adminPass, PASSWORD_DEFAULT), $now]);
            }
            $pdo->commit();
            require $root . '/app/upgrade.php';
            run_upgrades($pdo);

            $config = [
                'base_url' => $baseUrl,
                'db' => $db,
                'app_key' => base64_encode(random_bytes(32)),
                'timezone' => 'Asia/Kolkata',
                'debug' => false,
                'trust_proxy' => false,
            ];
            $php = "<?php\n// Generated by the installer on {$now}. Keep this file private.\nreturn " . var_export($config, true) . ";\n";
            if (file_put_contents($configFile, $php, LOCK_EX) === false) {
                throw new RuntimeException('Could not write app/config.php');
            }
            @chmod($configFile, 0640);
            foreach (['storage/logs', 'storage/uploads', 'uploads/brand'] as $dir) {
                if (!is_dir($root . '/' . $dir)) {
                    @mkdir($root . '/' . $dir, 0755, true);
                }
            }
            $done = true;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Install · Devs Arun</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#0A0A0A;color:#fff;min-height:100vh;display:grid;place-items:center;padding:32px 16px}
.card{width:100%;max-width:620px;background:#111;border:1px solid rgba(255,255,255,.08);border-radius:24px;padding:32px}
h1{margin:0 0 4px;font-size:26px}p{color:rgba(255,255,255,.6)}h2{font-size:14px;letter-spacing:.15em;text-transform:uppercase;color:#4ADE80;margin:28px 0 12px}
label{display:block;font-size:13px;color:rgba(255,255,255,.7);margin:12px 0 6px}input{width:100%;padding:12px 14px;border-radius:14px;border:1px solid rgba(255,255,255,.1);background:#0A0A0A;color:#fff;font-size:15px}
input:focus{outline:2px solid rgba(74,222,128,.5);border-color:transparent}.row{display:grid;grid-template-columns:2fr 1fr;gap:12px}
button,.btn{margin-top:24px;width:100%;padding:14px;border:0;border-radius:999px;background:#4ADE80;color:#0A0A0A;font-weight:700;font-size:15px;cursor:pointer;text-align:center;text-decoration:none;display:block}
.err{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);color:#fca5a5;padding:12px 14px;border-radius:14px;margin:8px 0;font-size:14px}
.ok{background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.3);color:#86efac;padding:16px;border-radius:14px}
.logo{font-weight:800;font-size:20px;margin-bottom:20px}.logo span{color:#4ADE80}
</style></head><body><div class="card">
<div class="logo">&lt;<span>DA</span>/&gt; Devs Arun</div>
<?php if ($done): ?>
  <h1>Installation complete 🎉</h1>
  <div class="ok">Database created, content seeded and your owner account is ready. The installer is now locked.</div>
  <p>Next: log in and open <strong>Settings</strong> to add SMTP, Razorpay and your AI API key.</p>
  <a class="btn" href="../admin/login/">Open admin panel →</a>
<?php else: ?>
  <h1>Install your website</h1>
  <p>Create an empty MySQL database (e.g. in Hostinger / cPanel), then fill this form once.</p>
  <?php foreach ($errors as $err): ?><div class="err"><?= h($err) ?></div><?php endforeach; ?>
  <form method="post" autocomplete="off">
    <h2>Database</h2>
    <div class="row"><div><label>Host</label><input name="db_host" value="<?= h($in('db_host', 'localhost')) ?>" required></div><div><label>Port</label><input name="db_port" value="<?= h($in('db_port', '3306')) ?>" required></div></div>
    <label>Database name</label><input name="db_name" value="<?= h($in('db_name')) ?>" required>
    <div class="row"><div><label>Database user</label><input name="db_user" value="<?= h($in('db_user')) ?>" required></div><div><label>Password</label><input type="password" name="db_pass"></div></div>
    <h2>Site</h2>
    <label>Site URL (no trailing slash)</label><input name="base_url" value="<?= h($in('base_url', $guessUrl)) ?>" required>
    <h2>Owner admin account</h2>
    <label>Your name</label><input name="admin_name" value="<?= h($in('admin_name', 'Arun')) ?>" required>
    <label>Email</label><input type="email" name="admin_email" value="<?= h($in('admin_email')) ?>" required>
    <label>Password (min 10 chars, letters + numbers)</label><input type="password" name="admin_pass" minlength="10" required>
    <button type="submit">Install now</button>
  </form>
<?php endif; ?>
</div></body></html>

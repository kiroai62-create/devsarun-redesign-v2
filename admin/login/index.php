<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/admin_boot.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
if (admin()) {
    redirect('admin/');
}
$error = '';
if (is_post()) {
    if (!csrf_valid()) {
        $error = 'Session expired. Please try again.';
    } else {
        [$ok, $error] = admin_attempt((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
        if ($ok) {
            $to = (string)($_SESSION['after_login'] ?? '');
            unset($_SESSION['after_login']);
            // Only allow local admin paths as redirect targets.
            if ($to !== '' && preg_match('#^' . preg_quote(BASE_PATH . '/admin/', '#') . '[A-Za-z0-9/_\-?=&%.]*$#', $to)) {
                header('Location: ' . $to);
                exit;
            }
            redirect('admin/');
        }
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Admin login · <?= e(setting('site_name', 'Devs Arun')) ?></title>
<link rel="icon" href="<?= e(brand_favicon_url()) ?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body class="grid min-h-screen place-items-center bg-neo-black px-4 font-sans text-white antialiased">
<div class="pointer-events-none fixed inset-0 grid-bg"></div>
<form method="post" class="card relative w-full max-w-sm p-8" autocomplete="on">
  <?= csrf_field() ?>
  <img src="<?= e(brand_logo_url()) ?>" alt="" class="h-12 w-12 rounded-2xl">
  <h1 class="mt-6 text-2xl font-bold tracking-tight">Welcome back</h1>
  <p class="mt-1 text-sm text-white/50">Sign in to your admin panel.</p>
  <?php if ($error): ?><p class="mt-5 rounded-2xl border border-red-400/30 bg-red-400/10 px-4 py-3 text-sm text-red-200"><?= e($error) ?></p><?php endif; ?>
  <label class="label mt-6" for="email">Email</label><input id="email" class="field" type="email" name="email" required autocomplete="username" value="<?= e((string)($_POST['email'] ?? '')) ?>">
  <label class="label mt-4" for="password">Password</label><input id="password" class="field" type="password" name="password" required autocomplete="current-password">
  <button class="btn-primary mt-6 w-full" type="submit"><?= icon('lock', 'h-4 w-4') ?> Sign in</button>
  <p class="mt-5 text-center text-xs text-white/35">Protected · 6 failed attempts locks for 15 minutes</p>
</form>
</body></html>

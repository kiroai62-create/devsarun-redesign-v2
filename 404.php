<?php
declare(strict_types=1);
if (!defined('ROOT')) {
    require __DIR__ . '/app/bootstrap.php';
}
http_response_code(404);
page_start(['title' => 'Page not found', 'noindex' => true]);
?>
<section class="mx-auto max-w-xl px-5 pt-40 text-center">
  <p class="font-mono text-7xl font-bold text-neo-green">404</p>
  <h1 class="mt-6 text-3xl font-bold">This page doesn't exist</h1>
  <p class="mt-3 text-white/60">The link may be old. Try the services page — or ask the AI assistant.</p>
  <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row"><a href="<?= e(url('')) ?>" class="btn-primary">Go home</a><a href="<?= e(url('services/')) ?>" class="btn-ghost">Browse services</a></div>
</section>
<?php page_end();

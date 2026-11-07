<?php
declare(strict_types=1);
require dirname(__DIR__, 3) . '/app/admin_boot.php';

/*
 * POST /admin/store/ai/ (JSON) — AI writes the product page from the PDF text.
 * Body: text (extracted in the browser, pages separated by \f) or product_id (uses the saved text),
 *       file_name, page_count, title_hint, instructions.
 */
if (!is_post()) {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}
if (!admin()) {
    json_out(['ok' => false, 'error' => 'Your admin session expired. Please log in again.'], 401);
}
csrf_check(true);
if (!can('manager')) {
    json_out(['ok' => false, 'error' => 'Your role is read-only.'], 403);
}
if (!ai_configured()) {
    json_out(['ok' => false, 'error' => 'No AI provider is configured. Add a Groq key or Cloudflare account ID + token in Settings → AI assistant.'], 422);
}
@set_time_limit(180);

$text = (string)(json_body()['text'] ?? '');
$text = mb_substr(str_replace("\0", '', $text), 0, 300000);
$pid = (int)input('product_id', 12);
if (trim($text) === '' && $pid) {
    $text = (string)db_val('SELECT pdf_text FROM products WHERE id = ?', [$pid]);
}
try {
    $data = store_generate_copy($text, [
        'file_name' => mb_substr(basename(input('file_name', 190)), 0, 190),
        'page_count' => (int)input('page_count', 8),
        'title_hint' => single_line(input('title_hint', 190)),
        'instructions' => input('instructions', 1000),
    ]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'AI could not write the page: ' . mb_substr($e->getMessage(), 0, 400)], 502);
}
audit('product.ai_write', 'product', $pid ?: null, ['engine' => $data['engine']]);
json_out(['ok' => true, 'data' => $data, 'scanned' => trim($text) === '']);

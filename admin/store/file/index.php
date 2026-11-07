<?php
declare(strict_types=1);
require dirname(__DIR__, 3) . '/app/admin_boot.php';
require_admin();

/* Streams the original PDF of a product to an admin (download, or inline for re-rendering previews). */
$p = db_row('SELECT id, slug, file_path, file_name FROM products WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
$file = $p ? store_pdf_path($p['file_path']) : null;
if (!$file) {
    http_response_code(404);
    exit('File not found');
}
$inline = ($_GET['inline'] ?? '') === '1';
if (!$inline) {
    audit('product.file_download', 'product', (int)$p['id']);
}
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($file));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $p['file_name'] ?: $p['slug'] . '.pdf') . '"');
header('Cache-Control: private, no-store');
readfile($file);

<?php
declare(strict_types=1);
require dirname(__DIR__, 3) . '/app/admin_boot.php';
require_admin();

$lead = db_row('SELECT id, attachment_path, attachment_name FROM leads WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$lead || !$lead['attachment_path'] || !preg_match('#^briefs/[0-9]{6}_[a-f0-9]{32}\.[a-z]{2,4}$#', $lead['attachment_path'])) {
    http_response_code(404);
    exit('File not found');
}
$file = STORAGE . '/uploads/' . $lead['attachment_path'];
if (!is_file($file)) {
    http_response_code(404);
    exit('File not found');
}
audit('lead.file_download', 'lead', (int)$lead['id']);
header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($file));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $lead['attachment_name'] ?: basename($file)) . '"');
header('X-Content-Type-Options: nosniff');
readfile($file);

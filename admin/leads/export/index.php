<?php
declare(strict_types=1);
require dirname(__DIR__, 3) . '/app/admin_boot.php';
require APP . '/lead_filters.php';
require_admin('manager');

[$where, $params] = lead_filters();
$rows = db_all("SELECT ref, created_at, status, priority, source, service_label, project_title, budget, timeline, value_estimate, name, email, country_code, phone, company, preferred_contact, call_time, heard_from, existing_website, follow_up_at, utm_source, utm_medium, utm_campaign, referrer, landing, description FROM leads WHERE $where ORDER BY id DESC LIMIT 20000", $params);
audit('leads.export', 'lead', null, ['count' => count($rows)]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // Excel UTF-8 BOM
fputcsv($out, $rows ? array_keys($rows[0]) : ['ref'], ',', '"', '');
foreach ($rows as $r) {
    // Neutralise spreadsheet formula injection.
    fputcsv($out, array_map(static fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $r), ',', '"', '');
}
fclose($out);

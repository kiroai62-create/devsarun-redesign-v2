<?php
declare(strict_types=1);

/** Build WHERE clause for lead list / export from query string. */
function lead_filters(): array
{
    $where = ['1=1'];
    $p = [];
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR ref LIKE ? OR company LIKE ? OR project_title LIKE ?)';
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], mb_substr($q, 0, 100)) . '%';
        array_push($p, $like, $like, $like, $like, $like, $like);
    }
    $status = (string)($_GET['status'] ?? '');
    if (in_array($status, LEAD_STATUSES, true)) {
        $where[] = 'status = ?';
        $p[] = $status;
    } elseif ($status === 'open') {
        $where[] = "status NOT IN ('won','lost','spam')";
    }
    $source = (string)($_GET['source'] ?? '');
    if (in_array($source, ['brief', 'contact', 'chat', 'audit', 'checkout', 'enquiry'], true)) {
        $where[] = 'source = ?';
        $p[] = $source;
    }
    if (($sid = (int)($_GET['service'] ?? 0)) > 0) {
        $where[] = 'service_id = ?';
        $p[] = $sid;
    }
    foreach (['from' => '>=', 'to' => '<='] as $k => $op) {
        $d = (string)($_GET[$k] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $where[] = "DATE(created_at) $op ?";
            $p[] = $d;
        }
    }
    return [implode(' AND ', $where), $p];
}

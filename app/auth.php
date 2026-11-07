<?php
declare(strict_types=1);

/* Admin authentication, roles and audit trail. */

const ROLES = ['viewer' => 1, 'manager' => 2, 'owner' => 3];

function admin(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $id = $_SESSION['admin_id'] ?? null;
    if (!$id) {
        return $user = null;
    }
    // Idle timeout (2 hours) and fingerprint check.
    if ((time() - (int)($_SESSION['admin_seen'] ?? 0)) > 7200 || ($_SESSION['admin_fp'] ?? '') !== admin_fingerprint()) {
        admin_logout();
        return $user = null;
    }
    $_SESSION['admin_seen'] = time();
    $row = db_row('SELECT id, name, email, role, is_active, last_login_at FROM admin_users WHERE id = ?', [(int)$id]);
    if (!$row || !(int)$row['is_active']) {
        admin_logout();
        return $user = null;
    }
    return $user = $row;
}

function admin_fingerprint(): string
{
    return hash('sha256', user_agent() . '|' . app_key());
}

function require_admin(string $minRole = 'viewer'): array
{
    $u = admin();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('admin/login/');
    }
    if ((ROLES[$u['role']] ?? 0) < (ROLES[$minRole] ?? 99)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
    return $u;
}

function can(string $minRole): bool
{
    $u = admin();
    return $u && (ROLES[$u['role']] ?? 0) >= (ROLES[$minRole] ?? 99);
}

/** For admin POST handlers: CSRF + role. */
function admin_post_guard(string $minRole = 'manager'): void
{
    csrf_check();
    if (!can($minRole)) {
        http_response_code(403);
        exit('Your role is read-only.');
    }
}

function admin_attempt(string $email, string $password): array
{
    $email = strtolower(trim($email));
    $recent = (int)db_val('SELECT COUNT(*) FROM login_attempts WHERE (ip_hash = ? OR email = ?) AND success = 0 AND created_at > (NOW() - INTERVAL 15 MINUTE)', [ip_hash(), $email]);
    if ($recent >= 6) {
        return [false, 'Too many failed attempts. Please wait 15 minutes and try again.'];
    }
    $row = db_row('SELECT * FROM admin_users WHERE email = ? LIMIT 1', [$email]);
    // Constant-ish time: always run a verify.
    $hash = $row['password_hash'] ?? '$2y$12$abcdefghijklmnopqrstuuJ0nYf4iT3Q1x8QeXy4c7S0p6m3n2b1a';
    $ok = password_verify($password, $hash) && $row && (int)$row['is_active'];
    db_insert('login_attempts', ['email' => mb_substr($email, 0, 190), 'ip_hash' => ip_hash(), 'success' => $ok ? 1 : 0, 'created_at' => now()]);
    if (!$ok) {
        return [false, 'Invalid email or password.'];
    }
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        db_update('admin_users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$row['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$row['id'];
    $_SESSION['admin_seen'] = time();
    $_SESSION['admin_fp'] = admin_fingerprint();
    $_SESSION['_csrf'] = random_token(32);
    db_update('admin_users', ['last_login_at' => now(), 'last_login_ip' => ip_hash()], 'id = ?', [$row['id']]);
    audit('auth.login', 'admin_user', (int)$row['id']);
    return [true, ''];
}

function admin_logout(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_seen'], $_SESSION['admin_fp']);
    session_regenerate_id(true);
}

function audit(string $action, string $entity = '', ?int $entityId = null, array $meta = []): void
{
    try {
        db_insert('audit_log', [
            'admin_id' => $_SESSION['admin_id'] ?? null,
            'action' => mb_substr($action, 0, 80),
            'entity' => mb_substr($entity, 0, 40),
            'entity_id' => $entityId,
            'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'ip_hash' => ip_hash(),
            'created_at' => now(),
        ]);
    } catch (Throwable $e) {
        error_log('audit failed: ' . $e->getMessage());
    }
}

function password_strong(string $p): bool
{
    return strlen($p) >= 10 && preg_match('/[A-Za-z]/', $p) && preg_match('/\d/', $p);
}

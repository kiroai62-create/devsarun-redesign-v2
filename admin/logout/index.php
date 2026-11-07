<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/admin_boot.php';

if (is_post() && csrf_valid()) {
    audit('auth.logout');
    admin_logout();
}
redirect('admin/login/');

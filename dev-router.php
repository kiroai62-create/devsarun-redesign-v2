<?php
/*
 * Local development router for PHP's built-in server — mirrors .htaccess:
 *   php -S localhost:8000 dev-router.php
 * Not used in production (blocked by .htaccess).
 */
$root = __DIR__;
$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (preg_match('#^/(app|database|storage|vendor|node_modules|\.git|\.kiro)(/|$)#', $path) || preg_match('#/\.#', $path)
    || preg_match('#^/(package(-lock)?\.json|tailwind\.config\.js|dev-router\.php|README\.md|composer\.(json|lock))$#', $path)
    || (str_starts_with($path, '/uploads/') && preg_match('#\.(php\d?|phtml|phar|html?|svg|js)$#i', $path))) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}
$routes = [
    '#^/services/([a-z0-9-]+)/?$#' => ['/services/index.php', 'slug'],
    '#^/brochure/([a-z0-9-]+)/?$#' => ['/brochure/index.php', 'slug'],
    '#^/pay/([a-f0-9]{16,64})/?$#' => ['/pay/index.php', 't'],
];
foreach ($routes as $re => [$file, $param]) {
    if (preg_match($re, $path, $m)) {
        $_GET[$param] = $m[1];
        $_REQUEST[$param] = $m[1];
        $_SERVER['SCRIPT_NAME'] = $file;
        require $root . $file;
        return true;
    }
}
if ($path === '/sitemap.xml') {
    require $root . '/sitemap.php';
    return true;
}
$full = $root . $path;
if (is_dir($full)) {
    if (!str_ends_with($path, '/')) {
        header('Location: ' . $path . '/', true, 301);
        return true;
    }
    if (is_file($full . 'index.php')) {
        $_SERVER['SCRIPT_NAME'] = $path . 'index.php';
        require $full . 'index.php';
        return true;
    }
}
if (is_file($full)) {
    return false; // let the built-in server serve static files / php files
}
require $root . '/404.php';
return true;

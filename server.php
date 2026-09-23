<?php

/**
 * Router cho PHP built-in server, mô phỏng "mod_rewrite" của Apache.
 * - Nếu request là file tĩnh tồn tại trong public/, để PHP server tự phục vụ.
 * - Nếu không, route về index.php ở gốc (entry point của dự án).
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    // Trả về false để PHP built-in server tự phục vụ file tĩnh
    return false;
}

// Mọi request khác → index.php ở gốc
require_once __DIR__.'/index.php';

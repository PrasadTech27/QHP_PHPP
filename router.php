<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    // If it's a php file, include it directly
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        require $file;
        exit();
    }
    // Otherwise serve static asset directly
    return false;
}

// Default entry point
require __DIR__ . '/index.php';

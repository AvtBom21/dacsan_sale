<?php

declare(strict_types=1);

$rootPath = __DIR__;
$uriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = $uriPath === false || $uriPath === null ? '/' : $uriPath;

if (str_starts_with($path, '/products_image/')) {
    $relative = ltrim($path, '/');
    if (preg_match('#^products_image/[A-Za-z0-9._/\-]+$#', $relative) === 1) {
        $legacyImage = realpath(dirname($rootPath) . '/' . $relative);
        $legacyRoot = realpath(dirname($rootPath) . '/products_image');
        if ($legacyImage !== false && $legacyRoot !== false && str_starts_with($legacyImage, $legacyRoot) && is_file($legacyImage)) {
            $extension = strtolower(pathinfo($legacyImage, PATHINFO_EXTENSION));
            $mimeTypes = [
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'svg' => 'image/svg+xml',
                'webp' => 'image/webp',
            ];
            if (!headers_sent() && isset($mimeTypes[$extension])) {
                header('Content-Type: ' . $mimeTypes[$extension]);
            }
            readfile($legacyImage);
            return true;
        }
    }
}

$publicFile = realpath($rootPath . '/public' . $path);
if ($publicFile !== false && is_file($publicFile)) {
    $extension = strtolower(pathinfo($publicFile, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4',
    ];

    if (!headers_sent() && isset($mimeTypes[$extension])) {
        header('Content-Type: ' . $mimeTypes[$extension]);
    }

    readfile($publicFile);
    return true;
}

if ($path === '/api' || str_starts_with($path, '/api/')) {
    require $rootPath . '/public/api/index.php';
    return true;
}

if ($path === '/admin/api' || str_starts_with($path, '/admin/api/')) {
    require $rootPath . '/admin/api/index.php';
    return true;
}

if ($path === '/admin' || str_starts_with($path, '/admin/')) {
    require $rootPath . '/admin/index.php';
    return true;
}

require $rootPath . '/public/index.php';
return true;

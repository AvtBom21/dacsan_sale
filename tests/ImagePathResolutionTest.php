<?php

declare(strict_types=1);

use DacSanNhaDan\Core\Autoloader;
use DacSanNhaDan\Services\CartQuoteService;
use DacSanNhaDan\Services\CatalogService;

$rootPath = dirname(__DIR__);

require_once $rootPath . '/app/Core/Autoloader.php';

Autoloader::register($rootPath . '/app');

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function serviceWithRoot(string $className, string $rootPath): object
{
    $reflection = new ReflectionClass($className);
    $service = $reflection->newInstanceWithoutConstructor();
    $property = $reflection->getProperty('legacyRootPath');
    $property->setAccessible(true);
    $property->setValue($service, $rootPath);

    return $service;
}

function invokePrivate(object $object, string $methodName, array $args): mixed
{
    $method = new ReflectionMethod($object, $methodName);
    $method->setAccessible(true);

    return $method->invokeArgs($object, $args);
}

$catalog = serviceWithRoot(CatalogService::class, $rootPath);
$catalogImage = invokePrivate($catalog, 'resolveImage', [
    'products_image/pro_006/bo-mot-nang_main.jpg',
    'Bò một nắng',
    true,
]);

assertSameValue('products_image/bo-mot-nang_main.jpg', $catalogImage['path'], 'Catalog should resolve legacy subfolder image paths to existing flat files.');
assertSameValue('../products_image/bo-mot-nang_main.jpg', $catalogImage['url'], 'Catalog should expose the existing flat image URL.');
assertSameValue(true, $catalogImage['exists'], 'Catalog should mark fallback-resolved flat files as existing.');
assertSameValue(false, $catalogImage['fallback_used'], 'Catalog should not use placeholder when a flat file fallback exists.');

$cartQuote = serviceWithRoot(CartQuoteService::class, $rootPath);
$cartImage = invokePrivate($cartQuote, 'imagePayload', [
    'products_image/pro_001/cha-lua-cay_main.jpg',
    'Chả lụa cây',
]);

assertSameValue('products_image/cha-lua-cay_main.jpg', $cartImage['path'], 'Cart quote should resolve legacy subfolder image paths to existing flat files.');
assertSameValue('../products_image/cha-lua-cay_main.jpg', $cartImage['url'], 'Cart quote should expose the existing flat image URL.');
assertSameValue(true, $cartImage['exists'], 'Cart quote should mark fallback-resolved flat files as existing.');
assertSameValue(false, $cartImage['fallback_used'], 'Cart quote should not use placeholder when a flat file fallback exists.');

echo 'Image path resolution tests passed.' . PHP_EOL;

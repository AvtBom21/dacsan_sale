<?php

declare(strict_types=1);

namespace DacSanNhaDan\Core;

final class Autoloader
{
    private string $basePath;

    private function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
    }

    public static function register(string $basePath): self
    {
        $loader = new self($basePath);
        spl_autoload_register([$loader, 'load']);

        return $loader;
    }

    public function load(string $class): void
    {
        $prefix = 'DacSanNhaDan\\';

        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $this->basePath . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)
            . '.php';

        if (is_file($file)) {
            require $file;
        }
    }
}

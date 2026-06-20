<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Core\AppException;
use finfo;
use Throwable;

final class UploadService
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** @var callable(string, string): bool */
    private $mover;

    public function __construct(
        private string $projectRoot,
        ?callable $mover = null
    ) {
        $this->mover = $mover ?? static fn (string $source, string $destination): bool =>
            move_uploaded_file($source, $destination);
    }

    /**
     * @param array<string, mixed> $file
     * @return array{path: string, absolute_path: string}
     */
    public function storeImage(array $file, string $prefix): array
    {
        $error = $this->integerField($file, 'error');
        if ($error !== UPLOAD_ERR_OK) {
            throw new AppException('Táº£i áº£nh tháº¥t báº¡i. MÃ£ lá»—i: ' . $error . '.', 422);
        }

        $tmpName = $this->stringField($file, 'tmp_name');
        if ($tmpName === '' || !is_file($tmpName)) {
            throw new AppException('File táº£i lÃªn khÃ´ng há»£p lá»‡.', 422);
        }

        $reportedSize = $this->integerField($file, 'size');
        $actualSize = filesize($tmpName);
        if (
            $reportedSize < 1
            || $reportedSize > self::MAX_BYTES
            || $actualSize === false
            || $actualSize < 1
            || $actualSize > self::MAX_BYTES
        ) {
            throw new AppException('áº¢nh pháº£i cÃ³ dung lÆ°á»£ng tá»« 1 byte Ä‘áº¿n 5 MiB.', 422);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])) {
            throw new AppException('Chá»‰ cháº¥p nháº­n áº£nh JPEG, PNG hoáº·c WebP.', 422);
        }

        [$uploadDirectory, $relativeDirectory] = $this->uploadDirectory();
        $safePrefix = $this->safePrefix($prefix);
        $filename = $safePrefix . '_' . bin2hex(random_bytes(6)) . '.' . self::MIME_EXTENSIONS[$mime];
        $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
        if (!$this->pathWithin($destination, $uploadDirectory)) {
            throw new AppException('ÄÆ°á»ng dáº«n lÆ°u áº£nh khÃ´ng há»£p lá»‡.', 500);
        }

        $mover = $this->mover;
        if (!$mover($tmpName, $destination) || !is_file($destination)) {
            throw new AppException('KhÃ´ng thá»ƒ lÆ°u file áº£nh.', 500);
        }

        return [
            'path' => $relativeDirectory . '/' . $filename,
            'absolute_path' => $destination,
        ];
    }

    /**
     * @param array<string, mixed> $file
     * @param callable(array{path: string, absolute_path: string}): mixed $persist
     */
    public function storeImageAndPersist(array $file, string $prefix, callable $persist): mixed
    {
        $stored = $this->storeImage($file, $prefix);
        try {
            return $persist($stored);
        } catch (Throwable $exception) {
            $this->removeStoredFile($stored['absolute_path']);
            throw $exception;
        }
    }

    public static function isSafeLocalImagePath(string $path): bool
    {
        return preg_match('#^products_image/[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpg|jpeg|png|webp)$#', $path) === 1;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function uploadDirectory(): array
    {
        $root = realpath($this->projectRoot);
        if ($root === false || !is_dir($root)) {
            throw new AppException('ThÆ° má»¥c gá»‘c cá»§a á»©ng dá»¥ng khÃ´ng há»£p lá»‡.', 500);
        }

        $candidate = $root . DIRECTORY_SEPARATOR . 'products_image';
        if (!is_dir($candidate) && !mkdir($candidate, 0775, true) && !is_dir($candidate)) {
            throw new AppException('KhÃ´ng thá»ƒ táº¡o thÆ° má»¥c products_image.', 500);
        }

        $resolved = realpath($candidate);
        if ($resolved === false || !$this->pathWithin($resolved, $root)) {
            throw new AppException('ThÆ° má»¥c products_image náº±m ngoÃ i project.', 500);
        }

        return [$resolved, 'products_image'];
    }

    private function safePrefix(string $prefix): string
    {
        $prefix = trim($prefix);
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $prefix);
        if (is_string($converted)) {
            $prefix = $converted;
        }
        $prefix = strtolower($prefix);
        $prefix = preg_replace('/[^a-z0-9]+/', '-', $prefix) ?? '';
        $prefix = trim($prefix, '-');
        $prefix = substr($prefix, 0, 60);

        return $prefix === '' ? 'image' : $prefix;
    }

    private function pathWithin(string $path, string $directory): bool
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $directory = rtrim(
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory),
            DIRECTORY_SEPARATOR
        );
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
            $directory = strtolower($directory);
        }

        return $path === $directory
            || str_starts_with($path, $directory . DIRECTORY_SEPARATOR);
    }

    private function removeStoredFile(string $absolutePath): void
    {
        [$uploadDirectory] = $this->uploadDirectory();
        if ($this->pathWithin($absolutePath, $uploadDirectory) && is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }

    /** @param array<string, mixed> $file */
    private function integerField(array $file, string $key): int
    {
        $value = $file[$key] ?? null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new AppException('ThÃ´ng tin file táº£i lÃªn khÃ´ng há»£p lá»‡.', 422);
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $file */
    private function stringField(array $file, string $key): string
    {
        $value = $file[$key] ?? null;
        if (!is_string($value)) {
            throw new AppException('ThÃ´ng tin file táº£i lÃªn khÃ´ng há»£p lá»‡.', 422);
        }

        return trim($value);
    }
}

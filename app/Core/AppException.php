<?php

declare(strict_types=1);

namespace DacSanNhaDan\Core;

use RuntimeException;
use Throwable;

final class AppException extends RuntimeException
{
    private int $httpStatus;

    /**
     * @var array<string, mixed>
     */
    private array $context;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message,
        int $httpStatus = 500,
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->httpStatus = $httpStatus;
        $this->context = $context;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}

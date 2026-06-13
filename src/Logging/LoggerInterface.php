<?php

declare(strict_types=1);

namespace Jb\Logging;

interface LoggerInterface
{
    /**
     * Write a log entry.
     *
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void;
}

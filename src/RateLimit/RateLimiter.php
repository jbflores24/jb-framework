<?php

declare(strict_types=1);

namespace Jb\RateLimit;

use JsonException;

class RateLimiter
{
    private string $storageDir;
    private int $windowSizeSeconds;
    private int $maxRequestsPerWindow;

    /**
     * Initialize the rate limiter.
     *
     * @param string $storageDir Directory to store rate-limit state files.
     * @param int $maxRequestsPerWindow Maximum requests allowed in the window.
     * @param int $windowSizeSeconds Sliding window size in seconds (default: 60).
     */
    public function __construct(
        string $storageDir,
        int $maxRequestsPerWindow = 100,
        int $windowSizeSeconds = 60
    ) {
        $this->storageDir = $storageDir;
        $this->maxRequestsPerWindow = $maxRequestsPerWindow;
        $this->windowSizeSeconds = $windowSizeSeconds;

        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Check whether an identifier is within the allowed limit and, if so, register the request.
     *
     * The window slides over the last N seconds (it does not reset on the calendar minute) and the
     * read-decide-write sequence runs under an exclusive lock on the identifier's own file, so
     * concurrent requests cannot both pass when only one slot is left.
     *
     * @param string $identifier IP or user_id.
     * @param int|null $maxRequests Custom limit (optional).
     * @return array{allowed: bool, remaining: int, resetAt: int, current: int, limit: int}
     */
    public function check(string $identifier, ?int $maxRequests = null): array
    {
        $max = $maxRequests ?? $this->maxRequestsPerWindow;
        $now = time();
        $windowStart = $now - $this->windowSizeSeconds;

        $handle = $this->open($identifier, LOCK_EX);
        $requests = $this->read($handle, $windowStart);

        $count = count($requests);
        $allowed = $count < $max;
        $remaining = max(0, $max - $count - 1);

        if ($allowed) {
            $requests[] = $now;
            $this->write($handle, $requests);
        }

        $this->close($handle);
        $this->prune($now);

        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            // The identifier is free again when its oldest request leaves the window.
            'resetAt' => ($requests[0] ?? $now) + $this->windowSizeSeconds,
            'current' => $count + 1,
            'limit' => $max,
        ];
    }

    /**
     * Return the current state without incrementing the counter.
     *
     * @param string $identifier IP or user_id.
     * @param int|null $maxRequests Custom limit (optional).
     * @return array{count: int, allowed: bool, remaining: int}
     */
    public function status(string $identifier, ?int $maxRequests = null): array
    {
        $max = $maxRequests ?? $this->maxRequestsPerWindow;
        $windowStart = time() - $this->windowSizeSeconds;

        $handle = $this->open($identifier, LOCK_SH);
        $count = count($this->read($handle, $windowStart));
        $this->close($handle);

        return [
            'count' => $count,
            'allowed' => $count < $max,
            'remaining' => max(0, $max - $count),
        ];
    }

    /**
     * Reset the counter for a given identifier.
     */
    public function reset(string $identifier): void
    {
        $handle = $this->open($identifier, LOCK_EX);
        $this->write($handle, []);
        $this->close($handle);
    }

    /**
     * Flush all stored rate-limit data (useful for tests).
     */
    public function flush(): void
    {
        $files = glob($this->storageDir . '/rate_limit_*.json');
        if ($files !== false) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Build a stable identifier from a client IP and optional user id.
     *
     * @param string $clientIp Client IP address.
     * @param string|null $userId Authenticated user id (when available).
     */
    public static function getIdentifier(string $clientIp, ?string $userId = null): string
    {
        if ($userId !== null) {
            return 'user_' . $userId;
        }

        return 'ip_' . $clientIp;
    }

    /**
     * Open (creating if needed) the identifier's file and take the lock.
     *
     * Once the lock is held, the path must still point to the file that was opened: the cleanup may
     * have removed it in between, and writing to a removed file would split the state in two.
     *
     * @return resource
     */
    private function open(string $identifier, int $lock)
    {
        $file = $this->storageDir . '/rate_limit_' . hash('sha256', $identifier) . '.json';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $handle = fopen($file, 'c+');
            if ($handle === false) {
                throw new \RuntimeException('No se pudo abrir el estado del limitador de peticiones.');
            }
            flock($handle, $lock);

            clearstatcache(true, $file);
            $opened = fstat($handle);
            $current = @stat($file);
            if ($current !== false && $opened !== false && $opened['ino'] === $current['ino'] && $opened['dev'] === $current['dev']) {
                return $handle;
            }

            flock($handle, LOCK_UN);
            fclose($handle);
        }

        throw new \RuntimeException('No se pudo abrir el estado del limitador de peticiones.');
    }

    /**
     * @param resource $handle
     * @return list<int> Timestamps still inside the window.
     */
    private function read($handle, int $windowStart): array
    {
        rewind($handle);
        $contents = stream_get_contents($handle);

        try {
            $data = json_decode($contents === false || $contents === '' ? '[]' : $contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (!is_array($data)) {
            return [];
        }

        return array_values(array_filter($data, static fn ($ts) => is_int($ts) && $ts > $windowStart));
    }

    /**
     * @param resource $handle
     * @param list<int> $requests
     */
    private function write($handle, array $requests): void
    {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($requests, JSON_THROW_ON_ERROR));
        fflush($handle);
    }

    /**
     * @param resource $handle
     */
    private function close($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * Occasionally remove state files whose last write is older than two windows (every entry in
     * them has already expired). Also clears the per-minute files of earlier versions.
     */
    private function prune(int $now): void
    {
        if (random_int(1, 100) !== 1) {
            return;
        }

        $files = glob($this->storageDir . '/rate_limit_*.json');
        foreach ($files === false ? [] : $files as $file) {
            $mtime = @filemtime($file);
            if ($mtime === false || $mtime >= $now - 2 * $this->windowSizeSeconds) {
                continue;
            }

            // Only remove a file nobody is using: take its lock without waiting and delete it while holding it.
            $handle = @fopen($file, 'r+');
            if ($handle === false) {
                continue;
            }
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                clearstatcache(true, $file);
                $mtime = @filemtime($file);
                if ($mtime !== false && $mtime < $now - 2 * $this->windowSizeSeconds) {
                    @unlink($file);
                }
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }
    }
}

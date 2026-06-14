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
     * Check whether an identifier is within the allowed limit.
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

        $data = $this->loadData();
        $requests = $data[$identifier] ?? [];

        // Clean timestamps outside the current window.
        $requests = array_values(array_filter($requests, static fn ($ts) => $ts > $windowStart));

        $count = count($requests);
        $allowed = $count < $max;
        $remaining = max(0, $max - $count - 1);
        $resetAt = $now + $this->windowSizeSeconds;

        // Register the new request if allowed.
        if ($allowed) {
            $requests[] = $now;
            $data[$identifier] = $requests;
            $this->saveData($data);
        }

        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'resetAt' => $resetAt,
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
        $now = time();
        $windowStart = $now - $this->windowSizeSeconds;

        $data = $this->loadData();
        $requests = $data[$identifier] ?? [];

        $requests = array_values(array_filter($requests, static fn ($ts) => $ts > $windowStart));
        $count = count($requests);

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
        $data = $this->loadData();
        unset($data[$identifier]);
        $this->saveData($data);
    }

    /**
     * Flush all stored rate-limit data (useful for tests).
     */
    public function flush(): void
    {
        $files = glob($this->storageDir . '/rate_limit_*.json');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
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
     * Load data from the current minute's JSON file.
     *
     * @return array<string, list<int>>
     */
    private function loadData(): array
    {
        $file = $this->getCurrentFile();

        if (!file_exists($file)) {
            return [];
        }

        try {
            $contents = file_get_contents($file);

            return json_decode($contents === false ? '[]' : $contents, true, 512, JSON_THROW_ON_ERROR) ?? [];
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * Save data to the current minute's JSON file.
     *
     * @param array<string, list<int>> $data
     */
    private function saveData(array $data): void
    {
        $file = $this->getCurrentFile();

        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            file_put_contents($file, $json, LOCK_EX);
        } catch (JsonException) {
            // Silently ignore JSON encoding errors.
        }
    }

    /**
     * Resolve the storage file for the current minute.
     */
    private function getCurrentFile(): string
    {
        // Group by minute: rate_limit_2026_05_12_14_30.json
        $timestamp = date('Y_m_d_H_i');

        return $this->storageDir . '/rate_limit_' . $timestamp . '.json';
    }
}
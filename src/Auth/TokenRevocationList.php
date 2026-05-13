<?php

declare(strict_types=1);

namespace Jb\Auth;

/**
 * Persist revoked JWTs by token hash until their expiration timestamp.
 */
class TokenRevocationList
{
    public function __construct(private readonly string $storageFile)
    {
        $directory = dirname($this->storageFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (!is_file($this->storageFile)) {
            file_put_contents($this->storageFile, json_encode([]));
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function revoke(string $token, int $expiresAt, array $metadata = []): void
    {
        $items = $this->read();
        $items[$this->hash($token)] = [
            'expires_at' => $expiresAt,
            'revoked_at' => time(),
            'token_type' => $metadata['token_type'] ?? null,
            'user_id' => $metadata['user_id'] ?? null,
            'trace_id' => $metadata['trace_id'] ?? null,
        ];
        $this->write($items);
    }

    public function isRevoked(string $token): bool
    {
        $items = $this->read();
        $key = $this->hash($token);

        if (!isset($items[$key])) {
            return false;
        }

        if ($this->entryExpiresAt($items[$key]) <= time()) {
            unset($items[$key]);
            $this->write($items);
            return false;
        }

        return true;
    }

    public function cleanup(): int
    {
        $items = $this->read();
        $before = count($items);

        foreach ($items as $hash => $entry) {
            if ($this->entryExpiresAt($entry) <= time()) {
                unset($items[$hash]);
            }
        }

        $this->write($items);

        return $before - count($items);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function active(int $limit = 100): array
    {
        $items = $this->read();
        $active = [];

        foreach ($items as $hash => $entry) {
            $expiresAt = $this->entryExpiresAt($entry);
            if ($expiresAt <= time()) {
                continue;
            }

            $active[] = [
                'hash' => $hash,
                'expires_at' => $expiresAt,
                'revoked_at' => is_array($entry) ? (int) ($entry['revoked_at'] ?? 0) : 0,
                'token_type' => is_array($entry) ? ($entry['token_type'] ?? null) : null,
                'user_id' => is_array($entry) ? ($entry['user_id'] ?? null) : null,
                'trace_id' => is_array($entry) ? ($entry['trace_id'] ?? null) : null,
            ];
        }

        usort($active, static fn (array $a, array $b): int => ($b['revoked_at'] ?? 0) <=> ($a['revoked_at'] ?? 0));

        return array_slice($active, 0, max(1, $limit));
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $active = $this->active(5000);
        $expires24h = 0;
        $now = time();
        $byType = [
            'access' => 0,
            'refresh' => 0,
            'unknown' => 0,
        ];

        foreach ($active as $entry) {
            if (($entry['expires_at'] - $now) <= 86400) {
                $expires24h++;
            }

            $type = (string) ($entry['token_type'] ?? 'unknown');
            if (!isset($byType[$type])) {
                $byType[$type] = 0;
            }
            $byType[$type]++;
        }

        return [
            'active_total' => count($active),
            'expiring_within_24h' => $expires24h,
            'by_type' => $byType,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function read(): array
    {
        $raw = file_get_contents($this->storageFile);
        if ($raw === false || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, int> $items
     */
    private function write(array $items): void
    {
        file_put_contents($this->storageFile, json_encode($items, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private function entryExpiresAt(mixed $entry): int
    {
        if (is_array($entry)) {
            return (int) ($entry['expires_at'] ?? 0);
        }

        return (int) $entry;
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}

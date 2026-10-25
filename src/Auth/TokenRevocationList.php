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

        // Crear sin truncar: otro proceso puede estar escribiendo en este mismo momento.
        if (!is_file($this->storageFile)) {
            $handle = fopen($this->storageFile, 'c');
            if ($handle !== false) {
                fclose($handle);
            }
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function revoke(string $token, int $expiresAt, array $metadata = []): void
    {
        $this->mutate(function (array &$items) use ($token, $expiresAt, $metadata): bool {
            $items[$this->hash($token)] = [
                'expires_at' => $expiresAt,
                'revoked_at' => time(),
                'token_type' => $metadata['token_type'] ?? null,
                'user_id' => $metadata['user_id'] ?? null,
                'trace_id' => $metadata['trace_id'] ?? null,
            ];

            return true;
        });
    }

    public function isRevoked(string $token): bool
    {
        $items = $this->read();
        $key = $this->hash($token);

        if (!isset($items[$key])) {
            return false;
        }

        if ($this->entryExpiresAt($items[$key]) <= time()) {
            // Entrada vencida: se quita bajo el candado exclusivo, volviendo a leer por si cambió.
            $this->mutate(function (array &$current) use ($key): bool {
                if (isset($current[$key]) && $this->entryExpiresAt($current[$key]) <= time()) {
                    unset($current[$key]);

                    return true;
                }

                return false;
            });

            return false;
        }

        return true;
    }

    public function cleanup(): int
    {
        $removed = 0;
        $this->mutate(function (array &$items) use (&$removed): bool {
            $before = count($items);

            foreach ($items as $hash => $entry) {
                if ($this->entryExpiresAt($entry) <= time()) {
                    unset($items[$hash]);
                }
            }

            $removed = $before - count($items);

            return $removed > 0;
        });

        return $removed;
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
        $handle = fopen($this->storageFile, 'c+');
        if ($handle === false) {
            return [];
        }

        flock($handle, LOCK_SH);
        $items = $this->decode($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return $items;
    }

    /**
     * Read, change and write the list under one exclusive lock, so concurrent revocations cannot
     * overwrite each other. The callback receives the items by reference and returns whether it
     * changed them.
     *
     * @param callable(array<string, mixed>): bool $change
     */
    private function mutate(callable $change): void
    {
        $handle = fopen($this->storageFile, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir la lista de tokens revocados.');
        }

        flock($handle, LOCK_EX);

        try {
            $items = $this->decode($handle);

            if ($change($items)) {
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, json_encode($items, JSON_UNESCAPED_UNICODE));
                fflush($handle);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     * @return array<string, mixed>
     */
    private function decode($handle): array
    {
        rewind($handle);
        $raw = stream_get_contents($handle);
        if ($raw === false || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
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

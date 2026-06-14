<?php

declare(strict_types=1);

namespace Jb\Security\models;

class ScoreModel extends SecurityModel
{
    /**
     * Record a hit in a time window and return current attempts.
     *
     * @param string $key Logical counter key (e.g. ip, or "login:{ip}")
     * @param string $ip Real client IP, stored in the `ip` column
     * @param string $fingerprint Request fingerprint hash
     * @param int $window Window duration in seconds
     */
    public function hit(string $key, string $ip, string $fingerprint, int $window): int
    {
        $now = time();
        $nowStr = date('Y-m-d H:i:s', $now);
        $expiresStr = date('Y-m-d H:i:s', $now + $window);

        $statement = $this->pdo()->prepare(
            'SELECT * FROM security_scores WHERE score_key = :key AND expires_at > :now ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['key' => $key, 'now' => $nowStr]);
        $row = $statement->fetch();

        if (is_array($row)) {
            $attempts = ((int) $row['attempts']) + 1;
            $this->pdo()->prepare(
                'UPDATE security_scores SET attempts = :attempts, updated_at = :now WHERE id = :id'
            )->execute(['attempts' => $attempts, 'now' => $nowStr, 'id' => $row['id']]);

            return $attempts;
        }

        try {
            $this->pdo()->prepare(
                'INSERT INTO security_scores (score_key, fingerprint, attempts, expires_at, ip, window_start)
                 VALUES (:key, :fp, 1, :expires, :ip, :window_start)'
            )->execute([
                'key' => $key,
                'fp' => $fingerprint !== '' ? $fingerprint : null,
                'expires' => $expiresStr,
                'ip' => $ip,
                'window_start' => $nowStr,
            ]);

            return 1;
        } catch (\PDOException $e) {
            // Collision on uq_ip_window / uq_fingerprint_window: another score_key
            // already registered a row for this ip/fingerprint + window_start (same second).
            // Retry as UPDATE on that row so the count is not lost.
            $existing = $this->pdo()->prepare(
                'SELECT * FROM security_scores WHERE ip = :ip AND window_start = :window_start ORDER BY id DESC LIMIT 1'
            );
            $existing->execute(['ip' => $ip, 'window_start' => $nowStr]);
            $existingRow = $existing->fetch();

            if (is_array($existingRow)) {
                $attempts = ((int) $existingRow['attempts']) + 1;
                $this->pdo()->prepare(
                    'UPDATE security_scores SET attempts = :attempts, updated_at = :now WHERE id = :id'
                )->execute(['attempts' => $attempts, 'now' => $nowStr, 'id' => $existingRow['id']]);

                return $attempts;
            }

            // Could not persist; do not block the request for this, but count as 1 hit.
            return 1;
        }
    }

    /**
     * Return high risk score rows.
     *
     * @return list<array<string, mixed>>
     */
    public function highRisk(int $limit = 10): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM security_scores ORDER BY attempts DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
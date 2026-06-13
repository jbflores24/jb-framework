<?php

declare(strict_types=1);

namespace Jb\Security\models;

class ScoreModel extends SecurityModel
{
    /**
     * Record a hit in a time window and return current attempts.
     */
    public function hit(string $key, string $fingerprint, int $window): int
    {
        $now = time();
        $expires = date('Y-m-d H:i:s', $now + $window);
        $statement = $this->pdo()->prepare('SELECT * FROM security_scores WHERE score_key = :key AND expires_at > :now LIMIT 1');
        $statement->execute(['key' => $key, 'now' => date('Y-m-d H:i:s', $now)]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            $this->pdo()->prepare('INSERT INTO security_scores (score_key, fingerprint, attempts, expires_at) VALUES (:key, :fp, 1, :expires)')
                ->execute(['key' => $key, 'fp' => $fingerprint, 'expires' => $expires]);

            return 1;
        }

        $attempts = ((int) $row['attempts']) + 1;
        $this->pdo()->prepare('UPDATE security_scores SET attempts = :attempts WHERE id = :id')
            ->execute(['attempts' => $attempts, 'id' => $row['id']]);

        return $attempts;
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

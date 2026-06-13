<?php

declare(strict_types=1);

namespace Jb\Security\models;

class LogModel extends SecurityModel
{
    /**
     * Store one security event.
     *
     * @param array<string, mixed> $event
     */
    public function create(array $event): void
    {
        $sql = 'INSERT INTO security_logs (ip, user_id, endpoint, http_method, reason, severity, score, fingerprint, created_at)
                VALUES (:ip, :user_id, :endpoint, :method, :reason, :severity, :score, :fingerprint, :created_at)';
        $this->pdo()->prepare($sql)->execute([
            'ip' => $event['ip'],
            'user_id' => $event['user_id'],
            'endpoint' => $event['endpoint'],
            'method' => $event['method'],
            'reason' => $event['reason'],
            'severity' => $event['severity'],
            'score' => $event['score'],
            'fingerprint' => $event['fingerprint'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * List recent logs.
     *
     * @return list<array<string, mixed>>
     */
    public function list(int $limit = 50, int $offset = 0): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM security_logs ORDER BY id DESC LIMIT :limit OFFSET :offset');
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}

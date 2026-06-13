<?php

declare(strict_types=1);

namespace Jb\Security\models;

class AuditModel extends SecurityModel
{
    /**
     * Store an administrative security action.
     */
    public function record(?int $userId, string $action, string $target): void
    {
        $sql = 'INSERT INTO security_audit (user_id, action, target, created_at) VALUES (:user_id, :action, :target, :created_at)';
        $this->pdo()->prepare($sql)->execute([
            'user_id' => $userId,
            'action' => $action,
            'target' => $target,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * List audit entries.
     *
     * @return list<array<string, mixed>>
     */
    public function list(int $limit = 50, int $offset = 0): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM security_audit ORDER BY id DESC LIMIT :limit OFFSET :offset');
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}

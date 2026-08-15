<?php

declare(strict_types=1);

namespace Jb\Security\models;

class BlockModel extends SecurityModel
{
    /**
     * Crea o refresca un bloqueo activo.
     */
    public function block(string $ip, string $reason, int $score, int $minutes = 60): void
    {
        $sql = 'INSERT INTO security_blocks (ip, reason, score, blocked_until, active, created_at) VALUES (:ip, :reason, :score, :until, 1, :now)';
        $this->pdo()->prepare($sql)->execute([
            'ip' => $ip,
            'reason' => $reason,
            'score' => $score,
            'until' => date('Y-m-d H:i:s', time() + ($minutes * 60)),
            'now' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Devuelve un bloqueo activo por IP.
     *
     * @return array<string, mixed>|null
     */
    public function active(string $ip): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM security_blocks WHERE ip = :ip AND active = 1 AND blocked_until > :now ORDER BY id DESC LIMIT 1');
        $statement->execute(['ip' => $ip, 'now' => date('Y-m-d H:i:s')]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Desactiva los bloqueos de una IP.
     */
    public function unblock(string $ip): int
    {
        $statement = $this->pdo()->prepare('UPDATE security_blocks SET active = 0 WHERE ip = :ip AND active = 1');
        $statement->execute(['ip' => $ip]);

        return $statement->rowCount();
    }

    /**
     * Lista bloqueos activos recientes.
     *
     * @return list<array<string, mixed>>
     */
    public function list(int $limit = 50, int $offset = 0): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM security_blocks ORDER BY id DESC LIMIT :limit OFFSET :offset');
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}

<?php

declare(strict_types=1);

namespace Jb\Security\services;

use Jb\Database\Connection;

class CleanupService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Remove expired score windows and deactivate expired blocks.
     */
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $statement = $this->connection->pdo()->prepare('DELETE FROM security_scores WHERE expires_at <= :now');
        $statement->execute(['now' => $now]);

        $statement = $this->connection->pdo()->prepare('UPDATE security_blocks SET active = 0 WHERE active = 1 AND blocked_until <= :now');
        $statement->execute(['now' => $now]);
    }
}

<?php

declare(strict_types=1);

namespace Jb\Security\models;

use Jb\Database\Connection;
use PDO;

abstract class SecurityModel
{
    public function __construct(protected readonly Connection $connection)
    {
    }

    protected function pdo(): PDO
    {
        return $this->connection->pdo();
    }
}

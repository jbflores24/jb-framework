<?php

declare(strict_types=1);

namespace Jb\Security\models;

class WhitelistModel extends SecurityModel
{
    /**
     * Determine whether an IP is trusted.
     */
    public function contains(string $ip): bool
    {
        $statement = $this->pdo()->prepare('SELECT 1 FROM security_whitelist WHERE ip = :ip LIMIT 1');
        $statement->execute(['ip' => $ip]);

        return (bool) $statement->fetchColumn();
    }

    /**
     * Add an IP to the whitelist.
     */
    public function add(string $ip, string $description = ''): void
    {
        $statement = $this->pdo()->prepare('INSERT INTO security_whitelist (ip, description, created_at) VALUES (:ip, :description, :created_at)');
        $statement->execute(['ip' => $ip, 'description' => $description, 'created_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Remove an IP from the whitelist.
     */
    public function remove(string $ip): int
    {
        $statement = $this->pdo()->prepare('DELETE FROM security_whitelist WHERE ip = :ip');
        $statement->execute(['ip' => $ip]);

        return $statement->rowCount();
    }

    /**
     * List whitelisted IPs.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM security_whitelist ORDER BY id DESC');
        $statement->execute();

        return $statement->fetchAll();
    }
}

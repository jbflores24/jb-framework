<?php

declare(strict_types=1);

namespace Jb\Security\models;

class BlacklistModel extends WhitelistModel
{
    /**
     * Determine whether an IP is denied.
     */
    public function contains(string $ip): bool
    {
        $statement = $this->pdo()->prepare('SELECT 1 FROM security_blacklist WHERE ip = :ip LIMIT 1');
        $statement->execute(['ip' => $ip]);

        return (bool) $statement->fetchColumn();
    }

    /**
     * Add an IP to the blacklist.
     */
    public function add(string $ip, string $description = ''): void
    {
        $statement = $this->pdo()->prepare('INSERT INTO security_blacklist (ip, description, created_at) VALUES (:ip, :description, :created_at)');
        $statement->execute(['ip' => $ip, 'description' => $description, 'created_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Remove an IP from the blacklist.
     */
    public function remove(string $ip): int
    {
        $statement = $this->pdo()->prepare('DELETE FROM security_blacklist WHERE ip = :ip');
        $statement->execute(['ip' => $ip]);

        return $statement->rowCount();
    }

    /**
     * List blacklisted IPs.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM security_blacklist ORDER BY id DESC');
        $statement->execute();

        return $statement->fetchAll();
    }
}

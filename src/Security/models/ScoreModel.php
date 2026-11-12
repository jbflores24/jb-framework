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

        $pdo = $this->pdo();
        // Si el llamador ya abrió una transacción, esta operación participa en ella.
        $propia = !$pdo->inTransaction();
        if ($propia) {
            $pdo->beginTransaction();
        }

        try {
            $attempts = $this->sumarEnVentana($key, $ip, $fingerprint, $nowStr, $expiresStr);
            if ($propia) {
                $pdo->commit();
            }

            return $attempts;
        } catch (\PDOException) {
            if ($propia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // No se pudo persistir: no bloquear la petición por esto, contar como 1 intento.
            return 1;
        }
    }

    /**
     * Incrementa el contador de la ventana activa de forma atómica.
     *
     * Antes se hacía SELECT y luego UPDATE con el valor leído, así que dos peticiones
     * concurrentes leían el mismo número y una de las dos se perdía (lost update).
     * Ahora la fila activa se bloquea con SELECT ... FOR UPDATE y el incremento se hace
     * sobre el valor bloqueado, dentro de la transacción de hit().
     */
    private function sumarEnVentana(string $key, string $ip, string $fingerprint, string $nowStr, string $expiresStr): int
    {
        $pdo = $this->pdo();

        // 1. Ventana activa de esta clave: se bloquea hasta terminar la transacción.
        $statement = $pdo->prepare(
            'SELECT id, attempts FROM security_scores
             WHERE score_key = :key AND expires_at > :now
             ORDER BY id DESC LIMIT 1' . $this->sufijoBloqueo()
        );
        $statement->execute(['key' => $key, 'now' => $nowStr]);
        $row = $statement->fetch();

        if (is_array($row)) {
            return $this->incrementarFila((int) $row['id'], (int) $row['attempts'], $nowStr);
        }

        // 2. Sin ventana activa: crear la fila.
        try {
            $pdo->prepare(
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
            // Colisión en uq_ip_window / uq_fingerprint_window: otra petición creó la fila
            // en el mismo segundo. Se suma sobre esa fila, también bloqueándola.
            $existing = $pdo->prepare(
                'SELECT id, attempts FROM security_scores
                 WHERE ip = :ip AND window_start = :window_start
                 ORDER BY id DESC LIMIT 1' . $this->sufijoBloqueo()
            );
            $existing->execute(['ip' => $ip, 'window_start' => $nowStr]);
            $existingRow = $existing->fetch();

            if (is_array($existingRow)) {
                return $this->incrementarFila((int) $existingRow['id'], (int) $existingRow['attempts'], $nowStr);
            }

            throw $e;
        }
    }

    /**
     * SQLite no admite FOR UPDATE (y serializa las escrituras por sí mismo); en MySQL y
     * PostgreSQL la cláusula es necesaria para que el bloqueo funcione.
     */
    private function sufijoBloqueo(): string
    {
        $driver = (string) $this->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return in_array($driver, ['mysql', 'pgsql'], true) ? ' FOR UPDATE' : '';
    }

    /**
     * Suma un intento a una fila ya bloqueada por la transacción actual.
     */
    private function incrementarFila(int $id, int $intentosActuales, string $nowStr): int
    {
        $attempts = $intentosActuales + 1;
        $this->pdo()->prepare(
            'UPDATE security_scores SET attempts = :attempts, updated_at = :now WHERE id = :id'
        )->execute(['attempts' => $attempts, 'now' => $nowStr, 'id' => $id]);

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
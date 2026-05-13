<?php

declare(strict_types=1);

namespace Jb\Rate;

use JsonException;

class RateLimiter {
    private string $storageDir;
    private int $windowSizeSeconds;
    private int $maxRequestsPerWindow;
    private array $fileHandles = [];

    /**
     * Inicializar RateLimiter
     *
     * @param string $storageDir Directorio para almacenar archivos JSON
     * @param int $maxRequestsPerWindow Máximo de requests en ventana
     * @param int $windowSizeSeconds Tamaño de ventana en segundos (defecto: 60)
     */
    public function __construct(
        string $storageDir = __DIR__ . '/../../storage/rate_limits',
        int $maxRequestsPerWindow = 100,
        int $windowSizeSeconds = 60
    ) {
        $this->storageDir = $storageDir;
        $this->maxRequestsPerWindow = $maxRequestsPerWindow;
        $this->windowSizeSeconds = $windowSizeSeconds;

        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Verificar si identifier está dentro del límite
     *
     * @param string $identifier IP o user_id
     * @param int $maxRequests Límite personalizado (opcional)
     * @return array {allowed: bool, remaining: int, resetAt: int, current: int}
     */
    public function check(string $identifier, ?int $maxRequests = null): array {
        $max = $maxRequests ?? $this->maxRequestsPerWindow;
        $now = time();
        $windowStart = $now - $this->windowSizeSeconds;

        $data = $this->loadData();
        $requests = $data[$identifier] ?? [];

        // Limpiar timestamps fuera de la ventana
        $requests = array_filter($requests, fn($ts) => $ts > $windowStart);

        $count = count($requests);
        $allowed = $count < $max;
        $remaining = max(0, $max - $count - 1);
        $resetAt = $now + $this->windowSizeSeconds;

        // Registrar nuevo request si está permitido
        if ($allowed) {
            $requests[] = $now;
            $data[$identifier] = $requests;
            $this->saveData($data);
        }

        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'resetAt' => $resetAt,
            'current' => $count + 1,
            'limit' => $max
        ];
    }

    /**
     * Obtener estado sin incrementar contador
     *
     * @param string $identifier IP o user_id
     * @param int $maxRequests Límite personalizado (opcional)
     * @return array {count: int, allowed: bool, remaining: int}
     */
    public function status(string $identifier, ?int $maxRequests = null): array {
        $max = $maxRequests ?? $this->maxRequestsPerWindow;
        $now = time();
        $windowStart = $now - $this->windowSizeSeconds;

        $data = $this->loadData();
        $requests = $data[$identifier] ?? [];

        // Limpiar timestamps fuera de la ventana
        $requests = array_filter($requests, fn($ts) => $ts > $windowStart);
        $count = count($requests);

        return [
            'count' => $count,
            'allowed' => $count < $max,
            'remaining' => max(0, $max - $count)
        ];
    }

    /**
     * Resetear contador de identifier
     *
     * @param string $identifier IP o user_id
     * @return void
     */
    public function reset(string $identifier): void {
        $data = $this->loadData();
        unset($data[$identifier]);
        $this->saveData($data);
    }

    /**
     * Limpiar todos los datos (principalmente para tests)
     *
     * @return void
     */
    public function flush(): void {
        $files = glob($this->storageDir . '/rate_limit_*.json');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Cargar datos del archivo JSON actual
     *
     * @return array
     */
    private function loadData(): array {
        $file = $this->getCurrentFile();

        if (!file_exists($file)) {
            return [];
        }

        try {
            $contents = file_get_contents($file);
            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR) ?? [];
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * Guardar datos al archivo JSON actual
     *
     * @param array $data
     * @return void
     */
    private function saveData(array $data): void {
        $file = $this->getCurrentFile();

        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            file_put_contents($file, $json, LOCK_EX);
        } catch (JsonException) {
            // Silenciosamente ignorar errores de JSON
        }
    }

    /**
     * Obtener nombre de archivo para ventana de tiempo actual
     *
     * @return string
     */
    private function getCurrentFile(): string {
        // Agrupar por minuto: rate_limit_2026_05_12_14_30.json
        $timestamp = date('Y_m_d_H_i');
        return $this->storageDir . '/rate_limit_' . $timestamp . '.json';
    }

    /**
     * Obtener identificador de cliente (IP o usuario)
     * Prioriza usuario autenticado si existe
     *
     * @param string $clientIp IP del cliente
     * @param string|null $userId ID del usuario (de claims JWT)
     * @return string Identificador único
     */
    public static function getIdentifier(string $clientIp, ?string $userId = null): string {
        if ($userId !== null) {
            return 'user_' . $userId;
        }
        return 'ip_' . $clientIp;
    }
}

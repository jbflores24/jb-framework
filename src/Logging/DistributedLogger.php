<?php

declare(strict_types=1);

namespace Jb\Logging;

use JsonException;

class DistributedLogger {
    private string $storageDir;
    private string $alertsDir;

    /**
     * Inicializar DistributedLogger
     *
     * @param string $storageDir Directorio para logs JSON
     * @param string $alertsDir Directorio para alertas
     */
    public function __construct(
        string $storageDir = __DIR__ . '/../../storage/logs',
        string $alertsDir = __DIR__ . '/../../storage/alerts'
    ) {
        $this->storageDir = $storageDir;
        $this->alertsDir = $alertsDir;

        foreach ([$storageDir, $alertsDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Registrar evento de acceso HTTP
     *
     * @param array $data Datos del evento
     * @return void
     */
    public function logAccess(array $data): void {
        $event = [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'type' => 'ACCESS',
            'trace_id' => $data['trace_id'] ?? 'unknown',
            'method' => $data['method'] ?? 'UNKNOWN',
            'endpoint' => $data['endpoint'] ?? '/',
            'status' => $data['status'] ?? 0,
            'user_id' => $data['user_id'] ?? null,
            'client_ip' => $data['client_ip'] ?? 'unknown',
            'duration_ms' => $data['duration_ms'] ?? 0,
            'user_agent' => $data['user_agent'] ?? null,
        ];

        $this->write($event);
    }

    /**
     * Registrar evento de autenticación
     *
     * @param array $data Datos del evento
     * @return void
     */
    public function logAuthentication(array $data): void {
        $event = [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'type' => 'AUTHENTICATION',
            'trace_id' => $data['trace_id'] ?? 'unknown',
            'action' => $data['action'] ?? 'LOGIN', // LOGIN, REFRESH, LOGOUT
            'user_id' => $data['user_id'] ?? null,
            'email' => $data['email'] ?? null,
            'client_ip' => $data['client_ip'] ?? 'unknown',
            'success' => $data['success'] ?? true,
            'reason' => $data['reason'] ?? null,
        ];

        $this->write($event);

        // Alertar si fallo de autenticación
        if (!$event['success']) {
            $this->alert('AUTH_FAILURE', $event);
        }
    }

    /**
     * Registrar evento de rate limiting
     *
     * @param array $data Datos del evento
     * @return void
     */
    public function logRateLimitViolation(array $data): void {
        $event = [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'type' => 'RATE_LIMIT',
            'trace_id' => $data['trace_id'] ?? 'unknown',
            'identifier' => $data['identifier'] ?? 'unknown',
            'limit' => $data['limit'] ?? 0,
            'requests' => $data['requests'] ?? 0,
            'client_ip' => $data['client_ip'] ?? 'unknown',
            'endpoint' => $data['endpoint'] ?? '/',
        ];

        $this->write($event);
        $this->alert('RATE_LIMIT_VIOLATION', $event);
    }

    /**
     * Registrar evento de error HTTP
     *
     * @param array $data Datos del evento
     * @return void
     */
    public function logError(array $data): void {
        $event = [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'type' => 'ERROR',
            'trace_id' => $data['trace_id'] ?? 'unknown',
            'status' => $data['status'] ?? 500,
            'code' => $data['code'] ?? 'UNKNOWN',
            'message' => $data['message'] ?? 'Unknown error',
            'endpoint' => $data['endpoint'] ?? '/',
            'method' => $data['method'] ?? 'UNKNOWN',
            'client_ip' => $data['client_ip'] ?? 'unknown',
            'user_id' => $data['user_id'] ?? null,
        ];

        $this->write($event);

        // Alertar si error 5xx
        if (($event['status'] ?? 0) >= 500) {
            $this->alert('SERVER_ERROR', $event);
        }
    }

    /**
     * Registrar evento de cambio de datos
     *
     * @param array $data Datos del evento
     * @return void
     */
    public function logDataChange(array $data): void {
        $event = [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'type' => 'DATA_CHANGE',
            'trace_id' => $data['trace_id'] ?? 'unknown',
            'action' => $data['action'] ?? 'UNKNOWN', // CREATE, UPDATE, DELETE
            'entity' => $data['entity'] ?? 'unknown',
            'entity_id' => $data['entity_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'changes' => $data['changes'] ?? [],
            'client_ip' => $data['client_ip'] ?? 'unknown',
        ];

        $this->write($event);
    }

    /**
     * Obtener logs del día
     *
     * @param string $date Fecha en formato Y-m-d (defecto hoy)
     * @return array Lista de eventos
     */
    public function getLogs(string $date = 'today'): array {
        if ($date === 'today') {
            $date = date('Y-m-d');
        }

        $file = $this->getLogFile($date);

        if (!file_exists($file)) {
            return [];
        }

        try {
            $contents = file_get_contents($file);
            $lines = array_filter(explode("\n", $contents));
            
            $logs = [];
            foreach ($lines as $line) {
                $event = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $logs[] = $event;
            }

            return $logs;
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * Filtrar logs por criterio
     *
     * @param string $date Fecha
     * @param array $criteria {type, status, user_id, trace_id, etc}
     * @return array
     */
    public function filterLogs(string $date, array $criteria): array {
        $logs = $this->getLogs($date);

        return array_filter($logs, function ($event) use ($criteria) {
            foreach ($criteria as $key => $value) {
                if (!isset($event[$key]) || $event[$key] !== $value) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Obtener alertas del día
     *
     * @param string $date Fecha en formato Y-m-d (defecto hoy)
     * @return array Lista de alertas
     */
    public function getAlerts(string $date = 'today'): array {
        if ($date === 'today') {
            $date = date('Y-m-d');
        }

        $file = $this->getAlertsFile($date);

        if (!file_exists($file)) {
            return [];
        }

        try {
            $contents = file_get_contents($file);
            $lines = array_filter(explode("\n", $contents));
            
            $alerts = [];
            foreach ($lines as $line) {
                $alert = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $alerts[] = $alert;
            }

            return $alerts;
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * Obtener eventos críticos (últimas N horas)
     *
     * @param int $hours Horas hacia atrás
     * @return array
     */
    public function getCriticalEvents(int $hours = 24): array {
        $since = time() - ($hours * 3600);
        $critical = [];

        // Buscar en últimos N días
        for ($i = 0; $i < ceil($hours / 24); $i++) {
            $date = date('Y-m-d', time() - ($i * 86400));
            $alerts = $this->getAlerts($date);
            
            foreach ($alerts as $alert) {
                if (($alert['timestamp'] ?? 0) >= $since) {
                    $critical[] = $alert;
                }
            }
        }

        // Ordenar por timestamp descendente
        usort($critical, fn($a, $b) => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));

        return $critical;
    }

    /**
     * Limpiar logs anteriores a N días
     *
     * @param int $days Días a retener
     * @return int Cantidad de archivos eliminados
     */
    public function cleanup(int $days = 7): int {
        $count = 0;
        $cutoff = time() - ($days * 86400);

        $files = glob($this->storageDir . '/log_*.jsonl');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Limpiar todo para tests
     *
     * @return void
     */
    public function flush(): void {
        array_map('unlink', glob($this->storageDir . '/log_*.jsonl') ?: []);
        array_map('unlink', glob($this->alertsDir . '/alerts_*.jsonl') ?: []);
    }

    /**
     * Escribir evento a archivo
     *
     * @param array $event
     * @return void
     */
    private function write(array $event): void {
        $file = $this->getLogFile();

        try {
            $json = json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            file_put_contents($file, $json . "\n", FILE_APPEND | LOCK_EX);
        } catch (JsonException) {
            // Silenciosamente ignorar errores
        }
    }

    /**
     * Registrar alerta crítica
     *
     * @param string $type Tipo de alerta
     * @param array $event Evento que originó la alerta
     * @return void
     */
    private function alert(string $type, array $event): void {
        $alert = [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'alert_type' => $type,
            'event' => $event,
        ];

        $file = $this->getAlertsFile();

        try {
            $json = json_encode($alert, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            file_put_contents($file, $json . "\n", FILE_APPEND | LOCK_EX);
        } catch (JsonException) {
            // Silenciosamente ignorar errores
        }
    }

    /**
     * Obtener nombre de archivo de log para fecha
     *
     * @param string $date Fecha en formato Y-m-d (defecto hoy)
     * @return string
     */
    private function getLogFile(string $date = 'today'): string {
        if ($date === 'today') {
            $date = date('Y-m-d');
        }

        return $this->storageDir . '/log_' . $date . '.jsonl';
    }

    /**
     * Obtener nombre de archivo de alertas para fecha
     *
     * @param string $date Fecha en formato Y-m-d (defecto hoy)
     * @return string
     */
    private function getAlertsFile(string $date = 'today'): string {
        if ($date === 'today') {
            $date = date('Y-m-d');
        }

        return $this->alertsDir . '/alerts_' . $date . '.jsonl';
    }
}

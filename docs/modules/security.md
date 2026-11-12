# Módulo Security

## Proposito

Detectar patrones de riesgo y reforzar seguridad operacional en runtime.

## Responsabilidades

- evaluacion de requests con detectores especializados;
- scoring de riesgo;
- bloqueo y registro de eventos;
- endpoints de administracion de seguridad.

## Clases principales

- SecurityMiddleware
- SecurityManager
- ScoringEngine
- detectores en `src/Security/detectors`
- SecurityAdminController

## Dependencias

- Core pipeline;
- Logging;
- persistencia de datos de seguridad.

## Flujo interno

`Request` -> `SecurityMiddleware` -> `SecurityManager` -> detectores -> scoring -> allow o block -> log/auditoría.

## Extensibilidad

- nuevos detectores;
- ajuste de umbrales y politicas.

## Esquema de scoring

`ScoreModel::hit()` persiste cada intento en la tabla `security_scores` con las columnas `ip`, `window_start`, `score_key`, `fingerprint`, `attempts` y `expires_at`. Cuando no existe fila activa para el `score_key`, inserta una nueva con `attempts = 1`. Si ya existe, incrementa el contador dentro de una transacción: la fila activa se bloquea con `SELECT ... FOR UPDATE` (en MySQL y PostgreSQL) y el incremento se aplica sobre el valor bloqueado. Así, peticiones concurrentes no pierden intentos (antes el `SELECT` y el `UPDATE` eran independientes y dos peticiones podían escribir el mismo número). En SQLite, donde no existe `FOR UPDATE`, la cláusula se omite.

La tabla usa dos constraints `UNIQUE` compuestos — `uq_ip_window (ip, window_start)` y `uq_fingerprint_window (fingerprint, window_start)` — para garantizar que un mismo IP o fingerprint solo pueda generar una fila por ventana de tiempo, sin importar cuantos detectores distintos esten activos. En caso de colision de INSERT por estos constraints, `ScoreModel` degrada automaticamente a un `UPDATE` sobre la fila existente para no perder el conteo.

Nota: si la colisión ocurre en el mismo segundo entre claves distintas (por ejemplo `login:{ip}` y `404:{ip}`), ambas comparten la fila de esa IP y ese segundo. Es el comportamiento actual; si se quiere contadores independientes, hay que revisar el diseño de los constraints.

La limpieza la ejecuta `CleanupService::run()`, invocado al inicio de cada request por `SecurityMiddleware`. Elimina filas con `expires_at <= NOW()` y desactiva bloqueos vencidos (`blocked_until <= NOW()`).

## Riesgos tecnicos

- sobrecarga de falsos positivos;
- costos de mantenimiento si el catalogo de detectores crece sin gobernanza;
- en entornos con `sql_mode` estricto (`STRICT_TRANS_TABLES`), los INSERTs que omitan columnas `NOT NULL` sin `DEFAULT` lanzaran excepcion; el codigo actual garantiza que `ip` y `window_start` siempre se incluyen.

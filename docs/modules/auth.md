# Módulo Auth

## Proposito

Autenticar requests con JWT y aplicar autorizacion por permisos.

## Responsabilidades

- emision y validacion de JWT;
- inyección de claims autenticados a la `Request`;
- validacion de permisos por ruta.

## Clases principales

- JWT
- AuthMiddleware
- PermissionMiddleware
- AuthService (implementado en evolucion actual)
- TokenRevocationList (implementado para revocacion)

## Dependencias

- núcleo `Request`/`Response`;
- Config auth;
- logging opcional para auditoría.

## Flujo interno

Bearer token -> `AuthMiddleware` -> decodificación de JWT -> claims disponibles -> `PermissionMiddleware` valida permisos.

## Extensibilidad

- estrategias de revocacion;
- refresh policy configurable.

## Riesgos tecnicos

- complejidad de reglas de expiracion/revocacion si crece el modelo sin contratos claros.

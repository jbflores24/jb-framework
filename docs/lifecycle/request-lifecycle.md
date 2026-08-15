# Ciclo de vida de la solicitud

## Implementado

- La `Request` se captura desde las variables globales de HTTP.
- El `Router` resuelve la ruta y los parámetros dinámicos.
- Los middlewares agregan o validan contexto.
- El handler consume la `Request` enriquecida.
- `Response` serializa la salida.

## Riesgos

- enriquecimiento excesivo de la `Request` sin contratos claros entre middlewares.

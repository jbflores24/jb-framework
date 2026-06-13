# Request lifecycle

## Implementado

- Request se captura desde globals HTTP.
- Router resuelve ruta y parametros dinamicos.
- Middlewares agregan o validan contexto.
- Handler consume Request enriquecido.
- Response serializa salida.

## Riesgos

- enriquecimiento excesivo del Request sin contratos claros entre middlewares.

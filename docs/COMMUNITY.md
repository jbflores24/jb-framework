# Colaboración y seguridad

## Contribuciones

Las contribuciones deben ser pequeñas, cohesionadas y con impacto claro. Antes de proponer cambios:

- revisa el alcance del framework;
- confirma que el cambio encaja con una API REST JSON minimalista;
- ejecuta pruebas locales cuando el comportamiento cambie.

## Flujo recomendado

1. Crear una rama descriptiva.
2. Implementar un cambio acotado.
3. Ejecutar pruebas relevantes.
4. Actualizar la documentación pública si cambió la interfaz o el comportamiento.
5. Abrir un pull request con evidencia mínima de validación.

## Criterios de revisión

Se priorizan cambios que:

- mejoran seguridad;
- reducen complejidad;
- aumentan cobertura o validación;
- documentan decisiones públicas del framework.

Se revisan con más cautela los cambios que:

- agregan dependencias nuevas;
- amplían el alcance del framework fuera de REST JSON;
- introducen abstracciones difíciles de justificar.

## Conducta esperada

El proyecto espera interacción técnica respetuosa, centrada en evidencia y enfocada en el código, no en las personas. Las discusiones deben mantenerse útiles, concretas y profesionales.

## Reporte de seguridad

Las vulnerabilidades no corregidas no deben abrirse como issue público. El reporte debe incluir:

- resumen del problema;
- impacto esperado;
- pasos de reproducción;
- versión o commit afectado;
- mitigación sugerida, si existe.

## Tiempos objetivo de respuesta

- acuse inicial dentro de 3 días hábiles;
- triage técnico dentro de 7 días hábiles;
- decisión o plan de corrección dentro de 14 días hábiles.

## Estado de la gobernanza

JB Framework ya cuenta con plantillas de issue y pull request, workflow de pruebas y lineamientos básicos de colaboración. Esta carpeta concentra la referencia pública necesaria para cualquier contribuidor externo.
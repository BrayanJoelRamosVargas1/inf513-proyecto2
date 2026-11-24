# Ajuste documental de DEMO 0.3

Fecha: 10/11/2026. Corrección posterior a la auditoría del paquete. No introduce DEMO 0.4 ni modifica la aplicación, las migraciones o los datos.

## Correcciones

- Se sincronizan los estados de los 30 RF entre `02-TRAZABILIDAD.csv` y `02-TRAZABILIDAD.md`. RF-01 deja de presentar perfiles, revocaciones y permisos temporales como pendientes; RF-12 distingue la subsanación DEMO implementada de los plazos y atribuciones institucionales pendientes.
- Se actualizan también los textos antiguos del CSV sobre rectificaciones de méritos (RF-16), reasignación de evaluadores (RF-19), rectificación/publicación versionada (RF-20), rondas sucesivas de desempate (RF-27) y protección de versiones por triggers (RF-30).
- RF-04 aclara el registro DEMO de vacantes retenidas por renuncia, sin reemplazos automáticos. Su procedimiento institucional sigue pendiente.
- RF-30 corrige las referencias operativas de ruta y tablas para coincidir con el Markdown vigente. No se cambia su definición contractual.
- Las dos matrices identifican métodos concretos de selección, gobernanza y participación presentes en el XML registrado. `PASS_BASE_DEMO_0_3` significa evidencia registrada del escenario correspondiente; no cierre integral del RF ni ejecución nueva.
- El apartado histórico 0.2 remite ahora al XML y registro conservados en `evidence/baseline-02/`, en lugar de los archivos actuales de 0.3. Se corrige el nombre del script de comprobación HTTP de participación.

## Validación del ajuste

El resultado reproducible de las comprobaciones documentales está en `evidence/traceability-documentation-fix.json`: 30 identificadores únicos, nombres y campos contractuales preservados, estados y referencias de pruebas sincronizados, métodos existentes en código y XML, pruebas históricas conservadas y hash canónico idéntico. Se comprueba también que los archivos versionados de la aplicación y las evidencias previas no cambiaron. El empaquetador verifica CRC y todos los hashes del manifiesto al generar el ZIP.

Los informes históricos de 0.1 y 0.2 y la auditoría inicial de fuentes mantienen sus fechas y alcances. Sus cifras históricas no representan el estado actual. No se reescriben resultados anteriores.

## Pendientes que se conservan

El XML existente registra 102 pruebas, 311 aserciones y cero errores/fallos. Este ajuste no reejecuta PHPUnit, PostgreSQL, restauraciones, concurrencia ni una demostración funcional. No se convierte la evidencia histórica en validación independiente.

El contraste directo de los cuatro PDF y tres fotografías originales continúa NOT_VERIFIED. No se recuperaron ni verificaron esas fuentes durante este ajuste. Siguen pendientes los procedimientos institucionales de subsanación, aceptación, incompatibilidades y cobertura de vacantes, además de validación institucional y pruebas independientes de seguridad, carga e infraestructura. Las manifestaciones DEMO no equivalen a aceptación institucional ni autorizan adjudicar reemplazos.

Se preserva íntegro `CU_Y_RF_CANONICOS.md`, incluidos los 8 CU y RF-01..RF-30. SHA-256: `fc7bd40b944002fc698eb56234816de4b36ce4ee11486e335673edd8f17e11e2`.

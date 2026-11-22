# INF513-SA · Proyecto 2 · Grupo 09

**Estado local actual:** PROC-01 reproducido y corregido; diagnóstico de colas atrasadas y pruebas adicionales de concurrencia y restauración. 235 pruebas Laravel y 1402 aserciones, conservando los 227 identificadores anteriores. [Resultados, operación y límites](docs/ROBUSTEZ_PROCESADOR_PROC01.md).

Se mantiene **NO_GO_INSTITUCIONAL**. Este cierre conserva el procesador anterior y su historial. Siguen pendientes los documentos de postulantes, EICAR, la operación permanente y las aprobaciones institucionales. No se desplegó al sitio remoto.

Antecedentes conservados: [procesador supervisado](docs/PROCESADOR_DOCUMENTAL_SUPERVISADO.md), [integridad en transiciones y ClamAV](docs/INTEGRIDAD_EN_TRANSICIONES_Y_CLAMAV.md), [cuarentena](docs/CUARENTENA_FUENTES_INSTITUCIONALES.md) y [preparación institucional](docs/PREPARACION_INSTITUCIONAL_FASE_1.md). Sus registros tienen el alcance y la fecha de esas entregas.

Sistema académico de selección de auxiliares FICCT: React, API Laravel y PostgreSQL.

[Aplicación publicada](https://www.tecnoweb.org.bo/inf513/grupo09sa/proyecto2/p2) · [Arquitectura e instalación](docs/INF513_P2_IMPLEMENTACION.md) · [Guía de defensa](docs/GUIA_DEFENSA_CAMBIOS_EN_VIVO.md) · [Respaldo e incidentes](docs/RUNBOOK_BD_INCIDENTES.md) · [Inventario de rutas API](docs/INF513_P2_OPENAPI.json).

Antecedente académico: 189 pruebas Laravel / 1026 aserciones, 18 pruebas React y recorrido remoto principal de 22 comprobaciones. Se conserva el contrato de ocho CU y treinta RF. Las cifras históricas no se suman a la ejecución actual. Consultar evidence/current-validation.json y evidence/inf513-p2.

**DEMO académica con datos sintéticos; validación institucional pendiente.** Pagos ficticios y decisiones DEMO identificadas. Las credenciales, configuración privada, documentos de postulantes y dependencias instaladas están excluidos del repositorio y de los paquetes.

Instalar Composer y npm desde los archivos de bloqueo, configurar una base/esquema exclusivo mediante .env.example, generar clave solo en primera instalación, migrar sin borrar datos y compilar con npm run build. Para la subruta docente usar VITE_ASSET_BASE=/inf513/grupo09sa/proyecto2/build/. Servir únicamente public y mantener APP_DEBUG=false. El seeder de una base DEMO nueva guarda accesos aleatorios en .local/ACCESOS_DEMO.txt; mantenerlo privado. Leer la guía completa antes de actualizar una instalación existente.

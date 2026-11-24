# Arquitectura del sistema existente

La interfaz académica actual es una SPA React servida en `/p2`, que consume la API REST de Laravel 12 mediante Axios. PostgreSQL mantiene la persistencia y las restricciones. Las vistas Blade se conservan como interfaz complementaria. Node se utiliza para compilar y probar el frontend; no ejecuta las reglas académicas ni decide permisos. Los apartados de extensiones conservan la evolución histórica de la aplicación.

La capa de presentación reúne navegación por permisos, temas, accesibilidad y formularios. Los controladores API validan las peticiones y delegan las operaciones al dominio compartido (`Selection`, `Participation`, `Rectifications` y los servicios documentales). La capa de datos conserva transacciones, claves, restricciones e históricos protegidos mediante triggers. La autorización se verifica en el servidor, además de controlar qué acciones muestra cada interfaz.

Los documentos permanecen en almacenamiento privado. El procesador compartido y ClamAV gestionan cuarentena y resultados de análisis; una descarga requiere autorización, análisis vigente e integridad de bytes. Los adaptadores de supervisión dependen del entorno de instalación. Su aceptación operativa debe demostrarse por separado en cada servidor.

`PortalController`: formularios/validación HTTP y vistas. `Access`: permisos por convocatoria y propiedad. `Selection`: transacciones y reglas de negocio. `Scores`: decimal exacto, validación y redondeo. Migraciones: integridad referencial, unicidad y rangos CHECK de PostgreSQL. `SecureResponse`: cabeceras de seguridad y rechazo de cuentas inactivas. No se usa ocultación de botones como autorización.

## Relaciones principales

```mermaid
erDiagram
    users ||--o{ memberships : posee
    calls ||--o{ memberships : delimita
    calls ||--o{ offers : contiene
    categories ||--o{ offers : clasifica
    offers ||--o{ applications : recibe
    users ||--o{ applications : registra
    applications ||--o{ documents : versiona
    applications ||--o{ reviews : verifica
    offers ||--o{ assignments : asigna
    users ||--o{ assignments : evalua
    assignments ||--o{ grades : autoriza
    applications ||--o{ grades : recibe
    offers ||--o{ tie_exams : convoca
    offers ||--o{ publications : conserva
    calls ||--o{ audit_events : registra
```

## Flujo operativo DEMO

```mermaid
flowchart LR
 A[Convocatoria borrador] --> B[Configurar ofertas y publicar]
 B --> C[Postulación y documentos privados]
 C --> D[Envío y revisión por criterio]
 D --> E{Cumple requisitos}
 E -->|Sí| F[Habilitación y CPD]
 E -->|No| G[Inhabilitación con motivo]
 F --> H[Docentes asignados registran notas]
 H --> I[Cálculo decimal por postulación]
 I --> J{Empate que afecta plaza}
 J -->|Sí| K[Nuevo examen exclusivo de empatados]
 K --> L[Condiciones, comisión, resultados y acta]
 L --> M[Aprobación DEMO y snapshot]
 J -->|No| M
 M --> N[Reporte PDF/XLSX y auditoría]
```

## Transacciones, versiones y concurrencia

Se bloquea la fila de convocatoria antes de postulación/oferta/nota en las mutaciones del dominio. Esta serialización conservadora evita carreras de límite, cupo y publicación en la escala de la DEMO. Las restricciones únicas protegen persona/oferta, asignación de área y nota por asignación/postulación. Las pruebas `scripts/concurrency.py` usan dos procesos PHP independientes y conexiones PostgreSQL, no una base en memoria.

La publicación congela las ofertas de una convocatoria. Cada postulación guarda snapshot; cada resultado audita notas y pesos usados; aprobar conserva publicación inmutable para el portal. Las rectificaciones posteriores utilizan el procedimiento de revisión y nueva publicación implementado en `Rectifications`; no sustituyen silenciosamente las publicaciones históricas. `.parent_id` permite relacionar convocatorias. Los históricos conservan ofertas distintas 01/02 y su fuente.

## Fórmula del fixture, no normativa de composición

Notas de áreas o de componentes sobre 100, pesos que suman 100: `examen = HALF_UP_2((Σ nota × porcentaje / 100) × 0.6)`; `final = mérito + examen`. Se usa BCMath con precisión interna de 8 decimales. Las notas de entrada aceptan dos decimales; los campos son NUMERIC, nunca float. No se mezclan áreas con escrito/práctico. `NULL` bloquea y no equivale a cero.

## Permisos

| Perfil | Lectura/operaciones |
|---|---|
| Postulante | Su expediente, sus archivos y resultados; crear/enviar dentro de recepción |
| Revisor con membresía | Expedientes y documentos de su convocatoria; revisión, habilitación y registro CPD |
| Evaluador con membresía y asignación | Solo postulaciones de ofertas asignadas y sus propias notas; sin archivos de identidad |
| Autoridad con membresía | Configuración, consolidado, notas, decisiones, desempate, reportes y auditoría del ámbito; sin descarga implícita de CI |
| Administrador técnico | Crear cuentas, tipos y ámbitos; sin acceso implícito a expedientes o resultados; no se autoasigna permisos |

Un usuario puede tener varias membresías en distintos ámbitos. La composición formal de autoridades y la separación de funciones institucionales siguen pendientes de validación.

## Límites operativos

Servidor PHP de desarrollo y usuario dueño del cluster únicamente para DEMO local; no configuración productiva. Auditoría append-only desde el portal, no invulnerable frente al administrador de infraestructura. Almacenamiento privado con descargas autorizadas, MIME/extensión/tamaño, checksum y nombres generados. No hay sustitución de documentos físicos, integración CPD, nóminas ni asistencia. El módulo académico de pagos es una simulación con métodos, transacciones y planes; no cobra dinero ni establece tasas institucionales.

## Colaboración de clases

```mermaid
classDiagram
 class PortalController
 class Selection
 class Access
 class Scores
 class SecureResponse
 PortalController --> Selection : operaciones
 PortalController --> Access : lectura autorizada
 Selection --> Access : ámbito e identidad
 Selection --> Scores : aritmética exacta
 SecureResponse --> PortalController : sesión y cabeceras
```

## Secuencia crítica: confirmación y selección

```mermaid
sequenceDiagram
 actor Docente
 actor Autoridad
 participant Portal as PortalController
 participant Dominio as Selection
 participant DB as PostgreSQL
 Docente->>Portal: Confirmar nota asignada
 Portal->>Dominio: grade(usuario, postulación, asignación, nota)
 Dominio->>DB: BEGIN y lock convocatoria/postulación
 Dominio->>Dominio: Verificar ámbito, identidad y habilitación
 Dominio->>DB: Nota + evento auditoría y COMMIT
 Autoridad->>Portal: Calcular y abrir ranking
 Portal->>Dominio: calculate / ranking
 Dominio->>DB: Leer notas confirmadas y conservar resultado
 alt Empate por última plaza
 Dominio-->>Autoridad: Nuevo examen requerido
 Autoridad->>Portal: Condiciones, comisión, examen y acta
 Portal->>Dominio: openTie / resolveTie
 Dominio->>DB: Conservar participantes, resultados y decisión
 end
 Autoridad->>Portal: Aprobar selección DEMO
 Portal->>Dominio: approve
 Dominio->>DB: Lock, validar cupos, snapshot y auditoría
```

## Extensión transaccional DEMO 0.2

GovernanceController utiliza Administration y Rectifications. Las escrituras de Selection y de ambos servicios pasan por Access::transaction: cerrojo común de permisos, identidad actual, convocatoria y entidad. Las lecturas autorizadas usan membresía vigente y cuenta activa. SecureResponse compara security_version de sesión y usuario; revocar, renovar o cambiar perfil invalida las sesiones existentes.

correction_requests conserva propuestas y decisiones; score_versions conserva cada nota confirmada y corrección; result_revisions captura ranking anterior/nuevo y las versiones de entrada. Las publicaciones encadenan revisión y publicación anterior. Triggers PostgreSQL bloquean UPDATE/DELETE de estos históricos y de audit_events. Las proyecciones actuales de nota y postulación cambian únicamente en transacciones controladas; una corrección deja la selección under_review hasta republicar.

El cerrojo común simplifica el orden y evita revocar concurrentemente con una nota todavía autorizada; es deliberadamente conservador para DEMO. No se ha validado su rendimiento a volumen institucional. Detalles y limitaciones en DEMO_0_2_CAMBIOS_Y_VALIDACION.md.

## Extensión DEMO 0.3

ParticipationController utiliza Participation y el mismo cerrojo de Access::transaction. Las proyecciones de participación y versión de expediente se agregan sin borrar estados anteriores. dossier_snapshots conserva documentos/revisiones; amendments gobierna entrega y revisión; participation_requests/decisions separa petición y decisión; vacancy_events conserva el origen de una plaza retenida; award_choices conserva manifestaciones por publicación/versiones. Documentos y cuatro tablas históricas adicionales tienen triggers de inmutabilidad (nueve en total).

El ranking solo incluye participación activa y respeta seats - held_vacancies para evitar cubrir renuncias. result_revisions admite un origen por rectificación o por solicitud de participación; no se modifica una publicación anterior. El documento de contraste distingue reglas heredadas, consultas actuales y políticas técnicas DEMO.

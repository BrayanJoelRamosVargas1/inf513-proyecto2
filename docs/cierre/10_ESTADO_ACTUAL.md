# Cierre técnico local — 24/11/2026

El mismo sistema Laravel/React/PostgreSQL continúa activo en http://127.0.0.1:8116/p2. No se recreó la base operativa, no se sustituyó el backend ni se publicó remotamente.

| Matriz existente | Estado comprobado |
|---|---|
| 8 CU | 7 verificados localmente; CU5 parcial por homologación externa CPD |
| 30 RF | 29 verificados localmente; RF-15 parcial por formulario oficial CPD |
| 14 INF513 | 10 verificados localmente; 4 pendientes de infraestructura/publicación/entrega |
| 123 acciones | 95 verificadas localmente; 28 equivalencias verificadas; ningún identificador eliminado |

Las 98 filas antes abiertas se resolvieron vinculando pruebas vigentes o ejecutando variantes dirigidas. No se infiere aceptación por existir una ruta. Las equivalencias enlazan sus identificadores originales y comparten el controlador, Request, validación y servicios; sus positivos/persistencia se enlazan a React y sus rechazos se compararon por HTTP. Esto no certifica todos los payloads ni cada variante visual de los formularios heredados.

**Arquitectura:** React/Axios y API REST son la presentación principal. Las rutas Blade conservan compatibilidad, sin ser requisito especial de Tecnoweb. Comparten dominio y transacciones; permanecen protegidas. No se retiraron más rutas ni módulos. El catálogo distingue funcionalidades de usuario, contratos internos, compatibilidad y equivalencias.

**Correcciones nuevas:** IA-021: el login/salida HTML omitía eventos de acceso con IP; ahora delega en SessionController, sin duplicar login/auditoría. IA-022: la validación heredada podía aparecer en inglés; SecureResponse fija español para web y API. Ambos defectos se reprodujeron antes y pasan después. IA-020 conserva su cierre: primera ronda continuada y segunda resuelta, sin falso pendiente.

**Pruebas:** 306 PHP / 1973 aserciones, sin fallos; 62 React aprobadas y compilación vigente. Compatibilidad: 156 comprobaciones de lecturas, documentos originales, reportes y permisos por seis perfiles sintéticos. Adaptadores: 33 comparaciones de validación/rechazo, mismo error y estado HTTP. Ambos recibos finales conservaron fuente e instrumento invariables durante ejecución. El primer recibo de lecturas se conserva como antecedente porque se añadió una rama al instrumento durante su ejecución; se repitió congelado y ese segundo recibo es el vigente.

**Qué hace cada perfil:** el postulante inicia expedientes, carga originales, envía, subsana dentro de una ventana autorizada y consulta resultados/adjudicaciones propias. El revisor consulta documentos privados de su ámbito, revisa requisitos, decide habilitación, registra respaldo/puntaje de méritos y revisa subsanaciones. El evaluador registra/confirma notas únicamente en las áreas asignadas; no descarga expedientes ni habilita. La autoridad gestiona sus convocatorias, asignaciones, etapas, desempates, publicaciones, decisiones de participación y reportes; su cargo no concede acceso a documentos privados. El administrador mantiene cuentas, permisos y tipos, sin calificar ni aprobar resultados por su perfil técnico. Decano/Vicedecano usan responsabilidades autorizadas por convocatoria: no se inventaron nuevos privilegios oficiales.

**CPD:** el archivo, referencia, asociación al expediente y puntaje manual están comprobados. No se certifica un formulario estructurado institucional sin sus campos homologados. Por eso CU5/RF-15 conservan la dependencia externa. La búsqueda sin tildes es mejora opcional; se conserva ILIKE sensible a acentos y las 81 consultas existentes, sin introducir unaccent ni dependencia nueva en el alojamiento.

**Preservación:** evidencia independiente compara la línea de base de 50 tablas: 45 idénticas y filas originales conservadas en tráfico/auditoría; 21 rutas de contador sin disminuciones. Los 35 archivos privados originales y el SHA-256 canónico permanecen intactos. Sincronización y nueva comprobación se ejecutan antes de empaquetar.

**Candidato:** preparación del tarball de esta misma fuente, con instantánea de estas matrices bajo docs/cierre, manifiesto y exclusión de secretos. La instalación del hash exacto tendrá aceptación externa al tarball para evitar circularidad. No se da por instalada hasta que esa ejecución termine.

**Pendientes externos exactos:** INF513-11 Tecnoweb/PostgreSQL asignado y capacidades reales (ClamAV/trabajador/almacenamiento/triggers); INF513-12 GitHub público actualizado; INF513-13 aceptación y entrega del tarball candidato; INF513-14 entrega/defensa en servidor docente. No se realizaron cambios externos. NO_GO_INSTITUCIONAL permanece por homologación y autorización oficial, separado de la aceptación técnica local.

Evidencia vigente: evidence/phpunit-compatibility-final.xml; evidence/react-tie-label-current.json; evidence/matrix-current-totals.json; evidence/preservation-closure-latest.json; sistema/evidence/integral-audit/closure-legacy-reads-verified/; sistema/evidence/integral-audit/closure-legacy-aliases-verified/.

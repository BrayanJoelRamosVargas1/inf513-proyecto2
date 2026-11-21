# INF513-SA · Proyecto 2 · Grupo 09

## Estado al 21 de noviembre de 2026

Interfaz React en `/p2`, API Laravel con sesión y PostgreSQL. Se conserva la interfaz anterior para comparación y continuidad. Se mantienen intactos el contrato de 8 CU / 30 RF y los registros históricos. La reconversión académica no constituye autorización institucional.

La ejecución actual de PHPUnit registra 189 pruebas y 1026 aserciones. Las verificaciones nuevas cubren sesión, permisos, navegación desde PostgreSQL, postulación y evaluación, preferencias, búsqueda, métricas, visitas, pagos ficticios, configuración, administración, subsanación versionada, retiro aprobado, publicación, manifestación DEMO y rectificación sin sobrescribir publicaciones. Las ejecuciones anteriores son antecedentes; no deben sumarse como pruebas adicionales.

Verificación independiente desde una copia del código publicable: Composer y npm instalados desde sus archivos de bloqueo; PHPUnit 189/1026; React 18/18; compilación de producción correcta. Recorrido de navegador: 21 comprobaciones, incluidos descargas PDF/Excel reales y rechazo CSRF sin token. Seis combinaciones de tema/modo y contraste con letra al 200 % revisados con axe y pantalla de 320 píxeles. Restauración en otra base: 41 tablas idénticas y nueve triggers. La QA remota adicional completó 11 comprobaciones de permisos, cargas rechazadas, privacidad, estadísticas, ayuda, cookies y enlaces; sus capturas esperan la navegación cargada. El análisis automático de accesibilidad no sustituye una evaluación completa con personas usuarias.

## Arquitectura

[Guía de defensa](GUIA_DEFENSA_CAMBIOS_EN_VIVO.md) · [Respaldo e incidentes](RUNBOOK_BD_INCIDENTES.md)

React → HTTP JSON `/api/v1` → controladores Laravel → servicios de dominio → PostgreSQL y almacenamiento privado.

Las mutaciones académicas de la API utilizan los servicios existentes de selección, administración, rectificaciones y participación. Los permisos se comprueban en el servidor, incluidos ámbito por convocatoria, vigencia, asignaciones y separación de responsables. El menú consulta `navigation_items` y `navigation_roles`; ocultar una opción no reemplaza la autorización.

La sesión usa cookies de primera parte y protección CSRF. El cliente solicita `/api/v1/csrf` y envía el token en las mutaciones. No almacena contraseñas ni tokens de acceso en localStorage. La política de contenido de React permite scripts locales; la interfaz anterior conserva su política sin scripts. No se cargan bibliotecas desde CDN.

## Perfiles

| Perfil | Trabajo principal | Límites |
|---|---|---|
| Postulante | Crear cuenta, preparar y enviar su expediente, atender subsanaciones, consultar resultados y registrar manifestaciones | Solo expedientes propios; no se autoevalúa ni adjudica plazas |
| Revisor | Cotejar documentos y requisitos, habilitar, registrar respaldo CPD, revisar subsanaciones | Convocatorias autorizadas; no aprueba sus propias rectificaciones |
| Evaluador | Registrar borradores y confirmar notas de sus áreas | Asignaciones vigentes; sin acceso al detalle documental reservado |
| Autoridad | Configurar convocatorias, conducir etapas, gestionar desempates, aprobar publicaciones y decisiones de participación | Ámbito vigente; sin reemplazos automáticos ni modificación de publicaciones anteriores |
| Administrador | Cuentas, perfiles, responsabilidades y tipos de auxiliatura | Ser administrador no concede facultades académicas |

Las responsabilidades pueden combinarse cuando existe autorización vigente. No se elimina una combinación válida para simplificar la interfaz.

## Requisitos académicos adicionales

| Requisito | Implementación local | Evidencia necesaria para cierre |
|---|---|---|
| React + API Laravel + PostgreSQL | Interfaz, rutas JSON y persistencia real | PHPUnit, compilación y recorrido de navegador |
| Roles de negocio | Postulante, revisor, evaluador y autoridad, además de administrador | Autorización y recorrido por perfil |
| Menú desde BD | Dos tablas, responsabilidades efectivas | Prueba que cambia la etiqueta desde PostgreSQL |
| Accesos e IP | Eventos de ingreso correcto, fallido y salida | API y revisión del registro sin contraseñas |
| Tres temas y día/noche | Niños, jóvenes, adultos; preferencias persistentes | Seis combinaciones, móvil y contraste |
| Validación cliente/servidor | Formularios y errores JSON en español | Casos inválidos y flujo completo |
| Contador al pie | PostgreSQL, recibo idempotente por visita | Reintento sin duplicación |
| Estadísticas | Ámbito académico y actividad autorizada | Filtrado por usuario/rol |
| Buscador en cabecera | Convocatorias y ofertas visibles | Exclusión de borradores ajenos y datos privados |
| Pagos y planes | Módulo aislado de simulación | Propiedad, idempotencia, cuotas y suma exacta |

Los pagos son ficticios. No se solicitan números de tarjeta, CVV ni credenciales bancarias; no condicionan las postulaciones. Las cuotas conservan importes exactos y no permiten registrar dos pagos exitosos para una misma cuota.

## Instalación y ejecución

Requiere PHP compatible con `composer.lock`, extensiones PostgreSQL, Composer, PostgreSQL y Node compatible con `package-lock.json`. Entorno local comprobado: PHP 8.3.30, PostgreSQL 15 y Node 22.23.2.

1. Instalar dependencias con `composer install` y `npm ci`.
2. Crear `.env` a partir de `.env.example`; configurar una base exclusiva de la aplicación y una clave propia con `php artisan key:generate`.
3. Respaldar la base y el almacenamiento privado antes de actualizar una instalación existente.
4. Ejecutar `php artisan migrate --force`. Nunca usar `migrate:fresh` contra datos que deban conservarse.
5. Ejecutar `npm run build` y servir únicamente el directorio `public`.
6. En una base DEMO nueva, `php artisan db:seed` genera usuarios sintéticos y guarda sus accesos aleatorios en `.local/ACCESOS_DEMO.txt`; mantener ese archivo privado. El seeder conserva una base que ya contiene convocatorias.
7. Abrir `/p2`. El acceso anterior sigue disponible en `/login`.

Pruebas: utilizar una base exclusiva `ficct_test` y las salvaguardas de `scripts/test_environment.py`; ejecutar PHPUnit. `npm test` ejecuta las pruebas de React. Nunca apuntar las pruebas con RefreshDatabase a la base operativa.

## Alojamiento y entrega

Repositorio publicado: https://github.com/BrayanJoelRamosVargas1/inf513-proyecto2 .

Aplicación desplegada: https://www.tecnoweb.org.bo/inf513/grupo09sa/proyecto2/p2 .
Se ejecutó un recorrido real de 22 comprobaciones: administración, autoridad, revisor, evaluador y estudiante; carga de cinco PDF, habilitación, méritos, notas, resultado 68, publicación, manifestación DEMO, exportaciones PDF/Excel y pagos ficticios. Las capturas y registros corresponden a datos sintéticos. Las verificaciones locales anteriores de 21 comprobaciones son antecedentes distintos.

Servidor comprobado: PHP 8.4.28, PostgreSQL mediante PDO, esquema exclusivo ficct_p2 (41 tablas y nueve triggers). No se modificaron los esquemas de otros proyectos. El servidor carece de BCMath nativo: composer.lock incorpora phpseclib/bcmath_compat; se verificó la suma decimal en el servidor y se repitieron las 189 pruebas / 1026 aserciones con el motor de compatibilidad en el entorno aislado.

La raíz pública contiene únicamente el punto de entrada y recursos; aplicación, dependencias y configuración están en una carpeta privada protegida. HTTPS, cookies Secure/HttpOnly/SameSite=Lax y ruta exclusiva de Proyecto 2. Se comprobaron respuestas 403/404 al intentar leer archivos privados. Las credenciales aleatorias están fuera del repositorio y los paquetes.

La compilación para el servidor utiliza VITE_ASSET_BASE=/inf513/grupo09sa/proyecto2/build/. APP_URL y SESSION_PATH deben corresponder a la instalación; APP_DEBUG=false. La interfaz conservada también ajusta sus enlaces a la subruta. No ejecutar generadores exclusivos del entorno local en producción.

No incluir `.env`, accesos DEMO locales, dumps, archivos privados de postulantes, `vendor` ni `node_modules` en el repositorio público. Conservar fuentes normativas y evidencias históricas en los paquetes privados originales cuando no corresponda publicarlas.

## Pendientes institucionales

Puntaje mínimo aprobatorio, discrepancia de rúbrica de laboratorio, composición oficial de exámenes y reglas institucionales de subsanación, renuncia y compatibilidad de plazas. Los desempates requieren examen y decisión de comisión; no se inventa fórmula ganadora. La configuración de áreas es por convocatoria. Las decisiones DEMO se identifican como tales.

## Detalles de operación y límites técnicos

Las estadísticas filtran postulaciones y accesos por fecha; la convocatoria filtra los datos académicos. Documentos, notas y adjudicaciones corresponden a postulaciones autorizadas. Solo el administrador obtiene el conteo global de usuarios. Las series muestran hasta 90 fechas en hora de Bolivia. La búsqueda incluye tipos, convocatorias y ofertas, mantiene la consulta al paginar y rechaza páginas inexistentes. Un permiso en una convocatoria no abre los documentos de otra.

La retención de telemetría es una política técnica DEMO de 90 días configurable en config/portal.php. ficct:telemetry-prune solo informa por defecto; --apply elimina eventos antiguos de acceso. No elimina expedientes, auditoría, publicaciones, contadores ni recibos idempotentes. No hay una tarea automática programada; la institución debe aprobar su política de conservación.

Los reportes CSV de pagos son privados del propietario y se identifican como simulaciones. Un pago sin plan no reduce el saldo de cuotas. No se procesan tarjetas ni dinero real.

La ayuda de Cuenta distingue cada responsabilidad efectiva; solo el postulante obtiene las seis muestras PDF A4, identificadas como MUESTRA. No son documentos institucionales válidos. Un evaluador recibe la guía de evaluación, sin detalle reservado del expediente.

Las pruebas automatizadas, axe y recorridos sintéticos no constituyen un pentest exhaustivo, una prueba formal de carga ni validación de accesibilidad con usuarios. Tampoco certifican vigencia normativa, aprobación docente o autorización para concursos reales. Deben resolverse las decisiones institucionales indicadas antes de uso institucional.

# INF513-SA · Proyecto 2 · Grupo 09

## Estado al 21 de noviembre de 2026

Interfaz React en `/p2`, API Laravel con sesión y PostgreSQL. Se conserva la interfaz anterior para comparación y continuidad. Se mantienen intactos el contrato de 8 CU / 30 RF y los registros históricos. La reconversión académica no constituye autorización institucional.

La ejecución actual de PHPUnit registra 182 pruebas y 970 aserciones. Las verificaciones nuevas cubren sesión, permisos, navegación desde PostgreSQL, postulación y evaluación, preferencias, búsqueda, métricas, visitas, pagos ficticios, configuración, administración, subsanación versionada, retiro aprobado, publicación, manifestación DEMO y rectificación sin sobrescribir publicaciones. Las ejecuciones anteriores son antecedentes; no deben sumarse como pruebas adicionales.

Verificación independiente desde una copia del código publicable: Composer y npm instalados desde sus archivos de bloqueo; PHPUnit 182/970; React 9/9; compilación de producción correcta. Recorrido de navegador: 21 comprobaciones, incluidos descargas PDF/Excel reales y rechazo CSRF sin token. Seis combinaciones de tema/modo y contraste con letra al 200 % revisados con axe y pantalla de 320 píxeles. Restauración en otra base: 41 tablas idénticas y nueve triggers. El análisis automático de accesibilidad no sustituye una evaluación completa con personas usuarias.

## Arquitectura

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

Repositorio creado: https://github.com/BrayanJoelRamosVargas1/inf513-proyecto2 . La creación del repositorio no demuestra que el código esté publicado ni desplegado.

Ruta docente prevista: `https://www.tecnoweb.org.bo/inf513/grupo09sa/proyecto2`. El sondeo inicial devolvió 404. SSH, FTP y el servidor PostgreSQL respondieron; esto no confirma autenticación ni versiones del alojamiento. Falta verificar el usuario asignado, acceso real, directorio público, versión PHP, extensiones, base autorizada, rewrite y permisos. No se han aplicado migraciones remotas.

Para un subdirectorio, compilar con `VITE_ASSET_BASE=/inf513/grupo09sa/proyecto2/build/`, configurar `APP_URL` con la URL real y comprobar cookies, rutas de React, API y archivos desde red externa. `APP_DEBUG=false`, HTTPS, `.env` fuera del directorio público. Estas instrucciones requieren prueba en el servidor antes de declarar entrega lista.

No incluir `.env`, accesos DEMO locales, dumps, archivos privados de postulantes, `vendor` ni `node_modules` en el repositorio público. Conservar fuentes normativas y evidencias históricas en los paquetes privados originales cuando no corresponda publicarlas.

## Pendientes institucionales

Puntaje mínimo aprobatorio, discrepancia de rúbrica de laboratorio, composición oficial de exámenes y reglas institucionales de subsanación, renuncia y compatibilidad de plazas. Los desempates requieren examen y decisión de comisión; no se inventa fórmula ganadora. La configuración de áreas es por convocatoria. Las decisiones DEMO se identifican como tales.

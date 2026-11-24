# Guía de demostración · INF513 P2 · Grupo 09

Nota de auditoría — 24/11/2026: esta guía conserva el recorrido y las cifras de una entrega docente anterior. La revisión en curso se ejecuta localmente en http://127.0.0.1:8116/p2 y en una copia aislada para pruebas. No se ha confirmado que la URL remota tenga el código actual. Las cifras de 189 pruebas y el recorrido remoto que aparecen abajo son antecedentes, no resultados de esta auditoría. Consultar `../../AUDITORIA_INTEGRAL_INF513_20261123/PROGRESO.json` en este espacio de trabajo para el avance y sus pendientes; ese informe no implica cierre integral ni publicación.

Abrir https://www.tecnoweb.org.bo/inf513/grupo09sa/proyecto2/p2 . Utilizar las cuentas sintéticas del archivo privado de accesos. Mantener ventanas o perfiles separados para cada cuenta; nunca mostrar contraseñas en una grabación.

## Recorrido por responsabilidades

1. Administrador: mostrar perfiles, permisos con ámbito y vigencia, tipos y menú. Explicar que administrar cuentas no autoriza calificar ni publicar.
2. Postulante: crear cuenta, seleccionar una oferta de convocatoria abierta, preparar borrador, descargar muestras PDF de Cuenta, cargar documentos reales y confirmar envío. Mostrar que solo ve sus expedientes. Las muestras están marcadas y no sirven para concurso real.
3. Revisor: abrir una convocatoria asignada, revisar por criterio y confirmar habilitación con motivo. Registrar méritos CPD con respaldo. Una subsanación crea otra versión y requiere nueva revisión.
4. Evaluador: abrir sus áreas asignadas, guardar borrador y confirmar nota. El detalle documental reservado no aparece; su menú no incluye Mis postulaciones por este rol.
5. Autoridad: mostrar configuración por convocatoria, etapas, ranking, registro de desempate mediante nuevo examen y aprobación de publicación. No declarar ganadores empatados ni reemplazantes automáticamente.
6. Postulante: consultar publicación y registrar manifestación DEMO sobre plaza. Mostrar que una renuncia o rectificación conserva publicaciones anteriores.
7. Cada cuenta: búsqueda por tipo/asignatura con paginación, estadísticas de su ámbito, contador, Cuenta con ayuda específica y tres temas en día/noche.
8. Pagos: simular intento fallido y exitoso, plan en cuotas y CSV. Explicar que es un ejercicio académico sin dinero real y sin efecto en selección.

La convocatoria abierta de presentación es independiente de la convocatoria ya evaluada. No reutilizar un proceso cerrado para fingir una postulación nueva. Los identificadores pueden variar entre instalaciones.

## Cambios verificables durante la defensa

| Cambio | Dónde | Verificación |
|---|---|---|
| Texto o subtítulo de una pantalla React | resources/js/portal/*.jsx | Compilar y comprobar el texto en el navegador |
| Colores, tipografía y tamaños | resources/js/portal/portal.css | Probar día/noche, contraste y pantalla pequeña |
| Etiqueta y orden del menú | navigation_items de PostgreSQL | Recargar sesión; la API obtiene el menú desde BD |
| Visibilidad del menú por responsabilidad | navigation_roles y permisos vigentes | Probar otra cuenta; el servidor debe seguir rechazando accesos no autorizados |
| Convocatoria, oferta y áreas | Pantallas de autoridad | Configurar antes de congelar/publicar; no cambiar procesos históricos |
| Métricas y búsqueda | WorkspaceController.php y analytics.jsx | Validar ámbito, filtros y resultados; no reemplazar consultas por números fijos |
| Política de telemetría DEMO | config/portal.php | Ejecutar primero ficct:telemetry-prune sin --apply |

Para compilar en la ruta docente, definir VITE_ASSET_BASE=/inf513/grupo09sa/proyecto2/build/ antes de npm run build. Desplegar recursos y manifest de forma coherente. Respaldar antes de cambiar el servidor. No editar directamente publicaciones ni notas confirmadas mediante SQL.

## Evidencia y preguntas pendientes

Regresión actual: 189 pruebas Laravel, 1026 aserciones; 18 pruebas React. El recorrido remoto principal registró 22 comprobaciones. Consultar evidence/inf513-p2 y la documentación de implementación para límites y resultados adicionales. No sumar los registros históricos.

Puntaje aprobatorio, rúbrica CPD discrepante, autoridades y procedimientos institucionales requieren confirmación. Conservar los 8 CU y 30 RF canónicos. La demostración acredita funcionamiento técnico con datos sintéticos, no autorización institucional.

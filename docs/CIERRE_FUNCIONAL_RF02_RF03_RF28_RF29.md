# Cierre funcional de RF-02, RF-03, RF-28 y RF-29 — DEMO 0.3

Fecha: 15/11/2026, Bolivia. Se implementaron funciones de la aplicación y se ejecutaron pruebas nuevas. Continúa DEMO 0.3, sin alterar los ocho CU ni los treinta RF canónicos.

## Funciones incorporadas

- **RF-02:** gestión y número de convocatoria en campos separados, alta y edición del borrador, consulta y auditoría existentes. Ambos quedan congelados al publicar. Una migración aditiva permite nulos para los registros previos; no extrae datos del título ni modifica históricos. No se inventa una secuencia oficial de numeración.
- **RF-03:** edición de tipos sin uso y disponibilidad para configuraciones futuras, con motivo y auditoría. Solo administrador. Tipos ya usados conservan nombre y régimen; desactivarlos no cambia sus ofertas ni cancela postulaciones. No se elimina historia.
- **RF-28:** consolidado de toda la convocatoria para su autoridad efectiva. Incluye ofertas sin candidatos, notas confirmadas/corregidas por área, pesos, méritos, examen, final, posición por puntaje y cobertura/publicación. Notas iguales mantienen posición igual; no decide el desempate. Los resultados incompletos no se convierten en cero ni generan posición definitiva.
- **RF-29:** PDF y Excel con resultados, áreas, documentación requerida y plazas. Se distingue última publicación de estado actual, adjudicación activa de selección que figuró antes de una renuncia y cupo sin publicación de una vacante confirmada. El Excel usa texto explícito para evitar interpretar nombres como fórmulas.
- **RF-05:** se corrigió la etiqueta HTML que impedía seleccionar correctamente el ámbito Facultad en el formulario de creación.

Los nuevos informes no contienen binarios privados, rutas de almacenamiento, CI, correo ni motivos sensibles. La autoridad conserva los mismos límites de descarga de archivos privados. Un archivo de subsanación no enviado no sustituye el expediente vigente, y una subsanación enviada pendiente de nueva revisión no hereda una aprobación documental anterior.

El consolidado utiliza una transacción de lectura consistente en PostgreSQL; no mezcla versiones de diferentes instantes entre sus tablas. La exportación deja auditoría y vuelve a comprobar el permiso antes de entregar el archivo. No se cambió Selection::ranking ni se introdujo un umbral aprobatorio supuesto.

## Pruebas ejecutadas

**136 pruebas / 585 aserciones, cero errores y fallos finales.** Se conservaron los 119 identificadores de la entrega operativa. Hay 17 pruebas nuevas: nueve C01–C09 y ocho D01–D08. La regresión se ejecutó en ficct_test, con cachés de pruebas separadas. Los datos de la instalación disponible y las evidencias previas permanecieron iguales durante la ejecución.

La migración se aplicó a la instancia operativa después de respaldarla: 31 tablas anteriores conservadas, filas y columnas previas comparadas, dos campos nuevos nulos y un registro nuevo de migración. No se volvió a sembrar la base ni se restauró encima de ella.

Recorrido Chromium nuevo sobre una copia aislada: **16 comprobaciones y 103 respuestas HTTP** (78 de estado 200, 20 redirecciones 302 y cinco rechazos 403 esperados). Incluye el circuito de participación y los nuevos formularios de catálogo e identificación. El servidor temporal se detuvo y las 31 tablas de la instancia disponible permanecieron iguales. Se compararon los registros históricos de notas, documentos, snapshots, manifestaciones y publicaciones. En la copia se confirmó por base de datos la gestión/número/ámbito y la edición/desactivación del tipo.

Consolidado real en tres convocatorias: seis capturas (escritorio y 320 px), sin desbordamiento global ni hallazgos automáticos de las etiquetas WCAG seleccionadas. Doce sondeos adicionales de página/PDF/Excel para administrador, postulante, revisor y evaluador recibieron 403. La inyección de axe utilizó bypassCSP exclusivamente en el contexto de prueba: la política del servidor no se modificó. El primer intento de inyección fue bloqueado por la CSP, como corresponde.

Se descargaron PDF y Excel desde sus enlaces reales. El PDF de cuatro páginas se renderizó con Poppler y se inspeccionaron visualmente sus cuatro secciones. Las pruebas verificaron el Excel de cuatro hojas, los datos decimales, la presencia de ofertas vacías y el tratamiento de cadenas que comienzan por signo igual. Formato Pint y sintaxis de los ocho archivos PHP nuevos/modificados comprobados.

Los intentos de desarrollo fallidos se conservan: conflicto del nombre de un auxiliar de prueba con PHPUnit y dos errores de compilación de la vista, luego corregidos. Los directorios de intentos y ejecuciones previas no se presentan como resultados finales. La ejecución vigente está en regresion/.

## Uso

Instancia local: http://127.0.0.1:8116. Entrar como autoridad de la convocatoria y abrir **Ver consolidado de toda la convocatoria**. En Usuarios y permisos, el administrador dispone de **Administrar tipos existentes**. Gestión/número aparecen en crear y editar borrador; la consulta distingue los valores no registrados. El acceso a la nueva pantalla también funciona con rutas cacheadas.

## Estado de los treinta RF y límites

AUDITORIA_CIERRE_30_RF.md coteja los treinta nombres canónicos con código y evidencia. Las matrices CSV y Markdown conservan el contrato y contienen las referencias C/D nuevas. El XML vigente se indica en evidence/current-validation.json; los archivos históricos no fueron sustituidos.

La base DEMO tiene evidencia ejecutada para los treinta RF; esto no demuestra cobertura exhaustiva ni cierre integral de sus subcasos. No hay otro faltante técnico concreto confirmado en esta revisión del núcleo. Clonación asistida, integraciones oficiales, antivirus y política de retención de producción, formatos institucionales y pruebas externas/representativas siguen fuera de lo verificado. No se atribuye a esta fase una nueva prueba de carga o una nueva auditoría externa.

Quedan pendientes las decisiones institucionales ya registradas: puntaje aprobatorio, rúbricas, composición de exámenes, autoridades, hitos/plazos, aceptación/compatibilidades y expediente digital. Los siete originales ya fueron recuperados y contrastados; no son archivos pendientes de recuperación. Su vigencia y autorización institucional requieren confirmación. La consulta institucional sigue sin enviarse.

Los verificadores de entregas anteriores se conservan para sus propios ZIP. El verificador de este paquete es scripts/verify_functional_release.py; verify_traceability.py usa el puntero al XML vigente sin alterar el original. Ninguno de ellos vuelve a ejecutar Laravel al revisar el ZIP.

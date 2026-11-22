# Integridad en transiciones y pruebas reales de ClamAV

Continuación local del refuerzo de cuarentena. Fecha: 21/11/2026. Base: c5afb2740bea14696b34f55835987b0a3f0aa46f. No se modificó la versión publicada ni se habilitaron concursos oficiales.

## Hallazgo corregido

El dictamen identificó correctamente una diferencia: la descarga comprobaba bytes e integridad, mientras el envío y la conclusión de revisión solo comprobaban el estado registrado del análisis.

Ahora ambas transiciones verifican disponibilidad, lectura y SHA-256 actual de cada versión conservada. Un archivo ausente, ilegible, alterado o mayor al límite impide la transición. No se inserta un envío o revisión parcial ni cambia la versión del expediente cuando la comprobación falla; la transacción revierte sus cambios. Se permite observar y devolver un expediente aunque sus fuentes estén bloqueadas.

El envío conserva en su snapshot la fuente, versión, SHA-256 comprobado, identificador del análisis y fecha de comprobación. La conclusión de revisión incorpora las comprobaciones a la auditoría. Los snapshots, fuentes y revisiones anteriores permanecen intactos; no se les añaden retrospectivamente estas verificaciones.

## Concurrencia e integridad

La decisión bloquea las filas de sus fuentes, ordenadas por identificador, dentro de su transacción. El inicio de un análisis adquiere el mismo bloqueo antes de registrar un nuevo intento. El análisis del archivo ocurre fuera de ese bloqueo breve.

Si otro análisis ya está pendiente o invalida el resultado anterior, la decisión se rechaza. Si la decisión ya está verificando la fuente, un nuevo análisis espera hasta que termine la transacción. También se revalida el identificador del análisis después de leer los bytes. El snapshot consulta nuevamente los estados después de obtener los bloqueos para conservar metadatos coherentes.

La prueba con dos procesos PHP y conexiones PostgreSQL independientes observó directamente el bloqueo mediante pg_blocking_pids. El nuevo intento se creó después del commit del envío. Ambos análisis de ese ensayo utilizaron ClamAV real y firmas oficiales sobre un PDF MUESTRA. La instrumentación solo coordinó el instante de lectura; no simuló el bloqueo ni los resultados del motor.

Este ensayo acredita ese orden de operaciones, no rendimiento bajo carga. Tampoco convierte una base local en infraestructura institucional. Un administrador que controla almacenamiento y base puede alterar archivos después de una decisión; esta fase registra lo verificado en ese momento, no promete inmutabilidad física permanente. Las copias externas protegidas siguen pendientes.

## Motor real: alcance comprobado y límites

Se descargó ClamAV portable 1.5.5 para Windows x64 desde la [distribución oficial](https://www.clamav.net/downloads), sin instalar servicios ni cambiar el antivirus del equipo. Se verificó el SHA-256 del ZIP contra el digest del activo oficial de GitHub. Freshclam descargó y verificó las bases oficiales main, daily y bytecode en un laboratorio privado separado.

| Caso real | Resultado observado |
|---|---|
| PDF MUESTRA limpio, firmas oficiales | Adaptador: clean |
| PDF con marcador inocuo y una firma de ensayo adicional | Adaptador: blocked; clamscan confirmó FICCT.TestOnly.Acceptance.UNOFFICIAL FOUND |
| Directorio de firmas inexistente | Adaptador: error |
| Copia genuina de main.cvd firmado, demasiado antiguo | Adaptador: error; clamscan confirmó que la base supera dos días |

No se modificaron cabeceras CVD ni el reloj para producir el caso de antigüedad. No hubo dobles de prueba en estos cuatro casos del adaptador. La firma inocua adicional se cargó solo en una copia de ensayo, nunca en las firmas operativas o del servidor docente.

**Límite importante:** EICAR no quedó validado. El archivo de prueba no estuvo disponible al leerlo y el intento por entrada estándar devolvió error, no detección. No se atribuye ese resultado a un componente específico del equipo sin más evidencia. Se conserva el informe del intento fallido en clamav-real.json; no se sustituyó por un PASS.

clamav-adaptador-real.json resume cuatro casos aprobados con alcance limitado y conserva el intento EICAR como NOT_VERIFIED. No equivale a aceptación antimalware completa, corpus de malware, auditoría externa ni autorización institucional.

Las opciones y códigos de clamscan se contrastaron con el [manual oficial](https://github.com/Cisco-Talos/clamav/blob/main/docs/man/clamscan.1.in). Los ejecutables, bases y muestras permanecen fuera del paquete de código. No se habilitaron excepciones ni se desactivaron protecciones del equipo.

## Evidencia de esta entrega

- 213 pruebas Laravel y 1223 aserciones, sin fallos. Se conservan los 209 identificadores anteriores y se añaden Q11–Q14.
- Q11/Q12: ausencia y alteración de archivos en envío y revisión; rechazo sin cambios de estado o historial; comprobaciones incorporadas solo en decisiones nuevas.
- Q13/Q14: cambio de análisis durante la lectura, detectado antes de completar envío o revisión.
- Ensayo independiente con dos procesos y bloqueo observado por PostgreSQL.
- Cuatro casos reales del adaptador y dos confirmaciones nativas; EICAR pendiente.
- La regresión y la actualización local preservaron las 47 tablas y 35 archivos privados de la instalación operativa. La prueba concurrente utilizó otra base y dejó intactas las 47 tablas operativas.
- No se cambiaron React ni el esquema en esta corrección. Las 22 pruebas React de la entrega anterior son antecedentes, no otra ejecución en esta fase.

Consultar evidence/integrity-transitions/. Los informes anteriores se conservan como antecedentes. No se alteraron los ocho CU, treinta RF, fórmulas DEMO ni restricciones oficiales.

## Reproducción y operación

El script scripts/verify_clamav_adapter.py ejecuta el adaptador real con un binario, directorio de firmas y directorio de firmas genuinas antiguas suministrados por el operador. Requiere PHP, Composer instalado y Python. No modifica la base de datos, no descarga motores ni cambia protecciones del sistema.

Invocación: python scripts/verify_clamav_adapter.py --binary RUTA_CLAMSCAN --database DIRECTORIO_FIRMAS --aged-database DIRECTORIO_FIRMAS_ANTIGUAS

El ensayo crea archivos y copias de firmas bajo .local/clamav-adapter-checks, excluido del repositorio. La firma adicional es de prueba y no debe copiarse a la configuración operativa. El operador debe garantizar procedencia y permisos de binario y firmas. Un directorio simplemente vacío no es una prueba de firmas vencidas: el verificador exige además el diagnóstico nativo de antigüedad.

La instalación operativa conserva su configuración anterior. El laboratorio usa rutas privadas explícitas; no configura automáticamente el servidor docente ni una implantación institucional. Los comandos manuales y límites de cuarentena siguen documentados en CUARENTENA_FUENTES_INSTITUCIONALES.md.

## Pendientes

La cola supervisada, reintentos, recuperación tras interrupción forzada y limpieza de temporales huérfanos siguen pendientes. El finally actual limpia temporales en terminación normal o excepción; no se presenta como solución ante caída del proceso. No se extendió todavía esta protección a los expedientes de postulantes.

Siguen pendientes EICAR/corpus y aceptación antimalware, carga representativa, auditoría externa, copia protegida fuera de la base, retención y aceptación de infraestructura. Las seis condiciones institucionales continúan sin aprobación. NO_GO_INSTITUCIONAL y official_enabled=false se conservan.

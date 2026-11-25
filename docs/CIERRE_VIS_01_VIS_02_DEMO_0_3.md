# Cierre de VIS-01 y VIS-02 — DEMO 0.3

Fecha: 10/11/2026. Ajuste de presentación sobre la entrega de validación local de 199 archivos. No se inicia DEMO 0.4. Los dos hallazgos están **CERRADOS en el alcance técnico comprobado**; esto no constituye autorización institucional.

## Qué cambia

**VIS-01.** `resources/views/application.blade.php` muestra «Detalle de revisión restringido» al perfil que no recibe las revisiones. Propietario, revisor y autoridad conservan la visualización de las decisiones reales; si aún no existe revisión, el propietario sigue viendo «Pendiente». No se amplían permisos ni se modifica la habilitación. El rechazo de descarga de documentos privados al evaluador continúa probado.

**VIS-02.** `resources/views/results.blade.php` muestra el total como «plaza/plazas sin cubrir» y distingue sus motivos. Usa únicamente `vacancies` y `held_vacancies`, ya calculados por el dominio. La cantidad no cubierta por insuficiencia es la diferencia entre ambos: no se cuentan dos veces las plazas retenidas. La lógica de ranking y selección no cambia. También se corrigen singular y plural en el encabezado y el aviso de renuncia.

| Ejemplo sintético verificado | Cupos | Candidatos activos en ranking | Retenidas | Insuficiencia | Total sin cubrir |
|---|---:|---:|---:|---:|---:|
| V04: renuncia, con otro candidato disponible | 1 | 1 | 1 | 0 | 1 |
| V05: insuficiencia sin renuncia | 2 | 1 | 0 | 1 | 1 |
| V06: situación mixta | 3 | 1 | 1 | 1 | 2 |
| V07: ambos motivos en plural | 4 | 0 | 2 | 2 | 4 |
| V08: todas las plazas cubiertas | 1 | 1 | 0 | 0 | 0 |

Estos son escenarios técnicos de prueba, no reglas ni cupos universitarios. Las renuncias de las regresiones se producen mediante el circuito de solicitud, decisión y nueva aprobación; no se simula su efecto cambiando directamente el contador de vacantes. Si el ranking no puede calcularse por revisiones o notas pendientes, se conserva el aviso existente.

## Validación nueva

Todas las evidencias de este ajuste están en `evidence/cierre-visual-03/`, separadas de las anteriores.

| Control | Resultado / archivo |
|---|---|
| Reproducción antes de corregir | Ocho regresiones nuevas: seis fallos esperados con las vistas anteriores y dos casos de control aprobados. `reproduccion-antes.json`. |
| PHPUnit completo tras corregir | **110 pruebas / 415 aserciones, cero errores y fallos**. `phpunit.xml`, `tests.txt`, `regresion.json`. |
| Casos anteriores | Los 102 identificadores anteriores siguen presentes y aprobados; se añaden V01..V08 a `ParticipationTest`. |
| Distribución PHPUnit | 38 selección + 31 gobernanza + 40 participación (32 anteriores y 8 visuales) + 1 adicional. |
| Recorrido HTTP real | **21 comprobaciones y 104 solicitudes**: 93 respuestas 200, nueve 403, una 419 y una 429; todas esperadas. Sesiones y CSRF activos. `recorrido-http.json`. |
| Sintaxis y estilo | 65 archivos PHP, las dos vistas compiladas a PHP y Pint aprobados. `sintaxis-y-estilo.json`. |
| Matrices y contrato | 30 filas CSV–Markdown coherentes y hash canónico preservado. `documental.json`. |
| Revisión visual nueva | Evaluador y autoridad, con capturas completas y ambos hallazgos cerrados. `visual.json`, `pantallas/`. |
| Conservación | 31 tablas y 18 archivos privados de la DEMO idénticos antes/después de la nueva batería; 69 archivos de evidencia histórica intactos. `regresion.json`, y comprobación final en `conservacion-final.json`. |

V01 comprueba que la restricción no aparezca como trabajo pendiente, que no se exponga el detalle y que la descarga privada siga rechazada. V02 y V03 conservan las decisiones reales para los perfiles autorizados y el estado verdaderamente pendiente. V04..V08 comparan además diez tablas antes/después de leer la pantalla, incluidas publicaciones, notas, documentos y permisos.

El caso anterior P18 mantiene su escenario e identificador; únicamente cambia la expectativa literal del aviso «vacante(s) retenida(s)» por su redacción singular corregida. No se elimina ninguna regresión. La diferencia de 104 aserciones respecto de las 311 anteriores corresponde a los ocho casos nuevos.

Durante el trabajo se corrigió una combinación de directivas Blade que provocó errores de compilación en ejecuciones intermedias. La entrega contiene el resultado final aprobado y la comprobación de las vistas compiladas. El verificador también emite texto UTF-8 para evitar errores de consola en Windows.

## Alcance y conservación

Los únicos cambios del comportamiento mostrado por la aplicación están en las dos vistas indicadas. No cambian controladores, servicios de dominio, rutas, permisos, cálculos, migraciones, ocho CU ni treinta RF. El contrato conserva el SHA-256 `fc7bd40b944002fc698eb56234816de4b36ce4ee11486e335673edd8f17e11e2`.

Los comprobadores admiten `--output-dir` para guardar esta ejecución aparte; PHPUnit permite que existan regresiones nuevas y exige conservar todos los identificadores anteriores. El recorrido agrega cuatro comprobaciones y tres solicitudes a las 17/101 previas, utilizando una base y archivos sintéticos nuevos en el puerto local 8104. La revisión en navegador confirma las dos pantallas finales; los casos mixtos y de insuficiencia se verifican mediante las respuestas renderizadas de PHPUnit, no se presentan como capturas manuales adicionales.

Los ZIP anteriores de 180 y 199 archivos y todas sus evidencias se conservan intactos. El nuevo archivo se denomina `SISTEMA_FICCT_GRUPO09_DEMO_0_3_CIERRE_VISUAL.zip`. Los informes anteriores mantienen sus resultados y hallazgos históricos; el presente documento registra su cierre posterior. `README.md` y `ESTADO_PROYECTO.md` enlazan el estado vigente.

## Reproducir

Con dependencias PHP y PostgreSQL local disponibles, desde `sistema/`:

```powershell
python scripts/revalidate_03.py --output-dir evidence/cierre-visual-03
python scripts/walkthrough_03.py --port 8104 --keep-server --output-dir evidence/cierre-visual-03
python scripts/verify_traceability.py --output evidence/cierre-visual-03/documental.json
```

El puerto debe estar libre antes del recorrido. Se crea una base nueva en cada ejecución y las credenciales quedan solo en `.local/`; no se incluyen en el paquete. El comprobador documental toma por defecto el ZIP corregido de 180 archivos de la carpeta superior y puede recibir otra ruta mediante `--package`. Las ejecuciones siguientes deben guardarse en una carpeta distinta si se necesita conservar también estos resultados.

## Pendientes que no se cierran

Continúa pendiente recuperar y contrastar directamente los cuatro PDF y tres fotografías originales, confirmar los procedimientos institucionales y realizar las validaciones externas de seguridad, carga e infraestructura. No se repitieron aquí las pruebas de concurrencia ni restauración; sus evidencias anteriores siguen siendo históricas. Esta es una ejecución local en el mismo equipo, no una auditoría externa ni una autorización de despliegue institucional.

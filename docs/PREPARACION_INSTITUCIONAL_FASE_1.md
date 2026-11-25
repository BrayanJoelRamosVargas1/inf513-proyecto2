# Preparación institucional FICCT · Fase 1 local

**Actualización posterior:** la descarga y revisión de fuentes requieren ahora análisis antimalware válido. [Cuarentena, operación y límites actuales](CUARENTENA_FUENTES_INSTITUCIONALES.md). Las cifras y recorridos siguientes corresponden a la primera entrega.


Fecha local: 21/11/2026. Base académica: revisión Git 72e0911faaebae9ddd1f4ff4799cb2de79ef8ef0.

## Resultado

Se añadió un circuito de preparación documental separado de las operaciones del concurso. No se habilitó producción ni se modificó el motor DEMO. La incorporación está disponible localmente en /p2/institucional para responsabilidades de autoridad.

La revisión aportada identifica correctamente los límites de Selection, Scores, los perfiles declarados por el usuario y las publicaciones DEMO. official_enabled continúa en false; activar artificialmente el indicador tampoco permite activar expedientes mediante el nuevo módulo. Los ocho CU y treinta RF canónicos se conservan byte por byte.

Esta fase no incluye verificación de identidad institucional, firma electrónica, activación oficial, importación certificada de históricos, motor normativo oficial ni procedimiento de examen extraordinario operativo.

## Funciones implementadas

Una autoridad con permiso vigente crea un expediente vinculado a una convocatoria de su ámbito y designa otra cuenta con responsabilidad vigente en ese mismo ámbito para revisión. Esa responsabilidad es un permiso de la aplicación, no una acreditación universitaria. No se permite autorrevisión. Administradores, postulantes, evaluadores y autoridades ajenas al expediente no pueden acceder.

Cada fuente conserva archivo privado, SHA-256 calculado sobre sus bytes, tipo real, tamaño, referencia, versión consecutiva, fecha declarada, páginas/artículos, clasificación y notas. PDF/JPG/PNG hasta 15 MB. Una nueva carga con la misma referencia añade otra versión; jamás reemplaza la anterior. El archivo se descarga con autorización y nueva comprobación de integridad. La huella demuestra integridad, no autenticidad ni vigencia.

Los documentos pueden clasificarse como referencia histórica o propuesta no validada. Las fechas son metadatos declarados que deben cotejarse; no abren convocatorias ni alteran plazos existentes. Las fuentes 01/2026 y 02/2026 no se importan como ofertas activas.

Al solicitar revisión se congela un snapshot que conserva todas las versiones, referencias actuales y estado de las diez condiciones. Se guarda el texto serializado exacto y su SHA-256 dentro del registro, evitando depender del orden de claves de JSONB para verificarlo. Una observación devuelve a borrador; otro envío conserva el anterior. La revisión técnica completada cierra esa preparación sin abrir un concurso. Para una preparación posterior se crea otro expediente.

Los estados de cada condición son MISSING o DOCUMENTED_UNVERIFIED. Ningún archivo ni revisión marca automáticamente G01..G10 como aprobado. La respuesta permanece NO_GO_INSTITUCIONAL, con official_activation_allowed=false, incluso cuando las diez condiciones tienen archivos.

Las mutaciones usan el cerrojo de permisos existente, bloqueo de fila y versión esperada. Una solicitud obsoleta devuelve 409. Las fuentes, envíos y revisiones tienen tres triggers adicionales que rechazan UPDATE/DELETE. Los eventos se incorporan a la auditoría existente; no constituyen una auditoría externa.

## Uso local

1. Ingresar con autoridad y abrir **Preparación institucional**.
2. Elegir una convocatoria del ámbito y una segunda autoridad vigente. Si falta esa segunda cuenta, la interfaz lo explica; debe asignarse mediante la administración existente, sin compartir credenciales ni asignarse a sí mismo como revisor.
3. Crear borrador, cargar fuentes y sus metadatos. Registrar dudas y conflictos; no inventar umbral, fórmula, redondeo o vigencia.
4. Enviar a revisión y entrar con la otra cuenta para observar o registrar revisión técnica.
5. Consultar versiones, descargas y envíos históricos. El aviso de bloqueo institucional permanece.

Las pruebas de navegador usan identidades y PDF MUESTRA en una copia aislada. No se agregaron esas autoridades sintéticas ni permisos a la base operativa para fingir acreditación.

## Verificación de esta fase

- PHPUnit contra ficct_test: 199 pruebas / 1123 aserciones, cero fallos. Se conservan las 189 pruebas anteriores y se añaden I01–I10.
- React: 21 pruebas aprobadas, incluidas tres nuevas para estado pendiente, historial congelado y falta de segunda autoridad.
- Navegador: seis comprobaciones del circuito completo, dos versiones de PDF, observación, segundo envío, revisión y bloqueo. Sin errores JavaScript; axe y ancho de 320 píxeles sin hallazgos en las pantallas examinadas.
- La regresión preservó las 41 tablas y 35 archivos privados de la instalación anterior. La migración añadió cuatro tablas y tres triggers; conservó las tablas de negocio preexistentes y sus archivos.
- Restauración independiente: 45 tablas idénticas y 12 triggers recuperados, incluidos los tres de la ampliación. No se alteró la base operativa.
- La prueba de navegador comparó las 45 tablas de la instalación operativa y dejó intactos sus datos; los cambios del recorrido quedaron solo en la copia aislada.

Evidencias en evidence/institutional-preparation/. Estos resultados son locales; no son otra ejecución en tecnoweb.org.bo. Los resultados remotos de la entrega académica anterior son antecedentes separados. No equivalen a pentest, prueba formal de carga ni aceptación institucional.

## API incorporada

| Método | Ruta bajo /api/v1 | Función |
|---|---|---|
| GET / POST | /institutional/dossiers | Expedientes del ámbito / crear borrador |
| GET | /institutional/dossiers/{id} | Detalle, condiciones, fuentes e historial |
| POST | /institutional/dossiers/{id}/sources | Añadir versión privada |
| POST | /institutional/dossiers/{id}/submission | Congelar envío a revisión |
| POST | /institutional/dossiers/{id}/review | Observar o registrar revisión técnica |
| POST | /institutional/dossiers/{id}/activation | Rechazar activación con 409 y NO_GO |
| GET | /institutional/sources/{id}/download | Descargar fuente autorizada e íntegra |

Se conservan sesión, CSRF y cabeceras de seguridad. No se incorporó autenticación pública para estas rutas ni un mecanismo de aprobación institucional.

## Referencia extraordinaria contrastada

Se consultó la [publicación oficial de UAGRM del 24/02/2026](https://uagrm.edu.bo/index.php/carreras/ficct-ir/noticias/comunicado-auxiliares-ficct_b7da9a), que anuncia repetición de Redes y Soporte Técnico el 25/02/2026 a las 16:00 para estudiantes observados/notificados. Es una referencia para diseñar otro procedimiento, separado del desempate por última plaza. No se presume su aplicabilidad a otros concursos ni se reabre ese examen histórico.

## Pendientes que mantienen el bloqueo

G01 autorización escrita; G02 vigencia normativa; G03 catálogos/plazos oficiales; G04 identidad y elegibilidad; G05 rúbricas, umbral y redondeo; G06 comisión y actos acreditados; G07 repetición por observaciones; G08 privacidad y retención; G09 seguridad independiente, carga y contingencia; G10 infraestructura y responsables habilitados.

El listado deriva del dictamen aportado y sirve para seguimiento técnico; no es normativa universitaria. La repetición extraordinaria sigue pendiente de desarrollo. No se inventaron reglas para cerrarla. También siguen pendientes integración/cotejo oficial CPD, firma y resolución válidas, importación certificada, logs fuera de la base, análisis antimalware de documentos, carga representativa y autorización de operación.

No se desplegó esta ampliación al servidor docente ni se reemplazó el paquete académico anterior. Para instalar en una copia nueva: seguir la guía base, instalar dependencias bloqueadas, configurar base exclusiva, respaldar y ejecutar php artisan migrate --force; compilar React con la base apropiada. Nunca migrate:fresh sobre datos que deban conservarse. La política de privacidad deberá abarcar estas fuentes; por ahora no hay borrado automático.

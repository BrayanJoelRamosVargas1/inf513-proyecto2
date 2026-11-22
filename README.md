# INF513-SA · Proyecto 2 · Grupo 09

**Corrección local de integridad en transiciones:** 213 pruebas Laravel / 1223 aserciones. Envío y conclusión de revisión verifican bytes y disponibilidad. ClamAV real: cuatro casos de alcance limitado; EICAR pendiente. [Evidencias y límites](docs/INTEGRIDAD_EN_TRANSICIONES_Y_CLAMAV.md). Las cifras siguientes corresponden a versiones anteriores.


**Refuerzo local de cuarentena institucional:** 209 pruebas Laravel / 1184 aserciones, 22 pruebas React y seis comprobaciones de navegador. El analizador real no está instalado/configurado: las fuentes quedan bloqueadas hasta disponer de un análisis válido. [Operación y límites](docs/CUARENTENA_FUENTES_INSTITUCIONALES.md). Las cifras de versiones anteriores son antecedentes.


**Ampliación local: preparación institucional, fase 1.** [Uso, arquitectura y pendientes](docs/PREPARACION_INSTITUCIONAL_FASE_1.md). 199 pruebas Laravel / 1123 aserciones, 21 pruebas React y seis comprobaciones de navegador. Sin activación oficial ni despliegue de esta ampliación. Las cifras siguientes describen la entrega académica anterior.

Sistema académico de selección de auxiliares FICCT: React, API Laravel y PostgreSQL.

[Aplicación publicada](https://www.tecnoweb.org.bo/inf513/grupo09sa/proyecto2/p2) · [Arquitectura e instalación](docs/INF513_P2_IMPLEMENTACION.md) · [Guía de defensa](docs/GUIA_DEFENSA_CAMBIOS_EN_VIVO.md) · [Respaldo e incidentes](docs/RUNBOOK_BD_INCIDENTES.md) · [Inventario de rutas API](docs/INF513_P2_OPENAPI.json).

Verificación actual: 189 pruebas Laravel / 1026 aserciones, 18 pruebas React y recorrido remoto principal de 22 comprobaciones. Se conserva el contrato de ocho CU y treinta RF. Los registros históricos son antecedentes y no se suman a la ejecución actual. Consultar evidence/current-validation.json y evidence/inf513-p2.

**DEMO académica con datos sintéticos; validación institucional pendiente.** Pagos ficticios y decisiones DEMO identificadas. Las credenciales, configuración privada, documentos de postulantes y dependencias instaladas están excluidos del repositorio y de los paquetes.

Instalar Composer y npm desde los archivos de bloqueo, configurar una base/esquema exclusivo mediante .env.example, generar clave solo en primera instalación, migrar sin borrar datos y compilar con npm run build. Para la subruta docente usar VITE_ASSET_BASE=/inf513/grupo09sa/proyecto2/build/. Servir únicamente public y mantener APP_DEBUG=false. El seeder de una base DEMO nueva guarda accesos aleatorios en .local/ACCESOS_DEMO.txt; mantenerlo privado. Leer la guía completa antes de actualizar una instalación existente.

<?php

return [
    // 'institutional' = instalación real (convocatorias OFICIAL); 'training' = entrenamiento con datos sintéticos (DEMO).
    'experience' => env('FICCT_EXPERIENCE', 'institutional'),
    // Archivo (relativo al proyecto) donde DemoSeeder guarda las credenciales ficticias generadas.
    'access_file' => env('FICCT_ACCESS_FILE', '.local/ACCESOS_DEMO.txt'),
    // Nota final mínima de aprobación que se propone al crear una oferta (0-100). Vacío = sin propuesta.
    // Cada oferta la puede ajustar antes de publicar la convocatoria.
    'approval_threshold_default' => env('FICCT_NOTA_MINIMA', 51) === '' ? null : (float) env('FICCT_NOTA_MINIMA', 51),
    // Retención de la telemetría de navegación; no corresponde a expedientes ni a la auditoría académica.
    'telemetry_retention_days' => max(1, min(365, (int) env('PORTAL_TELEMETRY_RETENTION_DAYS', 90))),
];

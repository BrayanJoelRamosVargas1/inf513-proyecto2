<?php

return [
    'experience' => env('FICCT_EXPERIENCE', 'training'),
    // Archivo (relativo al proyecto) donde DemoSeeder guarda las credenciales ficticias generadas.
    'access_file' => env('FICCT_ACCESS_FILE', '.local/ACCESOS_DEMO.txt'),
    // Política técnica DEMO; no corresponde a expedientes ni auditoría académica.
    'telemetry_retention_days' => max(1, min(365, (int) env('PORTAL_TELEMETRY_RETENTION_DAYS', 90))),
];

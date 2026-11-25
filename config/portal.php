<?php

return [
    'experience' => env('FICCT_EXPERIENCE', 'training'),
    // Política técnica DEMO; no corresponde a expedientes ni auditoría académica.
    'telemetry_retention_days' => max(1, min(365, (int) env('PORTAL_TELEMETRY_RETENTION_DAYS', 90))),
];

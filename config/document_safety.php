<?php

return [
    // Technical policy, subject to institutional acceptance. No web request can choose a scanner.
    'binary' => env('FICCT_CLAMSCAN_BINARY', ''),
    'database' => env('FICCT_CLAMSCAN_DATABASE', ''),
    'max_database_age_days' => 2,
    'valid_hours' => 24,
    'timeout_seconds' => 60,
];

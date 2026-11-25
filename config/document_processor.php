<?php

return [
    // Operational policy, not university regulations. No automatic web approval.
    'max_attempts' => 3,
    'lease_seconds' => 120,
    'retry_seconds' => 30,
    'orphan_seconds' => 300,
    'cleanup_seconds' => 3600,
    'backlog_seconds' => 300,
];

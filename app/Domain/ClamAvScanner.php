<?php

namespace App\Domain;

use Illuminate\Support\Facades\Process;

final class ClamAvScanner implements DocumentScanner
{
    public function scan(string $path): array
    {
        $binary = config('document_safety.binary');
        $database = config('document_safety.database');
        if (! is_string($binary) || $binary === '' || ! is_string($database) || $database === '') {
            return ['state' => 'error', 'engine' => 'unavailable', 'reason' => 'scanner_not_configured'];
        }
        try {
            $version = Process::timeout(10)->run([$binary, '--version']);
            $engine = trim($version->output());
            if (! $version->successful() || ! preg_match('~^ClamAV [^\r\n]{1,180}$~D', $engine)) {
                return ['state' => 'error', 'engine' => 'unavailable', 'reason' => 'scanner_version_unavailable'];
            }
            $result = Process::timeout(config('document_safety.timeout_seconds'))->run([
                $binary, '--stdout', '--no-summary', '--database='.$database,
                '--fail-if-cvd-older-than='.config('document_safety.max_database_age_days'),
                '--alert-exceeds-max=yes', '--alert-encrypted=yes',
                '--max-filesize=16M', '--max-scansize=64M', '--max-files=1000',
                '--follow-file-symlinks=0', '--follow-dir-symlinks=0', '--', $path,
            ]);
            // Limits, encryption and detections all block access; never delete the original file.
            if ($result->exitCode() === 1) {
                return ['state' => 'blocked', 'engine' => $engine, 'reason' => 'detection_or_unscannable_content'];
            }
            if ($result->successful() && str_contains($result->output(), $path.': OK')) {
                return ['state' => 'clean', 'engine' => $engine, 'reason' => 'no_detection'];
            }

            return ['state' => 'error', 'engine' => $engine, 'reason' => 'scan_incomplete'];
        } catch (\Throwable) {
            // Do not return scanner output or exception paths to a browser or the public log.
            return ['state' => 'error', 'engine' => 'unavailable', 'reason' => 'scanner_failed_or_timed_out'];
        }
    }
}

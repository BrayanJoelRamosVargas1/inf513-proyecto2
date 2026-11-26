<?php

namespace App\Domain;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Espacio temporal de análisis: detección de uso concurrente y borrado seguro de cargas terminadas. */
final class ScanWorkspace
{
    public function busy(int $job): bool
    {
        $path = Storage::disk('local')->path('institutional-scan-work/job-'.$job.'/.lock');
        if (! is_file($path)) {
            return false;
        }
        if (is_link($path)) {
            return true;
        }
        $handle = @fopen($path, 'r+b');
        if (! is_resource($handle)) {
            return true;
        }
        $free = flock($handle, LOCK_EX | LOCK_NB);
        if ($free) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);

        return ! $free;
    }

    public function cleanup(bool $apply = false): array
    {
        $base = Storage::disk('local')->path('institutional-scan-work');
        $eligible = $removed = $skipped = $legacy = 0;
        if (! is_dir($base)) {
            return compact('eligible', 'removed', 'skipped', 'legacy');
        }
        $root = realpath($base);
        if (is_link($base) || dirname($root) !== realpath(Storage::disk('local')->path(''))) {
            $skipped++;

            return compact('eligible', 'removed', 'skipped', 'legacy');
        }
        foreach (new \DirectoryIterator($base) as $item) {
            if ($item->isDot()) {
                continue;
            }
            if (! preg_match('/^job-([1-9][0-9]*)$/D', $item->getFilename(), $m) || $item->isLink() || ! $item->isDir()) {
                $legacy++;

                continue;
            }
            $directory = $item->getPathname();
            if (dirname(realpath($directory)) !== $root) {
                $skipped++;

                continue;
            }
            $job = DB::table('institutional_scan_jobs')->find((int) $m[1]);
            $ended = $job && DB::table('institutional_scan_results')->where('job_id', $job->id)->exists();
            $active = $job && DB::table('institutional_scan_tasks')->where('job_id', $job->id)->where('state', 'leased')->where('lease_until', '>', now())->exists();
            if (! $job || ! $ended || $active || CarbonImmutable::parse($job->created_at)->gt(now()->subSeconds(config('document_processor.cleanup_seconds')))) {
                $skipped++;

                continue;
            }
            $lock = $directory.'/.lock';
            $payload = $directory.'/payload.bin';
            if (! file_exists($payload)) {
                continue; // Keep the stable zero-byte lock marker; no payload to erase.
            }
            if (! is_file($payload) || dirname(realpath($payload)) !== realpath($directory) || is_link($lock) || is_link($payload) || array_diff(scandir($directory), ['.', '..', '.lock', 'payload.bin'])) {
                $skipped++;

                continue;
            }
            // A dry run must not create even a missing marker.
            $handle = @fopen($lock, $apply ? 'c+b' : 'r+b');
            if (! is_resource($handle) || ! flock($handle, LOCK_EX | LOCK_NB)) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
                $skipped++;

                continue;
            }
            try {
                $eligible++;
                if ($apply) {
                    if (is_file($payload) && ! @unlink($payload)) {
                        $skipped++;

                        continue;
                    }
                    // Keep the locked marker until after the payload is gone.
                    ScanEvents::record(null, $job->source_id, $job->id, 'temporary_payload_cleaned', [], $job->document_id);
                    $removed++;
                }
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
            // Never unlink a lock inode another process could be about to acquire.
            // The empty marker and directory are retained; only document bytes are removed.
        }

        return compact('eligible', 'removed', 'skipped', 'legacy');
    }
}

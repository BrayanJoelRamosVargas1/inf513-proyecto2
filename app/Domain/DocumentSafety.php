<?php

namespace App\Domain;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class DocumentSafety
{
    public static function policyHash(): string
    {
        return hash('sha256', json_encode(['adapter' => 'clamav-v1', 'config' => config('document_safety')], JSON_THROW_ON_ERROR));
    }

    public function status(int $sourceId): array
    {
        // Select the newest started job, not the latest completion. A slow older scan
        // must never release a file after a newer scan has blocked or quarantined it.
        $job = DB::table('institutional_scan_jobs')->where('source_id', $sourceId)->orderByDesc('id')->first();
        $result = $job ? DB::table('institutional_scan_results')->where('job_id', $job->id)->first() : null;
        $state = $result?->state ?? 'pending';
        if ($job && $job->policy_hash !== self::policyHash()) {
            $state = 'pending';
        }
        if ($state === 'clean' && CarbonImmutable::parse($result->finished_at)->addHours(config('document_safety.valid_hours'))->lte(now())) {
            $state = 'expired';
        }

        return ['state' => $state, 'download_allowed' => $state === 'clean', 'job_id' => $job?->id, 'finished_at' => $result?->finished_at];
    }

    public function bytes(object $source): string
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($source->path), 409, 'La fuente no está disponible para comprobar su integridad.');
        $stream = $disk->readStream($source->path);
        try {
            $bytes = stream_get_contents($stream, 15728641);
        } finally {
            fclose($stream);
        }
        abort_unless(is_string($bytes) && strlen($bytes) <= 15728640 && hash('sha256', $bytes) === $source->sha256, 409, 'La fuente no coincide con su huella registrada.');

        return $bytes;
    }

    public function scan(int $sourceId): array
    {
        $source = Selection::row('institutional_sources', $sourceId);
        $job = DB::table('institutional_scan_jobs')->insertGetId(['source_id' => $sourceId, 'sha256' => $source->sha256, 'policy_hash' => self::policyHash(), 'created_at' => now()]);
        $temp = null;
        try {
            $bytes = $this->bytes($source);
            $directory = Storage::disk('local')->path('institutional-scan-work');
            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new \RuntimeException('scan_workspace_unavailable');
            }
            $temp = tempnam($directory, 'scan-');
            if ($temp === false || file_put_contents($temp, $bytes) !== strlen($bytes)) {
                throw new \RuntimeException('scan_snapshot_unavailable');
            }
            chmod($temp, 0600);
            $result = app(DocumentScanner::class)->scan($temp);
            if (! in_array($result['state'] ?? '', ['clean', 'blocked', 'error'], true) || empty($result['engine']) || empty($result['reason'])) {
                throw new \RuntimeException('invalid_scanner_response');
            }
            // Re-check original integrity too; never release bytes changed during scanning.
            $this->bytes($source);
        } catch (\Throwable) {
            $result = ['state' => 'error', 'engine' => 'unavailable', 'reason' => 'integrity_or_scanner_failure'];
        } finally {
            if (is_string($temp) && is_file($temp)) {
                unlink($temp);
            }
        }
        DB::table('institutional_scan_results')->insert(['job_id' => $job, 'state' => $result['state'], 'engine' => $result['engine'], 'reason' => $result['reason'], 'finished_at' => now()]);

        return ['source_id' => $sourceId, 'job_id' => $job, 'state' => $result['state'], 'reason' => $result['reason']];
    }
}

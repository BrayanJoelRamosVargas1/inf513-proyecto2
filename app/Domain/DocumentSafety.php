<?php

namespace App\Domain;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;

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
        $stream = null;
        try {
            abort_unless($disk->exists($source->path), 409, 'La fuente no está disponible para comprobar su integridad.');
            $stream = $disk->readStream($source->path);
            abort_unless(is_resource($stream), 409, 'La fuente no está disponible para comprobar su integridad.');
            $bytes = stream_get_contents($stream, 15728641);
        } catch (FilesystemException) {
            abort(409, 'La fuente no está disponible para comprobar su integridad.');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        abort_unless(is_string($bytes) && strlen($bytes) <= 15728640 && hash('sha256', $bytes) === $source->sha256, 409, 'La fuente no coincide con su huella registrada.');

        return $bytes;
    }

    /**
     * Caller holds the dossier and permission locks in its transaction.
     * Scan starts take the same source lock; results remain append-only.
     */
    public function assertCurrentSources(int $dossierId): array
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Source verification requires the decision transaction.');
        }
        $sources = DB::table('institutional_sources')->where('dossier_id', $dossierId)->orderBy('id')->lockForUpdate()->get();
        $checks = [];
        foreach ($sources as $source) {
            $before = $this->status($source->id);
            abort_unless($before['download_allowed'], 422, 'El análisis de alguna fuente falta, venció o bloqueó el archivo.');
            $bytes = $this->bytes($source);
            $after = $this->status($source->id);
            abort_unless($after['download_allowed'] && $before['job_id'] === $after['job_id'], 422, 'El análisis cambió durante la comprobación. Reintenta la operación.');
            $checks[] = ['source_id' => $source->id, 'source_version' => $source->version, 'sha256' => hash('sha256', $bytes), 'scan_job_id' => $after['job_id'], 'checked_at' => now()->toIso8601String()];
        }

        return $checks;
    }

    public function scan(int $sourceId): array
    {
        [$source, $job] = DB::transaction(function () use ($sourceId) {
            $source = Selection::row('institutional_sources', $sourceId, true);
            $job = DB::table('institutional_scan_jobs')->insertGetId(['source_id' => $sourceId, 'sha256' => $source->sha256, 'policy_hash' => self::policyHash(), 'created_at' => now()]);

            return [$source, $job];
        });
        $result = $this->analyse($job);
        DB::table('institutional_scan_results')->insertOrIgnore(['job_id' => $job, 'state' => $result['state'], 'engine' => $result['engine'], 'reason' => $result['reason'], 'finished_at' => now()]);

        $persisted = DB::table('institutional_scan_results')->where('job_id', $job)->first();

        return ['source_id' => $sourceId, 'job_id' => $job, 'state' => $persisted->state, 'reason' => $persisted->reason];
    }

    /** Run I/O only; the processor fences completion with its persisted lease. */
    public function analyse(int $job): array
    {
        $record = Selection::row('institutional_scan_jobs', $job);
        $source = Selection::row('institutional_sources', $record->source_id);
        if ($record->policy_hash !== self::policyHash() || $record->sha256 !== $source->sha256) {
            return ['state' => 'error', 'engine' => 'unavailable', 'reason' => 'policy_or_source_changed'];
        }
        $temp = null;
        $guard = null;
        try {
            $bytes = $this->bytes($source);
            $directory = Storage::disk('local')->path('institutional-scan-work/job-'.$job);
            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new \RuntimeException('scan_workspace_unavailable');
            }
            $base = Storage::disk('local')->path('institutional-scan-work');
            if (is_link($base) || is_link($directory) || dirname(realpath($base)) !== realpath(Storage::disk('local')->path(''))
                || dirname(realpath($directory)) !== realpath($base)
                || is_link($directory.'/.lock') || is_link($directory.'/payload.bin')) {
                throw new \RuntimeException('scan_workspace_unsafe');
            }
            $guard = fopen($directory.'/.lock', 'c+b');
            if (! is_resource($guard) || ! flock($guard, LOCK_EX | LOCK_NB)) {
                throw new \RuntimeException('scan_workspace_busy');
            }
            $temp = $directory.'/payload.bin';
            if (file_put_contents($temp, $bytes) !== strlen($bytes)) {
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
                @unlink($temp);
            }
            if (is_resource($guard)) {
                flock($guard, LOCK_UN);
                fclose($guard);
            }
        }

        return $result;
    }
}

<?php

namespace App\Domain;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DocumentProcessor
{
    private function event(?object $task, ?int $source, ?int $job, string $action, array $details = [], ?int $document = null): void
    {
        ScanEvents::record($task, $source, $job, $action, $details, $document);
    }

    private function newJob(object $source, ?int $document = null): int
    {
        return DB::table('institutional_scan_jobs')->insertGetId([
            'source_id' => $document === null ? $source->id : null, 'document_id' => $document, 'sha256' => $source->sha256,
            'policy_hash' => DocumentSafety::policyHash(), 'created_at' => now(),
        ]);
    }

    public function enqueue(int $sourceId, string $kind = 'institutional'): int
    {
        return DB::transaction(function () use ($sourceId, $kind) {
            $target = new DocumentTarget($sourceId, $kind);
            $source = $target->row(true);
            $active = DB::table('institutional_scan_tasks')->where($target->column(), $sourceId)->whereIn('state', ['queued', 'leased'])->lockForUpdate()->first();
            if ($active) {
                return $active->id;
            }
            $job = $this->newJob($source, $kind === 'applicant' ? $sourceId : null);
            $id = DB::table('institutional_scan_tasks')->insertGetId([
                ...$target->fields(), 'job_id' => $job, 'state' => 'queued',
                'max_attempts' => max(1, min(10, (int) config('document_processor.max_attempts'))),
                'available_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $task = Selection::row('institutional_scan_tasks', $id);
            $this->event($task, $task->source_id, $job, 'queued');

            return $id;
        });
    }

    public function claim(?int $id = null): ?object
    {
        $seconds = (int) config('document_processor.lease_seconds');
        if ($seconds <= (int) config('document_safety.timeout_seconds') + 10) {
            throw new \LogicException('Worker lease must exceed scanner and version timeouts.');
        }
        $candidates = DB::table('institutional_scan_tasks')->where('state', 'queued')->where('available_at', '<=', now())
            ->when($id, fn ($q) => $q->where('id', $id))->orderBy('id')->limit(50)->get(['id', 'source_id', 'document_id']);
        foreach ($candidates as $candidate) {
            $claimed = DB::transaction(function () use ($candidate, $seconds) {
                // Same order as decisions/enqueue/recovery: source, then task.
                $source = DocumentTarget::fromRow($candidate)->skipLocked();
                if (! $source) {
                    return null;
                }
                $task = DB::table('institutional_scan_tasks')->where('id', $candidate->id)->lockForUpdate()->first();
                if ($task->state !== 'queued' || CarbonImmutable::parse($task->available_at)->gt(now())) {
                    return null;
                }
                $latest = DocumentTarget::fromRow($task)->jobs()->max('id');
                if ($task->attempts > 0 || (int) $latest !== (int) $task->job_id) {
                    $this->closeInterrupted($task->job_id, 'superseded_or_retry');
                    $task->job_id = $this->newJob($source, $task->document_id);
                }
                $task->attempts++;
                $task->lease_token = (string) Str::uuid();
                $task->lease_until = now()->addSeconds($seconds)->toIso8601String();
                DB::table('institutional_scan_tasks')->where('id', $task->id)->update([
                    'state' => 'leased', 'job_id' => $task->job_id, 'attempts' => $task->attempts,
                    'lease_token' => $task->lease_token, 'lease_until' => $task->lease_until, 'updated_at' => now(),
                ]);
                $task->state = 'leased';
                $this->event($task, $task->source_id, $task->job_id, 'claimed', ['lease_until' => $task->lease_until]);

                return $task;
            });
            if ($claimed) {
                return $claimed;
            }
        }

        return null;
    }

    private function closeInterrupted(int $job, string $reason): void
    {
        DB::table('institutional_scan_results')->insertOrIgnore([
            'job_id' => $job, 'state' => 'error', 'engine' => 'processor',
            'reason' => $reason, 'finished_at' => now(),
        ]);
    }

    public function finish(object $lease, array $result): array
    {
        return DB::transaction(function () use ($lease, $result) {
            DocumentTarget::fromRow($lease)->row(true);
            $task = Selection::row('institutional_scan_tasks', $lease->id, true);
            if ($task->state !== 'leased' || $task->lease_token !== $lease->lease_token || CarbonImmutable::parse($task->lease_until)->lte(now())) {
                $this->event($lease, $task->source_id, $lease->job_id, 'late_completion_ignored', ['reported_state' => $result['state']]);

                return ['task_id' => $task->id, 'state' => 'ignored', 'reason' => 'lease_lost'];
            }
            DB::table('institutional_scan_results')->insert([
                'job_id' => $task->job_id, 'state' => $result['state'], 'engine' => $result['engine'],
                'reason' => $result['reason'], 'finished_at' => now(),
            ]);
            $retry = $result['state'] === 'error' && $task->attempts < $task->max_attempts;
            $state = $retry ? 'queued' : ($result['state'] === 'error' ? 'failed' : 'completed');
            $available = now()->addSeconds($this->backoff($task->attempts));
            DB::table('institutional_scan_tasks')->where('id', $task->id)->update([
                'state' => $state, 'available_at' => $available, 'lease_token' => null, 'lease_until' => null, 'updated_at' => now(),
            ]);
            $this->event($task, $task->source_id, $task->job_id, $retry ? 'retry_scheduled' : 'finished',
                [
                    'result' => $result['state'],
                    'reason' => $result['reason'],
                    'task_state' => $state,
                    'available_at' => $available->toIso8601String(),
                ]);

            return [
                'task_id' => $task->id,
                ...DocumentTarget::fromRow($task)->fields(),
                'job_id' => $task->job_id,
                'state' => $result['state'],
                'task_state' => $state,
                'reason' => $result['reason'],
            ];
        });
    }

    private function backoff(int $attempt): int
    {
        return min(300, max(1, (int) config('document_processor.retry_seconds')) * (2 ** max(0, $attempt - 1)));
    }

    public function runOne(?int $id = null): array
    {
        $lease = $this->claim($id);
        if (! $lease) {
            return ['state' => 'idle'];
        }
        $result = app(DocumentSafety::class)->analyse($lease->job_id);

        return $this->finish($lease, $result);
    }

    public function recover(bool $apply = false): array
    {
        $expired = DB::table('institutional_scan_tasks')->where('state', 'leased')->where('lease_until', '<=', now())->orderBy('id')->limit(100)->get();
        $recovered = 0;
        foreach ($expired as $row) {
            if (! $apply) {
                continue;
            }
            DB::transaction(function () use ($row, &$recovered) {
                $target = DocumentTarget::fromRow($row);
                $target->row(true);
                $task = Selection::row('institutional_scan_tasks', $row->id, true);
                if ($task->state !== 'leased' || CarbonImmutable::parse($task->lease_until)->gt(now())) {
                    return;
                }
                $this->closeInterrupted($task->job_id, 'worker_abandoned');
                $retry = $task->attempts < $task->max_attempts;
                DB::table('institutional_scan_tasks')->where('id', $task->id)->update([
                    'state' => $retry ? 'queued' : 'failed', 'lease_token' => null, 'lease_until' => null,
                    'available_at' => now()->addSeconds($this->backoff($task->attempts)), 'updated_at' => now(),
                ]);
                $this->event($task, $task->source_id, $task->job_id, 'abandoned_recovered', ['retry' => $retry]);
                $recovered++;
            });
        }

        return ['expired_leases' => $expired->count(), 'recovered' => $recovered, 'apply' => $apply];
    }

    /** Also recover attempts started by the legacy one-shot API, without deleting evidence. */
    public function recoverUnmanaged(bool $apply = false): array
    {
        $old = DB::table('institutional_scan_jobs as j')
            ->where('j.created_at', '<=', now()->subSeconds(config('document_processor.orphan_seconds')))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('institutional_scan_results as r')->whereColumn('r.job_id', 'j.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('institutional_scan_events as e')->whereColumn('e.job_id', 'j.id'))
            ->orderBy('j.id')->limit(100)->get(['j.id', 'j.source_id', 'j.document_id']);
        $count = $superseded = 0;
        foreach ($old as $row) {
            if (! $apply || app(ScanWorkspace::class)->busy($row->id)) {
                continue;
            }
            DB::transaction(function () use ($row, &$count, &$superseded) {
                $target = DocumentTarget::fromRow($row);
                $target->row(true);
                if (DB::table('institutional_scan_results')->where('job_id', $row->id)->exists() || app(ScanWorkspace::class)->busy($row->id)) {
                    return;
                }
                // Scan starts take this same source lock. Never invalidate a
                // newer scan when closing an obsolete legacy attempt.
                $latest = $target->jobs()->max('id');
                if ((int) $latest !== (int) $row->id) {
                    $this->closeInterrupted($row->id, 'unmanaged_attempt_superseded');
                    $this->event(null, $row->source_id, $row->id, 'unmanaged_superseded', ['superseded_by_job_id' => $latest], $row->document_id);
                    $superseded++;

                    return;
                }
                $this->closeInterrupted($row->id, 'unmanaged_attempt_abandoned');
                $id = $this->enqueue($target->id, $target->kind);
                $this->event(Selection::row('institutional_scan_tasks', $id), $row->source_id, $row->id, 'unmanaged_recovered', [], $row->document_id);
                $count++;
            });
        }

        return ['unmanaged_pending' => $old->count(), 'recovered' => $count, 'superseded' => $superseded, 'apply' => $apply];
    }

    /** Read-only operational diagnosis; never authorizes document access. */
    public function queueStatus(): array
    {
        return app(ScanDiagnostics::class)->queueStatus();
    }

    public function cleanup(bool $apply = false): array
    {
        return app(ScanWorkspace::class)->cleanup($apply);
    }
}

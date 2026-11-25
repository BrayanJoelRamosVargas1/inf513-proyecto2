<?php

namespace App\Domain;

use Illuminate\Support\Facades\DB;

/** A target of the shared scanner, never a grant of file access. */
final class DocumentTarget
{
    public function __construct(public readonly int $id, public readonly string $kind = 'institutional')
    {
        if ($id < 1 || ! in_array($kind, ['institutional', 'applicant'], true)) {
            throw new \InvalidArgumentException('Invalid document target.');
        }
    }

    public static function fromRow(object $row): self
    {
        return isset($row->document_id) ? new self($row->document_id, 'applicant') : new self($row->source_id);
    }

    public function column(): string
    {
        return $this->kind === 'applicant' ? 'document_id' : 'source_id';
    }

    public function table(): string
    {
        return $this->kind === 'applicant' ? 'documents' : 'institutional_sources';
    }

    public function fields(): array
    {
        return [$this->column() => $this->id];
    }

    public function row(bool $lock = false): object
    {
        return Selection::row($this->table(), $this->id, $lock);
    }

    public function jobs()
    {
        return DB::table('institutional_scan_jobs')->where($this->column(), $this->id);
    }

    public function skipLocked(): ?object
    {
        return DB::table($this->table())->where('id', $this->id)->lock('FOR UPDATE SKIP LOCKED')->first();
    }
}

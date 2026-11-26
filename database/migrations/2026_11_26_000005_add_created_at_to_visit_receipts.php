<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Los recibos de visitas no tenían fecha: sin ella no se podían purgar y crecían sin límite. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('visit_receipts', 'created_at')) {
            DB::statement('ALTER TABLE visit_receipts ADD COLUMN created_at timestamptz NOT NULL DEFAULT now()');
            DB::statement('CREATE INDEX IF NOT EXISTS visit_receipts_created_at_index ON visit_receipts (created_at)');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS visit_receipts_created_at_index');
        DB::statement('ALTER TABLE visit_receipts DROP COLUMN IF EXISTS created_at');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unknown historical seats are not zero vacancies or an invented seat.
        Schema::table('offers', fn (Blueprint $t) => $t->unsignedInteger('seats')->nullable()->change());
        Schema::table('offers', fn (Blueprint $t) => $t->string('requirement_profile', 20)->nullable());
        DB::unprepared("CREATE OR REPLACE FUNCTION documentary_seats_guard() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF NEW.seats IS NULL AND NOT (NEW.is_subject AND NEW.scheme = 'PENDING_COMPOSITION' AND EXISTS (SELECT 1 FROM calls WHERE id=NEW.call_id AND mode='HISTORICAL' AND status='archived')) THEN RAISE EXCEPTION 'Unknown seats allowed only for archived documentary subjects'; END IF; RETURN NEW; END; \$\$");
        DB::unprepared('CREATE TRIGGER documentary_seats_guard BEFORE INSERT OR UPDATE ON offers FOR EACH ROW EXECUTE FUNCTION documentary_seats_guard()');
        Schema::create('institutional_subjects', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('original_name');
        });
        Schema::create('institutional_call_profiles', function (Blueprint $t) {
            $t->foreignId('call_id')->primary()->constrained('calls');
            $t->string('source_key', 40)->unique();
            $t->string('source_sha256', 64);
            $t->jsonb('profile');
            $t->timestampTz('imported_at');
        });
        Schema::create('institutional_subject_offers', function (Blueprint $t) {
            $t->foreignId('offer_id')->primary()->constrained('offers');
            $t->foreignId('subject_id')->constrained('institutional_subjects');
            $t->foreignId('call_id')->constrained('calls');
            $t->unsignedInteger('printed_number');
            $t->unsignedInteger('source_page');
            $t->string('source_group');
            $t->unique(['call_id', 'subject_id']);
        });
        Schema::create('institutional_register', function (Blueprint $t) {
            $t->id();
            $t->foreignId('call_id')->constrained('calls');
            $t->string('kind', 30);
            $t->string('reference', 100);
            $t->string('record_scope', 40)->default('call');
            $t->unsignedInteger('version');
            $t->jsonb('payload');
            $t->foreignId('source_id')->nullable()->constrained('institutional_sources');
            $t->foreignId('recorded_by')->constrained('users');
            $t->timestampTz('created_at');
            $t->unique(['call_id', 'kind', 'record_scope', 'reference', 'version'], 'institutional_register_version_unique');
        });
        DB::unprepared('CREATE TRIGGER institutional_register_immutable BEFORE UPDATE OR DELETE ON institutional_register FOR EACH ROW EXECUTE FUNCTION institutional_record_immutable()');
    }

    public function down(): void
    {
        // An installed catalogue must be restored from backup, never silently discarded.
        foreach (['institutional_register', 'institutional_subject_offers', 'institutional_call_profiles', 'institutional_subjects'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('El catálogo contiene evidencia. Restaure el respaldo; no se permite rollback destructivo.');
            }
        }
        foreach (['institutional_register', 'institutional_subject_offers', 'institutional_call_profiles', 'institutional_subjects'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::unprepared('DROP TRIGGER documentary_seats_guard ON offers; DROP FUNCTION documentary_seats_guard()');
        Schema::table('offers', fn (Blueprint $t) => $t->unsignedInteger('seats')->nullable(false)->change());
        Schema::table('offers', fn (Blueprint $t) => $t->dropColumn('requirement_profile'));
    }
};

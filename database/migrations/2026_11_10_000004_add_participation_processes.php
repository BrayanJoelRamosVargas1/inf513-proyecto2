<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $t) {
            $t->string('participation')->default('active');
            $t->integer('dossier_version')->default(1);
        });
        Schema::table('offers', fn (Blueprint $t) => $t->integer('held_vacancies')->default(0));
        Schema::create('amendments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained();
            $t->integer('base_version');
            $t->json('allowed_kinds');
            $t->timestampTz('deadline');
            $t->string('status')->default('open');
            $t->foreignId('opened_by')->constrained('users');
            $t->text('reason');
            $t->text('evidence');
            $t->timestampTz('submitted_at')->nullable();
            $t->timestampsTz();
        });
        Schema::table('documents', fn (Blueprint $t) => $t->foreignId('amendment_id')->nullable()->constrained());
        Schema::create('dossier_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained();
            $t->integer('version');
            $t->string('phase');
            $t->json('snapshot');
            $t->foreignId('user_id')->nullable()->constrained();
            $t->timestampTz('created_at');
            $t->unique(['application_id', 'version', 'phase']);
        });
        foreach (DB::table('applications')->where('status', 'submitted')->get() as $a) {
            $docs = DB::table('documents')->where('application_id', $a->id)->orderByDesc('version')->get()->unique('kind')->values()->map(fn ($d) => ['id' => $d->id, 'kind' => $d->kind, 'version' => $d->version, 'sha256' => $d->sha256])->all();
            DB::table('dossier_snapshots')->insert(['application_id' => $a->id, 'version' => 1, 'phase' => 'baseline_at_migration', 'snapshot' => json_encode(['documents' => $docs, 'reviews' => DB::table('reviews')->where('application_id', $a->id)->get()->all(), 'eligibility' => $a->eligibility, 'note' => 'Estado conocido al migrar; no se infiere la fecha original de envío.']), 'created_at' => now()]);
        }
        Schema::create('participation_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained();
            $t->string('kind');
            $t->foreignId('requester_id')->constrained('users');
            $t->text('reason');
            $t->text('evidence');
            $t->string('status')->default('pending');
            $t->timestampsTz();
        });
        Schema::create('participation_decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('participation_request_id')->unique()->constrained();
            $t->foreignId('user_id')->constrained();
            $t->boolean('approved');
            $t->text('reason');
            $t->timestampTz('created_at');
        });
        Schema::create('vacancy_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('offer_id')->constrained();
            $t->foreignId('application_id')->constrained();
            $t->foreignId('participation_request_id')->unique()->constrained();
            $t->foreignId('publication_id')->constrained();
            $t->text('reason');
            $t->timestampTz('created_at');
        });
        Schema::create('award_choices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained();
            $t->foreignId('publication_id')->constrained();
            $t->integer('version');
            $t->foreignId('user_id')->constrained();
            $t->string('choice');
            $t->text('reason');
            $t->text('evidence');
            $t->string('basis')->default('DEMO_NO_COMPATIBILITY_RULE');
            $t->timestampTz('created_at');
            $t->unique(['application_id', 'publication_id', 'version']);
        });
        Schema::table('result_revisions', function (Blueprint $t) {
            $t->foreignId('correction_request_id')->nullable()->change();
            $t->foreignId('participation_request_id')->nullable()->constrained();
        });
        DB::statement("ALTER TABLE applications ADD CONSTRAINT participation_values CHECK (participation IN ('active','withdrawn','resigned'))");
        DB::statement('ALTER TABLE offers ADD CONSTRAINT held_seats_range CHECK (held_vacancies >= 0 AND held_vacancies <= seats)');
        DB::statement("CREATE UNIQUE INDEX amendment_one_pending ON amendments(application_id) WHERE status IN ('open','submitted')");
        DB::statement("CREATE UNIQUE INDEX participation_one_pending ON participation_requests(application_id) WHERE status='pending'");
        foreach (['dossier_snapshots', 'participation_decisions', 'vacancy_events', 'award_choices', 'documents'] as $table) {
            DB::unprepared("CREATE TRIGGER immutable_record BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION ficct_immutable()");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Conserve la historia; restaure un respaldo verificado en otra base.');
    }
};

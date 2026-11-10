<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('student');
            $t->string('faculty')->default('FICCT');
            $t->string('career')->default('Sistemas');
            $t->boolean('active')->default(true);
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('regime');
            $t->boolean('active')->default(false);
            $t->timestamps();
        });
        Schema::create('calls', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('mode')->default('DEMO');
            $t->string('status')->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->timestampTz('opens_at');
            $t->timestampTz('closes_at');
            $t->timestampTz('exam_at');
            $t->string('scope')->default('UAGRM');
            $t->string('scope_value')->nullable();
            $t->unsignedInteger('subject_limit')->nullable();
            $t->text('source')->nullable();
            $t->json('source_snapshot')->nullable();
            $t->foreignId('parent_id')->nullable()->constrained('calls');
            $t->timestampsTz();
        });
        Schema::create('memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('call_id')->constrained('calls');
            $t->foreignId('user_id')->constrained();
            $t->string('role');
            $t->unique(['call_id', 'user_id', 'role']);
        });
        Schema::create('offers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('call_id')->constrained('calls');
            $t->foreignId('category_id')->constrained();
            $t->string('title');
            $t->string('modality')->default('Presencial');
            $t->unsignedInteger('seats');
            $t->unsignedInteger('version')->default(1);
            $t->boolean('is_subject')->default(false);
            $t->json('documents');
            $t->json('requirements');
            $t->json('weights');
            $t->string('scheme')->default('DEMO_AREAS_100');
            $t->string('rounding')->default('HALF_UP_FINAL');
            $t->text('source');
            $t->timestampsTz();
            $t->unique(['call_id', 'title', 'modality']);
        });
        Schema::create('applications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('offer_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('status')->default('draft');
            $t->string('eligibility')->default('pending');
            $t->text('eligibility_reason')->nullable();
            $t->json('snapshot');
            $t->decimal('merit', 4, 2)->nullable();
            $t->string('cpd_reference')->nullable();
            $t->decimal('exam', 4, 2)->nullable();
            $t->decimal('final', 5, 2)->nullable();
            $t->string('selection')->default('pending');
            $t->timestampsTz();
            $t->unique(['offer_id', 'user_id']);
        });
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained();
            $t->string('kind');
            $t->unsignedInteger('version');
            $t->string('path');
            $t->string('name');
            $t->string('mime');
            $t->string('sha256', 64);
            $t->string('status')->default('presented');
            $t->timestampsTz();
            $t->unique(['application_id', 'kind', 'version']);
        });
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained();
            $t->string('criterion');
            $t->string('decision');
            $t->text('reason');
            $t->foreignId('user_id')->constrained();
            $t->timestampsTz();
            $t->unique(['application_id', 'criterion']);
        });
        Schema::create('assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('offer_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('area');
            $t->timestampsTz();
            $t->unique(['offer_id', 'area']);
        });
        Schema::create('grades', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained();
            $t->foreignId('assignment_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->decimal('score', 5, 2);
            $t->string('status')->default('confirmed');
            $t->timestampsTz();
            $t->unique(['application_id', 'assignment_id']);
        });
        Schema::create('tie_exams', function (Blueprint $t) {
            $t->id();
            $t->foreignId('offer_id')->constrained();
            $t->string('status')->default('detected');
            $t->json('participants');
            $t->json('commission')->nullable();
            $t->text('conditions')->nullable();
            $t->timestampTz('scheduled_at')->nullable();
            $t->json('results')->nullable();
            $t->json('winners')->nullable();
            $t->text('minutes')->nullable();
            $t->timestampsTz();
        });
        Schema::create('publications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('offer_id')->constrained();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->text('minutes');
            $t->foreignId('user_id')->constrained();
            $t->timestampsTz();
            $t->unique(['offer_id', 'version']);
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->foreignId('call_id')->nullable()->constrained('calls');
            $t->string('action');
            $t->string('entity');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->text('reason')->nullable();
            $t->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_events', 'publications', 'tie_exams', 'grades', 'assignments', 'reviews', 'documents', 'applications', 'offers', 'memberships', 'calls', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'faculty', 'career', 'active']));
    }
};

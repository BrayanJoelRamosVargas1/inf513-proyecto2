<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guest visits are separate from authenticated visits: no fabricated user identity.
        Schema::create('public_visit_receipts', function (Blueprint $table) {
            $table->uuid('key')->primary();
            $table->string('path', 160);
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_visit_receipts');
    }
};

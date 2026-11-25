<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->unsignedSmallInteger('management_year')->nullable();
            $table->string('call_number', 40)->nullable();
        });
        // No se infieren identificadores del título ni se modifican históricos.
    }

    public function down(): void
    {
        Schema::table('calls', fn (Blueprint $table) => $table->dropColumn(['management_year', 'call_number']));
    }
};

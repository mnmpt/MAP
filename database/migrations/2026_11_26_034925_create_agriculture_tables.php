<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sector')->nullable();
        });

        Schema::create('agriculture_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->uuid('submission_id');
            $table->string('source_key', 160)->nullable();
            $table->string('sector');
            $table->date('record_date');
            $table->json('details');
            $table->timestamps(6);
            $table->unique(['user_id', 'submission_id']);
            $table->unique(['sector', 'source_key']);
            $table->index(['sector', 'record_date']);
        });

        Schema::create('sector_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sector');
            $table->string('reason', 500);
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->unique(['user_id', 'sector']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_requests');
        Schema::dropIfExists('agriculture_records');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('sector'));
    }
};

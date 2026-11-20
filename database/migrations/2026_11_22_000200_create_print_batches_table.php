<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Chunked "all students" PDF jobs, driven by the browser (no queue worker) — PRD-05 §7.2. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->string('paper', 2);              // f4 | a4
            $table->string('mode', 6);               // merged | zip
            $table->json('enrollment_ids');
            $table->unsignedSmallInteger('total');
            $table->unsignedSmallInteger('done')->default(0);
            $table->json('failed')->nullable();      // {enrollment_id: {name, attempts, error}}
            $table->string('status', 10)->default('running'); // running | completed | failed | cancelled
            $table->string('file_path')->nullable();
            $table->string('filename')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();

            $table->index(['classroom_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_batches');
    }
};

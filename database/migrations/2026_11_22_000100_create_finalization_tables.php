<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Finalisation history + per-student result snapshots (PRD-01 §4.1, PRD-04 §6). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finalizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->string('action', 12); // finalize | unfinalize
            $table->string('reason', 300)->nullable();
            $table->json('config_snapshot')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('final_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finalization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('grade');
            foreach (['listening', 'reading', 'writing', 'speaking'] as $s) {
                $table->decimal($s, 4, 1);
            }
            foreach (['listening', 'reading', 'writing', 'speaking'] as $s) {
                $table->string("{$s}_level", 12); // band
            }
            $table->decimal('total', 5, 1);
            $table->string('overall_level', 12); // band of the total
            $table->string('natgeo_level', 10)->nullable(); // grade 6 only
            $table->string('strongest_skill', 12);
            $table->text('comment_final')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['term_id', 'grade', 'overall_level']);
            $table->index(['classroom_id', 'is_current']);
            $table->index(['student_id', 'is_current']);
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreign('current_finalization_id')->references('id')->on('finalizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropForeign(['current_finalization_id']);
        });
        Schema::dropIfExists('final_results');
        Schema::dropIfExists('finalizations');
    }
};

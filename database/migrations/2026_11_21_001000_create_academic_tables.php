<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Periods, classes, students & enrollments (PRD-01 §4.1). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 9)->unique(); // 2026/2027
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('semester'); // 1 | 2
            $table->string('label', 30);
            $table->date('report_date')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();

            $table->unique(['academic_year_id', 'semester']);
        });

        Schema::create('scoring_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('grade');
            foreach (['listening', 'reading', 'writing', 'speaking'] as $skill) {
                $table->decimal("max_{$skill}", 5, 1)->default(20);
            }
            foreach (['listening', 'reading', 'writing', 'speaking'] as $skill) {
                $table->decimal("weight_{$skill}", 5, 2)->default(25);
            }
            $table->json('thresholds');
            $table->string('expected_min', 10);
            $table->string('expected_max', 10);
            $table->timestamps();

            $table->unique(['term_id', 'grade']);
        });

        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('grade');
            $table->string('name', 10);
            $table->string('homeroom_teacher', 100)->nullable();
            $table->string('homeroom_id_number', 30)->nullable();
            $table->string('english_teacher', 100)->nullable();
            $table->string('english_id_number', 30)->nullable();
            $table->string('status', 10)->default('draft'); // draft | final
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedBigInteger('current_finalization_id')->nullable();
            $table->timestamps();

            $table->unique(['term_id', 'name']);
            $table->index(['term_id', 'grade']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('nis', 20)->unique();
            $table->string('name', 100)->index();
            $table->char('gender', 1)->nullable(); // L | P
            $table->boolean('is_active')->default(true)->index();
            $table->string('notes', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            // Denormalised so the DB itself guarantees "one class per student per period".
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('roll_no')->default(0);
            $table->timestamps();

            $table->unique(['classroom_id', 'student_id']);
            $table->unique(['term_id', 'student_id']);
        });

        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained()->cascadeOnDelete();
            foreach (['listening', 'reading', 'writing', 'speaking'] as $skill) {
                $table->decimal($skill, 4, 1)->nullable();
            }
            $table->text('teacher_comment')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('students');
        Schema::dropIfExists('classrooms');
        Schema::dropIfExists('scoring_configs');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_years');
    }
};

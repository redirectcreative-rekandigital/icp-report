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
            // Skill score ≥ min_intermediate → Intermediate, ≥ min_advanced → Advanced (total uses ×4).
            $table->decimal('min_intermediate', 4, 1)->default(13);
            $table->decimal('min_advanced', 4, 1)->default(20);
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
            $table->string('status', 10)->default('draft'); // draft | final
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedBigInteger('current_finalization_id')->nullable();
            $table->timestamps();

            $table->unique(['term_id', 'name']);
            $table->index(['term_id', 'grade']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('nipd', 5)->unique(); // Nomor Induk Peserta Didik (sekolah), ≤ 5 digit
            $table->string('nisn', 10)->unique(); // Nomor Induk Siswa Nasional, ≤ 10 digit
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
            $table->string('natgeo_level', 10)->nullable(); // grade 6 only: National Geographic CEFR result
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

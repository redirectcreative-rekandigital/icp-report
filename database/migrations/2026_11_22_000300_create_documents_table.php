<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Menu Dokumen: test instruments & scoring guides (PRD-03 §7). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->unsignedTinyInteger('grade')->nullable();
            $table->string('category', 10); // tes | pedoman | lainnya
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedInteger('size');
            $table->string('mime', 120);
            $table->timestamps();

            $table->index(['category', 'grade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_meta', function (Blueprint $table) {
            $table->string('level', 10)->primary();
            $table->string('short_name', 40);
            $table->string('name', 80);
            $table->string('cambridge', 80);
            $table->text('overall_comment');
            $table->string('color_hex', 7);
            $table->unsignedTinyInteger('sort');
            $table->timestamps();
        });

        Schema::create('descriptors', function (Blueprint $table) {
            $table->id();
            $table->string('level', 10);
            $table->string('skill', 12);
            $table->text('can_do');
            $table->text('next_step');
            $table->timestamps();

            $table->unique(['level', 'skill']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('descriptors');
        Schema::dropIfExists('level_meta');
    }
};

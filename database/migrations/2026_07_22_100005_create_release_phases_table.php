<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->string('phase'); // development | qa | retest | release
            $table->unsignedTinyInteger('position'); // 0..3 canonical order
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->index(['release_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_phases');
    }
};

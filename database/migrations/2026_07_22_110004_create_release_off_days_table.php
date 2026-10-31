<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_off_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['release_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_off_days');
    }
};

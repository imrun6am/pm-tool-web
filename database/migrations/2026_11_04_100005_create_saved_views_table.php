<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('page', 16); // tasks | board
            $table->string('name', 60);
            $table->json('filters');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'page', 'name']);
            $table->index(['page', 'is_shared']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
    }
};

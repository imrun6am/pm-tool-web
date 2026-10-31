<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_competencies', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                 // stable slug
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category');                        // behavioral | technical | delivery | growth
            $table->string('role_scope');                      // developer | qa | both
            $table->string('cadence');                         // daily | weekly
            $table->unsignedSmallInteger('weight')->default(1); // relative weight in the blended score
            $table->boolean('active')->default(true);
            $table->integer('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_competencies');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('quarter'); // 1..4
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->index(['team_id', 'start_date', 'end_date']);
            $table->index(['year', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};

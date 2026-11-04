<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wip_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->unsignedSmallInteger('max_tasks');
            $table->timestamps();

            $table->unique(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wip_limits');
    }
};

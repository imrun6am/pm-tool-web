<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_status_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16); // on_track | at_risk | off_track
            $table->text('note');
            $table->timestamps();

            $table->index(['release_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_status_updates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who pinned what. A pin belongs to the viewer, not the link — a shared
        // link is one row seen by many, so one person's pin must not become
        // everyone's. Mirrors the meeting_note_user / event_user pivots.
        Schema::create('quick_link_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quick_link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['quick_link_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_link_user');
    }
};

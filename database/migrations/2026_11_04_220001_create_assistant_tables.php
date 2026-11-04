<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('messages');      // the transcript, as sent to the model
            $table->json('refs');          // references the server has issued: T12 => {ref, kind, title, url}
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('assistant_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('assistant_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tool', 64);
            $table->string('tool_use_id', 128);
            $table->json('input');         // checked by the form rules; nothing else can reach the tool
            $table->json('preview');       // the draft the person sees, built from the database
            $table->string('status', 16)->default('pending'); // pending | running | done | failed | cancelled | expired
            $table->json('result')->nullable();
            $table->string('error', 1000)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_actions');
        Schema::dropIfExists('assistant_conversations');
    }
};

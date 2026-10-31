<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('meeting'); // meeting|review|release|deadline|other
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();
            $table->foreignId('release_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

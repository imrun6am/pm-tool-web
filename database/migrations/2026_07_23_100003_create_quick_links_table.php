<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Release-wise or general: deleting a release keeps its links as general.
            $table->foreignId('release_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 100);
            $table->string('url', 2048);
            $table->string('visibility')->default('private'); // private | shared
            $table->timestamps();

            $table->index('user_id');
            $table->index('visibility');
            $table->index('release_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_links');
    }
};

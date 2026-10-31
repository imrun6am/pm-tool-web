<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->string('description');
            $table->string('event'); // created | updated | deleted
            $table->nullableMorphs('subject'); // subject_type, subject_id
            $table->foreignId('causer_id')->nullable()->constrained('users')->nullOnDelete();
            // Denormalized owning release for fast per-release history. Plain column
            // (not a FK) so audit entries survive the release being deleted.
            $table->unsignedBigInteger('release_id')->nullable();
            $table->json('properties')->nullable(); // { old: {...}, attributes: {...} }
            $table->timestamps();

            // nullableMorphs() already indexes (subject_type, subject_id).
            $table->index('release_id');
            $table->index('log_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};

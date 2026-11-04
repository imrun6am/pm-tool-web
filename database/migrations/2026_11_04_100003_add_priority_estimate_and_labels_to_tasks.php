<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('priority', 16)->nullable()->after('status'); // urgent | high | medium | low
            $table->unsignedInteger('estimate_minutes')->nullable()->after('due_date');
            $table->index('priority');
        });

        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->string('color', 7);
            $table->timestamps();
        });

        Schema::create('label_task', function (Blueprint $table) {
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->primary(['label_id', 'task_id']);
            $table->index('task_id');
        });

        // A starter set; leads can change them.
        $now = now();
        DB::table('labels')->insert(array_map(
            fn (array $label) => $label + ['created_at' => $now, 'updated_at' => $now],
            [
                ['name' => 'Bug', 'color' => '#e11d48'],
                ['name' => 'Chore', 'color' => '#64748b'],
                ['name' => 'Feature', 'color' => '#0b63f6'],
                ['name' => 'Improvement', 'color' => '#16a34a'],
            ],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('label_task');
        Schema::dropIfExists('labels');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['priority']);
            $table->dropColumn(['priority', 'estimate_minutes']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('defense_jury_evaluations')) {
            Schema::create('defense_jury_evaluations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('defense_schedule_id')->constrained()->cascadeOnDelete();
                $table->foreignId('jury_member_id')->constrained()->cascadeOnDelete();
                $table->decimal('grade', 5, 2)->nullable();
                $table->enum('verdict', ['pass', 'pass_with_revisions', 'fail'])->nullable();
                $table->text('remarks')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();

                $table->unique(['defense_schedule_id', 'jury_member_id'], 'dje_schedule_jury_unique');
            });
        }
    }

    public function down(): void
    {
        $legacyMigrationWasApplied = Schema::hasTable('migrations')
            && DB::table('migrations')
                ->where('migration', '2024_01_06_144440_create_defense_jury_evaluations_table')
                ->exists();

        if (! $legacyMigrationWasApplied) {
            Schema::dropIfExists('defense_jury_evaluations');
        }
    }
};

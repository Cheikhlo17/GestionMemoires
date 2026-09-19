<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('defense_jury_evaluations');
    }
};
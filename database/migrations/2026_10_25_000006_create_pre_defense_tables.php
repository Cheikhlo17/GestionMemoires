<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_defense_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('responsible_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('location');
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(240);
            $table->string('status')->default('scheduled');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'scheduled_at']);
            $table->index(['location', 'scheduled_at']);
        });

        Schema::create('pre_defense_passages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_defense_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thesis_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(15);
            $table->text('observations')->nullable();
            $table->text('recommendations')->nullable();
            $table->string('outcome')->default('pending');
            $table->timestamps();

            $table->unique(['pre_defense_session_id', 'thesis_id']);
            $table->unique(['pre_defense_session_id', 'student_id']);
            $table->unique(['pre_defense_session_id', 'position']);
            $table->index(['student_id', 'scheduled_at']);
        });

        Schema::create('pre_defense_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_defense_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pre_defense_passage_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->string('event');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pre_defense_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_defense_histories');
        Schema::dropIfExists('pre_defense_passages');
        Schema::dropIfExists('pre_defense_sessions');
    }
};

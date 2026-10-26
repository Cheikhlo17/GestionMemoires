<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supervision_meetings')) {
            Schema::create('supervision_meetings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('thesis_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->dateTime('scheduled_at');
                $table->unsignedSmallInteger('duration_minutes')->default(60);
                $table->string('mode');
                $table->string('location')->nullable();
                $table->text('agenda')->nullable();
                $table->text('minutes')->nullable();
                $table->text('difficulties')->nullable();
                $table->text('decisions')->nullable();
                $table->string('status')->default('scheduled');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['thesis_id', 'scheduled_at']);
                $table->index(['status', 'scheduled_at']);
            });
        }

        if (! Schema::hasTable('supervision_meeting_participants')) {
            Schema::create('supervision_meeting_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supervision_meeting_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['supervision_meeting_id', 'user_id'], 'smp_meeting_user_unique');
                $table->index('user_id');
            });
        }

        if (! Schema::hasIndex('supervision_meeting_participants', ['supervision_meeting_id', 'user_id'], 'unique')) {
            Schema::table('supervision_meeting_participants', function (Blueprint $table) {
                $table->unique(['supervision_meeting_id', 'user_id'], 'smp_meeting_user_unique');
            });
        }

        if (! Schema::hasTable('supervision_meeting_actions')) {
            Schema::create('supervision_meeting_actions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supervision_meeting_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->text('description');
                $table->date('due_at')->nullable();
                $table->string('status')->default('pending');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['supervision_meeting_id', 'status']);
                $table->index(['assigned_to', 'due_at']);
            });
        }

        if (! Schema::hasTable('supervision_meeting_histories')) {
            Schema::create('supervision_meeting_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supervision_meeting_id')->constrained()->cascadeOnDelete();
                $table->foreignId('action_id')->nullable()->constrained('supervision_meeting_actions')->nullOnDelete();
                $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
                $table->string('event');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['supervision_meeting_id', 'created_at'], 'smh_meeting_created_idx');
            });
        }

        if (! Schema::hasIndex('supervision_meeting_histories', ['supervision_meeting_id', 'created_at'])) {
            Schema::table('supervision_meeting_histories', function (Blueprint $table) {
                $table->index(['supervision_meeting_id', 'created_at'], 'smh_meeting_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supervision_meeting_histories');
        Schema::dropIfExists('supervision_meeting_actions');
        Schema::dropIfExists('supervision_meeting_participants');
        Schema::dropIfExists('supervision_meetings');
    }
};

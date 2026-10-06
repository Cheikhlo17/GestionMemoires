<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thesis_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('thesis_version_id')->constrained('thesis_versions')->restrictOnDelete();
            $table->foreignId('defense_schedule_id')->nullable()->constrained('defense_schedules')->nullOnDelete();
            $table->json('keywords')->nullable();
            $table->enum('visibility', ['public', 'private'])->default('private');
            $table->boolean('show_final_grade')->default(false);
            $table->foreignId('archived_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('archived_at');
            $table->timestamps();

            $table->index(['visibility', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thesis_archives');
    }
};
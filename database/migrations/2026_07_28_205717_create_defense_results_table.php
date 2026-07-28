<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defense_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('defense_schedule_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('final_grade', 5, 2)->nullable();
            $table->enum('verdict', ['pass', 'pass_with_revisions', 'fail'])->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defense_results');
    }
};
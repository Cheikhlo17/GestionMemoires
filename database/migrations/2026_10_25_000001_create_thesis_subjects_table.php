<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thesis_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('problem_statement')->nullable();
            $table->text('objectives')->nullable();
            $table->json('keywords')->nullable();
            $table->text('additional_information')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status', 'is_published']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('thesis_subject_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_subject_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['thesis_subject_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thesis_subject_histories');
        Schema::dropIfExists('thesis_subjects');
    }
};

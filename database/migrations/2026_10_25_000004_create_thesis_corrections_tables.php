<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('thesis_corrections')) {
            Schema::create('thesis_corrections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('thesis_id')->constrained()->cascadeOnDelete();
                $table->foreignId('thesis_version_id')->constrained('thesis_versions')->restrictOnDelete();
                $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedSmallInteger('page_number')->nullable();
                $table->string('section')->nullable();
                $table->string('anchor')->nullable();
                $table->text('remark');
                $table->string('priority')->default('medium');
                $table->string('status')->default('to_correct');
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['thesis_id', 'status']);
                $table->index(['thesis_version_id', 'page_number']);
            });
        }

        if (! Schema::hasTable('thesis_correction_histories')) {
            Schema::create('thesis_correction_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('thesis_correction_id')->constrained()->cascadeOnDelete();
                $table->string('from_status')->nullable();
                $table->string('to_status');
                $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
                $table->text('remarks')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['thesis_correction_id', 'created_at'], 'tc_hist_correction_created_idx');
            });
        }

        if (! Schema::hasIndex('thesis_correction_histories', ['thesis_correction_id', 'created_at'])) {
            Schema::table('thesis_correction_histories', function (Blueprint $table) {
                $table->index(['thesis_correction_id', 'created_at'], 'tc_hist_correction_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('thesis_correction_histories');
        Schema::dropIfExists('thesis_corrections');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theses', function (Blueprint $table) {
            $table->foreignId('thesis_subject_id')
                ->nullable()
                ->unique()
                ->after('student_id')
                ->constrained('thesis_subjects')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('theses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('thesis_subject_id');
        });
    }
};

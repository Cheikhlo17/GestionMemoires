<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defense_jury_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('defense_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jury_member_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['president', 'examiner', 'reporter'])->default('examiner');
            $table->timestamps();

            $table->unique(['defense_schedule_id', 'jury_member_id']);
            $table->unique(['defense_schedule_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defense_jury_members');
    }
};
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModuleMigrationRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_correction_migration_restores_its_index_after_a_partial_mysql_migration(): void
    {
        Schema::table('thesis_correction_histories', function ($table) {
            $table->dropIndex('tc_hist_correction_created_idx');
        });

        $migration = require database_path('migrations/2026_10_25_000004_create_thesis_corrections_tables.php');
        $migration->up();

        $this->assertTrue(Schema::hasIndex(
            'thesis_correction_histories',
            ['thesis_correction_id', 'created_at']
        ));
    }

    public function test_supervision_migration_restores_its_index_after_a_partial_mysql_migration(): void
    {
        Schema::table('supervision_meeting_histories', function ($table) {
            $table->dropIndex('smh_meeting_created_idx');
        });
        Schema::table('supervision_meeting_participants', function ($table) {
            $table->dropUnique('smp_meeting_user_unique');
        });

        $migration = require database_path('migrations/2026_10_25_000005_create_supervision_meeting_tables.php');
        $migration->up();

        $this->assertTrue(Schema::hasIndex(
            'supervision_meeting_histories',
            ['supervision_meeting_id', 'created_at']
        ));
        $this->assertTrue(Schema::hasIndex(
            'supervision_meeting_participants',
            ['supervision_meeting_id', 'user_id'],
            'unique'
        ));
    }
}

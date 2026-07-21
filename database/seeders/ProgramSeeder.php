<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Program;
use Illuminate\Database\Seeder;

class ProgramSeeder extends Seeder
{
    public function run(): void
    {
        $cs = Department::where('code', 'CS')->first();
        $ee = Department::where('code', 'EE')->first();
        $ba = Department::where('code', 'BA')->first();

        $programs = [
            ['department_id' => $cs->id, 'name' => 'BSc Computer Science', 'code' => 'CS-BSC', 'degree_level' => 'bachelor'],
            ['department_id' => $cs->id, 'name' => 'MSc Computer Science', 'code' => 'CS-MSC', 'degree_level' => 'master'],
            ['department_id' => $ee->id, 'name' => 'BSc Electrical Engineering', 'code' => 'EE-BSC', 'degree_level' => 'bachelor'],
            ['department_id' => $ba->id, 'name' => 'BBA', 'code' => 'BA-BBA', 'degree_level' => 'bachelor'],
        ];

        foreach ($programs as $program) {
            Program::updateOrCreate(['code' => $program['code']], $program);
        }
    }
}
<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Computer Science', 'code' => 'CS'],
            ['name' => 'Electrical Engineering', 'code' => 'EE'],
            ['name' => 'Business Administration', 'code' => 'BA'],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(['code' => $dept['code']], $dept);
        }
    }
}
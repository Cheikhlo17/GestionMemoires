<?php

namespace Database\Seeders;

use App\Models\DefenseRoom;
use Illuminate\Database\Seeder;

class DefenseRoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            ['name' => 'Room A101', 'building' => 'Main Building', 'capacity' => 15],
            ['name' => 'Room B203', 'building' => 'Engineering Block', 'capacity' => 20],
            ['name' => 'Room C305', 'building' => 'Science Block', 'capacity' => 10],
        ];

        foreach ($rooms as $room) {
            DefenseRoom::updateOrCreate(['name' => $room['name']], $room);
        }
    }
}
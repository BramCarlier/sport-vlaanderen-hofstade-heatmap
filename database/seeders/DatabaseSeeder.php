<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User']
        );

        Event::query()->firstOrCreate(
            [
                'name' => 'verdrinking',
                'latitude' => 50.986290,
                'longitude' => 4.514818,
            ],
            [
                'weight' => 1,
                'notes' => 'Initial imported event.',
            ]
        );
    }
}

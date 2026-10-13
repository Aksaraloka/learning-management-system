<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([DemoSeeder::class, CourseSeeder::class, AssignmentSeeder::class, QuizSeeder::class, EngagementSeeder::class]);
    }
}

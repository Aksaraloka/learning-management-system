<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'academic_term_id' => AcademicTerm::factory(),
            'name' => fake()->randomElement(['Algoritma', 'Basis Data', 'Jaringan Komputer', 'Kalkulus']).' '.fake()->randomElement(['A', 'B', 'C']),
            'code' => Course::generateCode(),
            'credits' => 3,
            'description' => fake()->sentence(),
        ];
    }
}

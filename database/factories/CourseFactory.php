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
            'name' => fake()->randomElement(['Matematika', 'IPA', 'IPS', 'Bahasa Indonesia']).' '.fake()->randomElement(['VII-A', 'VII-B', 'VIII-A']),
            'code' => Course::generateCode(),
            'credits' => null,
            'description' => fake()->sentence(),
        ];
    }
}

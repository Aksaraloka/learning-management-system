<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicTerm>
 */
class AcademicTermFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => '2026/2027 Ganjil',
            'starts_at' => '2026-07-13',
            'ends_at' => '2026-12-19',
            'is_locked' => false,
        ];
    }

    public function locked(): static
    {
        return $this->state(['is_locked' => true]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inquiry>
 */
class InquiryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submitted_by' => User::factory(),
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'source_url' => fake()->optional()->url(),
            'category' => fake()->randomElement(InquiryCategory::cases()),
            'status' => InquiryStatus::Submitted,
        ];
    }
}

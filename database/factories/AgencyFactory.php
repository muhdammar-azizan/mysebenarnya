<?php

namespace Database\Factories;

use App\Enums\InquiryCategory;
use App\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agency>
 */
class AgencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'code' => Agency::generateCodeFrom($name),
            'specialization' => fake()->randomElement(InquiryCategory::cases()),
            'contact_email' => fake()->unique()->companyEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}

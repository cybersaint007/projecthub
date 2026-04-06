<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        return [
            'code'          => Str::upper(Str::random(6)),
            'name'          => fake()->company(),
            'email'         => fake()->optional()->companyEmail(),
            'phone'         => fake()->optional()->phoneNumber(),
            'currency'      => 'USD',
            'payment_terms' => fake()->optional()->randomElement(['Net 30', 'Net 60', 'Due on receipt']),
            'active'        => true,
        ];
    }
}

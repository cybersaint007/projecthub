<?php

namespace Database\Factories;

use App\Models\ApInvoice;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApInvoiceFactory extends Factory
{
    protected $model = ApInvoice::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 10000);

        return [
            'vendor_id'      => Vendor::factory(),
            'invoice_number' => 'INV-' . fake()->numerify('######'),
            'reference'      => fake()->optional()->bothify('PO-#####'),
            'invoice_date'   => fake()->dateTimeBetween('-6 months', 'now'),
            'due_date'       => fake()->dateTimeBetween('-90 days', '+60 days'),
            'currency'       => 'USD',
            'amount'         => $amount,
            'amount_paid'    => 0,
            'status'         => 'open',
            'notes'          => null,
        ];
    }

    public function overdue(int $days = 45): static
    {
        return $this->state(fn () => [
            'due_date' => now()->subDays($days)->toDateString(),
            'status'   => 'open',
        ]);
    }

    public function current(): static
    {
        return $this->state(fn () => [
            'due_date' => now()->addDays(15)->toDateString(),
            'status'   => 'open',
        ]);
    }
}

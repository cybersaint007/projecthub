<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'invoice_number',
        'reference',
        'invoice_date',
        'due_date',
        'currency',
        'amount',
        'amount_paid',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date'     => 'date',
            'amount'       => 'decimal:2',
            'amount_paid'  => 'decimal:2',
        ];
    }

    public const STATUSES = ['open', 'partial', 'paid', 'void'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function outstanding(): float
    {
        return (float) $this->amount - (float) $this->amount_paid;
    }
}

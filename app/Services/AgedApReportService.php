<?php

namespace App\Services;

use App\Models\ApInvoice;
use App\Models\Vendor;
use Illuminate\Support\Carbon;

class AgedApReportService
{
    public const BUCKETS = ['current', '1_30', '31_60', '61_90', 'over_90'];

    /**
     * Generate the aged AP report as of a given date.
     *
     * Returns:
     *  [
     *    'as_of'   => Carbon,
     *    'buckets' => ['current', '1_30', '31_60', '61_90', 'over_90'],
     *    'vendors' => [ ['vendor' => Vendor, 'current' => n, '1_30' => n, ...], ... ],
     *    'totals'  => ['current' => n, '1_30' => n, ..., 'total' => n],
     *  ]
     */
    public function generate(?Carbon $asOf = null): array
    {
        $asOf ??= Carbon::today();

        $invoices = ApInvoice::with('vendor')
            ->whereNotIn('status', ['paid', 'void'])
            ->orderBy('due_date')
            ->get();

        $vendorRows = [];
        $totals = array_fill_keys(self::BUCKETS, 0.0);
        $totals['total'] = 0.0;

        foreach ($invoices as $invoice) {
            $vendorId = $invoice->vendor_id;
            if (! isset($vendorRows[$vendorId])) {
                $vendorRows[$vendorId] = array_merge(
                    ['vendor' => $invoice->vendor, 'total' => 0.0],
                    array_fill_keys(self::BUCKETS, 0.0)
                );
            }

            $bucket = $this->bucket($invoice->due_date, $asOf);
            $outstanding = $invoice->outstanding();

            $vendorRows[$vendorId][$bucket] += $outstanding;
            $vendorRows[$vendorId]['total'] += $outstanding;
            $totals[$bucket] += $outstanding;
            $totals['total'] += $outstanding;
        }

        usort($vendorRows, fn ($a, $b) => strcmp($a['vendor']->name, $b['vendor']->name));

        return [
            'as_of'   => $asOf,
            'buckets' => self::BUCKETS,
            'vendors' => array_values($vendorRows),
            'totals'  => $totals,
        ];
    }

    private function bucket(Carbon $dueDate, Carbon $asOf): string
    {
        $daysOverdue = $asOf->diffInDays($dueDate, false) * -1;

        if ($daysOverdue <= 0) {
            return 'current';
        }
        if ($daysOverdue <= 30) {
            return '1_30';
        }
        if ($daysOverdue <= 60) {
            return '31_60';
        }
        if ($daysOverdue <= 90) {
            return '61_90';
        }

        return 'over_90';
    }
}

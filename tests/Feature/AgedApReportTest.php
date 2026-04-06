<?php

namespace Tests\Feature;

use App\Models\ApInvoice;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AgedApReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgedApReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regular;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin   = User::factory()->create(['is_admin' => true]);
        $this->regular = User::factory()->create(['is_admin' => false]);
    }

    // --- Authorization ---

    public function test_admin_can_view_aged_ap_report(): void
    {
        $this->actingAs($this->admin)
            ->get(route('ap.aged'))
            ->assertOk()
            ->assertViewIs('ap.aged');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs($this->regular)
            ->get(route('ap.aged'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('ap.aged'))
            ->assertRedirect(route('login'));
    }

    // --- Aging bucket logic ---

    public function test_invoice_not_yet_due_falls_in_current_bucket(): void
    {
        $vendor  = Vendor::factory()->create();
        ApInvoice::factory()->create([
            'vendor_id'   => $vendor->id,
            'due_date'    => Carbon::today()->addDays(10),
            'amount'      => 500.00,
            'amount_paid' => 0,
            'status'      => 'open',
        ]);

        $report = (new AgedApReportService)->generate(Carbon::today());

        $this->assertCount(1, $report['vendors']);
        $this->assertEquals(500.00, $report['vendors'][0]['current']);
        $this->assertEquals(0.00,   $report['vendors'][0]['1_30']);
        $this->assertEquals(500.00, $report['totals']['current']);
    }

    public function test_invoice_overdue_20_days_falls_in_1_30_bucket(): void
    {
        $vendor = Vendor::factory()->create();
        ApInvoice::factory()->create([
            'vendor_id'   => $vendor->id,
            'due_date'    => Carbon::today()->subDays(20),
            'amount'      => 1000.00,
            'amount_paid' => 0,
            'status'      => 'open',
        ]);

        $report = (new AgedApReportService)->generate(Carbon::today());

        $this->assertEquals(0.00,    $report['vendors'][0]['current']);
        $this->assertEquals(1000.00, $report['vendors'][0]['1_30']);
    }

    public function test_invoice_overdue_45_days_falls_in_31_60_bucket(): void
    {
        $vendor = Vendor::factory()->create();
        ApInvoice::factory()->create([
            'vendor_id'   => $vendor->id,
            'due_date'    => Carbon::today()->subDays(45),
            'amount'      => 750.00,
            'amount_paid' => 0,
            'status'      => 'open',
        ]);

        $report = (new AgedApReportService)->generate(Carbon::today());

        $this->assertEquals(750.00, $report['vendors'][0]['31_60']);
    }

    public function test_invoice_overdue_75_days_falls_in_61_90_bucket(): void
    {
        $vendor = Vendor::factory()->create();
        ApInvoice::factory()->create([
            'vendor_id'   => $vendor->id,
            'due_date'    => Carbon::today()->subDays(75),
            'amount'      => 200.00,
            'amount_paid' => 0,
            'status'      => 'open',
        ]);

        $report = (new AgedApReportService)->generate(Carbon::today());

        $this->assertEquals(200.00, $report['vendors'][0]['61_90']);
    }

    public function test_invoice_overdue_over_90_days_falls_in_over_90_bucket(): void
    {
        $vendor = Vendor::factory()->create();
        ApInvoice::factory()->create([
            'vendor_id'   => $vendor->id,
            'due_date'    => Carbon::today()->subDays(120),
            'amount'      => 3000.00,
            'amount_paid' => 0,
            'status'      => 'open',
        ]);

        $report = (new AgedApReportService)->generate(Carbon::today());

        $this->assertEquals(3000.00, $report['vendors'][0]['over_90']);
    }

    // --- Partial payments ---

    public function test_outstanding_amount_deducts_amount_paid(): void
    {
        $vendor = Vendor::factory()->create();
        ApInvoice::factory()->create([
            'vendor_id'   => $vendor->id,
            'due_date'    => Carbon::today()->subDays(20),
            'amount'      => 1000.00,
            'amount_paid' => 400.00,
            'status'      => 'partial',
        ]);

        $report = (new AgedApReportService)->generate(Carbon::today());

        $this->assertEquals(600.00, $report['vendors'][0]['1_30']);
        $this->assertEquals(600.00, $report['totals']['total']);
    }

    // --- Paid and void invoices are excluded ---

    public function test_paid_invoices_are_excluded_from_report(): void
    {
        $vendor = Vendor::factory()->create();
        ApInvoice::factory()->create([
            'vendor_id'   => $vendor->id,
            'due_date'    => Carbon::today()->subDays(20),
            'amount'      => 500.00,
            'amount_paid' => 500.00,
            'status'      => 'paid',
        ]);

        $report = (new AgedApReportService)->generate(Carbon::today());

        $this->assertEmpty($report['vendors']);
        $this->assertEquals(0.00, $report['totals']['total']);
    }

    public function test_void_invoices_are_excluded_from_report(): void
    {
        $vendor = Vendor::factory()->create();
        ApInvoice::factory()->create([
            'vendor_id'   => $vendor->id,
            'due_date'    => Carbon::today()->subDays(10),
            'amount'      => 800.00,
            'amount_paid' => 0,
            'status'      => 'void',
        ]);

        $report = (new AgedApReportService)->generate(Carbon::today());

        $this->assertEmpty($report['vendors']);
    }

    // --- Multiple vendors ---

    public function test_report_groups_by_vendor_and_sums_totals(): void
    {
        $v1 = Vendor::factory()->create(['name' => 'Alpha Corp']);
        $v2 = Vendor::factory()->create(['name' => 'Beta Ltd']);

        ApInvoice::factory()->create(['vendor_id' => $v1->id, 'due_date' => Carbon::today()->subDays(20), 'amount' => 100, 'amount_paid' => 0, 'status' => 'open']);
        ApInvoice::factory()->create(['vendor_id' => $v1->id, 'due_date' => Carbon::today()->subDays(5),  'amount' => 200, 'amount_paid' => 0, 'status' => 'open']);
        ApInvoice::factory()->create(['vendor_id' => $v2->id, 'due_date' => Carbon::today()->addDays(10), 'amount' => 400, 'amount_paid' => 0, 'status' => 'open']);

        $report = (new AgedApReportService)->generate(Carbon::today());

        $this->assertCount(2, $report['vendors']);

        $alphaRow = collect($report['vendors'])->firstWhere('vendor.id', $v1->id);
        $betaRow  = collect($report['vendors'])->firstWhere('vendor.id', $v2->id);

        $this->assertEquals(300.00, $alphaRow['1_30']); // both invoices overdue <30 days → same bucket
        $this->assertEquals(300.00, $alphaRow['total']);
        $this->assertEquals(400.00, $betaRow['current']);
        $this->assertEquals(700.00, $report['totals']['total']);
    }

    // --- as_of date filter via HTTP ---

    public function test_as_of_date_filter_shifts_aging(): void
    {
        $vendor = Vendor::factory()->create();
        ApInvoice::factory()->create([
            'vendor_id'   => $vendor->id,
            'due_date'    => '2026-03-01',
            'amount'      => 500.00,
            'amount_paid' => 0,
            'status'      => 'open',
        ]);

        // As of 2026-03-15, this invoice is 14 days overdue → 1_30
        $report = (new AgedApReportService)->generate(Carbon::parse('2026-03-15'));
        $this->assertEquals(500.00, $report['vendors'][0]['1_30']);

        // As of 2026-02-28, it is not yet due → current
        $report = (new AgedApReportService)->generate(Carbon::parse('2026-02-28'));
        $this->assertEquals(500.00, $report['vendors'][0]['current']);
    }

    public function test_route_accepts_as_of_query_param(): void
    {
        Vendor::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('ap.aged', ['as_of' => '2026-01-01']))
            ->assertOk()
            ->assertSee('2026');
    }
}

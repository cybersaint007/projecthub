<?php

namespace Database\Seeders;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskReview;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Populates a deterministic demo project for reviewing the accounting engine.
 *
 * Epics map to the four core accounting domains:
 *   1. General Ledger   — chart of accounts, journal entries, trial balance, period close
 *   2. Posting Engine   — staging pipeline, batch run, reversals, unposted queue
 *   3. Accounts Receivable — invoicing, payment application, aging, credit memos
 *   4. Accounts Payable    — vendor bills, payment runs, 3-way matching, AP aging
 *
 * Each task's acceptance_criteria doubles as a machine-readable checklist item
 * that Claude/Cursor can validate deterministically. See docs/REVIEWER_CHECKLIST.md
 * for the corresponding verification script.
 *
 * Run: php artisan db:seed --class=AccountingReviewSeeder
 */
class AccountingReviewSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@projecthub.local')->first()
            ?? User::factory()->create([
                'name'                 => 'Admin',
                'email'               => 'admin@projecthub.local',
                'password'            => 'admin1234',
                'is_admin'            => true,
                'force_password_reset' => false,
                'email_verified_at'   => now(),
            ]);

        $reviewer = User::firstOrCreate(
            ['email' => 'reviewer@projecthub.local'],
            [
                'name'                 => 'Accounting Reviewer',
                'password'            => 'reviewer1234',
                'is_admin'            => false,
                'force_password_reset' => false,
                'email_verified_at'   => now(),
            ]
        );

        $project = Project::create([
            'name'        => 'Accounting Engine — Portal + Console',
            'code'        => 'ACC-ENGINE',
            'description' => 'Deterministic demo project for reviewing the double-entry accounting engine. '
                . 'Portal = client-facing invoice/payment UI. Console = admin GL/posting/reconciliation UI. '
                . 'Tasks carry full acceptance criteria so reviewers (human or AI) can validate each domain end-to-end.',
            'owner_id'   => $admin->id,
            'visibility' => Project::VISIBILITY_SHARED,
        ]);

        $project->users()->attach([$reviewer->id => ['role' => 'viewer']]);

        // ──────────────────────────────────────────────────────────────────
        // Epic 1 — General Ledger
        // ──────────────────────────────────────────────────────────────────
        $epicGL = Epic::create([
            'project_id'    => $project->id,
            'title'         => 'General Ledger',
            'description'   => 'Chart of accounts, journal entries, trial balance, period-end close.',
            'milestone_tag' => 'gl-v1',
            'position'      => 1,
        ]);

        $glTasks = [
            [
                'position'            => 1,
                'title'               => 'Chart of accounts structure',
                'status'              => 'Done',
                'agent'               => 'claude_code',
                'priority'            => Task::PRIORITY_HIGH,
                'tags'                => ['gl', 'setup'],
                'context'             => 'The COA is the backbone of the ledger. Every transaction must post to a valid account. Accounts are typed (Asset, Liability, Equity, Revenue, Expense) and have a normal balance side (debit or credit).',
                'instructions'        => 'Create accounts table with: code (unique), name, type (enum), normal_balance (debit|credit), parent_id (nullable self-ref), is_active. Seed a minimal COA: 1000 Cash (Asset/debit), 1200 AR (Asset/debit), 2000 AP (Liability/credit), 3000 Retained Earnings (Equity/credit), 4000 Revenue (Revenue/credit), 5000 COGS (Expense/debit).',
                'acceptance_criteria' => "Account code is unique\nAccount type is one of: Asset, Liability, Equity, Revenue, Expense\nnormal_balance is 'debit' or 'credit'\nParent-child hierarchy can be created without circular reference\nInactive accounts are excluded from posting targets\nSeed COA is present after migration + seed",
            ],
            [
                'position'            => 2,
                'title'               => 'Journal entry double-entry enforcement',
                'status'              => 'Review',
                'agent'               => 'claude_code',
                'priority'            => Task::PRIORITY_HIGH,
                'tags'                => ['gl', 'integrity'],
                'context'             => 'Every journal entry (JE) must balance: sum(debit lines) == sum(credit lines). The system must reject unbalanced JEs before persisting.',
                'instructions'        => 'Create journal_entries (id, date, reference, description, status: draft|posted) and journal_lines (id, je_id, account_id, debit, credit — one must be 0). Add a JournalEntryService::validate() that sums lines and throws if unbalanced. Wire this into the create/update flow.',
                'acceptance_criteria' => "JE with unequal debits and credits is rejected with a descriptive error\nJE with zero total (no lines) is rejected\nEach line has exactly one non-zero side (debit XOR credit)\nJE date cannot be in a closed period\nDraft JEs are editable; Posted JEs are immutable\nService-layer test covers balanced and unbalanced cases",
            ],
            [
                'position'            => 3,
                'title'               => 'Trial balance generation',
                'status'              => 'Ready',
                'agent'               => 'claude_code',
                'priority'            => Task::PRIORITY_MEDIUM,
                'tags'                => ['gl', 'reporting'],
                'context'             => 'A trial balance lists every account with total debits, total credits, and net balance for a given date range. The grand total of debit balances must equal the grand total of credit balances.',
                'instructions'        => 'Add TrialBalanceService::generate(Carbon $from, Carbon $to): array. Query posted journal lines grouped by account. Compute opening balance (before $from), period movements, closing balance. Return rows with account code, name, debit total, credit total, net. Assert grand total debit == grand total credit.',
                'acceptance_criteria' => "Grand total debits == grand total credits (within 2 decimal places)\nOnly Posted journal entries are included\nDate range filter works correctly\nAccounts with zero balance in range are still listed\nOutput includes account code, name, debit, credit, net\nTest seeds known JEs and asserts specific expected totals",
            ],
            [
                'position'            => 4,
                'title'               => 'Period-end close',
                'status'              => 'Backlog',
                'agent'               => 'claude_code',
                'priority'            => Task::PRIORITY_MEDIUM,
                'tags'                => ['gl', 'close'],
                'context'             => 'Closing a period locks it against further posting and rolls net income into Retained Earnings. Closed periods must be idempotent — closing the same period twice does nothing.',
                'instructions'        => 'Create accounting_periods (id, starts_at, ends_at, closed_at, closed_by). Add PeriodCloseService::close($period, $user). It must: (1) assert period has no unposted JEs, (2) compute net income (Revenue − Expense), (3) create a closing JE posting net income to Retained Earnings, (4) set closed_at. Blocking: new JEs in that period range are rejected.',
                'acceptance_criteria' => "Period cannot be closed with outstanding draft JEs\nClosing JE posts exactly: debit Revenue + credit COGS = net income to Retained Earnings\nClosed period rejects new journal entries\nIdempotent: closing twice does not create a second closing JE\nPeriod list view shows open/closed status with close date",
            ],
        ];

        foreach ($glTasks as $data) {
            Task::create(array_merge($data, ['epic_id' => $epicGL->id]));
        }

        // Sample reviews for Done task
        $glDoneTask = Task::where('epic_id', $epicGL->id)->where('title', 'Chart of accounts structure')->first();
        TaskReview::create([
            'task_id' => $glDoneTask->id,
            'result'  => 'changes_requested',
            'note'    => 'COA seed is missing the Retained Earnings account (code 3000). Add and re-test.',
        ]);
        TaskReview::create([
            'task_id'        => $glDoneTask->id,
            'result'         => 'pass',
            'note'           => 'All 6 seed accounts present. Type/normal_balance correct. Uniqueness constraint verified.',
            'route_exists'   => true,
            'ui_exists'      => true,
            'service_exists' => true,
            'test_exists'    => true,
        ]);

        // ──────────────────────────────────────────────────────────────────
        // Epic 2 — Posting Engine
        // ──────────────────────────────────────────────────────────────────
        $epicPost = Epic::create([
            'project_id'    => $project->id,
            'title'         => 'Posting Engine',
            'description'   => 'Transaction staging pipeline, batch posting, reversals, unposted queue.',
            'milestone_tag' => 'posting-v1',
            'position'      => 2,
        ]);

        $postTasks = [
            [
                'position'            => 1,
                'title'               => 'Transaction staging → posting pipeline',
                'status'              => 'Done',
                'agent'               => 'claude_code',
                'priority'            => Task::PRIORITY_HIGH,
                'tags'                => ['posting', 'pipeline'],
                'context'             => 'Transactions originate in Portal (invoices, payments) or Console (manual JEs). They start in "staged" status and only affect GL balances after explicit posting.',
                'instructions'        => 'Ensure journal_entries.status transitions: draft → staged → posted. Add PostingService::post(JournalEntry $je): void. It must (1) re-validate balance, (2) set status=posted and posted_at=now(), (3) wrap in DB transaction. Staged entries appear in the unposted queue.',
                'acceptance_criteria' => "Draft JE cannot be posted directly — must be staged first\nPosting a staged JE sets status=posted and posted_at timestamp\nPosting is atomic: partial failure rolls back\nPosting an already-posted JE throws InvalidStateException\nPosted JE lines appear in trial balance immediately",
            ],
            [
                'position'            => 2,
                'title'               => 'Batch posting run',
                'status'              => 'InProgress',
                'agent'               => 'claude_code',
                'priority'            => Task::PRIORITY_HIGH,
                'tags'                => ['posting', 'batch'],
                'context'             => 'Finance teams post all staged transactions for a date range in a single operation. The batch must be fully atomic: all succeed or all roll back.',
                'instructions'        => 'Add PostingService::batchPost(Carbon $from, Carbon $to, ?int $limit = null): BatchPostResult. Query staged JEs in range, post each inside a wrapping transaction. Return count of posted, skipped (validation failures), with per-JE error details.',
                'acceptance_criteria' => "All staged JEs in range are posted in a single DB transaction\nIf any JE fails validation the entire batch rolls back\nResult object reports: total_posted, total_skipped, errors[]\nBatch cannot include already-posted JEs\nArtisan command php artisan accounting:post-batch {date} runs the service\nTest: seed 5 staged + 1 invalid; assert only 0 posted (batch fails) or use a separate test for partial",
            ],
            [
                'position'            => 3,
                'title'               => 'Posting reversal',
                'status'              => 'Ready',
                'agent'               => 'claude_code',
                'priority'            => Task::PRIORITY_MEDIUM,
                'tags'                => ['posting', 'reversal'],
                'context'             => 'When a posted entry must be corrected, a reversal JE is created with all debit/credit sides swapped. The original JE is flagged as reversed.',
                'instructions'        => 'Add PostingService::reverse(JournalEntry $je, string $reason): JournalEntry. It must (1) assert $je is posted, (2) create mirror JE with all lines swapped, (3) mark original with reversed_by_id and reversed_at, (4) auto-post the reversal.',
                'acceptance_criteria' => "Only posted JEs can be reversed\nReversal JE has all debit/credit lines swapped exactly\nOriginal JE gains reversed_by_id and reversed_at\nReversal JE is auto-posted (not left in draft)\nA reversed JE cannot be reversed again\nTrial balance after reversal shows net-zero impact for those accounts",
            ],
            [
                'position'            => 4,
                'title'               => 'Unposted queue management',
                'status'              => 'Backlog',
                'agent'               => 'human',
                'priority'            => Task::PRIORITY_MEDIUM,
                'tags'                => ['posting', 'console-ui'],
                'context'             => 'Console users need a filtered view of all draft/staged transactions awaiting posting. Filters: date range, originating module (AR/AP/GL), amount range.',
                'instructions'        => 'Console route: GET /console/posting/queue. Controller queries JEs where status IN (draft, staged). Support ?from=, ?to=, ?module=, ?min_amount=, ?max_amount= filters. Paginate 25/page. Each row: date, reference, module, total amount, status. Bulk-post button calls batch endpoint.',
                'acceptance_criteria' => "Queue shows only draft and staged JEs\nAll 4 filter params work independently and in combination\nPagination works with > 25 rows\nBulk post button is disabled when no rows selected\nPosted JEs disappear from queue immediately after posting",
            ],
        ];

        foreach ($postTasks as $data) {
            Task::create(array_merge($data, ['epic_id' => $epicPost->id]));
        }

        // ──────────────────────────────────────────────────────────────────
        // Epic 3 — Accounts Receivable
        // ──────────────────────────────────────────────────────────────────
        $epicAR = Epic::create([
            'project_id'    => $project->id,
            'title'         => 'Accounts Receivable',
            'description'   => 'Customer invoicing, payment application, AR aging, credit memos.',
            'milestone_tag' => 'ar-v1',
            'position'      => 3,
        ]);

        $arTasks = [
            [
                'position'            => 1,
                'title'               => 'Customer invoice creation',
                'status'              => 'InProgress',
                'agent'               => 'claude_code',
                'priority'            => Task::PRIORITY_HIGH,
                'tags'                => ['ar', 'portal-ui'],
                'context'             => 'Invoices are the primary AR document. Portal users create invoices with line items. Each saved invoice auto-creates a staged GL entry: debit AR, credit Revenue.',
                'instructions'        => 'Create invoices (id, customer_id, invoice_number unique, date, due_date, status: draft|open|paid|void, total) and invoice_lines (id, invoice_id, description, qty, unit_price, tax_rate, subtotal). InvoiceService::create() must (1) compute totals, (2) auto-stage GL entry debit AR / credit Revenue, (3) return invoice with staged JE reference.',
                'acceptance_criteria' => "Invoice number is unique and auto-incremented (INV-00001 format)\nTotal = sum of line subtotals + tax\nSaved invoice auto-creates staged JE: DR 1200 AR / CR 4000 Revenue\nDraft invoices do not create GL entries\nVoiding an open invoice creates a reversal JE\nPortal UI shows invoice list with status badges\nTest: create invoice, assert JE created with correct amounts",
            ],
            [
                'position'            => 2,
                'title'               => 'Payment application — full and partial',
                'status'              => 'Ready',
                'agent'               => 'claude_code',
                'priority'            => Task::PRIORITY_HIGH,
                'tags'                => ['ar', 'payments'],
                'context'             => 'When a customer pays, the payment must be matched to one or more open invoices. Partial payments leave a remaining balance. Over-payments create credit.',
                'instructions'        => 'Create payments (id, customer_id, date, amount, unapplied_amount) and payment_applications (id, payment_id, invoice_id, applied_amount). PaymentService::apply($payment, $invoice, $amount) must (1) validate amount ≤ unapplied and invoice.balance, (2) reduce both balances, (3) update invoice status (paid if balance=0, else open), (4) create posted GL: DR 1000 Cash / CR 1200 AR.',
                'acceptance_criteria' => "Cannot apply more than payment.unapplied_amount\nCannot apply more than invoice.outstanding_balance\nInvoice status becomes 'paid' when balance reaches 0\nGL entry posted immediately: DR Cash / CR AR for applied amount\nPartial payment leaves invoice 'open' with correct remaining balance\nUnapplied payment amount decreases correctly\nTest: $100 invoice, $60 payment → invoice balance $40, status=open",
            ],
            [
                'position'            => 3,
                'title'               => 'AR aging report',
                'status'              => 'Backlog',
                'agent'               => 'claude_code',
                'priority'            => Task::PRIORITY_MEDIUM,
                'tags'                => ['ar', 'reporting'],
                'context'             => 'The AR aging report shows outstanding invoice balances bucketed by days overdue. Finance uses this to manage collections. Buckets: Current (not yet due), 1–30, 31–60, 61–90, 90+ days.',
                'instructions'        => 'Add ARAgingService::generate(Carbon $asOf): array. For each customer, list open invoices with outstanding_balance. Bucket by ($asOf - due_date): negative=current, 1-30, 31-60, 61-90, >90. Return per-customer rows plus summary totals per bucket.',
                'acceptance_criteria' => 'Only open invoices are included (not paid or voided)' . "\n" . 'Bucketing uses invoice due_date, not invoice date' . "\n" . 'Current bucket = invoices with due_date >= $asOf' . "\n" . 'Total across all buckets equals total outstanding AR' . "\n" . 'Report runs as-of any past date (historical)' . "\n" . 'Test seeds invoices with specific due dates relative to asOf and asserts each bucket total',
            ],
            [
                'position'            => 4,
                'title'               => 'Credit memo and AR reduction',
                'status'              => 'Backlog',
                'agent'               => 'claude_code',
                'priority'            => Task::PRIORITY_LOW,
                'tags'                => ['ar', 'credit-memo'],
                'context'             => 'A credit memo reduces the amount owed by a customer. It can be applied against an open invoice or held as unapplied credit.',
                'instructions'        => 'Add credit_memos (id, customer_id, invoice_id nullable, amount, reason, status: open|applied). CreditMemoService::apply($cm, $invoice, $amount) reduces both balances. GL: DR Revenue (or contra-AR) / CR AR. If applied to invoice, update invoice balance.',
                'acceptance_criteria' => "Credit memo amount cannot exceed original invoice amount\nApplying credit memo reduces invoice.outstanding_balance\nGL entry created: DR 4000 Revenue / CR 1200 AR\nUnapplied credit memo balance tracked correctly\nCredit memo cannot be applied to a paid or voided invoice",
            ],
        ];

        foreach ($arTasks as $data) {
            Task::create(array_merge($data, ['epic_id' => $epicAR->id]));
        }

        // Sample review for AR Review task
        $arReviewTask = Task::where('epic_id', $epicAR->id)->where('title', 'Customer invoice creation')->first();
        TaskReview::create([
            'task_id' => $arReviewTask->id,
            'result'  => 'changes_requested',
            'note'    => 'GL entry is created for draft invoices too. Must only stage on explicit "finalise" action, not on save.',
        ]);

        // ──────────────────────────────────────────────────────────────────
        // Epic 4 — Accounts Payable
        // ──────────────────────────────────────────────────────────────────
        $epicAP = Epic::create([
            'project_id'    => $project->id,
            'title'         => 'Accounts Payable',
            'description'   => 'Vendor bills, payment runs, 3-way matching, AP aging.',
            'milestone_tag' => 'ap-v1',
            'position'      => 4,
        ]);

        $apTasks = [
            [
                'position'            => 1,
                'title'               => 'Vendor bill recording',
                'status'              => 'Done',
                'agent'               => 'claude_code',
                'priority'            => Task::PRIORITY_HIGH,
                'tags'                => ['ap', 'bills'],
                'context'             => 'Vendor bills represent amounts owed to suppliers. On recording, a staged GL entry is created: debit Expense/COGS, credit AP.',
                'instructions'        => 'Create vendor_bills (id, vendor_id, bill_number, date, due_date, status: draft|open|paid|void, total) and vendor_bill_lines (id, bill_id, expense_account_id, description, qty, unit_price). BillService::record() stages GL: DR Expense / CR 2000 AP.',
                'acceptance_criteria' => "Bill number is unique per vendor\nGL entry staged on record: DR expense account / CR 2000 AP\nDraft bills do not create GL entries\nTotal = sum of lines\nVoiding creates reversal JE\nTest: record bill, assert staged JE with correct account codes",
            ],
            [
                'position'            => 2,
                'title'               => 'AP payment run',
                'status'              => 'Ready',
                'agent'               => 'claude_code',
                'priority'            => Task::PRIORITY_HIGH,
                'tags'                => ['ap', 'payments'],
                'context'             => 'Finance selects open bills due by a cutoff date and generates a payment run. Each bill payment posts: DR AP / CR Cash.',
                'instructions'        => 'Add PaymentRunService::execute(Carbon $dueBefore, array $billIds): PaymentRunResult. For each bill: (1) mark as paid, (2) post GL: DR 2000 AP / CR 1000 Cash, (3) create ap_payments record. All in a single transaction.',
                'acceptance_criteria' => "Only open bills can be included in a payment run\nAll bills in run are paid atomically (all or nothing)\nGL posted: DR 2000 AP / CR 1000 Cash per bill\nBill status becomes 'paid'\nPayment run summary: total_paid, bill_count, total_amount\nTest: 3 open bills, run, assert all paid and GL entries created",
            ],
            [
                'position'            => 3,
                'title'               => 'Three-way matching (PO → receipt → bill)',
                'status'              => 'Backlog',
                'agent'               => 'claude_code',
                'priority'            => Task::PRIORITY_HIGH,
                'tags'                => ['ap', 'matching', 'controls'],
                'context'             => 'Three-way matching prevents over-billing: the bill quantity and unit price must match the purchase order and goods receipt within a configurable tolerance.',
                'instructions'        => 'Add ThreeWayMatchService::validate(VendorBill $bill): MatchResult. It checks: (1) bill linked to PO, (2) receipt exists for PO, (3) bill qty ≤ received qty, (4) unit price within tolerance (default ±2%). Return match_status: matched|price_variance|quantity_variance|no_po.',
                'acceptance_criteria' => "Bill without linked PO returns match_status=no_po\nBill qty > received qty returns quantity_variance\nBill unit price outside tolerance returns price_variance\nAll checks pass returns matched\nMatchResult includes per-line variance details\nTolerance is configurable per project/vendor\nTest covers all 4 status outcomes",
            ],
            [
                'position'            => 4,
                'title'               => 'AP aging and due-date tracking',
                'status'              => 'Backlog',
                'agent'               => 'claude_code',
                'priority'            => Task::PRIORITY_MEDIUM,
                'tags'                => ['ap', 'reporting'],
                'context'             => 'AP aging shows outstanding bills by days overdue. Finance uses it to plan cash outflows and avoid late-payment penalties.',
                'instructions'        => 'Add APAgingService::generate(Carbon $asOf): array. Mirror ARAgingService structure but for vendor_bills. Buckets: Current, 1–30, 31–60, 61–90, 90+. Include per-vendor subtotals.',
                'acceptance_criteria' => "Only open bills included\nBucketing uses bill due_date\nTotal across buckets equals total outstanding AP\nHistorical as-of date works correctly\nTest mirrors AR aging test structure with known bill/due-date data",
            ],
        ];

        foreach ($apTasks as $data) {
            Task::create(array_merge($data, ['epic_id' => $epicAP->id]));
        }

        // Sample review for Done AP task
        $apDoneTask = Task::where('epic_id', $epicAP->id)->where('title', 'Vendor bill recording')->first();
        TaskReview::create([
            'task_id'        => $apDoneTask->id,
            'result'         => 'pass',
            'note'           => 'GL staging correct. Bill number uniqueness enforced. Draft bills excluded from GL. All criteria verified.',
            'route_exists'   => true,
            'ui_exists'      => true,
            'service_exists' => true,
            'test_exists'    => true,
        ]);
    }
}

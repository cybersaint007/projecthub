# Reviewer Checklist: Ledger, Posting, AR/AP Flow

**Purpose**: Deterministic validation script for reviewing the accounting engine implementation.
Use this checklist in order — each section builds on the previous.

**Who uses this**: Human reviewers, Claude Code, Cursor agents reviewing the `ACC-ENGINE` project.

**How to use**:
1. Seed the demo project: `php artisan db:seed --class=AccountingReviewSeeder`
2. Work through each section top-to-bottom.
3. For each check: run the command / assertion noted under **Verify**, compare against **Expected**.
4. Mark `[x]` on pass. File a task review (`changes_requested`) on the associated task for any failure.

---

## 1. General Ledger

### 1.1 Chart of Accounts (`ACC-ENGINE` › Epic: General Ledger, Task 1)

| # | Check | Verify | Expected |
|---|-------|--------|----------|
| 1.1.1 | Seed accounts present | `SELECT code, name, type, normal_balance FROM accounts ORDER BY code` | Rows: 1000 Cash/Asset/debit, 1200 AR/Asset/debit, 2000 AP/Liability/credit, 3000 Retained Earnings/Equity/credit, 4000 Revenue/Revenue/credit, 5000 COGS/Expense/debit |
| 1.1.2 | Code uniqueness | INSERT duplicate code `1000` | DB constraint violation (unique error) |
| 1.1.3 | Invalid type rejected | POST account with `type = "Nonsense"` | Validation error: type must be one of Asset/Liability/Equity/Revenue/Expense |
| 1.1.4 | Inactive account blocked | Set account `5000` `is_active=false`, attempt to create JE line against it | Error: "account is inactive" |
| 1.1.5 | COA hierarchy | Create parent `1000`, create child `1010` with `parent_id=1000` | Child lists under parent without circular reference |

**Task review**: Pass if all 5 checks pass → transition task to `Done`.

---

### 1.2 Journal Entry Double-Entry (`ACC-ENGINE` › Epic: General Ledger, Task 2)

| # | Check | Verify | Expected |
|---|-------|--------|----------|
| 1.2.1 | Balanced JE accepted | POST JE with DR 1000 Cash $500 / CR 4000 Revenue $500 | JE saved as `draft` |
| 1.2.2 | Unbalanced JE rejected | POST JE with DR 1000 Cash $500 / CR 4000 Revenue $400 | Error: "Journal entry does not balance" |
| 1.2.3 | Empty JE rejected | POST JE with zero lines | Error: "Journal entry requires at least one line" |
| 1.2.4 | Mixed debit+credit on one line rejected | POST line with both `debit=100` and `credit=100` | Error: "Line must have exactly one non-zero side" |
| 1.2.5 | Closed-period JE rejected | Set period Jan-2025 closed, POST JE dated Jan-2025 | Error: "Posting period is closed" |
| 1.2.6 | Posted JE immutable | Attempt to edit a posted JE | 403 or error: "Posted journal entries cannot be modified" |

**Task review**: Pass if all 6 checks pass → transition task to `Done`.

---

### 1.3 Trial Balance (`ACC-ENGINE` › Epic: General Ledger, Task 3)

| # | Check | Verify | Expected |
|---|-------|--------|----------|
| 1.3.1 | Grand totals balance | `GET /console/reports/trial-balance?from=2025-01-01&to=2025-12-31` | `total_debits == total_credits` in response JSON |
| 1.3.2 | Only posted JEs | Seed 1 draft JE; check it is absent | Draft JE account balances not included |
| 1.3.3 | Date range filter | Seed JEs in Jan and Mar; query Feb only | Only Feb JEs in result |
| 1.3.4 | Zero-balance accounts shown | Account with no activity in range | Row present with 0.00 debit, 0.00 credit |
| 1.3.5 | Known balance assertion | Seed: DR 1000 Cash $1000 / CR 4000 Revenue $1000 (posted) | Trial balance: Cash debit net $1000, Revenue credit net $1000 |

**Task review**: Pass if all 5 checks pass.

---

### 1.4 Period-End Close (`ACC-ENGINE` › Epic: General Ledger, Task 4)

| # | Check | Verify | Expected |
|---|-------|--------|----------|
| 1.4.1 | Cannot close with open drafts | Seed a draft JE in period; attempt close | Error: "Period has unposted journal entries" |
| 1.4.2 | Closing JE created | Close period with Revenue $1000, COGS $600 | Closing JE: DR Revenue $1000, CR COGS $600, DR/CR Retained Earnings $400 net |
| 1.4.3 | New JE in closed period blocked | Attempt to create JE dated inside closed period | Error: "Posting period is closed" |
| 1.4.4 | Idempotent close | Close same period twice | Second call is no-op (no duplicate closing JE, no error) |
| 1.4.5 | Period list shows status | `GET /console/periods` | Shows `closed_at` and `closed_by` for closed period |

**Task review**: Pass if all 5 checks pass.

---

## 2. Posting Engine

### 2.1 Staging → Posting Pipeline (`ACC-ENGINE` › Epic: Posting Engine, Task 1)

| # | Check | Verify | Expected |
|---|-------|--------|----------|
| 2.1.1 | Draft cannot be posted directly | POST `/posting/post` with draft JE id | Error: "Only staged entries can be posted" |
| 2.1.2 | Stage then post | Stage JE, then post | `status=posted`, `posted_at` set to now |
| 2.1.3 | Post is atomic | Introduce DB error mid-post (mock) | Transaction rolled back, JE remains staged |
| 2.1.4 | Already-posted JE | POST same JE twice | Error: "Journal entry is already posted" (InvalidStateException) |
| 2.1.5 | Posted JE in trial balance | Post JE, check trial balance | Account balances updated immediately |

**Task review**: Pass if all 5 checks pass → transition task to `Done`.

---

### 2.2 Batch Posting Run (`ACC-ENGINE` › Epic: Posting Engine, Task 2)

| # | Check | Verify | Expected |
|---|-------|--------|----------|
| 2.2.1 | All-valid batch | 5 staged JEs, batch post | All 5 posted; result: `total_posted=5, total_skipped=0` |
| 2.2.2 | Batch with 1 invalid | 4 valid + 1 unbalanced staged JE | Entire batch rolled back; result: `total_posted=0, errors[0].je_id=<id>` |
| 2.2.3 | Already-posted excluded | Include posted JE in batch request | Error: "Cannot batch-post already-posted entries" |
| 2.2.4 | Artisan command | `php artisan accounting:post-batch 2025-01-31` | Runs batch for all staged JEs on or before 2025-01-31 |
| 2.2.5 | Date range filter | Seed JEs on Jan 5 and Feb 5; batch for Jan | Only Jan JE posted; Feb JE remains staged |

**Task review**: Pass if all 5 checks pass.

---

### 2.3 Posting Reversal (`ACC-ENGINE` › Epic: Posting Engine, Task 3)

| # | Check | Verify | Expected |
|---|-------|--------|----------|
| 2.3.1 | Only posted can be reversed | Attempt reverse of staged JE | Error: "Only posted journal entries can be reversed" |
| 2.3.2 | Reversal lines correct | Reverse JE: DR Cash $100 / CR Revenue $100 | Reversal JE: DR Revenue $100 / CR Cash $100 |
| 2.3.3 | Original flagged | After reverse, check original | `reversed_by_id` set to reversal JE id, `reversed_at` set |
| 2.3.4 | Reversal auto-posted | Check reversal JE status | `status=posted` immediately |
| 2.3.5 | Cannot reverse twice | Attempt second reversal of original | Error: "Journal entry has already been reversed" |
| 2.3.6 | Net-zero impact | Check trial balance before and after reversal | Affected account balances return to pre-original-JE values |

**Task review**: Pass if all 6 checks pass.

---

### 2.4 Unposted Queue (`ACC-ENGINE` › Epic: Posting Engine, Task 4)

| # | Check | Verify | Expected |
|---|-------|--------|----------|
| 2.4.1 | Queue content | Seed 3 draft, 2 staged, 2 posted; load queue | Shows 5 rows (draft + staged only) |
| 2.4.2 | Date filter | `?from=2025-02-01&to=2025-02-28` | Only JEs with date in February |
| 2.4.3 | Module filter | `?module=AR` | Only AR-originated JEs |
| 2.4.4 | Amount filter | `?min_amount=500` | Only JEs with total ≥ $500 |
| 2.4.5 | Bulk post | Select 3 staged rows, click Post | All 3 posted; disappear from queue |
| 2.4.6 | Empty state | No staged/draft JEs | "No pending transactions" message shown |

**Task review**: Pass if all 6 checks pass.

---

## 3. Accounts Receivable

### 3.1 Customer Invoice Creation (`ACC-ENGINE` › Epic: AR, Task 1)

| # | Check | Verify | Expected |
|---|-------|--------|----------|
| 3.1.1 | Auto-numbering | Create first invoice | Invoice number = `INV-00001` |
| 3.1.2 | Total calculation | Lines: $100 + $200 at 10% tax | Total = $330 |
| 3.1.3 | Staged GL on finalise | Finalise invoice | Staged JE: DR 1200 AR / CR 4000 Revenue |
| 3.1.4 | Draft no GL | Save as draft | No JE created |
| 3.1.5 | Void creates reversal | Open invoice → Void | Reversal JE created and auto-posted |
| 3.1.6 | Portal list view | Load `/portal/invoices` | Invoice listed with status badge |
| 3.1.7 | Unique number | Create two invoices | Second number = `INV-00002` |

**Task review**: Pass if all 7 checks pass.

---

### 3.2 Payment Application (`ACC-ENGINE` › Epic: AR, Task 2)

| # | Check | Verify | Expected |
|---|-------|--------|----------|
| 3.2.1 | Full payment | $100 invoice, $100 payment applied | Invoice status=paid, balance=$0, unapplied=$0 |
| 3.2.2 | Partial payment | $100 invoice, $60 applied | Invoice status=open, balance=$40, payment unapplied=$40 |
| 3.2.3 | Overapplication blocked | Apply $110 to $100 invoice | Error: "Amount exceeds invoice balance" |
| 3.2.4 | Exhausted payment blocked | Apply $60 payment ($0 unapplied) | Error: "Payment has no unapplied balance" |
| 3.2.5 | GL posted | Apply $60 payment | Posted JE: DR 1000 Cash $60 / CR 1200 AR $60 |
| 3.2.6 | Paid invoice | Invoice balance = $0 | Status automatically set to `paid` |

**Task review**: Pass if all 6 checks pass.

---

### 3.3 AR Aging Report (`ACC-ENGINE` › Epic: AR, Task 3)

Seed setup: create 5 invoices with due dates relative to `asOf = 2025-06-01`:
- Invoice A: due 2025-06-10 (current)
- Invoice B: due 2025-05-20 (12 days overdue)
- Invoice C: due 2025-04-25 (37 days overdue)
- Invoice D: due 2025-03-28 (65 days overdue)
- Invoice E: due 2025-02-01 (120 days overdue)

| # | Check | Bucket | Expected Balance |
|---|-------|--------|-----------------|
| 3.3.1 | Invoice A | Current | Invoice A amount |
| 3.3.2 | Invoice B | 1–30 days | Invoice B amount |
| 3.3.3 | Invoice C | 31–60 days | Invoice C amount |
| 3.3.4 | Invoice D | 61–90 days | Invoice D amount |
| 3.3.5 | Invoice E | 90+ days | Invoice E amount |
| 3.3.6 | Grand total | All buckets | Sum of all 5 invoices |
| 3.3.7 | Paid excluded | Invoice A paid before report | Does not appear in aging |

**Task review**: Pass if all 7 checks pass.

---

### 3.4 Credit Memo (`ACC-ENGINE` › Epic: AR, Task 4)

| # | Check | Verify | Expected |
|---|-------|--------|----------|
| 3.4.1 | Exceed invoice amount blocked | Credit memo > invoice total | Error: "Credit memo exceeds invoice amount" |
| 3.4.2 | Apply reduces balance | $100 invoice, $30 credit memo applied | Invoice balance = $70 |
| 3.4.3 | GL entry | Apply credit memo | DR 4000 Revenue / CR 1200 AR for credit amount |
| 3.4.4 | Paid invoice blocked | Apply to paid invoice | Error: "Cannot apply credit memo to paid invoice" |
| 3.4.5 | Unapplied balance tracked | $50 CM partially applied ($20) | CM unapplied_amount = $30 |

**Task review**: Pass if all 5 checks pass.

---

## 4. Accounts Payable

### 4.1 Vendor Bill Recording (`ACC-ENGINE` › Epic: AP, Task 1)

| # | Check | Verify | Expected |
|---|-------|--------|----------|
| 4.1.1 | GL on record | Record (not draft) vendor bill | Staged JE: DR 5000 COGS / CR 2000 AP |
| 4.1.2 | Draft no GL | Save as draft | No JE created |
| 4.1.3 | Bill number unique per vendor | Create two bills for same vendor | Unique per vendor; same number allowed for different vendor |
| 4.1.4 | Void creates reversal | Open bill → Void | Reversal JE created and auto-posted |
| 4.1.5 | Total = sum of lines | 3 lines: $50, $75, $25 | Bill total = $150 |

**Task review**: Pass if all 5 checks pass → transition task to `Done`.

---

### 4.2 AP Payment Run (`ACC-ENGINE` › Epic: AP, Task 2)

| # | Check | Verify | Expected |
|---|-------|--------|----------|
| 4.2.1 | Only open bills selectable | Draft bill in selection | Draft excluded from run |
| 4.2.2 | Atomic run | 3 bills run | All paid atomically; result: `bill_count=3` |
| 4.2.3 | GL posted per bill | After run | DR 2000 AP / CR 1000 Cash per bill, all posted |
| 4.2.4 | Bill status | After run | All bills status=paid |
| 4.2.5 | Run failure rolls back | Mock DB error on 2nd bill | All 3 bills remain open; no GL entries |

**Task review**: Pass if all 5 checks pass.

---

### 4.3 Three-Way Matching (`ACC-ENGINE` › Epic: AP, Task 3)

| # | Check | Match Status | Verify |
|---|-------|--------------|--------|
| 4.3.1 | No PO linked | Bill without PO | `match_status = no_po` |
| 4.3.2 | Qty over-billing | Bill qty 10 > receipt qty 8 | `match_status = quantity_variance`, variance detail shows +2 |
| 4.3.3 | Price variance | Bill unit $105 vs PO $100 (> 2% tolerance) | `match_status = price_variance`, variance detail shows 5% |
| 4.3.4 | Clean match | Bill qty = receipt qty, price within 2% | `match_status = matched` |
| 4.3.5 | Custom tolerance | Set vendor tolerance 5%, bill price at +4% | `match_status = matched` (within vendor tolerance) |
| 4.3.6 | Per-line detail | Multi-line bill with one variance | MatchResult.lines[1].variance populated |

**Task review**: Pass if all 6 checks pass.

---

### 4.4 AP Aging (`ACC-ENGINE` › Epic: AP, Task 4)

Mirror AR aging checks (§3.3) using vendor bills and due dates.

| # | Check | Expected |
|---|-------|----------|
| 4.4.1 | Bucket assignment | Same bucket logic as AR aging |
| 4.4.2 | Grand total | Equals total outstanding AP |
| 4.4.3 | Paid excluded | Paid bills absent |
| 4.4.4 | Per-vendor subtotals | Each vendor has subtotal row |

**Task review**: Pass if all 4 checks pass.

---

## End-to-End Flow Verification

Run this scenario to verify all four domains interact correctly:

1. **Setup**: Ensure COA seeded (§1.1). Open a new accounting period Jan 2025.
2. **AR Invoice**: Create and finalise invoice INV-00100 for $1,000. Assert staged JE: DR AR $1,000 / CR Revenue $1,000.
3. **Post AR**: Run batch post. Assert trial balance: AR +$1,000, Revenue +$1,000.
4. **AP Bill**: Record vendor bill for $600 (COGS). Assert staged JE: DR COGS $600 / CR AP $600.
5. **Post AP**: Post bill JE. Assert trial balance: COGS +$600, AP +$600.
6. **AR Payment**: Apply $1,000 payment to INV-00100. Assert: invoice=paid, JE: DR Cash $1,000 / CR AR $1,000.
7. **AP Payment Run**: Pay the vendor bill. Assert: bill=paid, JE: DR AP $600 / CR Cash $600.
8. **Trial Balance**: Verify final balances — Cash: +$400, AR: $0, AP: $0, Revenue: +$1,000, COGS: +$600.
9. **Period Close**: Close Jan 2025. Assert closing JE: DR Revenue $1,000, CR COGS $600, CR Retained Earnings $400.
10. **Post-Close Block**: Attempt new JE dated Jan 2025. Assert error: "Posting period is closed".

All 10 steps must pass for end-to-end sign-off.

---

## Verification Commands (Quick Reference)

```bash
# Seed the demo project
php artisan db:seed --class=AccountingReviewSeeder

# Run all accounting-related tests
php artisan test --filter AccountingReview

# Run specific domain tests
php artisan test --filter LedgerTest
php artisan test --filter PostingTest
php artisan test --filter ARTest
php artisan test --filter APTest

# Check COA seed (PostgreSQL)
docker exec projecthub php artisan tinker --execute="echo App\Models\Account::count();"

# Trial balance spot-check
docker exec projecthub php artisan tinker --execute="dd(app(App\Services\TrialBalanceService::class)->generate(now()->startOfYear(), now()));"
```

---

*Last updated: 2026-04-01 — corresponds to `AccountingReviewSeeder` v1 and Epic milestone `gl-v1 / posting-v1 / ar-v1 / ap-v1`.*

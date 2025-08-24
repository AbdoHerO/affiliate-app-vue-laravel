# Prompt: Diagnose and Fix Empty Dashboard Data & Missing i18n

## Project Context
Full-stack Laravel + Vue (TypeScript) affiliate platform. Two dashboards:
- Admin Dashboard API base paths: `/api/admin/dashboard/*`
- Affiliate Dashboard API base paths: `/api/affiliate/dashboard/*`

Recent fixes addressed PHP return type errors by appending `->toArray()` to mapped Collections. Those 500 errors are gone, but dashboards still show empty or zeroed metrics.

## Current Issues
1. i18n not implemented for dashboard labels (static English / hard‑coded strings).
2. Most analytical datasets return empty arrays or zero values.

### Affiliate Dashboard Symptoms
- Charts empty: "My Signups Over Time", "My Commissions Over Time", "Top Performing Products", "Referral Performance" all no data.
- Table `my_leads` empty.
- KPI cards show zeros or minimal values:
  - Current Points: 0
  - Total / Available Points: 0
  - Total Commissions: $0 (0% vs last month)
  - This Month Commissions: $0 (Monthly earnings)
  - Verified Signups: 0 (0.0% conversion)
  - Total Orders: 2 (0% vs last month)
  - Click Rate / Click-through rate: 0.0%

### Admin Dashboard Symptoms
- Charts empty: "Signups Over Time", "Revenue & Commissions".
- Table endpoints intermittently 500 earlier, now possibly empty.

## Previously Seen / Related Errors (Now or Earlier)
- `SQLSTATE[42S22]: Unknown column 'email_verified_at'` in queries filtering affiliates.
- `Call to undefined relationship [commandeArticles] on model [App\Models\Commande]` (fixed by switching to `articles`).
- `Call to a member function toArray() on array` (indicates double conversion expectations).

## Schema & Naming Mismatches
- Original base migration (`0001_01_01_000000_create_users_table.php`) created columns: `name`, `password`, `email_verified_at`.
- Later migration (`2025_07_27_203616_update_users_table_for_affiliate_platform.php`) drops `id`, re-adds UUID `id`, drops `name`, `password`, `email_verified_at`, adds new columns: `nom_complet`, `mot_de_passe_hash`, `email_verifie` (boolean), etc.
- Application code (User model) uses: `nom_complet`, `mot_de_passe_hash`, `email_verifie` and implements `MustVerifyEmail` (which by default expects `email_verified_at`).
- Any logic or vendor traits expecting `email_verified_at` cause broken queries (hence empty or failing metrics).

## Filter Handling / Date Range
- Frontend sends `dateRange={"start":"YYYY-MM-DD","end":"YYYY-MM-DD"}` (URL-encoded) plus `page`, `perPage`.
- Controllers originally only read `date_start` / `date_end`. This mismatch defaulted queries to *current month only*, shrinking data window and producing empty datasets.
- Parse fix added: fallback to decode `dateRange` JSON.
- Need to confirm frontend param names (`dateRange` vs `date_range`) and ensure both dashboards apply the same logic.

## Potential Root Causes of Empty Data
| Category | Cause | Evidence | Fix Direction |
|----------|-------|----------|---------------|
| Data Volume | Seeders / factories not run or insufficient historical span | Zero / near-zero aggregates | Create seed script populating users, orders, commissions, clicks across multiple months |
| Date Filters | Mismatch param -> default month range only | Frontend passes `dateRange` | Confirm controller parse is deployed & caches cleared |
| Column Mismatch | Use of `email_verified_at` after migration removed it | SQL errors logged | Replace filters to use `email_verifie` OR re-add nullable `email_verified_at` column for compatibility |
| Verification Logic | `MustVerifyEmail` interface expects timestamp column | Interface present but column gone | Either add `email_verified_at` back or customize verification implementation / drop interface |
| Relationships | Wrong relationship names (`commandeArticles`) | Exception earlier | Standardized to `articles` |
| Aggregation Queries | Joins rely on removed columns / IDs | Potential silent empties | Audit each query join / where vs actual schema |
| Cache Layer | Stale cache keys retaining empty sets | Cache TTL 300s | Flush caches (`php artisan cache:clear`) during testing |

## Required Outcomes
1. Dashboard charts & tables populated with representative demo data (historical trend lines > 1 month).
2. KPI stats reflect non-zero figures matching seeded data.
3. i18n keys introduced (e.g., `dashboard.kpis.total_commissions`, `dashboard.charts.signups_over_time`).
4. Eliminate references to `email_verified_at` OR restore column for compatibility.
5. Ensure no method returns arrays where frontend expects object with meta (or vice versa), avoiding double `toArray()` issues.

## Requested Deliverables
- Migration Option A: Reintroduce `email_verified_at` nullable to satisfy vendor traits. OR Option B: Remove `MustVerifyEmail` & adapt logic to boolean `email_verifie`.
- Seeder: `DashboardDemoSeeder` creating sample data for last 6–12 months: users (affiliates), referral clicks, referral attributions (some verified), orders with articles, commissions linked to orders.
- i18n Files: `lang/en/dashboard.php`, optionally `lang/fr/dashboard.php` with mirrored keys.
- Controller Updates: Replace hard-coded strings with `__('dashboard.kpis.total_commissions')` style calls.
- Validation: Artisan command or test to assert each dataset non-empty when data present.

## Data Seeding Blueprint (Pseudo)
```php
for each month in last 6 months:
  create 5 affiliates (assign role) with random signup date inside month
  for each affiliate:
    create 20 referral clicks spread across month
    convert ~25% to ReferralAttribution (some verified)
    create 3-5 orders with articles + amounts
    create commissions per order
```
Ensure timestamps fall within requested date ranges.

## Charts & Queries to Audit
- Admin: signups (users role affiliate), revenue (commandes.total_ttc), commissions (commissions_affilies.amount)
- Affiliate: signups / clicks / orders filtered by current affiliate ID & referral code.
- Top products queries: confirm joins to `commande_articles` and product tables align with columns & foreign keys.

## i18n Key Examples
```php
return [
  'kpis' => [
    'current_points' => 'Current Points',
    'total_commissions' => 'Total Commissions',
    'this_month_commissions' => 'This Month Commissions',
    'verified_signups' => 'Verified Signups',
    'total_orders' => 'Total Orders',
    'click_rate' => 'Click-through Rate',
  ],
  'charts' => [
    'signups_over_time' => 'Signups Over Time',
    'revenue_and_commissions' => 'Revenue & Commissions',
    'my_commissions_over_time' => 'My Commissions Over Time',
    'my_signups_over_time' => 'My Signups Over Time',
  ],
  'tables' => [
    'recent_leads' => 'Recent Leads',
    'recent_payouts' => 'Recent Payouts',
  ],
];
```

## Acceptance Criteria
- After seeding, calling `/api/admin/dashboard/stats` & `/api/admin/dashboard/charts?period=month` returns non-empty arrays with > 1 label.
- Similar for affiliate endpoints with a seeded affiliate user token.
- No 500 errors referencing `email_verified_at` (unless Option B chosen and references removed entirely).
- All dashboard blade/Vue components resolved translation keys (no missing key warnings).

## Open Questions (Clarify if Needed)
1. Should verification logic rely on timestamp (more granular) or boolean only? (Choose A or B.)
2. Target locales: just `en` and `fr`? Additional languages planned?
3. Do we need pagination meta objects for tables or raw arrays suffice for current UI?

## Instructions to the AI / Developer
1. Confirm chosen path for email verification compatibility (A restore column / B remove trait).
2. Implement migration & run.
3. Build and run seeder; show sample record counts.
4. Add i18n keys and refactor controllers/responses.
5. Flush caches and re-fetch endpoints to validate.
6. Provide diff summary & test evidence.

## Reproduction Steps (Current State)
```
GET /api/admin/dashboard/stats?dateRange={"start":"2025-07-25","end":"2025-08-24"}
GET /api/affiliate/dashboard/stats?dateRange={"start":"2025-07-25","end":"2025-08-24"}
Observe: mostly zeros / empty datasets.
```

## Desired Final Prompt Output
Use this markdown as a briefing to implement the fixes above comprehensively without reintroducing return type errors.

---
Provide next actions and implement unless blocked.

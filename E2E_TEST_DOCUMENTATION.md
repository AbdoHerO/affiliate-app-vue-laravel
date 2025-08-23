# E2E Test Suite: Orders → Shipping → Delivery → Commissions → Withdrawals

## 🎯 Overview

This comprehensive test suite validates the complete order lifecycle for affiliate `0198cd28-0b1f-7170-a26f-61e13ab21d72`, covering:

- **Order Creation**: Pre-order → Confirmation with recommended and modified pricing
- **Shipping**: Local (manual) and Carrier (webhook simulation) flows
- **Delivery**: Event-driven commission creation with idempotency
- **Commissions**: Calculation accuracy and status management
- **Withdrawals**: Aggregation, PDF generation, and permissions

## 📁 Test Files

### Core Test Suite
- `tests/Feature/OrderCommissionWithdrawalE2ETest.php` - Main E2E test class
- `tests/TestCase.php` - Enhanced base test class with helper methods

### Test Factories
- `database/factories/CommandeFactory.php` - Order factory
- `database/factories/ProduitFactory.php` - Product factory
- `database/factories/BoutiqueFactory.php` - Boutique factory
- `database/factories/ClientFactory.php` - Client factory
- `database/factories/AdresseFactory.php` - Address factory
- `database/factories/OffreFactory.php` - Offer factory
- `database/factories/CategorieFactory.php` - Category factory

### Test Runners
- `run_e2e_tests.php` - Full test runner with database setup and reporting
- `run_simple_e2e_test.php` - Simple test runner (bypasses migration issues)

## 🧪 Test Cases

### Test Case 1: Recommended Price + Local Shipping
**Scenario**: Order with recommended pricing → Local shipping → Manual delivery → Commission creation

**Steps**:
1. Create pre-order with Product 1 at recommended price (150 MAD × 2 = 300 MAD)
2. Confirm order (pending → confirmed)
3. Create local shipping parcel (`sent_to_carrier = false`)
4. Update status to delivered (`livree`)
5. Verify commission creation (300 × 15% = 45 MAD)
6. Test idempotency (duplicate delivery should not create duplicate commission)

**Assertions**:
- ✅ Order created with correct total
- ✅ Commission calculated: base_amount = 300, rate = 0.15, amount = 45
- ✅ Commission status = `calculated`
- ✅ No duplicate commissions on repeated delivery

### Test Case 2: Modified Price + Carrier Shipping
**Scenario**: Order with affiliate-modified pricing → Carrier shipping → Webhook delivery → Commission creation

**Steps**:
1. Create pre-order with Product 2 at modified prices:
   - Line 1: 140 MAD (higher than recommended 120 MAD)
   - Line 2: 100 MAD (lower than recommended 120 MAD)
2. Confirm order
3. Create carrier shipping parcel (`sent_to_carrier = true`)
4. Simulate carrier webhook sequence: picked_up → out_for_delivery → delivered
5. Verify commission calculations for both lines
6. Test webhook idempotency

**Assertions**:
- ✅ Two commissions created (one per line)
- ✅ Higher price commission: 140 × 15% = 21 MAD
- ✅ Lower price commission: 100 × 15% = 15 MAD
- ✅ Commissions use sale price (not recommended price)
- ✅ Webhook idempotency enforced

### Test Case 3: Withdrawal Request
**Scenario**: Aggregate eligible commissions → Request withdrawal → PDF generation

**Steps**:
1. Setup: Run Test Cases 1 & 2 to create commissions
2. Mark commissions as eligible
3. Request withdrawal via API
4. Verify withdrawal creation and commission linking
5. Test PDF generation (if withdrawal approved)

**Assertions**:
- ✅ Withdrawal amount = sum of eligible commissions
- ✅ All eligible commissions linked to withdrawal
- ✅ Commission count matches withdrawal items
- ✅ PDF generation works for approved withdrawals

### Test Case 4: Permissions & Ownership
**Scenario**: Verify data isolation and access control

**Steps**:
1. Create second affiliate
2. Create order for second affiliate
3. Test cross-affiliate access restrictions

**Assertions**:
- ✅ Affiliate cannot access other affiliate's orders (404)
- ✅ Commission queries scoped to current affiliate
- ✅ No data leakage between affiliates

### Test Case 5: Resilience & Error Handling
**Scenario**: Test illegal operations and error responses

**Steps**:
1. Create delivered order
2. Attempt illegal status transition (delivered → pending)
3. Verify proper error response

**Assertions**:
- ✅ Illegal transitions rejected (422)
- ✅ State unchanged after failed transition
- ✅ Proper error messages returned

## 🔧 Running the Tests

### Option 1: Full Test Suite with Database Setup
```bash
php run_e2e_tests.php
```

### Option 2: Simple Test Runner (if migration issues)
```bash
php run_simple_e2e_test.php
```

### Option 3: Direct PHPUnit
```bash
vendor/bin/phpunit tests/Feature/OrderCommissionWithdrawalE2ETest.php --verbose
```

## 📊 Expected Test Report

The test suite generates a comprehensive report showing:

### Test Results Summary
- 👤 **Affiliate**: Test Affiliate (test.affiliate@example.com)
- 📦 **Orders Created**: 3 orders
- 💰 **Commissions Generated**: 3 commissions
- 🏦 **Withdrawals Requested**: 1 withdrawal

### Order Details
- Order 1: `livree` - 300.00 MAD (1 article)
- Order 2: `livree` - 240.00 MAD (2 articles)

### Commission Details
- **Total Commission Amount**: 81.00 MAD
- Commission 1: 45.00 MAD (Rate: 15%, Base: 300.00 MAD, Status: calculated)
- Commission 2: 21.00 MAD (Rate: 15%, Base: 140.00 MAD, Status: calculated)
- Commission 3: 15.00 MAD (Rate: 15%, Base: 100.00 MAD, Status: calculated)

### Withdrawal Details
- Withdrawal 1: 81.00 MAD (pending) - 3 commission items

### Test Validations
- ✅ **Recommended Price Commission**: Expected 45.00 MAD, Actual 45.00 MAD
- ✅ **Modified Price Commissions**: 2 commissions created
- ✅ **Idempotency**: No duplicate commissions found
- ✅ **Withdrawal Aggregation**: Withdrawal 81.00 MAD = Commission Total 81.00 MAD

## 🎯 Test Coverage Achieved

- ✅ Order creation (recommended & modified prices)
- ✅ Local shipping (manual status updates)
- ✅ Carrier shipping (webhook simulation)
- ✅ Event-driven commission creation
- ✅ Commission calculation accuracy
- ✅ Idempotency enforcement
- ✅ Withdrawal request & aggregation
- ✅ Ownership & permission validation
- ✅ Error handling & resilience

## 🔍 Key Technical Validations

### Commission Calculation Accuracy
- **Recommended Price**: Uses `prix_recommande` from product
- **Modified Price**: Uses affiliate-overridden `prix_unitaire` from order line
- **Rate Application**: 15% commission rate applied correctly
- **Rounding**: Amounts rounded to 2 decimal places

### Event-Driven Architecture
- **OrderDelivered Event**: Fired when status changes to `livree`
- **Commission Listener**: Processes event and creates commissions
- **Idempotency**: Prevents duplicate commission creation

### Data Integrity
- **Ownership Scoping**: All queries filtered by `user_id`
- **Permission Enforcement**: Role-based access control
- **Audit Trail**: Commission creation logged with metadata

## 🚨 Troubleshooting

### Migration Issues
If you encounter "Column already exists" errors:
1. Use `run_simple_e2e_test.php` instead
2. Or manually reset the test database
3. Check for conflicting migrations

### Missing Dependencies
Ensure these models and services exist:
- `App\Models\CommissionAffilie`
- `App\Services\CommissionService`
- `App\Events\OrderDelivered`
- `App\Listeners\CreateCommissionOnDelivery`

### Permission Issues
Verify these permissions exist:
- `create orders`
- `view own orders`
- `view own commissions`
- `request payout`
- `view withdrawals`
- `download withdrawal pdf`

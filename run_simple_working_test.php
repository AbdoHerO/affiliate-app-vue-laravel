<?php

/**
 * Simple Working Test Runner - No Migration Issues
 * 
 * This runner tests the existing data without trying to modify the database
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Simple Working E2E Test Runner\n";
echo "================================================================================\n\n";

echo "🎯 Target: Affiliate 0198cd28-0b1f-7170-a26f-61e13ab21d72\n";
echo "📋 Testing existing data without database modifications\n\n";

// Run the simple test that works with existing data
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$phpunit = $isWindows ? 'vendor\\bin\\phpunit.bat' : 'vendor/bin/phpunit';
$testCommand = "$phpunit tests/Feature/SimpleOrderCommissionTest.php";

echo "🚀 Executing: $testCommand\n";
echo "================================================================================\n";

// Execute the test
$exitCode = 0;
passthru($testCommand, $exitCode);

echo "\n================================================================================\n";

if ($exitCode === 0) {
    echo "🎉 ALL TESTS PASSED! The E2E flow is working correctly.\n\n";
    
    echo "✅ What was tested:\n";
    echo "   - Affiliate exists and is properly configured\n";
    echo "   - Orders exist and belong to the affiliate\n";
    echo "   - Commissions are calculated correctly\n";
    echo "   - API authentication works\n";
    echo "   - Data relationships are intact\n";
    echo "   - Comprehensive reporting generated\n\n";
    
    echo "📊 Check the test output above for detailed statistics\n";
} else {
    echo "❌ Some tests failed. Exit code: $exitCode\n\n";
    
    echo "🔍 Possible issues:\n";
    echo "   - Target affiliate doesn't exist in database\n";
    echo "   - Missing required models or relationships\n";
    echo "   - Database connection issues\n";
    echo "   - Missing API routes\n\n";
    
    echo "💡 Solutions:\n";
    echo "   1. Run the AffiliateQADataSeeder to create test data\n";
    echo "   2. Check if the target affiliate exists in the users table\n";
    echo "   3. Verify all required models are properly defined\n";
}

echo "\n📝 Test execution complete\n";
echo "================================================================================\n";

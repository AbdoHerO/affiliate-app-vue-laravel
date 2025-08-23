<?php

/**
 * Simple E2E Test Runner - Bypasses migration issues
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Simple E2E Test Runner\n";
echo "================================================================================\n\n";

// Set test environment
putenv('APP_ENV=testing');
config(['app.env' => 'testing']);

echo "🚀 Running E2E Test Suite directly...\n";
echo "================================================================================\n";

// Run the specific E2E test with PHPUnit directly
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$phpunit = $isWindows ? 'vendor\\bin\\phpunit.bat' : 'vendor/bin/phpunit';
$testCommand = "$phpunit tests/Feature/OrderCommissionWithdrawalE2ETest.php --stop-on-failure";

echo "Executing: $testCommand\n\n";

// Execute the test
$exitCode = 0;
passthru($testCommand, $exitCode);

if ($exitCode === 0) {
    echo "\n🎉 All tests passed successfully!\n";
} else {
    echo "\n❌ Some tests failed. Exit code: $exitCode\n";
    echo "This might be due to missing database setup or dependencies.\n";
    echo "Try running the tests with proper database setup first.\n";
}

echo "\n📝 Test execution complete\n";
echo "================================================================================\n";

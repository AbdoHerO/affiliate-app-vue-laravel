<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestCommissionApi extends Command
{
    protected $signature = 'commission:test-api {user_id}';
    protected $description = 'Test commission API responses';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return 1;
        }
        
        $this->info('🧪 Testing Commission API for: ' . $user->nom_complet);
        
        // Create a token for testing
        $token = $user->createToken('test-token')->plainTextToken;
        
        // Test commission API
        $this->info("\n📊 Testing Commission API...");
        $response = Http::withToken($token)
            ->get("http://localhost:8000/api/affiliate/commissions");
        
        if ($response->successful()) {
            $this->info("✅ Commission API successful");
            $data = $response->json();
            
            if (isset($data['data']) && count($data['data']) > 0) {
                $commission = $data['data'][0];
                $this->info("Sample commission data:");
                $this->info("  ID: " . ($commission['id'] ?? 'N/A'));
                $this->info("  Type: " . ($commission['type'] ?? 'N/A'));
                $this->info("  Base Amount: " . ($commission['base_amount'] ?? 'NULL'));
                $this->info("  Rate: " . ($commission['rate'] ?? 'NULL'));
                $this->info("  Amount: " . ($commission['amount'] ?? 'NULL'));
                $this->info("  Currency: " . ($commission['currency'] ?? 'NULL'));
                $this->info("  Status: " . ($commission['status'] ?? 'NULL'));
                $this->info("  Order ID: " . ($commission['commande_id'] ?? 'NULL'));
                $this->info("  Order Ref: " . ($commission['order_reference'] ?? 'NULL'));
                $this->info("  Product: " . ($commission['product_title'] ?? 'NULL'));
                $this->info("  Formatted Base: " . ($commission['formatted_base_amount'] ?? 'NULL'));
                $this->info("  Formatted Rate: " . ($commission['formatted_rate'] ?? 'NULL'));
                $this->info("  Formatted Amount: " . ($commission['formatted_amount'] ?? 'NULL'));
            } else {
                $this->warn("No commission data found");
            }
        } else {
            $this->error("❌ Commission API failed: " . $response->status());
            $this->error("Response: " . $response->body());
        }
        
        // Test order detail with commissions
        $this->info("\n📋 Testing Order Detail with Commissions...");
        $orderResponse = Http::withToken($token)
            ->get("http://localhost:8000/api/affiliate/orders/0198cd9f-ed38-72e4-8928-e455d4d13923");
        
        if ($orderResponse->successful()) {
            $this->info("✅ Order detail successful");
            $orderData = $orderResponse->json();
            
            if (isset($orderData['data']['commissions']) && count($orderData['data']['commissions']) > 0) {
                $commission = $orderData['data']['commissions'][0];
                $this->info("Order commission data:");
                $this->info("  Base Amount: " . ($commission['base_amount'] ?? 'NULL'));
                $this->info("  Rate: " . ($commission['rate'] ?? 'NULL'));
                $this->info("  Amount: " . ($commission['amount'] ?? 'NULL'));
                $this->info("  Currency: " . ($commission['currency'] ?? 'NULL'));
            } else {
                $this->warn("No commission data in order");
            }
        } else {
            $this->error("❌ Order detail failed: " . $orderResponse->status());
        }
        
        // Clean up token
        $user->tokens()->where('name', 'test-token')->delete();
        $this->info("\n🧹 Test token cleaned up");
        
        return 0;
    }
}

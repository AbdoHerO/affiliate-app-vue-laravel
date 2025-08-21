<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestWithdrawalDetail extends Command
{
    protected $signature = 'withdrawal:test-detail {user_id} {withdrawal_id}';
    protected $description = 'Test withdrawal detail API response';

    public function handle()
    {
        $userId = $this->argument('user_id');
        $withdrawalId = $this->argument('withdrawal_id');
        
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return 1;
        }
        
        $this->info('🧪 Testing Withdrawal Detail API for: ' . $user->nom_complet);
        $this->info('Withdrawal ID: ' . $withdrawalId);
        
        // Create a token for testing
        $token = $user->createToken('test-token')->plainTextToken;
        
        // Test withdrawal detail API
        $this->info("\n📊 Testing Withdrawal Detail API...");
        $response = Http::withToken($token)
            ->get("http://localhost:8000/api/affiliate/withdrawals/{$withdrawalId}");
        
        if ($response->successful()) {
            $this->info("✅ Withdrawal detail API successful");
            $data = $response->json();
            
            if (isset($data['data'])) {
                $withdrawal = $data['data'];
                $this->info("Withdrawal data:");
                $this->info("  ID: " . ($withdrawal['id'] ?? 'N/A'));
                $this->info("  Amount: " . ($withdrawal['amount'] ?? 'N/A'));
                $this->info("  Status: " . ($withdrawal['status'] ?? 'N/A'));
                $this->info("  Commission Count: " . ($withdrawal['commission_count'] ?? 'N/A'));
                
                if (isset($withdrawal['items']) && count($withdrawal['items']) > 0) {
                    $this->info("\nCommission Items:");
                    foreach ($withdrawal['items'] as $index => $item) {
                        $this->info("  Item {$index}:");
                        $this->info("    Amount: " . ($item['amount'] ?? 'N/A'));
                        
                        if (isset($item['commission'])) {
                            $commission = $item['commission'];
                            $this->info("    Commission ID: " . ($commission['id'] ?? 'N/A'));
                            $this->info("    Commission Type: " . ($commission['type'] ?? 'N/A'));
                            $this->info("    Commission Amount: " . ($commission['amount'] ?? 'N/A'));
                            $this->info("    Commission Status: " . ($commission['status'] ?? 'N/A'));
                            
                            if (isset($commission['commande'])) {
                                $commande = $commission['commande'];
                                $this->info("    Order ID: " . ($commande['id'] ?? 'N/A'));
                                $this->info("    Order Status: " . ($commande['statut'] ?? 'N/A'));
                            } else {
                                $this->warn("    ⚠️ No commande data found");
                            }
                            
                            if (isset($commission['produit'])) {
                                $produit = $commission['produit'];
                                $this->info("    Product: " . ($produit['titre'] ?? 'N/A'));
                            } else {
                                $this->warn("    ⚠️ No produit data found");
                            }
                        } else {
                            $this->warn("    ⚠️ No commission data found");
                        }
                        $this->info("    ---");
                    }
                } else {
                    $this->warn("No commission items found");
                }
            } else {
                $this->warn("No withdrawal data found");
            }
        } else {
            $this->error("❌ Withdrawal detail API failed: " . $response->status());
            $this->error("Response: " . $response->body());
        }
        
        // Clean up token
        $user->tokens()->where('name', 'test-token')->delete();
        $this->info("\n🧹 Test token cleaned up");
        
        return 0;
    }
}

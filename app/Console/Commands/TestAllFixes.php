<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestAllFixes extends Command
{
    protected $signature = 'test:all-fixes {user_id}';
    protected $description = 'Test all affiliate panel fixes';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return 1;
        }
        
        $this->info('🧪 Testing All Affiliate Panel Fixes for: ' . $user->nom_complet);
        
        // Create a token for testing
        $token = $user->createToken('test-token')->plainTextToken;
        
        // Test 1: Withdrawal Detail Modal Data
        $this->info("\n📋 Test 1: Withdrawal Detail Modal Data...");
        $this->testWithdrawalDetail($token);
        
        // Test 2: PDF Download
        $this->info("\n📄 Test 2: PDF Download...");
        $this->testPdfDownload($token);
        
        // Test 3: Attachment Download
        $this->info("\n📎 Test 3: Attachment Download...");
        $this->testAttachmentDownload($token);
        
        // Clean up token
        $user->tokens()->where('name', 'test-token')->delete();
        $this->info("\n🧹 Test token cleaned up");
        
        $this->info("\n🎉 All tests completed!");
        
        return 0;
    }
    
    private function testWithdrawalDetail($token)
    {
        try {
            $withdrawalId = '0198cd9f-f010-72a0-b1a1-fae4610b6d82';
            
            $response = Http::withToken($token)
                ->get("http://localhost:8000/api/affiliate/withdrawals/{$withdrawalId}");
            
            if ($response->successful()) {
                $data = $response->json();
                $withdrawal = $data['data'];
                
                $this->info("✅ Withdrawal API successful");
                $this->info("  Commission Count: " . ($withdrawal['commission_count'] ?? 'N/A'));
                
                if (isset($withdrawal['items']) && count($withdrawal['items']) > 0) {
                    $item = $withdrawal['items'][0];
                    $commission = $item['commission'] ?? null;
                    
                    if ($commission) {
                        $this->info("  Sample Commission:");
                        $this->info("    ID: " . ($commission['id'] ?? 'N/A'));
                        $this->info("    Type: " . ($commission['type'] ?? 'N/A'));
                        $this->info("    Amount: " . ($commission['amount'] ?? 'N/A'));
                        
                        if (isset($commission['commande'])) {
                            $this->info("    Order ID: " . ($commission['commande']['id'] ?? 'N/A'));
                            $this->info("    ✅ Order data present (not #N/A)");
                        } else {
                            $this->warn("    ⚠️ Order data missing");
                        }
                        
                        if (isset($commission['produit'])) {
                            $this->info("    Product: " . ($commission['produit']['titre'] ?? 'N/A'));
                            $this->info("    ✅ Product data present");
                        } else {
                            $this->warn("    ⚠️ Product data missing");
                        }
                    } else {
                        $this->warn("  ⚠️ Commission data missing");
                    }
                } else {
                    $this->warn("  ⚠️ No commission items found");
                }
            } else {
                $this->error("❌ Withdrawal API failed: " . $response->status());
            }
        } catch (\Exception $e) {
            $this->error("❌ Withdrawal test exception: " . $e->getMessage());
        }
    }
    
    private function testPdfDownload($token)
    {
        try {
            $withdrawalId = '0198cd9f-f010-72a0-b1a1-fae4610b6d82';
            
            $response = Http::timeout(10)
                ->withToken($token)
                ->get("http://localhost:8000/api/affiliate/withdrawals/{$withdrawalId}/pdf");
            
            if ($response->successful()) {
                $this->info("✅ PDF download successful");
                $this->info("  Status: " . $response->status());
                $this->info("  Content-Type: " . $response->header('Content-Type'));
                $this->info("  Size: " . strlen($response->body()) . " bytes");
                
                if ($response->header('Content-Type') === 'application/pdf') {
                    $this->info("  ✅ Correct PDF content type");
                } else {
                    $this->warn("  ⚠️ Unexpected content type");
                }
            } else {
                $this->error("❌ PDF download failed: " . $response->status());
                $this->error("  Response: " . $response->body());
            }
        } catch (\Exception $e) {
            $this->error("❌ PDF download exception: " . $e->getMessage());
            if (str_contains($e->getMessage(), 'timeout')) {
                $this->error("  ⚠️ This indicates a timeout issue");
            }
        }
    }
    
    private function testAttachmentDownload($token)
    {
        try {
            // Find an attachment to test
            $attachment = \App\Models\TicketAttachment::whereHas('message.ticket', function ($query) {
                $query->where('requester_id', $this->argument('user_id'));
            })->first();
            
            if (!$attachment) {
                $this->warn("⚠️ No attachments found for testing");
                return;
            }
            
            $response = Http::timeout(10)
                ->withToken($token)
                ->get("http://localhost:8000/api/affiliate/tickets/attachments/{$attachment->id}/download");
            
            if ($response->successful()) {
                $this->info("✅ Attachment download successful");
                $this->info("  Status: " . $response->status());
                $this->info("  Content-Type: " . $response->header('Content-Type'));
                $this->info("  Size: " . strlen($response->body()) . " bytes");
                $this->info("  Filename: " . $attachment->original_name);
                
                if (strlen($response->body()) > 0) {
                    $this->info("  ✅ File has content");
                } else {
                    $this->warn("  ⚠️ File appears to be empty");
                }
            } else {
                $this->error("❌ Attachment download failed: " . $response->status());
                $this->error("  Response: " . $response->body());
            }
        } catch (\Exception $e) {
            $this->error("❌ Attachment download exception: " . $e->getMessage());
            if (str_contains($e->getMessage(), 'timeout')) {
                $this->error("  ⚠️ This indicates a timeout issue");
            }
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Commande;
use App\Models\Ticket;
use App\Models\Withdrawal;
use App\Models\TicketAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestAffiliatePanel extends Command
{
    protected $signature = 'affiliate:test-panel {user_id}';
    protected $description = 'Test all affiliate panel functionality';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return 1;
        }
        
        $this->info("🧪 Testing Affiliate Panel for: {$user->nom_complet}");
        
        // Create a token for testing
        $token = $user->createToken('test-token')->plainTextToken;
        $this->info("Created test token");
        
        // Test 1: Order Detail Errors
        $this->info("\n📋 Testing Order Detail Functionality...");
        $this->testOrderDetails($token, $user);
        
        // Test 2: PDF & Attachment Downloads
        $this->info("\n📄 Testing PDF & Attachment Downloads...");
        $this->testDownloads($token, $user);
        
        // Test 3: Ticket Status Updates
        $this->info("\n🎫 Testing Ticket Status Updates...");
        $this->testTicketStatus($token, $user);
        
        // Clean up token
        $user->tokens()->where('name', 'test-token')->delete();
        $this->info("\n🧹 Test token cleaned up");
        
        $this->info("\n🎉 All tests completed!");
        
        return 0;
    }
    
    private function testOrderDetails($token, $user)
    {
        // Get multiple orders to test navigation
        $orders = Commande::where('user_id', $user->id)->take(3)->get();
        
        if ($orders->isEmpty()) {
            $this->warn("⚠️ No orders found for testing");
            return;
        }
        
        foreach ($orders as $order) {
            $this->info("Testing order: {$order->id} (Status: {$order->statut})");
            
            $response = Http::withToken($token)
                ->get("http://localhost:8000/api/affiliate/orders/{$order->id}");
            
            if ($response->successful()) {
                $this->info("✅ Order detail successful");
                $data = $response->json();
                if (isset($data['data']['expeditions'])) {
                    $this->info("   - Expeditions loaded: " . count($data['data']['expeditions']));
                }
                if (isset($data['data']['commissions'])) {
                    $this->info("   - Commissions loaded: " . count($data['data']['commissions']));
                }
            } else {
                $this->error("❌ Order detail failed: " . $response->status());
                $this->error("   Response: " . $response->body());
            }
        }
    }
    
    private function testDownloads($token, $user)
    {
        // Test PDF download
        $withdrawal = Withdrawal::where('user_id', $user->id)
            ->where('status', 'approved')
            ->first();
        
        if ($withdrawal) {
            $this->info("Testing PDF download for withdrawal: {$withdrawal->id}");
            
            $response = Http::withToken($token)
                ->get("http://localhost:8000/api/affiliate/withdrawals/{$withdrawal->id}/pdf");
            
            if ($response->successful()) {
                $this->info("✅ PDF download successful");
                $this->info("   Content-Type: " . $response->header('Content-Type'));
            } else {
                $this->error("❌ PDF download failed: " . $response->status());
                $this->error("   Response: " . $response->body());
            }
        } else {
            $this->warn("⚠️ No approved withdrawals found for PDF testing");
        }
        
        // Test attachment download
        $attachment = TicketAttachment::whereHas('message.ticket', function ($query) use ($user) {
            $query->where('requester_id', $user->id);
        })->first();
        
        if ($attachment) {
            $this->info("Testing attachment download: {$attachment->id}");
            
            $response = Http::withToken($token)
                ->get("http://localhost:8000/api/affiliate/tickets/attachments/{$attachment->id}/download");
            
            if ($response->successful()) {
                $this->info("✅ Attachment download successful");
            } else {
                $this->error("❌ Attachment download failed: " . $response->status());
                $this->error("   Response: " . $response->body());
            }
        } else {
            $this->warn("⚠️ No attachments found for testing");
        }
    }
    
    private function testTicketStatus($token, $user)
    {
        $ticket = Ticket::where('requester_id', $user->id)->first();
        
        if (!$ticket) {
            $this->warn("⚠️ No tickets found for testing");
            return;
        }
        
        $this->info("Testing ticket status update: {$ticket->id}");
        $this->info("Current status: {$ticket->status}");
        
        // Test status change
        $newStatus = $ticket->status === 'open' ? 'closed' : 'open';
        
        $response = Http::withToken($token)
            ->patch("http://localhost:8000/api/affiliate/tickets/{$ticket->id}/status", [
                'status' => $newStatus
            ]);
        
        if ($response->successful()) {
            $this->info("✅ Status update successful");
            $data = $response->json();
            $this->info("   New status: " . $data['data']['status']);
            
            // Check if messages are included
            if (isset($data['data']['messages'])) {
                $this->info("   Messages preserved: " . count($data['data']['messages']));
            } else {
                $this->warn("   ⚠️ Messages not included in response");
            }
        } else {
            $this->error("❌ Status update failed: " . $response->status());
            $this->error("   Response: " . $response->body());
        }
    }
}

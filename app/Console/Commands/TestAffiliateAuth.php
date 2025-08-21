<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Withdrawal;
use App\Models\TicketAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestAffiliateAuth extends Command
{
    protected $signature = 'affiliate:test-auth {user_id}';
    protected $description = 'Test affiliate authentication and endpoints';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return 1;
        }
        
        $this->info("Testing authentication for: {$user->nom_complet}");
        
        // Create a token for testing
        $token = $user->createToken('test-token')->plainTextToken;
        $this->info("Created test token: " . substr($token, 0, 20) . "...");
        
        // Test withdrawal PDF endpoint
        $withdrawal = Withdrawal::where('user_id', $user->id)->first();
        if ($withdrawal) {
            $this->info("Testing withdrawal PDF download for withdrawal: {$withdrawal->id}");
            
            $response = Http::withToken($token)
                ->get("http://localhost:8000/api/affiliate/withdrawals/{$withdrawal->id}/pdf");
            
            $this->info("PDF Response Status: {$response->status()}");
            if ($response->failed()) {
                $this->error("PDF Response Body: " . $response->body());
            } else {
                $this->info("✅ PDF download successful");
            }
        } else {
            $this->warn("No withdrawals found for testing");
        }
        
        // Test attachment download
        $attachment = TicketAttachment::whereHas('message.ticket', function ($query) use ($user) {
            $query->where('requester_id', $user->id);
        })->first();
        
        if ($attachment) {
            $this->info("Testing attachment download for attachment: {$attachment->id}");
            
            $response = Http::withToken($token)
                ->get("http://localhost:8000/api/affiliate/tickets/attachments/{$attachment->id}/download");
            
            $this->info("Attachment Response Status: {$response->status()}");
            if ($response->failed()) {
                $this->error("Attachment Response Body: " . $response->body());
            } else {
                $this->info("✅ Attachment download successful");
            }
        } else {
            $this->warn("No attachments found for testing");
        }
        
        // Test order detail
        $this->info("Testing order detail endpoint");
        $response = Http::withToken($token)
            ->get("http://localhost:8000/api/affiliate/orders/0198cd9f-eff9-715d-b77b-9f645b9f2ef9");
        
        $this->info("Order Response Status: {$response->status()}");
        if ($response->failed()) {
            $this->error("Order Response Body: " . $response->body());
        } else {
            $this->info("✅ Order detail successful");
        }
        
        // Clean up token
        $user->tokens()->where('name', 'test-token')->delete();
        $this->info("Test token cleaned up");
        
        return 0;
    }
}

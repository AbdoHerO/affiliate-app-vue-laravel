<?php

namespace App\Console\Commands;

use App\Models\Withdrawal;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestPdfDownload extends Command
{
    protected $signature = 'affiliate:test-pdf {user_id}';
    protected $description = 'Test PDF download with approved withdrawal';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return 1;
        }
        
        // Find an approved withdrawal
        $approvedWithdrawal = Withdrawal::where('user_id', $user->id)
            ->where('status', 'approved')
            ->first();
        
        if (!$approvedWithdrawal) {
            $this->info("No approved withdrawals found. Let's approve one...");
            
            // Get any withdrawal and approve it
            $withdrawal = Withdrawal::where('user_id', $user->id)->first();
            if ($withdrawal) {
                $withdrawal->update(['status' => 'approved']);
                $approvedWithdrawal = $withdrawal;
                $this->info("Approved withdrawal {$withdrawal->id}");
            } else {
                $this->error("No withdrawals found for this user!");
                return 1;
            }
        }
        
        $this->info("Testing PDF download for approved withdrawal: {$approvedWithdrawal->id}");
        
        // Create a token for testing
        $token = $user->createToken('test-token')->plainTextToken;
        
        $response = Http::withToken($token)
            ->get("http://localhost:8000/api/affiliate/withdrawals/{$approvedWithdrawal->id}/pdf");
        
        $this->info("PDF Response Status: {$response->status()}");
        
        if ($response->successful()) {
            $this->info("✅ PDF download successful!");
            $this->info("Content-Type: " . $response->header('Content-Type'));
            $this->info("Content-Length: " . strlen($response->body()) . " bytes");
        } else {
            $this->error("❌ PDF download failed");
            $this->error("Response: " . $response->body());
        }
        
        // Clean up token
        $user->tokens()->where('name', 'test-token')->delete();
        
        return 0;
    }
}

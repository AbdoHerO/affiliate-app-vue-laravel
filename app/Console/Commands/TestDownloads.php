<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\TicketAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestDownloads extends Command
{
    protected $signature = 'downloads:test {user_id}';
    protected $description = 'Test PDF and attachment downloads';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return 1;
        }
        
        $this->info('🧪 Testing Downloads for: ' . $user->nom_complet);
        
        // Create a token for testing
        $token = $user->createToken('test-token')->plainTextToken;
        
        // Test PDF download
        $this->info("\n📄 Testing PDF Download...");
        $this->testPdfDownload($token, '0198cd9f-f010-72a0-b1a1-fae4610b6d82');
        
        // Test attachment download
        $this->info("\n📎 Testing Attachment Download...");
        $this->testAttachmentDownload($token);
        
        // Clean up token
        $user->tokens()->where('name', 'test-token')->delete();
        $this->info("\n🧹 Test token cleaned up");
        
        return 0;
    }
    
    private function testPdfDownload($token, $withdrawalId)
    {
        try {
            $this->info("Testing PDF download for withdrawal: {$withdrawalId}");
            
            $response = Http::timeout(10) // Set timeout to 10 seconds
                ->withToken($token)
                ->get("http://localhost:8000/api/affiliate/withdrawals/{$withdrawalId}/pdf");
            
            $this->info("PDF Response Status: {$response->status()}");
            
            if ($response->successful()) {
                $this->info("✅ PDF download successful!");
                $this->info("Content-Type: " . $response->header('Content-Type'));
                $this->info("Content-Length: " . strlen($response->body()) . " bytes");
            } else {
                $this->error("❌ PDF download failed");
                $this->error("Response: " . $response->body());
            }
        } catch (\Exception $e) {
            $this->error("❌ PDF download exception: " . $e->getMessage());
            $this->error("Exception type: " . get_class($e));
        }
    }
    
    private function testAttachmentDownload($token)
    {
        // Find an attachment to test
        $attachment = TicketAttachment::whereHas('message.ticket', function ($query) {
            $query->where('requester_id', $this->argument('user_id'));
        })->first();
        
        if (!$attachment) {
            $this->warn("⚠️ No attachments found for testing");
            return;
        }
        
        try {
            $this->info("Testing attachment download: {$attachment->id}");
            $this->info("Attachment path: {$attachment->path}");
            $this->info("Attachment disk: {$attachment->disk}");
            
            $response = Http::timeout(10) // Set timeout to 10 seconds
                ->withToken($token)
                ->get("http://localhost:8000/api/affiliate/tickets/attachments/{$attachment->id}/download");
            
            $this->info("Attachment Response Status: {$response->status()}");
            
            if ($response->successful()) {
                $this->info("✅ Attachment download successful!");
                $this->info("Content-Type: " . $response->header('Content-Type'));
                $this->info("Content-Length: " . strlen($response->body()) . " bytes");
            } else {
                $this->error("❌ Attachment download failed");
                $this->error("Response: " . $response->body());
            }
        } catch (\Exception $e) {
            $this->error("❌ Attachment download exception: " . $e->getMessage());
            $this->error("Exception type: " . get_class($e));
        }
    }
}

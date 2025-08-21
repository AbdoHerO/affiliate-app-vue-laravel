<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Commande;
use App\Models\Ticket;
use App\Models\Withdrawal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestAffiliateEndpoints extends Command
{
    protected $signature = 'affiliate:test-endpoints {user_id}';
    protected $description = 'Test affiliate API endpoints';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return 1;
        }
        
        $this->info("Testing affiliate endpoints for: {$user->nom_complet}");
        
        // Get a sample order
        $order = Commande::where('user_id', $user->id)->first();
        if ($order) {
            $this->info("✅ Sample order found: {$order->id}");
            
            // Test order detail endpoint
            try {
                $orderResource = new \App\Http\Resources\Affiliate\OrderResource($order->load([
                    'boutique:id,nom,adresse',
                    'client:id,nom_complet,telephone,email',
                    'articles.produit:id,titre'
                ]));
                $this->info("✅ Order resource creation successful");
            } catch (\Exception $e) {
                $this->error("❌ Order resource failed: " . $e->getMessage());
            }
        } else {
            $this->warn("⚠️ No orders found for this user");
        }
        
        // Get a sample ticket
        $ticket = Ticket::where('requester_id', $user->id)->first();
        if ($ticket) {
            $this->info("✅ Sample ticket found: {$ticket->id}");
            
            // Test ticket resource
            try {
                $ticketResource = new \App\Http\Resources\Affiliate\TicketResource($ticket->load([
                    'requester:id,nom_complet,email',
                    'messages.sender:id,nom_complet,email'
                ]));
                $this->info("✅ Ticket resource creation successful");
            } catch (\Exception $e) {
                $this->error("❌ Ticket resource failed: " . $e->getMessage());
            }
        } else {
            $this->warn("⚠️ No tickets found for this user");
        }
        
        // Get a sample withdrawal
        $withdrawal = Withdrawal::where('user_id', $user->id)->first();
        if ($withdrawal) {
            $this->info("✅ Sample withdrawal found: {$withdrawal->id}");
            $this->info("   Status: {$withdrawal->status}");
            $this->info("   Amount: {$withdrawal->amount} MAD");
        } else {
            $this->warn("⚠️ No withdrawals found for this user");
        }
        
        $this->info("\n🔧 Route Tests:");
        
        // Test if routes exist
        $routes = [
            'affiliate.withdrawals.pdf',
            'affiliate.tickets.attachments.download'
        ];
        
        foreach ($routes as $routeName) {
            try {
                $url = route($routeName, ['id' => 'test-id']);
                $this->info("✅ Route '{$routeName}' exists: {$url}");
            } catch (\Exception $e) {
                $this->error("❌ Route '{$routeName}' missing: " . $e->getMessage());
            }
        }
        
        return 0;
    }
}

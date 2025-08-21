<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\ProfilAffilie;
use App\Models\GammeAffilie;
use Illuminate\Console\Command;

class FixAffiliateAccess extends Command
{
    protected $signature = 'affiliate:fix-access {user_id}';
    protected $description = 'Fix affiliate access by ensuring proper approval status and profile';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return 1;
        }
        
        $this->info("Current user status:");
        $this->info("Name: {$user->nom_complet}");
        $this->info("Email: {$user->email}");
        $this->info("Approval Status: {$user->approval_status}");
        $this->info("Has affiliate role: " . ($user->hasRole('affiliate') ? 'Yes' : 'No'));
        $this->info("Is approved affiliate: " . ($user->isApprovedAffiliate() ? 'Yes' : 'No'));
        
        // Fix approval status
        if ($user->approval_status !== 'approved') {
            $this->info("Updating approval status to 'approved'...");
            $user->update(['approval_status' => 'approved']);
        }
        
        // Ensure affiliate role
        if (!$user->hasRole('affiliate')) {
            $this->info("Assigning affiliate role...");
            $user->assignRole('affiliate');
        }
        
        // Ensure affiliate profile exists
        if (!$user->profilAffilie) {
            $this->info("Creating affiliate profile...");
            $defaultTier = GammeAffilie::where('code', 'BASIC')->first();
            
            ProfilAffilie::create([
                'utilisateur_id' => $user->id,
                'gamme_id' => $defaultTier?->id,
                'points' => 0,
                'statut' => 'actif',
                'notes_interne' => 'Profile created via fix command on ' . now()->format('Y-m-d H:i'),
            ]);
        }
        
        // Refresh user
        $user->refresh();
        
        $this->info("\nUpdated user status:");
        $this->info("Approval Status: {$user->approval_status}");
        $this->info("Has affiliate role: " . ($user->hasRole('affiliate') ? 'Yes' : 'No'));
        $this->info("Is approved affiliate: " . ($user->isApprovedAffiliate() ? 'Yes' : 'No'));
        $this->info("Has affiliate profile: " . ($user->profilAffilie ? 'Yes' : 'No'));
        
        $this->info("Affiliate access fixed successfully!");
        
        return 0;
    }
}

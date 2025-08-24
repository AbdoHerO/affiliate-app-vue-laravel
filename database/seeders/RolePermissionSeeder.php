<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions for affiliate platform
        $permissions = [
            // Admin permissions
            'manage users',
            'manage affiliates',
            'manage products',
            'manage orders',
            'manage payments',
            'view reports',
            'manage settings',

            // Affiliate permissions
            'create orders',
            'view own orders',
            'view own commissions',
            'view marketing materials',
            'update profile',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles
        $adminRole = Role::create(['name' => 'admin']);
        $affiliateRole = Role::create(['name' => 'affiliate']);

        // Assign permissions to admin role (admin has all permissions)
        $adminRole->givePermissionTo(Permission::all());

        // Assign specific permissions to affiliate role
        $affiliateRole->givePermissionTo([
            'create orders',
            'view own orders',
            'view own commissions',
            'view marketing materials',
            'update profile',
        ]);

        // Create default admin user
        $adminUser = User::create([
            'nom_complet' => 'Admin User',
            'email' => 'admin@cod.test',
            'mot_de_passe_hash' => Hash::make('password'),
            'email_verifie' => true,
            'statut' => 'actif',
            'kyc_statut' => 'non_requis',
        ]);

        // Assign admin role to the user
        $adminUser->assignRole('admin');

        // Create a sample affiliate user for testing
        $affiliateUser = User::create([
            'nom_complet' => 'Test Affiliate',
            'email' => 'affiliate@cod.test',
            'mot_de_passe_hash' => Hash::make('password'),
            'email_verifie' => true,
            'statut' => 'actif',
            'kyc_statut' => 'non_requis',
            'approval_status' => 'approved', // Ensure affiliate is approved for testing
        ]);

        // Assign affiliate role to the user
        $affiliateUser->assignRole('affiliate');

        // Create affiliate profile for the test user
        $defaultGamme = \App\Models\GammeAffilie::firstOrCreate([
            'code' => 'STANDARD',
        ], [
            'libelle' => 'Standard',
            'actif' => true,
        ]);

        \App\Models\ProfilAffilie::firstOrCreate([
            'utilisateur_id' => $affiliateUser->id,
        ], [
            'gamme_id' => $defaultGamme->id,
            'statut' => 'actif',
            'points' => 0,
        ]);

        $this->command->info('Roles, permissions, and users created successfully!');
        $this->command->info('Admin user: admin@cod.test / password');
        $this->command->info('Affiliate user: affiliate@cod.test / password');
    }
}

<?php

namespace App\Providers;

use App\Models\Commande;
use App\Models\User;
use App\Policies\CommandePolicy;
use App\Policies\UserApprovalPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Commande::class => CommandePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Register gates for user approval
        Gate::define('viewApprovalQueue', [UserApprovalPolicy::class, 'viewApprovalQueue']);
        Gate::define('approveUser', [UserApprovalPolicy::class, 'approve']);
        Gate::define('refuseUser', [UserApprovalPolicy::class, 'refuse']);
        Gate::define('resendVerification', [UserApprovalPolicy::class, 'resendVerification']);

        // Additional gates for order management
        Gate::define('manageAllOrders', function (User $user) {
            return $user->hasRole('admin');
        });

        Gate::define('createOrder', function (User $user) {
            return $user->isApprovedAffiliate();
        });

        Gate::define('viewOwnOrders', function (User $user) {
            return $user->hasRole('affiliate') && $user->isApprovedAffiliate();
        });
    }
}

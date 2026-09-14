<?php

namespace App\Providers;

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
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::before(function ($user, $ability) {
            if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
                return true;
            }
        });

        Gate::define('quyen', function ($user, $maQuyen) {
            if ($user->isAdmin()) {
                return true;
            }
            if (is_array($maQuyen)) {
                return $user->coMotTrongCacQuyen($maQuyen);
            }
            $permissions = array_filter(array_map('trim', explode(',', $maQuyen)));
            return $user->coMotTrongCacQuyen($permissions);
        });
    }
}

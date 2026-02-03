<?php

namespace App\Providers;

use App\Models\Allocation\Allocation;
use App\Policies\AllocationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register policies for models in subdirectories
        Gate::policy(Allocation::class, AllocationPolicy::class);
    }
}

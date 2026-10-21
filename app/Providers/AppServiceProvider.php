<?php

namespace App\Providers;

use App\Models\Proposal;
use App\Observers\ProposalObserver;
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
        Proposal::observe(ProposalObserver::class);
    }
}
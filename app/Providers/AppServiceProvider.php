<?php

namespace App\Providers;

use App\Models\Proposal;
use App\Models\Task;
use App\Observers\ProposalObserver;
use App\Observers\TaskObserver;
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
        Task::observe(TaskObserver::class);
    }
}
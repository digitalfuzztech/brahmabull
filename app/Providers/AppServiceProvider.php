<?php

namespace App\Providers;

use App\Models\BrahmaDeposit;
use App\Models\BrahmaPlayRequest;
use App\Models\Deposit;
use App\Observers\PromotionalSpinEligibilityObserver;
use Illuminate\Support\Facades\URL;
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
        Deposit::observe(PromotionalSpinEligibilityObserver::class);
        BrahmaDeposit::observe(PromotionalSpinEligibilityObserver::class);
        BrahmaPlayRequest::observe(PromotionalSpinEligibilityObserver::class);

        if (app()->environment('production')) {
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme('https');
        }
    }
}

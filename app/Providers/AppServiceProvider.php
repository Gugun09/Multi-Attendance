<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Leave;
use App\Models\Attendance;
use App\Observers\LeaveObserver;
use App\Observers\AttendanceObserver;

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
        // Register model observers
        Leave::observe(LeaveObserver::class);
        Attendance::observe(AttendanceObserver::class);
    }
}

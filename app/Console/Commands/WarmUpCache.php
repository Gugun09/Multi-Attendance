<?php

namespace App\Console\Commands;

use App\Services\CacheService;
use Illuminate\Console\Command;

class WarmUpCache extends Command
{
    protected $signature = 'cache:warmup {--force : Force cache refresh}';
    protected $description = 'Warm up application cache with frequently accessed data';

    public function handle()
    {
        $this->info('Starting cache warm-up...');

        if ($this->option('force')) {
            $this->info('Clearing existing cache...');
            $this->call('cache:clear');
        }

        // Warm up common caches
        CacheService::warmUpCache();

        $this->info('Cache warm-up completed!');
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OptimizeDatabase extends Command
{
    protected $signature = 'db:optimize {--analyze : Run ANALYZE TABLE}';
    protected $description = 'Optimize database tables and indexes';

    public function handle()
    {
        $this->info('Starting database optimization...');

        $tables = [
            'users',
            'tenants',
            'attendances',
            'leaves',
            'leave_balances',
            'security_logs',
            'login_attempts',
        ];

        foreach ($tables as $table) {
            $this->optimizeTable($table);
        }

        if ($this->option('analyze')) {
            $this->analyzeDatabase();
        }

        $this->info('Database optimization completed!');
    }

    private function optimizeTable(string $table): void
    {
        $this->info("Optimizing table: {$table}");

        try {
            // For MySQL
            if (DB::getDriverName() === 'mysql') {
                DB::statement("OPTIMIZE TABLE {$table}");
                $this->line("✓ Optimized {$table}");
            }
            
            // For PostgreSQL
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("VACUUM ANALYZE {$table}");
                $this->line("✓ Vacuumed {$table}");
            }
        } catch (\Exception $e) {
            $this->error("Failed to optimize {$table}: " . $e->getMessage());
        }
    }

    private function analyzeDatabase(): void
    {
        $this->info('Analyzing database performance...');

        // Check for missing indexes
        $this->checkMissingIndexes();
        
        // Check for slow queries
        $this->checkSlowQueries();
        
        // Check table sizes
        $this->checkTableSizes();
    }

    private function checkMissingIndexes(): void
    {
        $this->info('Checking for potential missing indexes...');

        $queries = [
            "SELECT table_name, column_name FROM information_schema.columns 
             WHERE table_schema = DATABASE() 
             AND column_name IN ('tenant_id', 'user_id', 'created_at', 'updated_at')
             AND table_name NOT IN (
                 SELECT DISTINCT table_name 
                 FROM information_schema.statistics 
                 WHERE table_schema = DATABASE()
             )",
        ];

        foreach ($queries as $query) {
            try {
                $results = DB::select($query);
                if (!empty($results)) {
                    $this->warn('Potential missing indexes found:');
                    foreach ($results as $result) {
                        $this->line("- {$result->table_name}.{$result->column_name}");
                    }
                }
            } catch (\Exception $e) {
                // Skip if query fails (different DB engines)
            }
        }
    }

    private function checkSlowQueries(): void
    {
        $this->info('Checking for slow queries...');

        try {
            // Enable slow query log temporarily
            DB::statement("SET GLOBAL slow_query_log = 'ON'");
            DB::statement("SET GLOBAL long_query_time = 1");
            
            $this->line('✓ Slow query logging enabled (queries > 1 second)');
        } catch (\Exception $e) {
            $this->warn('Could not enable slow query logging: ' . $e->getMessage());
        }
    }

    private function checkTableSizes(): void
    {
        $this->info('Checking table sizes...');

        try {
            $sizes = DB::select("
                SELECT 
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                    table_rows
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                ORDER BY (data_length + index_length) DESC
                LIMIT 10
            ");

            $this->table(['Table', 'Size (MB)', 'Rows'], array_map(function($size) {
                return [$size->table_name, $size->size_mb, $size->table_rows];
            }, $sizes));
        } catch (\Exception $e) {
            $this->warn('Could not retrieve table sizes: ' . $e->getMessage());
        }
    }
}
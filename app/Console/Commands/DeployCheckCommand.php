<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DeployCheckCommand extends Command
{
    protected $signature = 'deploy:check';
    protected $description = 'Kiểm tra deployment: file critical, vendor, .env, storage permissions';

    public function handle(): int
    {
        $this->info('=== DEPLOY HEALTH CHECK ===');
        $this->newLine();

        $allPass = true;

        // 1. Critical files
        $this->line('1. Critical files:');
        $criticalFiles = [
            'public/index.php',
            'public/.htaccess',
            '.htaccess',
            'server.php',
        ];
        foreach ($criticalFiles as $file) {
            $exists = File::exists(base_path($file));
            $this->logResult($exists, $file);
            if (! $exists) {
                $allPass = false;
            }
        }
        $this->newLine();

        // 2. Vendor
        $this->line('2. Composer autoload:');
        $vendorExists = File::exists(base_path('vendor/autoload.php'));
        $this->logResult($vendorExists, 'vendor/autoload.php');
        if (! $vendorExists) {
            $allPass = false;
        }
        $this->newLine();

        // 3. .env + APP_KEY
        $this->line('3. Environment:');
        $envExists = File::exists(base_path('.env'));
        $this->logResult($envExists, '.env');
        if (! $envExists) {
            $allPass = false;
        }

        $appKey = config('app.key');
        $hasKey = $appKey && str_starts_with($appKey, 'base64:');
        $this->logResult($hasKey, 'APP_KEY set in .env');
        if (! $hasKey) {
            $allPass = false;
        }
        $this->newLine();

        // 4. Storage writable
        $this->line('4. Storage writable:');
        $storagePaths = [
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'bootstrap/cache',
        ];
        foreach ($storagePaths as $path) {
            $fullPath = base_path($path);
            File::ensureDirectoryExists($fullPath);
            $writable = is_writable($fullPath);
            $this->logResult($writable, $path);
            if (! $writable) {
                $allPass = false;
            }
        }
        $this->newLine();

        // 5. Migrations pending check (best effort)
        $this->line('5. Database:');
        try {
            $hasUsers = \Illuminate\Support\Facades\Schema::hasTable('users');
            $this->logResult($hasUsers, 'users table exists');
            if (! $hasUsers) {
                $allPass = false;
            }
        } catch (\Throwable $e) {
            $this->warn('   SKIP - cannot connect DB: ' . $e->getMessage());
        }
        $this->newLine();

        // Result
        if ($allPass) {
            $this->info('>>> ALL CHECKS PASSED <<<');
            return self::SUCCESS;
        }

        $this->error('>>> SOME CHECKS FAILED - fix before going live <<<');
        return self::FAILURE;
    }

    private function logResult(bool $ok, string $label): void
    {
        $icon = $ok ? '<info>[PASS]</info>' : '<error>[FAIL]</error>';
        $this->line("   {$icon} {$label}");
    }
}

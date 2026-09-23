<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class B2bCheckSchemaCommand extends Command
{
    protected $signature = 'b2b:check-schema
        {--fix : Chỉ in danh sách bảng thiếu, không fail}';

    protected $description = 'Kiểm tra các bảng B2B còn thiếu để chạy migrate.';

    /**
     * Danh sách bảng B2B bắt buộc.
     */
    protected array $requiredTables = [
        'b2b_order_outbox',
        'b2b_idempotency_records',
        'webhook_outbox',
        'lich_su_gui_webhook',
        'khoan_giu_han_muc',
        'so_phat_sinh_cong_no',
        'cau_hinh_api_dai_ly',
    ];

    public function handle(): int
    {
        $missing = [];
        foreach ($this->requiredTables as $t) {
            $exists = Schema::hasTable($t);
            $this->line(sprintf('%-32s %s', $t, $exists ? '<fg=green>OK</fg=green>' : '<fg=red>THIẾU</fg=red>'));
            if (!$exists) {
                $missing[] = $t;
            }
        }

        // Reset cache để AppServiceProvider phát hiện lại ở request sau
        Cache::forget('b2b:schema_check');

        if (empty($missing)) {
            $this->info('✅ Tất cả bảng B2B đã sẵn sàng.');
            return self::SUCCESS;
        }

        $msg = '❌ Thiếu ' . count($missing) . ' bảng: ' . implode(', ', $missing);
        $this->error($msg);
        $this->line('');
        $this->warn('Khắc phục: chạy lệnh sau để tạo các bảng còn thiếu');
        $this->line('    <fg=yellow>php artisan migrate --force</>');
        $this->line('');

        Log::critical('B2B Schema thiếu: ' . implode(', ', $missing));

        return $this->option('fix') ? self::SUCCESS : self::FAILURE;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class MaintenanceController extends Controller
{
    /**
     * Trang bảo trì (Gồm 2 Tab: Bộ nhớ cache & Nhật ký trang web).
     */
    public function index(Request $request): View
    {
        $caches = $this->getCacheList();
        $logs = $this->parseLogFile(150);

        return view('admin.maintenance', [
            'caches' => $caches,
            'logs' => $logs,
            'logFileExists' => File::exists(storage_path('logs/laravel.log')),
            'logFileSize' => File::exists(storage_path('logs/laravel.log')) ? round(File::size(storage_path('logs/laravel.log')) / 1024, 2) . ' KB' : '0 KB',
        ]);
    }

    /**
     * Xóa tất cả bộ nhớ cache của hệ thống.
     */
    public function clearAllCache(): RedirectResponse
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');
            Artisan::call('config:clear');
            Artisan::call('event:clear');
            Cache::flush();
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Lỗi khi xóa bộ nhớ cache: ' . $e->getMessage()]);
        }

        return back()->with('success', 'Đã xóa toàn bộ bộ nhớ cache hệ thống thành công.');
    }

    /**
     * Xóa từng bộ nhớ cache cụ thể.
     */
    public function clearSpecificCache(string $type): RedirectResponse
    {
        try {
            switch ($type) {
                case 'application':
                    Cache::flush();
                    Artisan::call('cache:clear');
                    $name = 'Toàn bộ cache ứng dụng (Application Cache)';
                    break;
                case 'views':
                    Artisan::call('view:clear');
                    $name = 'Bộ nhớ cache giao diện (View Cache)';
                    break;
                case 'routes':
                    Artisan::call('route:clear');
                    $name = 'Bộ nhớ cache định tuyến (Route Cache)';
                    break;
                case 'config':
                    Artisan::call('config:clear');
                    $name = 'Bộ nhớ cache cấu hình (Config Cache)';
                    break;
                case 'permissions':
                    app(\App\Services\Authorization\PermissionCacheService::class)->clearAll();
                    Cache::forget('sp_permissions_cache');
                    $name = 'Bộ nhớ phân quyền (Permissions Cache)';
                    break;
                case 'providers':
                    // Xóa cache trạng thái Circuit Breaker & Provider cụ thể theo kết nối
                    $ketNoiIds = \App\Models\KetNoiNhaCungCap::pluck('id');
                    foreach ($ketNoiIds as $kId) {
                        Cache::forget("circuit_breaker_fails_{$kId}");
                        Cache::forget("circuit_breaker_tripped_{$kId}");
                        Cache::forget("ncc_provider_{$kId}");
                    }
                    Cache::forget('circuit_breaker_*');
                    $name = 'Bộ nhớ nhà cung cấp & Circuit Breaker';
                    break;
                case 'products':
                case 'discounts':
                    Cache::forget('products_cache');
                    Cache::forget('discounts_cache');
                    $name = 'Bộ nhớ bảng giá & chiết khấu sản phẩm';
                    break;
                default:
                    Cache::forget($type);
                    $name = "Bộ nhớ cache '{$type}'";
                    break;
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => "Không thể xóa cache {$type}: " . $e->getMessage()]);
        }

        return back()->with('success', "Đã xóa {$name} thành công.");
    }

    /**
     * API tải lại log theo thời gian thực (Realtime AJAX).
     */
    public function getLogs(): JsonResponse
    {
        $logs = $this->parseLogFile(150);
        return response()->json([
            'success' => true,
            'logs' => $logs,
        ]);
    }

    /**
     * Tải xuống toàn bộ nhật ký (ZIP hoặc file .log).
     */
    public function downloadLogs(): BinaryFileResponse|RedirectResponse
    {
        $logPath = storage_path('logs');
        $files = File::glob($logPath . '/*.log');

        if (empty($files)) {
            return back()->withErrors(['error' => 'Chưa có tập tin nhật ký nào trong thư mục logs.']);
        }

        $zipPath = storage_path('logs/web_logs_' . date('Ymd_His') . '.zip');
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();

            return response()->download($zipPath)->deleteFileAfterSend(true);
        }

        // Nếu không tạo được zip thì tải trực tiếp laravel.log
        return response()->download(storage_path('logs/laravel.log'));
    }

    /**
     * Danh sách các loại cache trong hệ thống.
     */
    private function getCacheList(): array
    {
        return [
            ['key' => 'permissions', 'name' => 'Permissions Cache', 'desc' => 'Bộ nhớ đệm phân quyền vai trò và người dùng'],
            ['key' => 'views', 'name' => 'View / Blade Compiled Cache', 'desc' => 'Bộ nhớ đệm giao diện mẫu Blade HTML'],
            ['key' => 'routes', 'name' => 'Route URL Cache', 'desc' => 'Bộ nhớ đệm định tuyến URL và Middleware'],
            ['key' => 'config', 'name' => 'Config Cache', 'desc' => 'Bộ nhớ đệm tệp cấu hình hệ thống (.env & config)'],
            ['key' => 'providers', 'name' => 'Provider & Circuit Breaker', 'desc' => 'Bộ nhớ đệm kết nối API và trạng thái ngắt mạch sự cố'],
            ['key' => 'products', 'name' => 'Products & Discounts Cache', 'desc' => 'Bộ nhớ đệm chiết khấu và danh mục sản phẩm'],
        ];
    }

    /**
     * Phân tích tệp nhật ký (laravel.log) thành mảng có cấu trúc và phân loại màu sắc.
     * Đọc an toàn theo tail buffer tránh cạn kiệt bộ nhớ (OOM) khi file log dung lượng lớn.
     */
    private function parseLogFile(int $maxLines = 150): array
    {
        $logFile = storage_path('logs/laravel.log');
        if (!File::exists($logFile)) {
            return [];
        }

        // Đọc tail buffer tối đa 256KB cuối file tránh tràn bộ nhớ
        $fileSize = (int) @filesize($logFile);
        $chunkSize = 256 * 1024;
        $handle = @fopen($logFile, 'rb');
        if (!$handle) {
            return [];
        }

        if ($fileSize > $chunkSize) {
            fseek($handle, -$chunkSize, SEEK_END);
        }
        $content = '';
        while (!feof($handle)) {
            $content .= fread($handle, 8192);
        }
        fclose($handle);

        $rawLines = explode("\n", trim($content));
        $rawLines = array_slice($rawLines, -$maxLines);

        $parsed = [];
        $pattern = '/^\[(?<date>\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:\d{2})?)\]\s+(?:(?<env>\w+)\.)?(?<level>EMERGENCY|ALERT|CRITICAL|ERROR|WARNING|WARN|NOTICE|INFO|DEBUG):\s+(?<message>.*)$/i';

        foreach ($rawLines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (preg_match($pattern, $line, $matches)) {
                $level = strtoupper($matches['level'] ?? 'INFO');
                if ($level === 'WARNING') $level = 'WARN';

                $parsed[] = [
                    'type' => 'log',
                    'level' => $level,
                    'time' => $matches['date'] ?? date('Y-m-d H:i:s'),
                    'thread' => rand(10, 99),
                    'message' => $matches['message'] ?? '',
                    'raw' => $line,
                ];
            } else {
                // Dòng bổ trợ (Stacktrace hoặc context)
                $parsed[] = [
                    'type' => 'stacktrace',
                    'level' => 'DEBUG',
                    'time' => '',
                    'thread' => '',
                    'message' => $line,
                    'raw' => $line,
                ];
            }
        }

        return array_reverse($parsed);
    }
}

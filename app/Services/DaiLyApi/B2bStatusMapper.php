<?php

namespace App\Services\DaiLyApi;

class B2bStatusMapper
{
    /**
     * Danh sách trạng thái công khai chuẩn của B2B API v1.
     */
    public const PUBLIC_PENDING = 'pending';
    public const PUBLIC_PROCESSING = 'processing';
    public const PUBLIC_SUCCESS = 'success';
    public const PUBLIC_FAILED = 'failed';
    public const PUBLIC_MANUAL_REVIEW = 'manual_review';

    public const ALL_PUBLIC_STATUSES = [
        self::PUBLIC_PENDING,
        self::PUBLIC_PROCESSING,
        self::PUBLIC_SUCCESS,
        self::PUBLIC_FAILED,
        self::PUBLIC_MANUAL_REVIEW,
    ];

    /**
     * Ánh xạ trạng thái nội bộ trong cơ sở dữ liệu sang trạng thái công khai B2B API.
     * Tuyệt đối không rò rỉ trạng thái nội bộ (như PROVIDER_PENDING, REFUND_PENDING, QUEUED...).
     */
    public static function toPublic(?string $internalStatus): string
    {
        if (!$internalStatus) {
            return self::PUBLIC_PENDING;
        }

        $upper = strtoupper(trim($internalStatus));

        return match ($upper) {
            'QUEUED', 'CHO_XU_LY', 'PENDING' => self::PUBLIC_PENDING,
            'PROCESSING', 'DANG_XU_LY', 'PROVIDER_PENDING' => self::PUBLIC_PROCESSING,
            'SUCCESS', 'HOAN_THANH' => self::PUBLIC_SUCCESS,
            'FAILED', 'THAT_BAI', 'REFUNDED' => self::PUBLIC_FAILED,
            'MANUAL_REVIEW', 'REFUND_PENDING' => self::PUBLIC_MANUAL_REVIEW,
            default => self::PUBLIC_PROCESSING,
        };
    }

    /**
     * Ánh xạ bộ lọc từ trạng thái công khai sang danh sách các trạng thái nội bộ tương ứng trong DB.
     * Bao phủ cả các trạng thái mới (tiếng Anh) và trạng thái cũ (tiếng Việt legacy).
     */
    public static function toInternalFilter(string $publicStatus): array
    {
        $lower = strtolower(trim($publicStatus));

        return match ($lower) {
            self::PUBLIC_PENDING => ['QUEUED', 'CHO_XU_LY', 'PENDING'],
            self::PUBLIC_PROCESSING => ['PROCESSING', 'DANG_XU_LY', 'PROVIDER_PENDING'],
            self::PUBLIC_SUCCESS => ['SUCCESS', 'HOAN_THANH'],
            self::PUBLIC_FAILED => ['FAILED', 'THAT_BAI', 'REFUNDED'],
            self::PUBLIC_MANUAL_REVIEW => ['MANUAL_REVIEW', 'REFUND_PENDING'],
            default => [strtoupper($publicStatus)],
        };
    }

    /**
     * Kiểm tra trạng thái công khai có phải là trạng thái cuối cùng hay không.
     */
    public static function isFinalPublicStatus(string $publicStatus): bool
    {
        return in_array($publicStatus, [self::PUBLIC_SUCCESS, self::PUBLIC_FAILED], true);
    }

    /**
     * Mã kết quả công khai tương ứng với trạng thái công khai.
     * Đây là hợp đồng ổn định dành cho đối tác, KHÔNG phụ thuộc trạng thái nội bộ.
     */
    public static function resultCode(string $publicStatus): string
    {
        return match ($publicStatus) {
            self::PUBLIC_SUCCESS => '00',
            self::PUBLIC_FAILED => '01',
            self::PUBLIC_MANUAL_REVIEW => '02',
            self::PUBLIC_PROCESSING => '03',
            default => '04', // pending
        };
    }

    /**
     * Diễn giải trạng thái công khai sang tiếng Việt cho tài liệu/tra cứu.
     */
    public static function moTa(string $publicStatus): string
    {
        return match ($publicStatus) {
            self::PUBLIC_PENDING => 'Đã tiếp nhận, đang chờ xử lý',
            self::PUBLIC_PROCESSING => 'Đã gửi nhà cung cấp, chưa có kết quả cuối',
            self::PUBLIC_SUCCESS => 'Nạp thành công',
            self::PUBLIC_FAILED => 'Nạp thất bại chắc chắn',
            self::PUBLIC_MANUAL_REVIEW => 'Chưa xác định kết quả, đang đối soát thủ công',
            default => 'Không xác định',
        };
    }
}

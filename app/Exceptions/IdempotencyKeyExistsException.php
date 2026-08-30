<?php

namespace App\Exceptions;

use App\Models\DonHang;
use Exception;

/**
 * Ném ra khi tạo đơn hàng với idempotency_key đã tồn tại cho cùng user.
 * Giúp controller phân biệt đây là request trùng lặp (không phải lỗi) và trả về đơn cũ.
 */
class IdempotencyKeyExistsException extends Exception
{
    public function __construct(private readonly DonHang $existingOrder)
    {
        parent::__construct('Đơn hàng với idempotency key này đã tồn tại.');
    }

    public function getOrder(): DonHang
    {
        return $this->existingOrder;
    }
}


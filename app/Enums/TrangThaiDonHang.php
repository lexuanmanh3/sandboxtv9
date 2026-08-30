<?php

namespace App\Enums;

enum TrangThaiDonHang: string
{
    case CREATED = 'CREATED';
    case WAITING_PAYMENT = 'WAITING_PAYMENT';
    case PAID = 'PAID';
    case QUEUED = 'QUEUED';
    case PROCESSING = 'PROCESSING';
    case PROVIDER_PENDING = 'PROVIDER_PENDING';
    case SUCCESS = 'SUCCESS';
    case FAILED = 'FAILED';
    case REFUND_PENDING = 'REFUND_PENDING';
    case REFUNDED = 'REFUNDED';
    case MANUAL_REVIEW = 'MANUAL_REVIEW';
}

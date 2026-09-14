<?php

namespace App\Http\Controllers\Api\B2B\V1;

use App\Http\Controllers\Controller;
use App\Models\DaiLyApi;
use App\Services\DaiLyApi\B2bCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function __construct(
        protected B2bCreditService $creditService
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var DaiLyApi $partner */
        $partner = $request->attributes->get('b2b_partner');

        $creditInfo = $this->creditService->tinhHanMucKhaDung($partner);

        return response()->json([
            'success' => true,
            'partner_code' => $partner->ma_dai_ly_api,
            'partner_name' => $partner->ten_dai_ly_api,
            'credit' => [
                'credit_limit' => $creditInfo['han_muc_duoc_cap'],
                'current_debt' => $creditInfo['cong_no_hien_tai'],
                'held_amount' => $creditInfo['khoan_dang_giu'],
                'available_credit' => $creditInfo['han_muc_kha_dung'],
                'warning_threshold' => $creditInfo['nguong_canh_bao'],
                'is_warning' => $creditInfo['canh_bao_vuot_nguong'],
                'can_receive_orders' => $creditInfo['cho_phep_nhan_don'],
            ],
            'currency' => 'VND',
            'server_time' => now()->toIso8601String(),
        ]);
    }
}

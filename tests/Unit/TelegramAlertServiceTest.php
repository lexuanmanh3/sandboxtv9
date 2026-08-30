<?php

namespace Tests\Unit;

use App\Models\KetNoiNhaCungCap;
use App\Models\LanGoiNhaCungCap;
use App\Models\NhaCungCap;
use App\Services\Alert\TelegramAlertService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramAlertServiceTest extends TestCase
{
    public function test_send_message_calls_telegram_api_successfully(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 123]], 200),
        ]);

        $service = new TelegramAlertService();
        $result = $service->sendMessage('-100123456789', 'Test cảnh báo', 'bot123:ABC-XYZ');

        $this->assertTrue($result);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'bot123:ABC-XYZ/sendMessage')
            && $request['chat_id'] === '-100123456789'
        );
    }

    public function test_alert_slow_transaction_sends_alert_when_enabled(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        config(['services.telegram.bot_token' => 'BOT_TOKEN_123']);

        $provider = new NhaCungCap();
        $provider->forceFill(['ten_ncc' => 'NCC HPZ', 'ma_ncc' => 'HPZ']);

        $connection = new KetNoiNhaCungCap();
        $connection->forceFill([
            'ten_ket_noi' => 'Kết nối HPZ',
            'cau_hinh_canh_bao_loi' => [
                'bat_canh_bao' => true,
                'nhom_canh_bao_chat_id' => '-100999',
            ],
        ]);
        $connection->setRelation('nhaCungCap', $provider);

        $lanGoi = new LanGoiNhaCungCap();
        $lanGoi->forceFill([
            'partner_ref_id' => 'REF123456',
            'ma_san_pham_ncc' => 'VIETTEL_10K',
        ]);
        $lanGoi->setRelation('ketNoi', $connection);

        $service = new TelegramAlertService();
        $sent = $service->alertSlowTransaction($lanGoi, 8.5, 5, $connection);

        $this->assertTrue($sent);
        Http::assertSent(fn ($request) => $request['chat_id'] === '-100999'
            && str_contains($request['text'], 'CẢNH BÁO XỬ LÝ GIAO DỊCH CHẬM')
        );
    }

    public function test_circuit_breaker_alert_sends_warning_message(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        config(['services.telegram.bot_token' => 'BOT_TOKEN_123']);

        $provider = new NhaCungCap();
        $provider->forceFill(['ten_ncc' => 'NCC AppotaPay', 'ma_ncc' => 'APPOTAPAY']);

        $connection = new KetNoiNhaCungCap();
        $connection->forceFill([
            'id' => 1,
            'ten_ket_noi' => 'Kết nối AppotaPay',
            'cau_hinh_canh_bao_loi' => [
                'nhom_canh_bao_chat_id' => '-100888',
            ],
        ]);
        $connection->setRelation('nhaCungCap', $provider);

        $service = new TelegramAlertService();
        $sent = $service->alertCircuitBreakerTriggered($connection, 5, 300);

        $this->assertTrue($sent);
        Http::assertSent(fn ($request) => $request['chat_id'] === '-100888'
            && str_contains($request['text'], 'CIRCUIT BREAKER')
            && str_contains($request['text'], '300 giây')
        );
    }
}

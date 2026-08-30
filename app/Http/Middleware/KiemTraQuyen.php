<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Audit\AuditService;

class KiemTraQuyen
{
    public function handle(Request $request, Closure $next, string $maQuyen): Response
    {
        // NOTE: Neu chua dang nhap thi day ve login.
        // Middleware nay chi xu ly quyen truy cap module/trang sau khi da co user.
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // NOTE: Lay user hien tai de kiem tra quyen tu cac vai tro dang duoc gan.
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        // NOTE: Cho phep route truyen mot hoac nhieu ma quyen, cach nhau bang dau phay.
        // Vi du: quyen:account.access hoac quyen:account.access,role.access.
        $danhSachQuyen = collect(explode(',', $maQuyen))
            ->map(fn ($quyen) => trim($quyen))
            ->filter()
            ->values()
            ->all();

        // NOTE: Neu user khong co bat ky quyen truy cap nao trong danh sach thi tra ve 403.
        if (!$user || ! $user->dangHoatDong() || !$user->coMotTrongCacQuyen($danhSachQuyen)) {
            app(AuditService::class)->record(
                'authorization.denied',
                $user,
                null,
                ['required_permissions' => $danhSachQuyen],
                'that_bai'
            );
            abort(403, 'Bạn không có quyền truy cập chức năng này.');
        }

        // NOTE: Co quyen truy cap thi cho request di tiep vao controller/view.
        return $next($request);
    }
}

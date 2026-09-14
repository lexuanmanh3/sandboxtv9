<?php

namespace App\Http\Middleware;

use App\Services\Authorization\PermissionCacheService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && ! $user->dangHoatDong()) {
            app(PermissionCacheService::class)->forgetUser((int) $user->getKey());
            if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return $request->expectsJson()
                ? response()->json(['message' => 'Tài khoản không còn hoạt động.'], 403)
                : redirect()->route('login')->withErrors(['ten_dang_nhap' => 'Tài khoản không còn hoạt động.']);
        }

        return $next($request);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ChangeAdminPasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:change-password {username=admin : Tên đăng nhập của tài khoản Admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Đổi mật khẩu tài khoản Quản trị viên an toàn qua Terminal';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $username = $this->argument('username');
        $user = User::where('ten_dang_nhap', $username)->first();

        if (!$user) {
            $this->error("Không tìm thấy tài khoản có tên đăng nhập '{$username}'.");
            return self::FAILURE;
        }

        $password = $this->secret('Nhập mật khẩu mới (tối thiểu 8 ký tự):');
        if (strlen($password ?? '') < 8) {
            $this->error('Mật khẩu quá ngắn, yêu cầu ít nhất 8 ký tự.');
            return self::FAILURE;
        }

        $confirm = $this->secret('Nhập lại mật khẩu để xác nhận:');
        if ($password !== $confirm) {
            $this->error('Mật khẩu xác nhận không khớp.');
            return self::FAILURE;
        }

        $user->update([
            'password' => Hash::make($password),
            'bat_buoc_doi_mat_khau' => false,
        ]);

        $this->info("Đổi mật khẩu cho tài khoản '{$username}' thành công!");
        return self::SUCCESS;
    }
}

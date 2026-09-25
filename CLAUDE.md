# Project Rules — Laravel Sandbox (Hostinger deploy)

## TUYỆT ĐỐI KHÔNG XÓA CÁC FILE CRITICAL

Những file sau **bắt buộc phải tồn tại** để Laravel chạy được trên Hostinger (Apache). Nếu thiếu → **toàn bộ site trả về 404**:

- `public/index.php` — front controller của Laravel
- `public/.htaccess` — Apache rewrite rules cho public/
- `.htaccess` (gốc) — redirect request về `public/`
- `server.php` — router cho PHP built-in server (dev local)

**Chỉ được dùng `Edit` để sửa nội dung, tuyệt đối không dùng `git rm` hoặc `rm` để xóa.**

## Deploy lên Hostinger

Sau khi pull code mới trên Hostinger, chạy:

```bash
php artisan deploy:check
```

Command này kiểm tra: file critical, vendor/, .env, APP_KEY, và quyền ghi storage/.

## Git hook

Kích hoạt pre-commit hook (chặn commit xóa file critical):

```bash
git config core.hooksPath .githooks
```

Chỉ cần chạy một lần sau khi clone repo.

## Cấu hình document root

Hostinger document root phải trỏ vào thư mục `public/` của project (ví dụ `public_html/public`).
Nếu không chỉnh được document root, root `.htaccess` sẽ tự redirect vào `public/`.

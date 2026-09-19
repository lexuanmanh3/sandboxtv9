# HƯỚNG DẪN VẬN HÀNH, PHỤC HỒI & TRIỂN KHAI — B2B API V1

Tài liệu này dành cho đội vận hành (DevOps / SRE) triển khai và giám sát hệ thống B2B.
Phạm vi: hàng đợi, lịch chạy nền, khoá thuê (lease), cache dùng chung, thứ tự cập nhật,
phương án quay lui và giám sát.

> **Nguyên tắc bất di bất dịch:** hệ thống KHÔNG bao giờ tự hoàn tiền, tự giải phóng hạn mức
> hay tự kết luận thất bại chỉ vì timeout, hết lượt retry, hết thời hạn tra cứu hay worker chết.
> Mọi thao tác tiền chỉ được kết luận khi có **bằng chứng dứt khoát** từ nhà cung cấp.

---

## 1. THÀNH PHẦN BẮT BUỘC PHẢI CHẠY

Hệ thống sẽ **sai tiền** nếu thiếu bất kỳ thành phần nào dưới đây. Đây không phải thành phần
tùy chọn.

| # | Thành phần | Lệnh | Tần suất | Vì sao bắt buộc |
| :-- | :--- | :--- | :--- | :--- |
| 1 | Queue worker | `php artisan queue:work --queue=default --tries=3 --timeout=60` | Thường trực (≥2 tiến trình) | Xử lý lệnh nạp, gửi webhook |
| 2 | Scheduler (cron) | `php artisan schedule:run` | Mỗi phút | Chạy 5 lệnh nền bên dưới |
| 3 | Cache dùng chung | Redis / Memcached | Thường trực | Chống replay nonce & rate limit phải dùng chung giữa các node |

### 1.1. Cron entry bắt buộc

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

### 1.2. Lịch chạy nền (đã đăng ký trong `app/Console/Kernel.php`)

| Lệnh | Tần suất | Chức năng |
| :--- | :--- | :--- |
| `b2b:recover-outbox` | Mỗi phút | Phục hồi đơn B2B kẹt ở outbox, đẩy lại job xử lý |
| `b2b:retry-webhooks` | Mỗi phút | Gửi lại webhook thất bại theo backoff |
| `topup:reconcile-pending` | 5 phút | Tra cứu nền đơn chưa rõ kết quả |
| `b2b:health-check` | 15 phút | Phát hiện khoản giữ treo, lệch sổ, tồn đọng outbox |
| `provider:sync-products appotapay` | Mỗi giờ | Đồng bộ danh mục sản phẩm |

---

## 2. CẤU HÌNH BẮT BUỘC TRƯỚC KHI CHẠY

### 2.1. Cache dùng chung (BẮT BUỘC)

```dotenv
B2B_SECURITY_CACHE_STORE=redis
```

**Vì sao:** nonce chống replay và bộ đếm rate limit phải nhìn thấy nhau giữa mọi node.
Nếu để `file` / `array` / `null`, mỗi node có bộ nhớ riêng và kẻ tấn công chỉ cần gửi
request lặp tới các node khác nhau là **vượt được cả chống replay lẫn rate limit**.

Kiểm tra: `php artisan b2b:health-check` sẽ báo `SECURITY_CACHE_NOT_SHARED` nếu cấu hình sai.

### 2.2. Hàng đợi

```dotenv
QUEUE_CONNECTION=redis      # hoặc database
```

> ⚠️ **KHÔNG dùng `QUEUE_CONNECTION=sync` ở môi trường thật.** Với `sync`, job nạp tiền chạy
> ngay trong tiến trình HTTP: một lệnh gọi nhà cung cấp chậm sẽ treo request của đối tác,
> và mọi cơ chế chống trùng theo worker (`WithoutOverlapping`, lease) mất tác dụng.

### 2.3. Ngưỡng thời gian — phải khớp nhau

Đây là cấu hình dễ sai nhất và hậu quả là **nạp trùng tiền thật**.

| Tham số | Vị trí | Khuyến nghị | Ràng buộc |
| :--- | :--- | :--- | :--- |
| `--timeout` của worker | lệnh `queue:work` | 60s | Phải **nhỏ hơn** `retry_after` |
| `retry_after` | `config/queue.php` | 90s | Phải **lớn hơn** `--timeout` |
| Lease xử lý đơn | cột `don_hang.xu_ly_lease_den` | 120s | Phải **lớn hơn** `--timeout` |
| Lease outbox webhook | cột `webhook_outbox.khoa_den` | 120s | Phải **lớn hơn** thời gian gửi webhook |

**Quy tắc:** `--timeout` < `retry_after`. Nếu `retry_after` nhỏ hơn `--timeout`, MySQL sẽ
giải phóng job trong khi worker cũ **vẫn đang chạy** → hai worker cùng xử lý một đơn →
**nguy cơ nạp trùng**. Đây là lỗi cấu hình nguy hiểm nhất trong hệ thống này.

Lease phải lớn hơn `--timeout` để một worker chết không bị worker khác "cướp" đơn quá sớm.

### 2.4. Giới hạn bảo vệ

```dotenv
B2B_SECURITY_CACHE_STORE=redis
B2B_IP_RATE_LIMIT_PER_MINUTE=300
B2B_MAX_BODY_BYTES=65536
B2B_MAX_QUERY_RANGE_DAYS=31
```

---

## 3. THỨ TỰ TRIỂN KHAI (BẮT BUỘC)

Thứ tự sai sẽ gây lỗi 500 hoặc sai tiền trong cửa sổ triển khai.

```
1. Sao lưu database                        ← bắt buộc, không bỏ qua
2. Bật maintenance mode (nếu có thể)       php artisan down --retry=60
3. Cập nhật mã nguồn
4. composer install --no-dev --optimize-autoloader
5. php artisan migrate --force             ← migration chỉ THÊM, không phá dữ liệu
6. php artisan config:cache && php artisan route:cache
7. KHỞI ĐỘNG LẠI TOÀN BỘ QUEUE WORKER      ← bắt buộc, xem cảnh báo dưới
8. Tắt maintenance mode                    php artisan up
9. Chạy kiểm tra sức khỏe                  php artisan b2b:health-check --json
```

> ⚠️ **Bước 7 không được bỏ qua.** Queue worker nạp mã nguồn vào bộ nhớ một lần khi khởi
> động. Worker cũ vẫn chạy code cũ sau khi deploy. Phải restart worker để chúng nạp code mới:
>
> ```bash
> php artisan queue:restart     # báo worker cũ thoát sau khi xử lý xong job hiện tại
> ```
>
> Với Supervisor: `supervisorctl restart all`

### 3.1. Kiểm tra trước khi thêm unique index

Nếu bản triển khai có thêm ràng buộc duy nhất mới, **phải** kiểm tra trùng lặp trước:

```bash
php tools/kiem-tra-trung-du-lieu.php
```

Lệnh này CHỈ ĐỌC. Nếu phát hiện nhóm trùng, **dừng lại** và xử lý dữ liệu thủ công trước
khi thêm index — nếu không migration sẽ thất bại giữa chừng.

---

## 4. QUY TRÌNH PHỤC HỒI SỰ CỐ

### 4.1. Worker chết giữa chừng

**Triệu chứng:** đơn kẹt ở `PROCESSING` quá lâu; `b2b:health-check` báo `STALE_ORDERS`.

**Cơ chế bảo vệ có sẵn:** mỗi đơn khi vào xử lý được gán một **lease** (`xu_ly_owner` +
`xu_ly_lease_den`). Worker chỉ xử lý đơn nếu lease đã hết hạn hoặc thuộc về chính nó.
Worker chết → lease hết hạn → worker khác tiếp quản an toàn.

**Xử lý:**
```bash
php artisan b2b:recover-outbox          # phục hồi đơn kẹt ở outbox
php artisan topup:reconcile-pending     # tra cứu lại đơn chưa rõ kết quả
php artisan b2b:health-check --json     # xác nhận đã sạch
```

> ❗ **TUYỆT ĐỐI KHÔNG** tự đặt đơn về `FAILED` hay tự giải phóng khoản giữ để "dọn dẹp".
> Nếu lệnh nạp đã được gửi sang nhà cung cấp, tiền có thể đã trừ. Phải **tra cứu bằng
> mã tham chiếu gốc** để lấy kết quả thật.

### 4.2. Webhook thất bại / đối tác không nhận được

Webhook dùng **outbox** với backoff lũy tiến và khoá sở hữu (`khoa_so_huu`). Gửi lại
**giữ nguyên `event_id`** và chữ ký, nên đối tác khử trùng được.

```bash
php artisan b2b:retry-webhooks
```

Kiểm tra tồn đọng:
```sql
SELECT trang_thai, COUNT(*) FROM webhook_outbox GROUP BY trang_thai;
```

Nếu có bản ghi `MANUAL_REVIEW` → đối tác cần kiểm tra endpoint. `b2b:health-check` sẽ báo
`WEBHOOK_MANUAL_REVIEW`.

### 4.3. Nghi ngờ lệch sổ công nợ

```bash
php artisan b2b:health-check --json      # báo DEBT_LEDGER_DRIFT kèm mã đại lý
php tools/xuat-danh-sach-doi-soat.php    # xuất danh sách chi tiết để đối soát
```

> ❗ **KHÔNG tự động backfill.** Sổ phát sinh công nợ là **bất biến** (`so_du_truoc` /
> `so_du_sau`). Ghi đè lịch sử đã chốt sẽ phá vỡ khả năng kiểm toán. Hãy xuất danh sách,
> đối soát với sao kê đối tác, rồi dùng `ghiNhanDieuChinh` để ghi bút toán điều chỉnh
> **mới** — giữ nguyên dấu vết.

### 4.4. Nghi ngờ nạp trùng (nghiêm trọng nhất)

1. **Dừng ngay** việc tạo đơn mới: `cho_phep_nhan_don = false` cho đại lý liên quan.
2. Tra cứu mọi lần gọi nhà cung cấp của đơn nghi vấn — kiểm tra `ma_tham_chieu` gửi sang NCC.
   Nguyên tắc: **một đơn = một mã tham chiếu gốc**, không bao giờ sinh mã mới để "thử lại".
3. Đối chiếu với sao kê nhà cung cấp để xác định số lần trừ tiền thật.
4. Chỉ sau khi có bằng chứng dứt khoát mới ghi bút toán điều chỉnh.

---

## 5. GIÁM SÁT

### 5.1. Báo động từ `b2b:health-check`

Lệnh trả **mã thoát khác 0** khi có báo động. Cấu hình monitoring bắt mã thoát này.

| Mã báo động | Mức | Ý nghĩa | Hành động |
| :--- | :--- | :--- | :--- |
| `DEBT_LEDGER_DRIFT` | 🔴 Nghiêm trọng | Số dư tổng hợp lệch sổ cái | Điều tra ngay — sai tiền |
| `NEGATIVE_DEBT_BALANCE` | 🔴 Nghiêm trọng | Công nợ âm | Điều tra ngay |
| `STUCK_CREDIT_HOLD` | 🟠 Cao | Đơn đã kết thúc nhưng khoản giữ vẫn `HOLDING` | Hạn mức bị chiếm dụng sai |
| `SECURITY_CACHE_NOT_SHARED` | 🟠 Cao | Cache không dùng chung | Sửa cấu hình — mất chống replay |
| `STALE_ORDERS` | 🟡 Trung bình | Đơn kẹt quá lâu | Kiểm tra worker |
| `ORDER_OUTBOX_BACKLOG` | 🟡 Trung bình | Outbox tồn đọng | Kiểm tra worker |
| `WEBHOOK_OUTBOX_BACKLOG` | 🟡 Trung bình | Webhook tồn đọng | Kiểm tra worker |
| `WEBHOOK_MANUAL_REVIEW` | 🟡 Trung bình | Webhook cần can thiệp | Liên hệ đối tác |

### 5.2. Chỉ số cần theo dõi

```sql
-- Khoản giữ đang treo (phải về 0 khi không có đơn đang xử lý)
SELECT COUNT(*), SUM(so_tien_giu) FROM khoan_giu_han_muc WHERE trang_thai = 'HOLDING';

-- Đơn chưa rõ kết quả
SELECT trang_thai_don_hang, COUNT(*) FROM don_hang
WHERE nguon_don='b2b' AND trang_thai_don_hang IN ('PROVIDER_PENDING','MANUAL_REVIEW')
GROUP BY trang_thai_don_hang;

-- Outbox tồn đọng
SELECT trang_thai, COUNT(*) FROM b2b_order_outbox GROUP BY trang_thai;
SELECT trang_thai, COUNT(*) FROM webhook_outbox GROUP BY trang_thai;

-- Job thất bại
SELECT COUNT(*) FROM failed_jobs;
```

**Ngưỡng cảnh báo gợi ý:**
- `khoan_giu_han_muc` HOLDING tăng đơn điệu trong nhiều giờ → worker không xử lý.
- `MANUAL_REVIEW` tăng nhanh → nhà cung cấp có sự cố, cần đối soát.
- `failed_jobs` > 0 → điều tra ngay.

### 5.3. Nhật ký cần theo dõi

Mọi bất biến then chốt đều ghi log có `ma_don_hang` / `ma_dai_ly_api` để truy vết.
**Log không bao giờ chứa secret, token hay chữ ký.**

---

## 6. PHƯƠNG ÁN QUAY LUI (ROLLBACK)

### 6.1. Quay lui mã nguồn

```bash
php artisan down --retry=60
git checkout <tag-truoc-do>
composer install --no-dev --optimize-autoloader
php artisan config:cache && php artisan route:cache
php artisan queue:restart        # bắt buộc
php artisan up
```

### 6.2. Migration — chỉ thêm, quay lui an toàn

Hai migration mới đều **chỉ thêm cột nullable và index không-duy-nhất**:

| Migration | Thay đổi | Quay lui |
| :--- | :--- | :--- |
| `2026_09_20_100000_add_processing_lease_to_don_hang_table` | Thêm `xu_ly_owner`, `xu_ly_lease_den` (nullable) + index | `migrate:rollback` xoá 2 cột — **mất dữ liệu lease**, code cũ không dùng nên an toàn |
| `2026_09_20_110000_add_ownership_lease_to_webhook_outbox_table` | Thêm `khoa_so_huu` (nullable) + index | Tương tự |

> ⚠️ **CẢNH BÁO:** rollback các migration này sẽ **mất** thông tin lease. Nếu đang có đơn
> ở trạng thái `PROCESSING` khi rollback, hãy để worker xử lý xong trước. Bản thân việc mất
> lease không gây nạp trùng (vì mã tham chiếu gốc vẫn còn trong `lan_goi_nha_cung_cap`),
> nhưng sẽ làm mất khả năng phát hiện worker chết.

### 6.3. Quay lui dữ liệu

**KHÔNG có quy trình tự động.** Sổ công nợ là bất biến và không được sửa lịch sử đã chốt.
Mọi điều chỉnh phải là bút toán **mới** qua `ghiNhanDieuChinh`, giữ nguyên dấu vết kiểm toán.

---

## 7. DANH MỤC LỆNH VẬN HÀNH

```bash
# Kiểm tra sức khỏe (mã thoát != 0 khi có báo động)
php artisan b2b:health-check
php artisan b2b:health-check --json --stale-hours=4

# Phục hồi
php artisan b2b:recover-outbox
php artisan b2b:retry-webhooks
php artisan topup:reconcile-pending
php artisan topup:reconcile-pending --limit=100 --min-interval=180 --slow-interval=3600

# Kiểm tra dữ liệu (chỉ đọc)
php tools/kiem-tra-trung-du-lieu.php
php tools/xuat-danh-sach-doi-soat.php
```

---

## 8. VIỆC CÒN LẠI TRƯỚC KHI CHẠY PRODUCTION

| # | Việc | Vì sao |
| :-- | :--- | :--- |
| 1 | Đặt `B2B_SECURITY_CACHE_STORE=redis` và xác nhận hết `SECURITY_CACHE_NOT_SHARED` | Thiếu → mất chống replay |
| 2 | Đặt `QUEUE_CONNECTION` khác `sync` | `sync` phá vỡ mọi cơ chế chống trùng |
| 3 | Xác nhận `--timeout` < `retry_after` < lease | Sai → nguy cơ nạp trùng |
| 4 | Chạy `php tools/kiem-tra-trung-du-lieu.php` | Phát hiện trùng trước khi thêm index |
| 5 | Diễn tập quy trình phục hồi trên staging | Chưa từng chạy thật |
| 6 | Cấu hình monitoring bắt mã thoát `b2b:health-check` | Chưa có cảnh báo tự động |
| 7 | Xác nhận chính sách lưu trữ bản ghi idempotency (hiện 7 ngày) | Chưa có job dọn dẹp |
| 8 | Kiểm thử tải với nhà cung cấp thật (sandbox của NCC) | Chưa thực hiện |

> Tài liệu này mô tả **cấu hình và quy trình**, không phải cam kết hệ thống đã sẵn sàng
> production. Xem mục "Hạn chế & phần chưa kiểm chứng" trong báo cáo bàn giao.

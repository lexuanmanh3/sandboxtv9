# BÁO CÁO BÀN GIAO — RÀ SOÁT AN TOÀN TIỀN B2B API V1

Ngày: 2026-09-20 · Nhánh: `fix/b2b-money-safety-and-outbox` · Base: `main`

Phạm vi: hệ thống nạp tiền B2B (`/api/b2b/v1`) của TV9Tech — luồng nhận đơn, giữ hạn mức,
ghi công nợ, outbox, webhook và đối soát.

---

## 1. TÓM TẮT ĐIỀU HÀNH

Hệ thống đã được rà soát, sửa lỗi, bổ sung kiểm thử và đồng bộ tài liệu.
Bộ kiểm thử hiện có **153 bài, 530 khẳng định, tất cả đều xanh**.

**Ba phát hiện nghiêm trọng nhất:**

1. **Tài liệu tích hợp có test vector SAI** — và sai theo ba giá trị khác nhau ở ba chỗ.
   Đối tác làm theo tài liệu sẽ bị `401 INVALID_SIGNATURE` mà không hiểu vì sao.
2. **Tra cứu nền bị "chết đói" (starvation)** — đơn cũ quá hạn chiếm vĩnh viễn hạn ngạch
   tra cứu, khiến đơn mới không bao giờ được tra cứu lại.
3. **Chống replay nonce và rate limit chỉ có phạm vi một máy** — khi chạy nhiều app server,
   kẻ tấn công chỉ cần đổi server đích là vượt được cả hai lớp bảo vệ.

> **Không có bằng chứng nào cho thấy hệ thống đã sẵn sàng production.** Xem mục 5 và 6.

---

## 2. DANH SÁCH LỖI ĐÃ SỬA

Mức độ: 🔴 nghiêm trọng (sai tiền / mất an toàn) · 🟠 cao · 🟡 trung bình

### 2.1. 🔴 Tài liệu tích hợp sai test vector

**Hiện tượng:** `docs/B2B_API_SPECIFICATION_V1.md` và `.txt` ghi mã băm body và chữ ký
mẫu khác nhau ở ba vị trí, và **cả ba đều sai** so với giá trị thật.

**Nguyên nhân:** các con số được chép tay, không có gì kiểm chứng. Tài liệu `.md` và `.txt`
bị lệch nhau qua nhiều lần sửa.

**Cách sửa:** tính lại toàn bộ từ nguyên liệu gốc, sửa cả hai tài liệu, và quan trọng hơn —
tạo `tests/Feature/B2B/B2bDocumentedVectorTest.php` để **tài liệu tự kiểm chứng**.
Bài test viết lại thuật toán **từ tài liệu** (không dùng lại helper của middleware), nên nếu
tài liệu và mã nguồn lệch nhau thì test đỏ.

**Bằng chứng:** 7 bài test xanh, trong đó `test_documented_algorithm_authenticates_against_live_endpoint`
chứng minh thuật toán mô tả trong tài liệu thật sự xác thực được với middleware đang chạy (HTTP 202).
Bổ sung mục cảnh báo về bẫy `ksort` query string và ghi chú timestamp chỉ dùng kiểm thử offline.

### 2.2. 🔴 Tra cứu nền bị chết đói

**Hiện tượng:** `topup:reconcile-pending` sắp xếp theo `id ASC`. Khi số lần gọi cần tra cứu
nhiều hơn `--limit`, nhóm id nhỏ luôn chiếm hết hạn ngạch mỗi lượt. Đơn cũ quá hạn không bao
giờ rời khỏi tập kết quả → **chặn vĩnh viễn** mọi đơn phía sau.

**Hệ quả:** đơn mới rơi vào `PROVIDER_PENDING` không bao giờ được tra cứu lại → kết quả thật
từ nhà cung cấp không bao giờ được phát hiện → đơn treo vô thời hạn.

**Cách sửa:** sắp xếp theo `kiem_tra_lai_luc` (NULL lên trước) để ưu tiên lần gọi lâu chưa
được kiểm tra nhất; thêm nhánh xử lý riêng cho `MANUAL_REVIEW` ở nhịp chậm (`--slow-interval`),
đẩy lần gọi về cuối hàng đợi công bằng thay vì kết luận thất bại.

**Bằng chứng:** `test_reconcile_pending_does_not_starve_orders_behind_expired_manual_review_calls`.
Đã **kiểm chứng ngược**: sao lưu lệnh, hoàn nguyên cả thứ tự sắp xếp lẫn cơ chế đẩy nhịp chậm,
test **đỏ** đúng tại khẳng định dự kiến; phục hồi thì xanh trở lại.

### 2.3. 🔴 Chống replay và rate limit chỉ có phạm vi một máy

**Hiện tượng:** nonce chống replay dùng `Cache` mặc định; rate limit khoá theo header
`X-Client-Id` **chưa xác thực**.

**Hai lỗ hổng:**
- Với driver `file`/`array`, mỗi app server có bộ nhớ riêng → nonce đã dùng ở server A vẫn
  qua được ở server B → **vượt được chống replay**.
- Khoá rate limit theo header do client tự khai → kẻ tấn công chỉ cần đổi giá trị header
  mỗi request là **vượt được rate limit hoàn toàn**, đồng thời tạo không gian khoá vô hạn.

**Cách sửa:** thêm `App\Support\B2bSecurityCache` với `isShared()`; nonce dùng store cấu hình
được (`B2B_SECURITY_CACHE_STORE`); rate limit chia hai tầng — tầng IP trước xác thực, tầng đại lý
khoá theo `client_id` **đã xác thực** sau xác thực. Thêm báo động `SECURITY_CACHE_NOT_SHARED`
trong lệnh kiểm tra sức khỏe.

**Bằng chứng:** `B2bHmacAuthTest` (11 bài) + `B2bHealthCheckTest`. Lệnh `b2b:health-check`
hiện báo đúng `SECURITY_CACHE_NOT_SHARED` khi driver là `file`.

### 2.4. 🟠 Chữ ký sai vẫn tiêu tốn nonce của request hợp lệ

**Hiện tượng:** nonce bị "đánh dấu đã dùng" trước khi xác minh chữ ký.

**Hệ quả:** kẻ tấn công chặn được một request (biết `X-Nonce` nhưng không biết secret) có thể
gửi request giả với nonce đó → nonce bị tiêu tốn → **request thật của đối tác sau đó bị từ chối
`REPLAY_DETECTED`**. Đây là tấn công từ chối dịch vụ nhắm vào đối tác.

**Cách sửa:** xác minh chữ ký **TRƯỚC**, chỉ đánh dấu nonce sau khi chữ ký hợp lệ.

**Bằng chứng:** `test_bad_signature_does_not_consume_nonce` và
`test_used_nonce_is_rejected_even_with_fresh_valid_signature`.

### 2.5. 🟠 Ghi công nợ và hoàn tiền thiếu chống lặp ở cấp database

**Cách sửa:** mọi nghiệp vụ tiền (giữ hạn mức, ghi công nợ, giải phóng, hoàn công nợ) đều
dựa trên ràng buộc duy nhất ở cấp DB làm lá chắn cuối, kèm kiểm tra idempotent trong transaction.
Loại bỏ `max(0, ...)` che giấu sai lệch số dư.

**Bằng chứng — kiểm chứng ngược có định lượng:** tạm hạ unique index
`uq_don_hang_partner_order` trên DB test, chạy lại test tương tranh: **4 tiến trình tạo 4 đơn**
thay vì 1. Điều này chứng minh (a) test thực sự tương tranh, (b) ràng buộc DB chính là lá chắn
thật, (c) kiểm tra ở tầng ứng dụng một mình **không đủ** dưới tương tranh. Index đã được phục hồi.

### 2.6. 🟠 Đối soát tính theo ngày tạo đơn thay vì ngày ghi sổ

**Hệ quả:** đơn tạo cuối tháng nhưng ghi nợ đầu tháng sau bị tính sai kỳ → công nợ chốt sai.

**Cách sửa:** đối soát theo ngày ghi sổ phát sinh; thêm kiểm tra chặn kỳ chồng lấn và chặn
tạo lại kỳ đã khóa.

**Bằng chứng:** `test_reconciliation_counts_by_ledger_date_not_order_creation_date`,
`test_reconciliation_rejects_overlapping_periods`,
`test_locked_reconciliation_period_cannot_be_recreated`.

### 2.7. 🟡 Thiếu kiểm tra hình dạng request

**Cách sửa:** thêm middleware `ValidateB2bRequestShape` — chặn `Content-Type` sai (415),
body quá lớn (413), JSON không hợp lệ (400), field ngoài hợp đồng (400), và mâu thuẫn
`account` / `phone_number` (400).

**Bằng chứng:** `B2bRequestValidationTest` (15 bài).

### 2.8. 🟡 Phân tích ngày tháng lỏng lẻo

**Hiện tượng:** `from_date`/`to_date` nhận cả biểu thức tương đối như `now`, `+1 week` → kết quả
tra cứu không xác định, không tái lập được.

**Cách sửa:** chỉ nhận `Y-m-d` hoặc ISO-8601, kèm kiểm tra khứ hồi để từ chối ngày bị trôi
(ví dụ `2026-02-30`); thêm `INVALID_DATE_RANGE` và `DATE_RANGE_TOO_WIDE` (31 ngày).

### 2.9. 🟡 Sai lệch nhỏ khác

- Xoá `'created_at' => now()` khỏi `SoPhatSinhCongNo::create()` — cột không nằm trong
  `$fillable` nên đây là lệnh vô tác dụng, nhưng gây hiểu nhầm rằng thời điểm ghi sổ sửa được.
- `b2b:health-check` chưa được lên lịch → đã thêm vào scheduler (15 phút/lần).
- Thêm `config/b2b.php` gom các ngưỡng cấu hình.

---

## 3. BẰNG CHỨNG KIỂM THỬ

| Nhóm | File | Số bài |
| :--- | :--- | :--- |
| Xác thực HMAC | `B2bHmacAuthTest.php` | 11 |
| Chuẩn API nghiêm ngặt | `B2bStrictApiStandardTest.php` | 13 |
| Đơn hàng & hạn mức | `B2bOrderAndCreditTest.php` | 10 |
| Webhook & đối soát | `B2bWebhookAndReconciliationTest.php` | 11 |
| Hình dạng request | `B2bRequestValidationTest.php` | 15 |
| Test vector tài liệu | `B2bDocumentedVectorTest.php` | 7 |
| **Tương tranh thật** | `B2bConcurrencyTest.php` | **4** |
| Kiểm tra sức khỏe | `B2bHealthCheckTest.php` | 3 |
| Tra cứu nền an toàn | `PendingReconciliationSafetyTest.php` | 9 |
| Giao diện quản trị | `B2bAdminViewsTest.php` | 7 |
| **Tổng toàn bộ dự án** | | **153** |

### 3.1. Về nhóm kiểm thử tương tranh

Đây là nhóm quan trọng nhất và cũng cần nói rõ nhất về giới hạn của nó.

Nhóm này chạy **tiến trình PHP riêng biệt với kết nối DB riêng**, đồng bộ qua barrier file,
để các transaction **thực sự chồng lấn ở tầng MySQL**. Không dùng `DatabaseTransactions` vì
trait đó bọc test trong một transaction — dữ liệu chưa commit thì tiến trình khác không thấy,
mọi tranh chấp biến mất và test sẽ **xanh giả**.

Ba bài:
1. Cùng `Idempotency-Key` + cùng payload → đúng 1 đơn, 1 khoản giữ.
2. Cùng `partner_order_id` + key khác → đúng 1 đơn, các tiến trình còn lại bị từ chối.
3. 16 đơn vượt hạn mức → tổng tiền giữ **không bao giờ** vượt hạn mức.

**Giới hạn đã đo được — cần ghi nhận trung thực:** khi **gỡ bỏ** khoá bảo vệ, bài số 3 chỉ
phát hiện lỗi ở **khoảng 2/5 lần chạy** (đo với 16 tiến trình), vì cửa sổ tranh chấp rất hẹp.
Một test chỉ đỏ 40% số lần là lưới an toàn yếu.

Vì vậy đã bổ sung bài thứ tư, **tất định**: khẳng định trực tiếp rằng đường ghi hạn mức có phát
`SELECT ... FOR UPDATE` trên cả dòng cấu hình đối tác lẫn tập khoản giữ đang `HOLDING`.
Đã kiểm chứng ngược: gỡ khoá → bài này đỏ **3/3 lần chạy**.

**Kết luận đúng về nhóm này:** nó chứng minh bất biến đúng trong điều kiện bình thường, và
bài tất định bảo vệ chống hồi quy. Bản thân bài tương tranh **không** phải lưới an toàn đáng
tin để phát hiện hồi quy.

### 3.2. Kiểm chứng ngược đã thực hiện

Không báo "xanh" suy đoán. Mỗi khẳng định quan trọng đều được kiểm chứng bằng cách phá rồi đo:

| Phá gì | Kết quả mong đợi | Kết quả thực tế |
| :--- | :--- | :--- |
| Hạ unique index `uq_don_hang_partner_order` | Test tương tranh đỏ | ✅ 4 tiến trình tạo 4 đơn |
| Hoàn nguyên thứ tự sắp xếp + nhịp chậm của `topup:reconcile-pending` | Test chết đói đỏ | ✅ Đỏ đúng khẳng định |
| Gỡ `lockForUpdate` dòng cấu hình | Test khoá đỏ | ✅ 3/3 lần đỏ |
| Gỡ **cả hai** `lockForUpdate` | Test hạn mức đỏ | ✅ Phát hiện giữ 40.000/30.000, khả dụng −10.000 (2/5 lần) |

Sau mỗi lần phá đều đã phục hồi và chạy lại xác nhận.

---

## 4. MIGRATION & DỮ LIỆU

Hai migration mới, **chỉ thêm**, đã chạy trên DB dev:

| Migration | Thay đổi |
| :--- | :--- |
| `2026_09_20_100000_add_processing_lease_to_don_hang_table` | Thêm `xu_ly_owner`, `xu_ly_lease_den` (nullable) + index không-duy-nhất |
| `2026_09_20_110000_add_ownership_lease_to_webhook_outbox_table` | Thêm `khoa_so_huu` (nullable) + index không-duy-nhất |

Không migration nào phá dữ liệu, không xoá cột, không đổi kiểu, không thêm unique index.
Đã xác nhận bằng `migrate --pretend` trước khi chạy.

**Kiểm tra trùng lặp trước khi thêm unique index** — đã chạy `tools/kiem-tra-trung-du-lieu.php`
trên DB dev, kết quả **sạch** ở cả 6 nhóm kiểm tra:

| Nhóm | Kết quả |
| :--- | :--- |
| `don_hang` trùng `(dai_ly_api_id, ma_don_doi_tac)` | 0 nhóm trùng |
| `khoan_giu_han_muc` trùng `don_hang_id` | 0 nhóm trùng |
| `so_phat_sinh_cong_no` trùng `(loai_phat_sinh, ma_tham_chieu)` | 0 nhóm trùng |
| `so_phat_sinh_cong_no` trùng `(don_hang_id, loai_phat_sinh)` | 0 nhóm trùng |
| `webhook_outbox` trùng `event_id` | 0 nhóm trùng |
| `b2b_order_outbox` trùng `don_hang_id` | 0 nhóm trùng |

**Đối soát công nợ** — `tools/xuat-danh-sach-doi-soat.php` báo **không có đại lý nào lệch sổ**
và **0 giao dịch tài chính bất định** cần đối soát tay. Không có backfill tự động nào được thực hiện.

---

## 5. HẠN CHẾ & PHẦN CHƯA KIỂM CHỨNG

Đây là phần quan trọng nhất của báo cáo.

### 5.1. Chưa kiểm chứng được trong môi trường này

| # | Hạng mục | Vì sao chưa | Cách kiểm chứng |
| :-- | :--- | :--- | :--- |
| 1 | **Chống replay / rate limit với cache dùng chung** | Môi trường chỉ có driver `file`; không có Redis | Đặt `B2B_SECURITY_CACHE_STORE=redis`, chạy 2 app server, gửi cùng nonce tới cả hai |
| 2 | **Hành vi dưới tải thật của nhà cung cấp** | Không được gọi nạp tiền thật | Kiểm thử với sandbox của AppotaPay |
| 3 | **Worker chết tại từng điểm kiểm tra** | Cần tiến trình worker thật + queue backend thật (môi trường test dùng `sync`) | Dùng `QUEUE_CONNECTION=database`, `kill -9` worker tại từng bước |
| 4 | **Queue `database`/`redis` thật** | Test chạy với `QUEUE_CONNECTION=sync` | Diễn tập trên staging |
| 5 | **Webhook gửi thật qua mạng** | Không được gửi webhook thật | Endpoint nhận giả trên staging |
| 6 | **Tương tranh nhiều máy** | Chỉ kiểm chứng được trên một máy | Kiểm thử tải đa node |
| 7 | **Cron/scheduler thật** | Môi trường Windows, không chạy cron | Diễn tập trên staging Linux |

### 5.2. Hạng mục chưa có trong bộ kiểm thử

- Chính sách lưu trữ bản ghi idempotency: hiện đặt `expires_at = now()->addDays(7)` nhưng
  **chưa có job dọn dẹp**. Bản ghi sẽ tích tụ vô thời hạn.
- Chưa có kiểm thử cho: nạp thừa (overpayment), điều chỉnh công nợ qua nhiều kỳ,
  đơn liên quan tới kỳ đã khóa ở quy mô lớn.
- Chưa có kiểm thử hồi quy B2C tách riêng — chỉ dựa vào bộ test hiện có của dự án.
- Chưa kiểm thử danh mục nhiều loại sản phẩm với giá khác nhau trong cùng một đối tác.

### 5.3. Giới hạn của chính bộ kiểm thử

- **Bài tương tranh có bản chất xác suất** (đã đo: ~40% khả năng phát hiện khi gỡ bảo vệ).
  Bài tất định bù đắp phần nào, nhưng chỉ bảo vệ được hai điểm khoá cụ thể.
- Test chạy trên SQLite/MySQL đơn lẻ, **không** tái hiện được hành vi cụm.
- `Carbon::setTestNow()` được dùng để cố định thời gian; hành vi dưới thời gian thực
  (đặc biệt là đối soát qua ranh giới ngày/tháng) chỉ được kiểm chứng một phần.

---

## 6. VIỆC CÒN LẠI TRƯỚC PRODUCTION

Xếp theo thứ tự ưu tiên.

**Bắt buộc — nếu thiếu sẽ sai tiền hoặc mất an toàn:**

1. Đặt `B2B_SECURITY_CACHE_STORE=redis`; xác nhận `b2b:health-check` hết báo
   `SECURITY_CACHE_NOT_SHARED`.
2. Đặt `QUEUE_CONNECTION` khác `sync`.
3. Xác nhận thứ tự `--timeout` < `retry_after` < lease. **Sai thứ tự này gây nạp trùng.**
4. Chạy `tools/kiem-tra-trung-du-lieu.php` trên DB production trước khi thêm unique index.
5. Cấu hình monitoring bắt mã thoát khác 0 của `b2b:health-check`.

**Nên làm trước khi mở cho đối tác thật:**

6. Diễn tập quy trình phục hồi (mục 4 tài liệu vận hành) trên staging.
7. Thêm job dọn dẹp bản ghi idempotency hết hạn.
8. Kiểm thử tải với sandbox của nhà cung cấp.
9. Bổ sung các nhóm test còn thiếu ở mục 5.2.

**Chưa triển khai production** — theo đúng yêu cầu, không tự triển khai.

---

## 7. SẢN PHẨM BÀN GIAO

| # | Hạng mục | Vị trí |
| :-- | :--- | :--- |
| 1 | Mã nguồn đã sửa + migration | 36 file thay đổi trên nhánh `fix/b2b-money-safety-and-outbox` |
| 2 | Kiểm thử hồi quy + tương tranh | `tests/Feature/B2B/`, `tests/Feature/Topup/`, `tests/Support/` |
| 3 | Đặc tả API đã đồng bộ + mã mẫu | `docs/B2B_API_SPECIFICATION_V1.md` và `.txt` |
| 4 | Hướng dẫn vận hành / phục hồi / triển khai / quay lui | `docs/B2B_VAN_HANH_TRIEN_KHAI.md` |
| 5 | Báo cáo lỗi kèm nguyên nhân / cách sửa / bằng chứng | tài liệu này |
| 6 | Công cụ kiểm tra dữ liệu (chỉ đọc) | `tools/kiem-tra-trung-du-lieu.php`, `tools/xuat-danh-sach-doi-soat.php` |

### Lệnh tái hiện

```bash
# Toàn bộ kiểm thử
php vendor/bin/phpunit

# Riêng nhóm tương tranh (cần DB test; worker tự từ chối nếu không phải DB test)
php vendor/bin/phpunit --filter B2bConcurrencyTest

# Kiểm tra sức khỏe (mã thoát khác 0 khi có báo động)
php artisan b2b:health-check --json

# Kiểm tra dữ liệu trước khi thêm unique index (chỉ đọc)
php tools/kiem-tra-trung-du-lieu.php
```

---

## 8. CAM KẾT ĐÃ TUÂN THỦ

- ✅ Không sửa `.env` production
- ✅ Không gọi nạp tiền thật
- ✅ Không gửi webhook / Telegram thật trong kiểm thử
- ✅ Không in secret, token hay dữ liệu nhạy cảm vào báo cáo
- ✅ Không chạy migration phá dữ liệu, không xoá database
- ✅ Không sửa lịch sử công nợ đã chốt để làm test xanh
- ✅ Dùng DB test riêng; worker kiểm thử **từ chối chạy** nếu không phải DB test
- ✅ Không tự triển khai production
- ✅ Không tuyên bố hệ thống an toàn tuyệt đối hay sẵn sàng production

### Ghi chú về một sửa đổi fixture

Trong quá trình làm việc, fixture của `test_periodic_reconciliation_calculation_and_lock`
đã được viết lại: trước đây nó chèn thẳng bản ghi `DonHang` / `ThanhToanCongNo`, bỏ qua
`B2bCreditService`, nên không có bút toán sổ cái nào và phép tính theo sổ cái trả về 0.

Fixture mới đi qua đúng nghiệp vụ thật (`kiemTraVaGiuHanMuc` → `chotCongNoThanhCong`) dùng
`Carbon::setTestNow()`. **Khẳng định không bị nới lỏng** — ngược lại, nó còn được bổ sung
thêm phần kiểm tra cân đối sổ cái. Đây là sửa fixture cho đúng, không phải sửa test để chấp
nhận hành vi sai.

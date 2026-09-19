# ĐẶC TẢ GIAO DIỆN KẾT NỐI B2B API V1 (B2B API SPECIFICATION V1)

Tài liệu này quy định chuẩn kết nối kỹ thuật bắt buộc cho toàn bộ Đại lý B2B và các công cụ tự động (DailyB2B) khi tích hợp vào hệ thống Cổng Dịch Vụ Topup Sandbox.

---

## 1. NGUYÊN TẮC BẢO MẬT & VẬN HÀNH BẮT BUỘC

1. **Chuẩn API Thống Nhất**: Mọi Đại lý phải gọi đúng endpoint chuẩn `POST /api/b2b/v1/orders`. Không sử dụng các endpoint tạm thời hay endpoint nới lỏng tham số.
2. **Ký Số HMAC-SHA256 Toàn Diện**: Tất cả request đều phải có chữ ký HMAC SHA-256 xác thực danh tính đối tác, tính toàn vẹn payload và chống tấn công Replay Attack.
3. **Idempotency Bắt Buộc Đối Với Thay Đổi Trạng Thái**: Mọi request làm thay đổi trạng thái (POST/PUT) bắt buộc phải truyền header `Idempotency-Key` dạng UUID v4.
4. **Giữ Hạn Mức Tức Thì & Transactional Outbox**: Khi tạo đơn hàng thành công, hệ thống lập tức tạm giữ hạn mức (`HOLDING`) và ghi nhận vào bảng `b2b_order_outbox` trong cùng một database transaction.
5. **Trạng Thái Bất Định (Pending/Timeout)**: Khi chưa có phản hồi cuối cùng từ nhà cung cấp viễn thông (AppotaPay), giao dịch được bảo lưu trạng thái `PENDING` hoặc chuyển `MANUAL_REVIEW`. **Nghiêm cấm tự ý hoàn tiền, nhả hạn mức hoặc tự động đặt lệnh nạp mới**.

---

## 2. XÁC THỰC & CHỮ KÝ SỐ (HMAC-SHA256)

### 2.1. Danh Sách Headers Bắt Buộc

| Header | Kiểu dữ liệu | Bắt buộc | Mô tả |
| :--- | :--- | :--- | :--- |
| `X-Client-Id` | String | Có | Mã Client ID được cấp khi đăng ký đại lý (ví dụ: `partner_test_client`) |
| `X-Timestamp` | Integer | Có | Unix timestamp (tính bằng giây). Sai lệch cho phép: $\le \pm 300$ giây (5 phút) |
| `X-Nonce` | String | Có | Chuỗi ngẫu nhiên tối thiểu 16 ký tự, duy nhất trong vòng 10 phút để chống Replay |
| `X-Signature` | String | Có | Chữ ký HMAC SHA-256 (dạng chuỗi hex chữ thường, 64 ký tự) |
| `Idempotency-Key` | String (UUID) | Có (POST/PUT) | Định danh duy nhất của giao dịch (UUID v4), bắt buộc cho mọi request tạo đơn |
| `Content-Type` | String | Có (POST/PUT) | `application/json` |

### 2.2. Chuẩn Canonical String (6 Dòng Thống Nhất)

Chuỗi Canonical String để tính chữ ký HMAC được ghép từ đúng 6 thành phần, phân tách bởi ký tự xuống dòng `\n` (`0x0A`):

```text
METHOD
CANONICAL_PATH_WITH_QUERY
TIMESTAMP
NONCE
IDEMPOTENCY_KEY
BODY_SHA256
```

#### Quy tắc chuẩn hóa từng dòng:
1. **METHOD**: Tên phương thức HTTP viết hoa (`GET`, `POST`, `PUT`, `DELETE`).
2. **CANONICAL_PATH_WITH_QUERY**: URI path kèm query string (nếu có tham số query, sắp xếp key theo alphabet `ksort`). Ví dụ: `/api/b2b/v1/orders` hoặc `/api/b2b/v1/orders?limit=20&page=1`.
3. **TIMESTAMP**: Giá trị nguyên của `X-Timestamp`.
4. **NONCE**: Giá trị nguyên bản của `X-Nonce`.
5. **IDEMPOTENCY_KEY**: Giá trị nguyên bản của `Idempotency-Key`. Nếu là request `GET` hoặc không có header này, để trống dòng này (vẫn giữ ký tự `\n`).
6. **BODY_SHA256**: Mã băm SHA-256 ở dạng hex chữ thường của raw JSON request body. Nếu request không có body (như `GET`), tính hash của chuỗi rỗng: `hash('sha256', '')` = `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`.

### 2.3. Mã Nguồn Mẫu Tính Chữ Ký (PHP)

```php
$method = 'POST';
$uri = '/api/b2b/v1/orders';
$timestamp = time();
$nonce = bin2hex(random_bytes(16));
$idempotencyKey = (string) Str::uuid();
$bodyJson = json_encode([
    'partner_order_id' => 'DH_TEST_001',
    'product_code' => 'TOPUP_VTE_10K',
    'account' => '0965657810',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$bodySha256 = hash('sha256', $bodyJson);

$canonical = implode("\n", [
    strtoupper($method),
    $uri,
    $timestamp,
    $nonce,
    $idempotencyKey,
    $bodySha256
]);

$signature = hash_hmac('sha256', $canonical, $clientSecret);
```

### 2.4. Test Vector Tham Chiếu (Test Vector Verification)

- **Secret Key**: `test_secret_key_123`
- **Method**: `POST`
- **URI**: `/api/b2b/v1/orders`
- **Timestamp**: `1726700000`
- **Nonce**: `a1b2c3d4e5f60718`
- **Idempotency-Key**: `8f4b1d64-9a3d-47df-9db2-1e9c9c991a01`
- **Raw Body**: `{"partner_order_id":"TEST_VEC_01","product_code":"TOPUP_VTE_10K","account":"0965657810"}`
- **Body SHA-256**: `d7e008a0df7fc2256ec47a7b8e1f57ecdbb7c0cefa37c569f4eaee0495f57a07`
- **Canonical String**:
  ```text
  POST
  /api/b2b/v1/orders
  1726700000
  a1b2c3d4e5f60718
  8f4b1d64-9a3d-47df-9db2-1e9c9c991a01
  d7e008a0df7fc2256ec47a7b8e1f57ecdbb7c0cefa37c569f4eaee0495f57a07
  ```
- **Calculated Signature**:
  `hash_hmac('sha256', canonical, 'test_secret_key_123')`

---

## 3. DANH MỤC ENDPOINT API

### 3.1. Tạo Đơn Hàng Nạp Tiền (Create Order)
- **Endpoint**: `POST /api/b2b/v1/orders`
- **Mục đích**: Khởi tạo yêu cầu nạp tiền điện thoại/topup.
- **Request Headers**: Đầy đủ 5 headers xác thực + `Idempotency-Key`.
- **Request Body (JSON)**:
  ```json
  {
    "partner_order_id": "ORD_20260919_001",
    "product_code": "TOPUP_VTE_10K",
    "account": "0965657810"
  }
  ```
- **Quy tắc kiểm tra**:
  - `partner_order_id`: Chuỗi định danh đơn của đối tác, độ dài 1-64 ký tự `[A-Za-z0-9_-]`. Duy nhất theo từng đối tác.
  - `product_code`: Mã sản phẩm chính xác trong hệ thống (tra cứu tại API `/services`). Hệ thống từ chối mọi mã không tồn tại.
  - `account`: Số điện thoại nạp tiền, gồm đúng 10 chữ số theo chuẩn `^0[0-9]{9}$`.
- **Response Thành Công (HTTP 202 Accepted)**:
  ```json
  {
    "success": true,
    "order_id": 1045,
    "partner_order_id": "ORD_20260919_001",
    "status": "pending",
    "product_code": "TOPUP_VTE_10K",
    "account": "0965657810",
    "amount": 10000,
    "price": 9800,
    "created_at": "2026-09-19T02:15:00+07:00",
    "completed_at": null
  }
  ```

### 3.2. Tra Cứu Đơn Hàng Theo ID (Get Order by ID)
- **Endpoint**: `GET /api/b2b/v1/orders/{id}`
- **Response**:
  ```json
  {
    "success": true,
    "data": {
      "order_id": 1045,
      "partner_order_id": "ORD_20260919_001",
      "status": "success",
      "product_code": "TOPUP_VTE_10K",
      "account": "0965657810",
      "amount": 10000,
      "price": 9800,
      "created_at": "2026-09-19T02:15:00+07:00",
      "completed_at": "2026-09-19T02:15:04+07:00"
    }
  }
  ```

### 3.3. Tra Cứu Danh Sách Đơn Hàng (Query Orders)
- **Endpoint**: `GET /api/b2b/v1/orders`
- **Query Params**:
  - `partner_order_id`: Lọc theo mã đơn của đối tác
  - `status`: Lọc theo trạng thái (`pending`, `processing`, `success`, `failed`, `manual_review`)
  - `from_date`, `to_date`: Lọc theo thời gian tạo (`Y-m-d H:i:s`)
  - `limit`: Số bản ghi mỗi trang (mặc định 20, tối đa 100)

### 3.4. Tra Cứu Dịch Vụ & Bảng Giá (Get Services & Products)
- **Endpoint**: `GET /api/b2b/v1/services`
- **Mục đích**: Lấy danh sách sản phẩm được cấp phép cho đối tác và đơn giá riêng (nếu có chính sách giá ưu đãi).

### 3.5. Tra Cứu Hạn Mức Tín Dụng (Get Credit Status)
- **Endpoint**: `GET /api/b2b/v1/credit`
- **Response**:
  ```json
  {
    "success": true,
    "data": {
      "credit_limit": 50000000,
      "current_debt": 12500000,
      "holding_amount": 50000,
      "available_limit": 37450000
    }
  }
  ```

---

## 4. QUY TRÌNH XỬ LÝ RETRY & IDEMPOTENCY

1. **Sinh Idempotency-Key**:
   - Đối tác phải sinh `Idempotency-Key` (UUID v4) và lưu bền vững trong cơ sở dữ liệu cùng bản ghi đơn hàng của đối tác trước khi gửi request lần đầu.
2. **Kịch Bản Mạng Bị Đứt Hoặc Timeout**:
   - Khi request tạo đơn bị timeout hoặc gặp lỗi mạng (5xx, cURL timeout), đối tác **BẮT BUỘC gửi lại (Retry) với NGUYÊN VẸN**:
     - `Idempotency-Key` ban đầu.
     - `partner_order_id` ban đầu.
     - Toàn bộ payload (`product_code`, `account`).
   - Cổng API sẽ nhận diện request trùng lặp an toàn: đọc lại thông tin đơn hàng đã tạo trước đó và trả về response thành công tương ứng mà **không trừ thêm hạn mức, không tạo đơn mới, không gửi lệnh nạp lặp lại**.
3. **Xung Đột Idempotency (IDEMPOTENCY_CONFLICT - HTTP 409)**:
   - Nếu đối tác tái sử dụng cùng `Idempotency-Key` nhưng gửi kèm payload khác (ví dụ đổi số điện thoại hoặc đổi gói nạp), hệ thống lập tức từ chối và trả về HTTP 409.
4. **Trùng Mã Đơn Đối Tác (DUPLICATE_ORDER - HTTP 409)**:
   - Nếu đối tác gửi `partner_order_id` đã tồn tại nhưng kèm `Idempotency-Key` khác, hệ thống từ chối với mã lỗi `DUPLICATE_ORDER`.

---

## 5. BẢNG MÃ TRẠNG THÁI & MÃ LỖI CHUẨN HÓA

### 5.1. Trạng Thái Đơn Hàng Public (Status)

| Trạng thái | Ý nghĩa | Hành động đối tác |
| :--- | :--- | :--- |
| `pending` | Đơn đã tiếp nhận, hạn mức đã giữ, đang chờ worker xử lý | Chờ hoặc tra cứu lại sau 5s |
| `processing` | Đang gọi nhà mạng viễn thông | Chờ webhook hoặc tra cứu lại sau 5-10s |
| `success` | Nạp tiền thành công | Hoàn tất giao dịch cho khách |
| `failed` | Giao dịch thất bại dứt khoát | Báo lỗi khách, có thể nạp lại đơn khác |
| `manual_review` | Nhà mạng phản hồi bất định (34/35/99/timeout), cần hậu kiểm | **KHÔNG nạp lại**, chờ kết quả đối soát/hậu kiểm |

### 5.2. Bảng Mã Lỗi (Error Codes)

| HTTP Status | Error Code | Mô tả |
| :--- | :--- | :--- |
| 400 | `MISSING_HEADERS` | Thiếu một trong các header bắt buộc (`X-Client-Id`, `X-Timestamp`, `X-Nonce`, `X-Signature`) |
| 400 | `MISSING_IDEMPOTENCY_KEY` | Thiếu header `Idempotency-Key` trong request POST/PUT |
| 400 | `VALIDATION_FAILED` | Dữ liệu đầu vào sai định dạng (số điện thoại không hợp lệ, thiếu trường...) |
| 401 | `REQUEST_EXPIRED` | Thời gian gửi request lệch quá $\pm 300$ giây so với giờ server |
| 401 | `REPLAY_DETECTED` | Header `X-Nonce` đã được gửi trong vòng 10 phút qua |
| 401 | `KEY_REVOKED` | Cặp khóa API của đối tác đã bị thu hồi hoặc đại lý bị khóa |
| 401 | `INVALID_SIGNATURE` | Chữ ký HMAC không khớp với payload hoặc headers |
| 403 | `IP_NOT_ALLOWED` | IP gửi request không nằm trong danh sách IP Whitelist |
| 403 | `UNAUTHORIZED_SERVICE` | Đối tác chưa được phân quyền sử dụng dịch vụ này |
| 404 | `PRODUCT_NOT_FOUND` | Mã sản phẩm (`product_code`) không tồn tại hoặc đã ngừng cung cấp |
| 404 | `ORDER_NOT_FOUND` | Không tìm thấy đơn hàng thuộc quyền sở hữu của đối tác |
| 409 | `IDEMPOTENCY_CONFLICT` | Tái sử dụng `Idempotency-Key` nhưng dữ liệu payload bị thay đổi |
| 409 | `DUPLICATE_ORDER` | Mã đơn `partner_order_id` đã được sử dụng với một key khác |
| 422 | `INSUFFICIENT_CREDIT` | Hạn mức tín dụng khả dụng của đối tác không đủ để giữ chỗ cho đơn này |
| 500 | `INTERNAL_SERVER_ERROR` | Lỗi hệ thống nội bộ, đối tác cần retry với nguyên vẹn `Idempotency-Key` |

---

## 6. CÁC HÀNH VI BỊ NGHIÊM CẤM ĐỐI VỚI ĐỐI TÁC

1. **Nghiêm cấm tự sinh mã đơn mới khi đơn cũ chưa rõ kết quả**: Nếu gọi API bị timeout hoặc đơn đang ở trạng thái `pending`/`processing`/`manual_review`, không được tạo đơn hàng mới với `partner_order_id` mới cho cùng một khách hàng để tránh nạp trùng 2 lần.
2. **Nghiêm cấm gửi request không có `Idempotency-Key`**: Hệ thống sẽ chặn ở tầng middleware và từ chối xử lý.
3. **Nghiêm cấm tự động suy diễn hoàn tiền**: Khi nhận mã lỗi không xác định từ nhà cung cấp hoặc đơn ở `manual_review`, tiền công nợ/hạn mức giữ nguyên. Không được tự động hoàn tiền cho khách cho đến khi có xác nhận `failed` dứt khoát từ Webhook hoặc API tra cứu.
4. **Nghiêm cấm Replay Nonce & Chữ Ký Cũ**: Mọi request mới phải có `X-Nonce` ngẫu nhiên mới và timestamp hiện thời. Mọi hành vi dùng lại Nonce sẽ bị hệ thống lưu vết và khóa tạm thời IP nếu vượt ngưỡng.

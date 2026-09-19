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
2. **CANONICAL_PATH_WITH_QUERY**: URI path kèm query string (nếu có tham số query, sắp xếp key theo alphabet `ksort`). Ví dụ: `/api/b2b/v1/orders` hoặc `/api/b2b/v1/orders?page=1&per_page=20`.
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

> **Trạng thái kiểm chứng:** các giá trị dưới đây được kiểm chứng tự động bởi
> `tests/Feature/B2B/B2bDocumentedVectorTest.php`. Nếu tài liệu và mã nguồn lệch nhau,
> test sẽ đỏ.
>
> **Lưu ý về Timestamp:** timestamp `1726700000` (19/09/2024) là giá trị CỐ ĐỊNH chỉ dùng
> để tái lập kết quả băm khi kiểm thử offline. Nó nằm ngoài cửa sổ ±300 giây nên KHÔNG
> dùng để gọi API thật. Khi gọi thật, luôn dùng `time()` hiện tại.

#### 2.4.1. Vector POST `/api/b2b/v1/orders`

- **Secret Key**: `test_secret_key_123`
- **Method**: `POST`
- **URI**: `/api/b2b/v1/orders`
- **Timestamp**: `1726700000`
- **Nonce**: `a1b2c3d4e5f60718`
- **Idempotency-Key**: `8f4b1d64-9a3d-47df-9db2-1e9c9c991a01`
- **Raw Body** (không có khoảng trắng thừa):
  `{"partner_order_id":"TEST_VEC_01","product_code":"TOPUP_VTE_10K","account":"0965657810"}`
- **Body SHA-256**: `26e7ec17bf8a772ca51a378431fc8d7b8ece029e386ff2b7f76d3dbe0dfc4ba4`
- **Canonical String** (6 dòng, phân tách bằng `\n`):
  ```text
  POST
  /api/b2b/v1/orders
  1726700000
  a1b2c3d4e5f60718
  8f4b1d64-9a3d-47df-9db2-1e9c9c991a01
  26e7ec17bf8a772ca51a378431fc8d7b8ece029e386ff2b7f76d3dbe0dfc4ba4
  ```
- **Calculated Signature** (`hash_hmac('sha256', canonical, 'test_secret_key_123')`):
  `fc6ca89f925aae3f12300b80916010612a6c85e6dfaa9787782a70bf97dc27a1`

#### 2.4.2. Vector GET `/api/b2b/v1/orders`

- **Secret Key**: `test_secret_key_123`
- **Method**: `GET`
- **URI gửi lên**: `/api/b2b/v1/orders?per_page=20&page=1`
- **Canonical URI (ĐÃ ksort)**: `/api/b2b/v1/orders?page=1&per_page=20`
- **Timestamp**: `1726700100`
- **Nonce**: `b2c3d4e5f60718a1`
- **Idempotency-Key**: *(để trống — GET không yêu cầu)*
- **Body SHA-256**: `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` (hash của chuỗi rỗng)
- **Canonical String**:
  ```text
  GET
  /api/b2b/v1/orders?page=1&per_page=20
  1726700100
  b2c3d4e5f60718a1

  e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
  ```
  (dòng thứ 5 trống — vẫn phải giữ ký tự `\n`)
- **Calculated Signature**:
  `616125c7cad022b4501c0ca01f8a1f86e0d54af72efd2c1c4d81ae4c7f790055`

> ⚠️ **Bẫy thường gặp:** query string phải được `ksort` **trước khi ký**, không ký theo
> nguyên văn URL. Ký trên `per_page=20&page=1` (chưa sắp xếp) sẽ bị trả về
> `401 INVALID_SIGNATURE` dù URL gửi lên giống hệt nhau.

#### 2.4.3. Đoạn mã kiểm chứng nhanh (PHP)

```php
$canonical = implode("\n", [
    'POST',
    '/api/b2b/v1/orders',
    '1726700000',
    'a1b2c3d4e5f60718',
    '8f4b1d64-9a3d-47df-9db2-1e9c9c991a01',
    hash('sha256', '{"partner_order_id":"TEST_VEC_01","product_code":"TOPUP_VTE_10K","account":"0965657810"}'),
]);

echo hash_hmac('sha256', $canonical, 'test_secret_key_123');
// fc6ca89f925aae3f12300b80916010612a6c85e6dfaa9787782a70bf97dc27a1
```

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
    "order_code": "B2B20260919021500A1B2C3",
    "partner_order_id": "ORD_20260919_001",
    "status": "pending",
    "message": "Đơn hàng B2B đã được tiếp nhận và đưa vào hàng đợi xử lý.",
    "account": "0965657810",
    "product_code": "TOPUP_VTE_10K",
    "amount": 10000,
    "price": 9800,
    "discount": 200,
    "created_at": "2026-09-19T02:15:00+07:00",
    "completed_at": null
  }
  ```
- **Ghi chú**: `202 Accepted` nghĩa là đơn **đã được tiếp nhận và giữ hạn mức**, KHÔNG phải
  đã nạp thành công. Đối tác phải chờ webhook hoặc tra cứu lại để biết kết quả cuối.
  `completed_at` chỉ có giá trị khi `status` là `success` hoặc `failed`.
- **Header `X-Cache: HIT`**: có mặt khi response được trả từ bản ghi idempotency
  (đối tác gửi lại cùng `Idempotency-Key` + cùng payload). Đây là tín hiệu an toàn,
  không phải lỗi.

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
  - `partner_order_id`: Lọc theo mã đơn của đối tác (trả về một đơn, HTTP 404 nếu không có)
  - `status`: Lọc theo trạng thái (`pending`, `processing`, `success`, `failed`, `manual_review`)
  - `from_date`: Lọc từ ngày (định dạng `Y-m-d` hoặc ISO-8601, ví dụ `2026-09-01`)
  - `to_date`: Lọc đến hết ngày (bao trọn ngày `to_date`, không cắt tại 00:00)
  - `page`: Trang hiện tại (mặc định `1`)
  - `per_page`: Số bản ghi mỗi trang (mặc định `20`, tối đa `100`)
- **Ràng buộc**:
  - Khoảng `from_date`–`to_date` tối đa **31 ngày**; vượt quá trả `400 DATE_RANGE_TOO_WIDE`.
  - `from_date` không được lớn hơn `to_date`; sai trả `400 INVALID_DATE_RANGE`.
  - Chỉ chấp nhận `Y-m-d` hoặc ISO-8601. Các biểu thức tương đối như `now`, `tomorrow`,
    `+1 week` bị từ chối để kết quả tra cứu luôn xác định.
- **Ghi chú về phân trang**: tham số là `per_page`, **không phải** `limit`.
- **Response**:
  ```json
  {
    "success": true,
    "current_page": 1,
    "per_page": 20,
    "total": 42,
    "data": [
      {
        "order_id": 1045,
        "order_code": "B2B20260919021500A1B2C3",
        "partner_order_id": "ORD_20260919_001",
        "account": "0965657810",
        "product_code": "TOPUP_VTE_10K",
        "product_name": "Viettel 10.000đ",
        "amount": 10000,
        "price": 9800,
        "status": "success",
        "payment_status": "chua_thanh_toan",
        "created_at": "2026-09-19T02:15:00+07:00",
        "completed_at": "2026-09-19T02:15:04+07:00"
      }
    ]
  }
  ```

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

Cột "HTTP" là mã trạng thái thực tế hệ thống trả về. Đối tác nên xử lý theo `error_code`,
không chỉ theo HTTP status.

| HTTP | Error Code | Mô tả | Đối tác nên làm gì |
| :--- | :--- | :--- | :--- |
| 400 | `MISSING_AUTHENTICATION_HEADERS` | Thiếu một trong các header bắt buộc (`X-Client-Id`, `X-Timestamp`, `X-Nonce`, `X-Signature`) | Sửa code tích hợp |
| 400 | `MISSING_IDEMPOTENCY_KEY` | Thiếu header `Idempotency-Key` trong request POST/PUT/DELETE | Sinh UUID v4 và gửi kèm |
| 400 | `INVALID_IDEMPOTENCY_KEY` | `Idempotency-Key` không phải định dạng UUID v4 | Sinh lại bằng UUID v4 |
| 400 | `INVALID_NONCE_FORMAT` | `X-Nonce` không khớp `^[A-Za-z0-9_-]{16,64}$` | Sinh nonce ngẫu nhiên ≥16 ký tự |
| 400 | `INVALID_JSON_BODY` | Body không phải JSON object hợp lệ | Sửa payload |
| 400 | `UNKNOWN_FIELD` | Body chứa field không có trong hợp đồng | Chỉ gửi `partner_order_id`, `product_code`, `account` |
| 400 | `AMBIGUOUS_ACCOUNT_FIELD` | Gửi cả `account` và `phone_number` với giá trị khác nhau | Chỉ dùng `account` |
| 400 | `INVALID_DATE_FORMAT` | `from_date`/`to_date` sai định dạng | Dùng `Y-m-d` hoặc ISO-8601 |
| 400 | `INVALID_DATE_RANGE` | `from_date` lớn hơn `to_date` | Đảo lại khoảng |
| 400 | `DATE_RANGE_TOO_WIDE` | Khoảng tra cứu vượt giới hạn (mặc định 31 ngày) | Thu hẹp khoảng tra cứu |
| 400 | `INVALID_STATUS_FILTER` | Giá trị `status` không hợp lệ | Dùng một trong 5 trạng thái public |
| 400 | `INVALID_REQUEST` | Dữ liệu đầu vào sai định dạng (số điện thoại, mã đơn...) | Sửa payload theo mục 3.1 |
| 401 | `REQUEST_EXPIRED` | `X-Timestamp` lệch quá ±300 giây so với giờ server | Đồng bộ NTP, ký lại với `time()` hiện tại |
| 401 | `REPLAY_DETECTED` | `X-Nonce` đã được dùng trong vòng 10 phút qua | Sinh nonce mới cho mỗi request |
| 401 | `INVALID_SIGNATURE` | Chữ ký HMAC không khớp | Kiểm tra lại canonical string (mục 2.2) |
| 401 | `INVALID_CLIENT_ID` | `X-Client-Id` không tồn tại | Kiểm tra lại client_id được cấp |
| 401 | `KEY_REVOKED` | Cặp khóa API đã bị thu hồi | Liên hệ TV9Tech để cấp khóa mới |
| 403 | `PARTNER_INACTIVE` | Tài khoản đại lý đang bị tạm khóa | Liên hệ TV9Tech |
| 403 | `IP_NOT_ALLOWED` | IP gửi request không nằm trong whitelist | Đăng ký IP với TV9Tech |
| 403 | `IP_ALLOWLIST_NOT_CONFIGURED` | Đại lý chưa được cấu hình IP whitelist | Đăng ký IP với TV9Tech |
| 403 | `PRODUCT_EXCLUDED` | Sản phẩm nằm trong danh sách loại trừ của đại lý | Dùng sản phẩm khác |
| 403 | `PRODUCT_UNAUTHORIZED` | Đại lý chưa được phân quyền dùng dịch vụ/sản phẩm này | Liên hệ TV9Tech để mở quyền |
| 404 | `INVALID_PRODUCT` | `product_code` không tồn tại hoặc đã ngừng cung cấp | Tra cứu lại `/services` |
| 404 | `ORDER_NOT_FOUND` | Không tìm thấy đơn thuộc quyền sở hữu của đối tác | Kiểm tra lại `order_id`/`partner_order_id` |
| 409 | `IDEMPOTENCY_CONFLICT` | Tái sử dụng `Idempotency-Key` với payload khác | Dùng key mới cho giao dịch mới |
| 409 | `DUPLICATE_ORDER` | `partner_order_id` đã tồn tại với key khác | Tra cứu đơn cũ thay vì tạo mới |
| 413 | `PAYLOAD_TOO_LARGE` | Body vượt giới hạn (mặc định 64 KiB) | Giảm kích thước payload |
| 415 | `UNSUPPORTED_MEDIA_TYPE` | `Content-Type` không phải `application/json` | Đặt đúng header |
| 422 | `INSUFFICIENT_CREDIT` | Hạn mức khả dụng không đủ để giữ chỗ cho đơn | Nạp thêm hạn mức hoặc chờ đối soát |
| 429 | `RATE_LIMIT_EXCEEDED` | Vượt giới hạn tần suất (xem `Retry-After`) | Backoff theo header `Retry-After` |
| 500 | `INTERNAL_ERROR` | Lỗi hệ thống nội bộ | Retry với **nguyên vẹn** `Idempotency-Key`, kèm `request_id` khi báo lỗi |
| 5xx | `ORDER_CREATION_FAILED` | Lỗi không phân loại được khi tạo đơn | Retry với nguyên vẹn `Idempotency-Key` |

> Response lỗi `500 INTERNAL_ERROR` có kèm `request_id`. Vui lòng cung cấp `request_id`
> khi liên hệ hỗ trợ — **không** gửi kèm secret key hoặc chữ ký.

### 5.3. Giới Hạn Tần Suất (Rate Limit)

Hệ thống áp dụng hai tầng giới hạn:

| Tầng | Khóa | Mặc định | Ghi chú |
| :--- | :--- | :--- | :--- |
| Theo IP | IP kết nối | 300 req/phút | Áp dụng TRƯỚC xác thực; bảo vệ hệ thống khỏi flood |
| Theo đại lý | `client_id` đã xác thực | Cấu hình riêng từng đối tác | Áp dụng SAU xác thực |

Mọi response đều kèm header `X-RateLimit-Limit` và `X-RateLimit-Remaining`.
Khi vượt ngưỡng, hệ thống trả `429` kèm `Retry-After` (giây).

---

## 6. CÁC HÀNH VI BỊ NGHIÊM CẤM ĐỐI VỚI ĐỐI TÁC

1. **Nghiêm cấm tự sinh mã đơn mới khi đơn cũ chưa rõ kết quả**: Nếu gọi API bị timeout hoặc đơn đang ở trạng thái `pending`/`processing`/`manual_review`, không được tạo đơn hàng mới với `partner_order_id` mới cho cùng một khách hàng để tránh nạp trùng 2 lần.
2. **Nghiêm cấm gửi request không có `Idempotency-Key`**: Hệ thống sẽ chặn ở tầng middleware và từ chối xử lý.
3. **Nghiêm cấm tự động suy diễn hoàn tiền**: Khi nhận mã lỗi không xác định từ nhà cung cấp hoặc đơn ở `manual_review`, tiền công nợ/hạn mức giữ nguyên. Không được tự động hoàn tiền cho khách cho đến khi có xác nhận `failed` dứt khoát từ Webhook hoặc API tra cứu.
4. **Nghiêm cấm Replay Nonce & Chữ Ký Cũ**: Mọi request mới phải có `X-Nonce` ngẫu nhiên mới và timestamp hiện thời. Mọi hành vi dùng lại Nonce sẽ bị hệ thống lưu vết và khóa tạm thời IP nếu vượt ngưỡng.

<?php
$secret = 'test_secret_key_123';

// 1. POST Test Vector
$method = 'POST';
$uri = '/api/b2b/v1/orders';
$timestamp = '1726700000';
$nonce = 'a1b2c3d4e5f60718';
$idempotencyKey = '8f4b1d64-9a3d-47df-9db2-1e9c9c991a01';
$rawBody = '{"partner_order_id":"TEST_VEC_01","product_code":"TOPUP_VTE_10K","account":"0965657810"}';
$bodySha256 = hash('sha256', $rawBody);
$canonicalPost = implode("\n", [
    $method,
    $uri,
    $timestamp,
    $nonce,
    $idempotencyKey,
    $bodySha256
]);
$sigPost = hash_hmac('sha256', $canonicalPost, $secret);

echo "=== POST TEST VECTOR ===" . PHP_EOL;
echo "Body SHA256: " . $bodySha256 . PHP_EOL;
echo "Canonical String:\n" . $canonicalPost . PHP_EOL;
echo "Signature: " . $sigPost . PHP_EOL . PHP_EOL;

// 2. GET Test Vector with query parameters (e.g. GET /api/b2b/v1/orders?limit=20&page=1)
$methodGet = 'GET';
$uriGet = '/api/b2b/v1/orders?limit=20&page=1';
$timestampGet = '1726700000';
$nonceGet = 'b2c3d4e5f60718a1';
$idempotencyKeyGet = '';
$emptyBodyHash = hash('sha256', '');
$canonicalGet = implode("\n", [
    $methodGet,
    $uriGet,
    $timestampGet,
    $nonceGet,
    $idempotencyKeyGet,
    $emptyBodyHash
]);
$sigGet = hash_hmac('sha256', $canonicalGet, $secret);

echo "=== GET TEST VECTOR ===" . PHP_EOL;
echo "Empty Body SHA256: " . $emptyBodyHash . PHP_EOL;
echo "Canonical String:\n" . $canonicalGet . PHP_EOL;
echo "Signature: " . $sigGet . PHP_EOL;

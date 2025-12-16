## API Endpoints

### 1. Create Hold
`POST /holds`

**Body**
```json
{
  "product_id": 1,
  "qty": 2
}
```

**Response**
```json
{
  "hold_id": 1,
  "expires_at": "2025-12-16T09:00:00Z"
}
```

### 2. Create Order
`POST /orders`

**Body**
```json
{
  "hold_id": 1
}
```

**Response**
```json
{
  "order_id": 1,
  "status": "pre_payment"
}
```

### 3. Payment Webhook
`POST /payments/webhook`

**Headers**
```
Idempotency-Key: PAYMENT-TEST-KEY-2
```

**Body**
```json
{
  "order_id": 1,
  "status": "success"
}
```

**Notes**
- Idempotent: repeated requests with the same `Idempotency-Key` are ignored.
- Safe for out-of-order arrival.
- Updates order status to `paid` or `cancelled` and releases stock on failure.

## Test Webhook Locally

1. Start your Laravel API
```bash
php artisan serve --port=8000
```
2. Run ngrok
```bash
ngrok http 8000
```
- Copy the public URL

3. Use Postman or a fake payment system to send POST requests to:
```
https://<ngrok-id>.ngrok.io/payments/webhook
```

4. Check `storage/logs/laravel.log` to verify webhook delivery and order status updates.

## Notes for Deployment

- Ensure `APP_URL` and cache driver are configured for production.
- Use a persistent cache (Redis/Database) for idempotency and concurrency.
- Webhook endpoint should be HTTPS for security.

## Concurrency & Stock Handling

- Holds immediately reduce available stock.
- Expired holds release stock automatically.
- Orders validate holds before processing payment.
- Payment Webhook enforces idempotency to avoid duplicate updates.

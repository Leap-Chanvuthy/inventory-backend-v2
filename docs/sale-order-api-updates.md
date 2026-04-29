# Sale Order API Updates

This document reflects the current Sale Order behavior for:
- statistics dashboard
- percentage-based payment workflow
- refund detail navigation payload
- report export filters

## 1) Sale Order List and Detail

### `GET /api/sale-orders`
Supports standard list filters:
- `page`, `per_page`
- `sort`
- `filter[search]`
- `filter[order_status]`
- `filter[date_from]`, `filter[date_to]`

UI persistence note:
- selected sale order should be persisted in URL by frontend as `sale_order_id={id}`
- the detail can be re-fetched with `GET /api/sale-orders/{id}`

### `GET /api/sale-orders/{id}`
Returns complete sale order detail with:
- customer
- items
- installments
- refunds
- status histories

---

## 2) Statistics API

### `GET /api/sale-orders/statistics`

#### Query params
- `date_from` (optional, `YYYY-MM-DD`)
- `date_to` (optional, `YYYY-MM-DD`)
- `group_by` (optional): `day | week | month | year`
- `customer_id` (optional)
- `status` (optional): single status or comma-separated statuses

#### Response includes
- status counts:
  - `total_orders`
  - `total_draft`
  - `total_processing`
  - `total_on_hold`
  - `total_completed`
  - `total_cancelled`
  - `total_refunded_records` (refund record count)
- revenue summary:
  - `gross_sales_usd`, `gross_sales_riel`
  - `net_revenue_usd`, `net_revenue_riel`
  - `average_order_value_usd`, `average_order_value_riel`
  - backward compatibility aliases:
    - `total_sales_usd`, `total_sales_riel`
    - `total_earning_usd`, `total_earning_riel`
- refund summary:
  - `total_refunded`
  - `total_refunded_usd`, `total_refunded_riel`
- discount summary:
  - `total_discount_amount`
- chart data:
  - `sales_trend[]` with grouped periods
- leaderboard data:
  - `top_customers[]`
  - `top_products[]`

#### Example response
```json
{
  "status": true,
  "message": "Success",
  "data": {
    "total_orders": 48,
    "total_draft": 4,
    "total_processing": 6,
    "total_on_hold": 3,
    "total_completed": 30,
    "total_cancelled": 5,
    "total_refunded_records": 7,
    "total_refunded": 7,
    "total_refunded_usd": 420.5,
    "total_refunded_riel": 1724050,
    "total_discount_amount": 315.75,
    "gross_sales_usd": 12890.0,
    "gross_sales_riel": 52849000,
    "total_sales_usd": 12890.0,
    "total_sales_riel": 52849000,
    "net_revenue_usd": 12469.5,
    "net_revenue_riel": 51124950,
    "average_order_value_usd": 429.67,
    "average_order_value_riel": 1761633.33,
    "total_earning_usd": 12469.5,
    "total_earning_riel": 51124950,
    "group_by": "month",
    "top_customers": [],
    "top_products": [],
    "filters": {
      "date_from": "2026-04-01",
      "date_to": "2026-04-30",
      "customer_id": null,
      "status": []
    },
    "sales_trend": [
      { "period": "2026-04", "total_sales_usd": 12890, "total_sales_riel": 52849000 }
    ]
  }
}
```

---

## 3) Statistics Report API

### `GET /api/sale-orders/statistics/report`

Uses the same filters as statistics API:
- `date_from`, `date_to`
- `group_by`
- `customer_id`
- `status`

Returns downloadable PDF report generated from filtered data.

---

## 4) Payment API (Percentage-Based)

### `POST /api/sale-orders/{id}/payments`

All payment updates are percentage-based.
Direct USD/KHR payment input is not accepted.

#### Request body
```json
{
  "payment_status": "INSTALLMENT",
  "payment_percentage": 30,
  "note": "First installment"
}
```

#### Rules
- `payment_status`: `PAID | INSTALLMENT | DEBT`
- `payment_percentage`: required, `0.01 - 100`, represents **new installment percentage**
- append-only installment history
- total paid percentage cannot exceed 100
- once order leaves `DRAFT`, payment status type cannot be changed
- cancelled/refunded orders cannot accept payment updates

#### Response example
```json
{
  "status": true,
  "message": "Sale order payment recorded successfully",
  "data": {
    "sale_order_id": 1,
    "payment_status": "INSTALLMENT",
    "paid_percentage_total": 30,
    "remaining_percentage": 70,
    "paid_amount_usd": 300,
    "paid_amount_riel": 1200000,
    "installment": {
      "id": 10,
      "sale_order_id": 1,
      "percentage": 30,
      "cumulative_percentage": 30
    },
    "sale_order": {}
  }
}
```

### Compatibility endpoint
`POST /api/sale-orders/{id}/installments` remains available and delegates to the same payment logic.

---

## 5) Payment Status Edit Rule

- `DRAFT`: payment status type can be set to `PAID | INSTALLMENT | DEBT`
- `PROCESSING`, `ON_HOLD`, `COMPLETED`: type change is blocked
- only new payment entries (percentage installments) are allowed

---

## 6) Refund APIs

### `GET /api/sale-orders/refund-records`
Returns paginated refund records (not completed order list), with:
- refund metadata
- linked sale order reference
- search and date filtering

### `GET /api/sale-orders/{id}/refunds`
Returns:
- `sale_order` header (order reference + customer)
- `refunds[]` with items
- `navigation.completed_sale_order_query`

#### Navigation payload example
```json
{
  "sale_order": {
    "id": 44,
    "order_no": "SO-20260420-0012",
    "navigation": {
      "completed_sale_order_query": {
        "sale_order_tab": "history",
        "sale_order_subtab": "completed",
        "sale_order_id": 44
      }
    }
  }
}
```

---

## 7) Validation Error Examples

### Payment exceeds remaining percentage
```json
{
  "status": false,
  "message": "Validation Error",
  "errors": {
    "payment_percentage": [
      "Installment percentage cannot exceed remaining 70%."
    ]
  }
}
```

### Payment status type changed after DRAFT
```json
{
  "status": false,
  "message": "Validation Error",
  "errors": {
    "payment_status": [
      "Payment status type cannot be changed after order leaves DRAFT."
    ]
  }
}
```

### Direct amount input rejected
```json
{
  "status": false,
  "message": "Validation Error",
  "errors": {
    "paid_amount_in_usd": [
      "Direct USD payment input is not allowed. Use payment_percentage instead."
    ]
  }
}
```

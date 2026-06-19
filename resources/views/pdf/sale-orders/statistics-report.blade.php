<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ data_get($meta ?? [], 'report_id', 'Sale Order Statistics Report') }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 9mm 10mm 9mm 10mm;
        }

        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #0f172a;
            font-family: siemreap, Arial, sans-serif;
            font-size: 9.4px;
            line-height: 1.36;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            vertical-align: top;
        }

        .top-bar {
            height: 7px;
            margin-bottom: 18px;
            background: #1d4ed8;
        }

        .muted {
            color: #64748b;
        }

        .strong {
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .kicker {
            color: #2563eb;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .report-title {
            margin-top: 3px;
            color: #1e3a8a;
            font-size: 25px;
            font-weight: bold;
            letter-spacing: 1.4px;
            line-height: 1.05;
        }

        .report-subtitle {
            margin-top: 3px;
            color: #64748b;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: .8px;
            text-transform: uppercase;
        }

        .meta-table {
            margin-top: 15px;
        }

        .meta-table td {
            padding: 1px 0;
            color: #475569;
            font-size: 9px;
        }

        .meta-label {
            width: 58px;
            color: #111827;
            font-weight: bold;
        }

        .right-meta {
            padding-top: 40px;
            color: #64748b;
            font-size: 8.5px;
            line-height: 1.45;
        }

        .cards-table {
            margin-top: 16px;
        }

        .card-left {
            padding-right: 7px;
        }

        .card-middle {
            padding-left: 4px;
            padding-right: 4px;
        }

        .card-right {
            padding-left: 7px;
        }

        .metric-card {
            height: 78px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }

        .metric-blue {
            border-left: 5px solid #3b82f6;
        }

        .metric-green {
            border-left: 5px solid #10b981;
        }

        .metric-orange {
            border-left: 5px solid #f97316;
        }

        .metric-purple {
            border-left: 5px solid #7c3aed;
        }

        .metric-inner {
            padding: 11px 12px;
        }

        .metric-label {
            color: #64748b;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .metric-value {
            margin-top: 7px;
            color: #0f172a;
            font-size: 19px;
            font-weight: bold;
            line-height: 1.1;
        }

        .metric-sub {
            margin-top: 5px;
            color: #2563eb;
            font-size: 8px;
            font-weight: bold;
        }

        .green {
            color: #047857;
        }

        .red {
            color: #b91c1c;
        }

        .section {
            margin-top: 19px;
        }

        .section-title {
            margin-bottom: 8px;
            padding-left: 9px;
            border-left: 5px solid #1d4ed8;
            color: #111827;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .section-green {
            border-left-color: #10b981;
        }

        .section-orange {
            border-left-color: #f97316;
        }

        .section-red {
            border-left-color: #ef4444;
        }

        .data-table {
            border: 1px solid #e5e7eb;
        }

        .data-table th {
            padding: 7px 8px;
            background: #f1f5f9;
            border-bottom: 1px solid #e5e7eb;
            color: #475569;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .data-table td {
            padding: 7px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }

        .row-light td {
            background: #fbfdff;
        }

        .status-pill {
            display: inline-block;
            min-width: 48px;
            padding: 2px 6px;
            font-size: 7.5px;
            font-weight: bold;
            text-align: center;
        }

        .status-peak,
        .status-high {
            background: #dcfce7;
            color: #166534;
        }

        .status-stable {
            background: #e2e8f0;
            color: #475569;
        }

        .list-table td {
            padding: 6px 8px;
            background: #fbfdff;
            border: 1px solid #e5e7eb;
            font-size: 8.8px;
        }

        .list-table .amount {
            width: 34%;
            color: #1e3a8a;
            font-weight: bold;
            text-align: right;
            white-space: nowrap;
        }

        .small {
            font-size: 8.4px;
        }

        .footer {
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            color: #94a3b8;
            font-size: 8px;
        }

        .footer-brand {
            color: #1e3a8a;
            font-weight: bold;
            letter-spacing: .4px;
            text-align: right;
        }
    </style>
</head>
<body>
@php
    $stats = $stats ?? [];
    $meta = $meta ?? [];

    $safeText = static function ($value, string $fallback = '-') {
        $text = trim((string) ($value ?? ''));
        return $text !== '' ? $text : $fallback;
    };

    $formatUsd = static fn ($amount) => '$' . number_format((float) ($amount ?? 0), 2);
    $formatKhr = static fn ($amount) => 'KHR ' . number_format((float) ($amount ?? 0), 2);

    $trendRows = collect(data_get($stats, 'sales_trend', []))->take(8)->values();
    $topProducts = collect(data_get($stats, 'top_products', []))->take(5)->values();
    $topCustomers = collect(data_get($stats, 'top_customers', []))->take(5)->values();
    $topRefundedCustomers = collect(data_get($stats, 'top_refunded_customers', []))->take(4)->values();
    $topCancelledCustomers = collect(data_get($stats, 'top_cancelled_customers', []))->take(4)->values();
    $maxTrendUsd = $trendRows->max(fn ($row) => (float) data_get($row, 'total_sales_usd', 0)) ?: 0;

    $resolveTrendStatus = static function ($usd) use ($maxTrendUsd) {
        $usd = (float) $usd;

        if ($maxTrendUsd > 0 && abs($usd - $maxTrendUsd) < 0.000001) {
            return 'PEAK';
        }

        if ($maxTrendUsd > 0 && $usd >= ($maxTrendUsd * 0.70)) {
            return 'HIGH';
        }

        return 'STABLE';
    };

    $statusClass = static function ($status) {
        return match (strtoupper((string) $status)) {
            'PEAK' => 'status-peak',
            'HIGH' => 'status-high',
            default => 'status-stable',
        };
    };
@endphp

<div class="top-bar"></div>

<table>
    <tr>
        <td style="width: 65%;">
            <div class="kicker">Official Statistics Report</div>
            <div class="report-title">SALES PERFORMANCE</div>
            <div class="report-subtitle">Inventory Management System</div>

            <table class="meta-table">
                <tr>
                    <td class="meta-label">Period:</td>
                    <td>{{ $safeText(data_get($meta, 'period_label'), 'All Time - Present') }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Customer:</td>
                    <td>{{ $safeText(data_get($meta, 'customer_label'), 'All Registered Clients') }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Status:</td>
                    <td>{{ $safeText(data_get($meta, 'status_label'), 'ALL') }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Grouping:</td>
                    <td>{{ $safeText(data_get($meta, 'group_by'), 'MONTH') }}</td>
                </tr>
            </table>
        </td>
        <td style="width: 35%;" class="right-meta text-right">
            <div><span class="strong">Report ID:</span> {{ $safeText(data_get($meta, 'report_id')) }}</div>
            <div><span class="strong">Generated:</span> {{ $safeText(data_get($meta, 'generated_at')) }}</div>
        </td>
    </tr>
</table>

<table class="cards-table">
    <tr>
        <td style="width: 25%;" class="card-left">
            <table class="metric-card metric-blue">
                <tr>
                    <td class="metric-inner">
                        <div class="metric-label">Net Revenue</div>
                        <div class="metric-value">{{ $formatUsd(data_get($stats, 'net_revenue_usd', 0)) }}</div>
                        <div class="metric-sub">{{ $formatKhr(data_get($stats, 'net_revenue_riel', 0)) }}</div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 25%;" class="card-middle">
            <table class="metric-card metric-green">
                <tr>
                    <td class="metric-inner">
                        <div class="metric-label">Total Orders</div>
                        <div class="metric-value">{{ number_format((int) data_get($stats, 'total_orders', 0)) }}</div>
                        <div class="metric-sub green">{{ number_format((int) data_get($stats, 'total_completed', 0)) }} completed</div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 25%;" class="card-middle">
            <table class="metric-card metric-orange">
                <tr>
                    <td class="metric-inner">
                        <div class="metric-label">Average Order</div>
                        <div class="metric-value">{{ $formatUsd(data_get($stats, 'average_order_value_usd', 0)) }}</div>
                        <div class="metric-sub">Per completed order</div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 25%;" class="card-right">
            <table class="metric-card metric-purple">
                <tr>
                    <td class="metric-inner">
                        <div class="metric-label">Refunded</div>
                        <div class="metric-value">{{ $formatUsd(data_get($stats, 'total_refunded_usd', 0)) }}</div>
                        <div class="metric-sub red">{{ number_format((int) data_get($stats, 'total_refunded', 0)) }} refund records</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="section">
    <div class="section-title">{{ $safeText(data_get($meta, 'group_by'), 'MONTH') }} Performance Trend</div>
    <table class="data-table">
        <tr>
            <th style="width: 22%;">Reporting Period</th>
            <th style="width: 22%;" class="num">Revenue USD</th>
            <th style="width: 28%;" class="num">Revenue KHR</th>
            <th style="width: 14%;" class="num">Status</th>
            <th style="width: 14%;" class="num">Share</th>
        </tr>
        @forelse($trendRows as $index => $row)
            @php
                $usd = (float) data_get($row, 'total_sales_usd', 0);
                $rowStatus = $resolveTrendStatus($usd);
                $share = $maxTrendUsd > 0 ? round(($usd / $maxTrendUsd) * 100, 1) : 0;
            @endphp
            <tr class="{{ $index % 2 === 1 ? 'row-light' : '' }}">
                <td class="strong">{{ $safeText(data_get($row, 'period')) }}</td>
                <td class="num">{{ $formatUsd($usd) }}</td>
                <td class="num">{{ $formatKhr(data_get($row, 'total_sales_riel', 0)) }}</td>
                <td class="num"><span class="status-pill {{ $statusClass($rowStatus) }}">{{ $rowStatus }}</span></td>
                <td class="num">{{ number_format($share, 1) }}%</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center muted">No sales trend data available.</td>
            </tr>
        @endforelse
    </table>
</div>

<div class="section">
    <table>
        <tr>
            <td style="width: 50%; padding-right: 9px;">
                <div class="section-title section-green">Top Products</div>
                <table class="list-table">
                    @forelse($topProducts as $product)
                        <tr>
                            <td>
                                <span class="strong">{{ $safeText(data_get($product, 'product_name'), 'Unknown Product') }}</span><br>
                                <span class="muted small">Qty sold: {{ number_format((float) data_get($product, 'quantity_sold', 0), 2) }}</span>
                            </td>
                            <td class="amount">{{ $formatUsd(data_get($product, 'total_sales_usd', 0)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="muted text-center">No product data available.</td>
                        </tr>
                    @endforelse
                </table>
            </td>
            <td style="width: 50%; padding-left: 9px;">
                <div class="section-title section-orange">Top Customers</div>
                <table class="list-table">
                    @forelse($topCustomers as $customer)
                        <tr>
                            <td>
                                <span class="strong">{{ $safeText(data_get($customer, 'customer_name'), 'Walk-in Customer') }}</span><br>
                                <span class="muted small">Orders: {{ number_format((int) data_get($customer, 'orders_count', 0)) }}</span>
                            </td>
                            <td class="amount">{{ $formatUsd(data_get($customer, 'total_sales_usd', 0)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="muted text-center">No customer data available.</td>
                        </tr>
                    @endforelse
                </table>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <table>
        <tr>
            <td style="width: 50%; padding-right: 9px;">
                <div class="section-title section-red">Refunded Customers</div>
                <table class="list-table">
                    @forelse($topRefundedCustomers as $customer)
                        <tr>
                            <td>
                                <span class="strong">{{ $safeText(data_get($customer, 'customer_name'), 'Walk-in Customer') }}</span><br>
                                <span class="muted small">Refunded orders: {{ number_format((int) data_get($customer, 'refunded_orders_count', 0)) }}</span>
                            </td>
                            <td class="amount">{{ $formatUsd(data_get($customer, 'total_refund_usd', 0)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="muted text-center">No refund data available.</td>
                        </tr>
                    @endforelse
                </table>
            </td>
            <td style="width: 50%; padding-left: 9px;">
                <div class="section-title">Cancelled Customers</div>
                <table class="list-table">
                    @forelse($topCancelledCustomers as $customer)
                        <tr>
                            <td>
                                <span class="strong">{{ $safeText(data_get($customer, 'customer_name'), 'Walk-in Customer') }}</span><br>
                                <span class="muted small">Cancelled orders: {{ number_format((int) data_get($customer, 'cancelled_orders_count', 0)) }}</span>
                            </td>
                            <td class="amount">{{ $formatUsd(data_get($customer, 'total_cancelled_usd', 0)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="muted text-center">No cancellation data available.</td>
                        </tr>
                    @endforelse
                </table>
            </td>
        </tr>
    </table>
</div>

<table class="footer">
    <tr>
        <td style="width: 60%;">
            Confidential document. Internal use only.<br>
            All figures are subject to final audit verification.
        </td>
        <td style="width: 40%;" class="footer-brand">INVENTORY MANAGEMENT SYSTEM</td>
    </tr>
</table>
</body>
</html>

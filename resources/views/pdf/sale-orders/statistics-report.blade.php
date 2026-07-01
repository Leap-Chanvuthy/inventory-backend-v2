<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ data_get($meta ?? [], 'report_id', 'Sale Order Statistics Report') }}</title>
    <style>
        body {
            margin: 0;
            color: #111827;
            font-family: dejavusanscondensed, siemreap, sans-serif;
            font-size: 9px;
            line-height: 1.25;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td,
        th {
            vertical-align: top;
        }

        .kh {
            font-family: siemreap, dejavusanscondensed, sans-serif;
        }

        .top-bar {
            height: 5px;
            background-color: #2563eb;
            margin-bottom: 9px;
        }

        .title {
            color: #1e3a8a;
            font-size: 22px;
            font-weight: bold;
            letter-spacing: .8px;
        }

        .subtitle {
            color: #64748b;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .muted {
            color: #64748b;
        }

        .strong {
            font-weight: bold;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .section-title {
            margin-top: 11px;
            margin-bottom: 5px;
            padding: 4px 6px;
            background-color: #eff6ff;
            border-left: 4px solid #2563eb;
            color: #1e3a8a;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .meta td {
            padding: 2px 0;
            font-size: 8.5px;
        }

        .meta-label {
            width: 58px;
            color: #374151;
            font-weight: bold;
        }

        .metric-table td {
            padding: 0 4px;
        }

        .metric {
            padding: 8px;
            border: 1px solid #dbe4f0;
            border-left-width: 4px;
            background-color: #f8fafc;
            min-height: 54px;
        }

        .metric-blue {
            border-left-color: #3b82f6;
        }

        .metric-green {
            border-left-color: #10b981;
        }

        .metric-orange {
            border-left-color: #f97316;
        }

        .metric-purple {
            border-left-color: #7c3aed;
        }

        .metric-label {
            color: #64748b;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .metric-value {
            margin-top: 4px;
            color: #0f172a;
            font-size: 15px;
            font-weight: bold;
        }

        .metric-sub {
            margin-top: 3px;
            color: #475569;
            font-size: 7.5px;
        }

        .data th {
            padding: 5px 6px;
            background-color: #f1f5f9;
            border: 1px solid #dbe4f0;
            color: #475569;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .data td {
            padding: 5px 6px;
            border: 1px solid #e5e7eb;
            font-size: 8.2px;
        }

        .row-alt td {
            background-color: #fbfdff;
        }

        .pill {
            display: inline-block;
            padding: 2px 5px;
            background-color: #e2e8f0;
            color: #475569;
            font-size: 7px;
            font-weight: bold;
        }

        .pill-good {
            background-color: #dcfce7;
            color: #166534;
        }

        .two-col td {
            width: 50%;
        }

        .left-col {
            padding-right: 5px;
        }

        .right-col {
            padding-left: 5px;
        }

        .footer {
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px solid #e5e7eb;
            color: #94a3b8;
            font-size: 7.5px;
        }
    </style>
</head>
<body>
@php
    $stats = is_array($stats ?? null) ? $stats : [];
    $meta = is_array($meta ?? null) ? $meta : [];

    $text = static function ($value, string $fallback = '-') {
        $value = trim((string) ($value ?? ''));
        return $value !== '' ? $value : $fallback;
    };

    $usd = static fn ($value) => '$' . number_format((float) ($value ?? 0), 2);
    $khr = static fn ($value) => 'KHR ' . number_format((float) ($value ?? 0), 2);
    $qty = static function ($value) {
        $value = (float) ($value ?? 0);
        return rtrim(rtrim(number_format($value, 4), '0'), '.') ?: '0';
    };

    $trendRows = collect(data_get($stats, 'sales_trend', []))->take(8)->values();
    $topProducts = collect(data_get($stats, 'top_products', []))->take(5)->values();
    $topCustomers = collect(data_get($stats, 'top_customers', []))->take(5)->values();
    $topRefundedCustomers = collect(data_get($stats, 'top_refunded_customers', []))->take(4)->values();
    $topCancelledCustomers = collect(data_get($stats, 'top_cancelled_customers', []))->take(4)->values();
    $maxTrendUsd = (float) ($trendRows->max(fn ($row) => (float) data_get($row, 'total_sales_usd', 0)) ?: 0);
@endphp

<div class="top-bar"></div>

<table>
    <tr>
        <td style="width: 63%;">
            <div class="subtitle">Official Statistics Report</div>
            <div class="title">Sales Performance</div>
            <table class="meta" style="margin-top: 8px;">
                <tr>
                    <td class="meta-label">Period:</td>
                    <td class="kh">{{ $text(data_get($meta, 'period_label'), 'All Time - Present') }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Customer:</td>
                    <td class="kh">{{ $text(data_get($meta, 'customer_label'), 'All Registered Clients') }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Status:</td>
                    <td>{{ $text(data_get($meta, 'status_label'), 'ALL') }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Grouping:</td>
                    <td>{{ $text(data_get($meta, 'group_by'), 'MONTH') }}</td>
                </tr>
            </table>
        </td>
        <td style="width: 37%;" class="right">
            <div><span class="strong">Report ID:</span> {{ $text(data_get($meta, 'report_id')) }}</div>
            <div><span class="strong">Generated:</span> {{ $text(data_get($meta, 'generated_at')) }}</div>
        </td>
    </tr>
</table>

<table class="metric-table" style="margin-top: 10px;">
    <tr>
        <td>
            <div class="metric metric-blue">
                <div class="metric-label">Net Revenue</div>
                <div class="metric-value">{{ $usd(data_get($stats, 'net_revenue_usd', 0)) }}</div>
                <div class="metric-sub">{{ $khr(data_get($stats, 'net_revenue_riel', 0)) }}</div>
            </div>
        </td>
        <td>
            <div class="metric metric-green">
                <div class="metric-label">Total Orders</div>
                <div class="metric-value">{{ number_format((int) data_get($stats, 'total_orders', 0)) }}</div>
                <div class="metric-sub">{{ number_format((int) data_get($stats, 'total_completed', 0)) }} completed</div>
            </div>
        </td>
        <td>
            <div class="metric metric-orange">
                <div class="metric-label">Average Order</div>
                <div class="metric-value">{{ $usd(data_get($stats, 'average_order_value_usd', 0)) }}</div>
                <div class="metric-sub">Per completed order</div>
            </div>
        </td>
        <td>
            <div class="metric metric-purple">
                <div class="metric-label">Refunded</div>
                <div class="metric-value">{{ $usd(data_get($stats, 'total_refunded_usd', 0)) }}</div>
                <div class="metric-sub">{{ number_format((int) data_get($stats, 'total_refunded', 0)) }} refund records</div>
            </div>
        </td>
    </tr>
</table>

<div class="section-title">{{ $text(data_get($meta, 'group_by'), 'MONTH') }} Performance Trend</div>
<table class="data">
    <tr>
        <th style="width: 24%;">Reporting Period</th>
        <th style="width: 22%;" class="right">Revenue USD</th>
        <th style="width: 26%;" class="right">Revenue KHR</th>
        <th style="width: 14%;" class="center">Status</th>
        <th style="width: 14%;" class="right">Share</th>
    </tr>
    @forelse($trendRows as $index => $row)
        @php
            $rowUsd = (float) data_get($row, 'total_sales_usd', 0);
            $share = $maxTrendUsd > 0 ? round(($rowUsd / $maxTrendUsd) * 100, 1) : 0;
            $rowStatus = $maxTrendUsd > 0 && abs($rowUsd - $maxTrendUsd) < 0.000001
                ? 'PEAK'
                : ($maxTrendUsd > 0 && $rowUsd >= ($maxTrendUsd * 0.70) ? 'HIGH' : 'STABLE');
        @endphp
        <tr class="{{ $index % 2 === 1 ? 'row-alt' : '' }}">
            <td>{{ $text(data_get($row, 'period')) }}</td>
            <td class="right">{{ $usd($rowUsd) }}</td>
            <td class="right">{{ $khr(data_get($row, 'total_sales_riel', 0)) }}</td>
            <td class="center"><span class="pill {{ in_array($rowStatus, ['PEAK', 'HIGH'], true) ? 'pill-good' : '' }}">{{ $rowStatus }}</span></td>
            <td class="right">{{ number_format($share, 1) }}%</td>
        </tr>
    @empty
        <tr>
            <td colspan="5" class="center muted">No sales trend data available.</td>
        </tr>
    @endforelse
</table>

<table class="two-col" style="margin-top: 8px;">
    <tr>
        <td class="left-col">
            <div class="section-title">Top Products</div>
            <table class="data">
                <tr>
                    <th>Product</th>
                    <th style="width: 22%;" class="right">Qty</th>
                    <th style="width: 28%;" class="right">Sales</th>
                </tr>
                @forelse($topProducts as $product)
                    <tr class="{{ $loop->even ? 'row-alt' : '' }}">
                        <td class="kh">{{ $text(data_get($product, 'product_name'), 'Unknown Product') }}</td>
                        <td class="right">{{ $qty(data_get($product, 'quantity_sold', 0)) }}</td>
                        <td class="right">{{ $usd(data_get($product, 'total_sales_usd', 0)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="center muted">No product data available.</td></tr>
                @endforelse
            </table>
        </td>
        <td class="right-col">
            <div class="section-title">Top Customers</div>
            <table class="data">
                <tr>
                    <th>Customer</th>
                    <th style="width: 22%;" class="right">Orders</th>
                    <th style="width: 28%;" class="right">Sales</th>
                </tr>
                @forelse($topCustomers as $customer)
                    <tr class="{{ $loop->even ? 'row-alt' : '' }}">
                        <td class="kh">{{ $text(data_get($customer, 'customer_name'), 'Walk-in Customer') }}</td>
                        <td class="right">{{ number_format((int) data_get($customer, 'orders_count', 0)) }}</td>
                        <td class="right">{{ $usd(data_get($customer, 'total_sales_usd', 0)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="center muted">No customer data available.</td></tr>
                @endforelse
            </table>
        </td>
    </tr>
</table>

<table class="two-col" style="margin-top: 8px;">
    <tr>
        <td class="left-col">
            <div class="section-title">Refunded Customers</div>
            <table class="data">
                <tr>
                    <th>Customer</th>
                    <th style="width: 28%;" class="right">Orders</th>
                    <th style="width: 28%;" class="right">Refund</th>
                </tr>
                @forelse($topRefundedCustomers as $customer)
                    <tr class="{{ $loop->even ? 'row-alt' : '' }}">
                        <td class="kh">{{ $text(data_get($customer, 'customer_name'), 'Walk-in Customer') }}</td>
                        <td class="right">{{ number_format((int) data_get($customer, 'refunded_orders_count', 0)) }}</td>
                        <td class="right">{{ $usd(data_get($customer, 'total_refund_usd', 0)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="center muted">No refund data available.</td></tr>
                @endforelse
            </table>
        </td>
        <td class="right-col">
            <div class="section-title">Cancelled Customers</div>
            <table class="data">
                <tr>
                    <th>Customer</th>
                    <th style="width: 28%;" class="right">Orders</th>
                    <th style="width: 28%;" class="right">Cancelled</th>
                </tr>
                @forelse($topCancelledCustomers as $customer)
                    <tr class="{{ $loop->even ? 'row-alt' : '' }}">
                        <td class="kh">{{ $text(data_get($customer, 'customer_name'), 'Walk-in Customer') }}</td>
                        <td class="right">{{ number_format((int) data_get($customer, 'cancelled_orders_count', 0)) }}</td>
                        <td class="right">{{ $usd(data_get($customer, 'total_cancelled_usd', 0)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="center muted">No cancellation data available.</td></tr>
                @endforelse
            </table>
        </td>
    </tr>
</table>

<table class="footer">
    <tr>
        <td>Confidential document. Internal use only. All figures are subject to final audit verification.</td>
        <td class="right strong">INVENTORY MANAGEMENT SYSTEM</td>
    </tr>
</table>
</body>
</html>

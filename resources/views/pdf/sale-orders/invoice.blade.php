<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice['title'] }} - {{ $invoice['number'] }}</title>
    <style>
        :root {
            --primary: #1e40af;
            --accent: #3b82f6;
            --muted: #64748b;
            --ink: #1e293b;
            --bg-soft: #f8fafc;
            --line: #dbe3ee;
            --ok: #10b981;
            --warn: #f59e0b;
            --danger: #ef4444;
            --hold: #7c3aed;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: var(--ink);
            background: #fff;
            line-height: 1.4;
        }

        @page {
            size: A4;
            margin: 12mm 10mm 12mm 10mm;
        }

        .page {
            width: 100%;
            position: relative;
        }

        .header-accent {
            height: 7px;
            border-radius: 10px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            margin-bottom: 10px;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .header td {
            vertical-align: top;
        }

        .company-logo {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            background: #dbeafe;
            color: #1d4ed8;
            text-align: center;
            font-size: 20px;
            font-weight: 700;
            line-height: 54px;
            display: inline-block;
            overflow: hidden;
        }

        .company-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .title {
            font-size: 26px;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: 0.4px;
            margin: 0 0 3px 0;
        }

        .subtitle {
            margin: 0;
            color: var(--accent);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.3px;
            text-transform: uppercase;
        }

        .company-name {
            margin: 4px 0 0 0;
            color: var(--ink);
            font-size: 14px;
            font-weight: 700;
        }

        .website {
            margin-top: 2px;
            color: var(--muted);
            font-size: 11px;
        }

        .invoice-number-card {
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            border-radius: 10px;
            padding: 7px 9px;
            text-align: right;
            width: 220px;
            margin-left: auto;
        }

        .invoice-label {
            margin: 0;
            font-size: 10px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .invoice-no {
            margin: 3px 0 0 0;
            font-size: 16px;
            font-weight: 800;
            color: #1d4ed8;
        }

        .invoice-generated {
            margin: 4px 0 0 0;
            font-size: 10px;
            color: var(--muted);
        }

        .meta-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin: 2px 0 8px 0;
        }

        .meta-card {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--bg-soft);
            padding: 7px;
        }

        .meta-label {
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .meta-value {
            color: var(--ink);
            font-size: 12px;
            font-weight: 700;
            margin-top: 4px;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .badge-draft { background: #fff7ed; color: #9a3412; }
        .badge-processing { background: #eff6ff; color: #1e40af; }
        .badge-on_hold { background: #f5f3ff; color: var(--hold); }
        .badge-completed { background: #ecfdf5; color: #065f46; }
        .badge-cancelled { background: #fef2f2; color: #991b1b; }
        .badge-refunded { background: #fef3c7; color: #92400e; }
        .badge-paid { background: #dcfce7; color: #166534; }
        .badge-installment { background: #dbeafe; color: #1e40af; }
        .badge-debt { background: #fee2e2; color: #991b1b; }

        .party-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-bottom: 8px;
        }

        .party-card {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 8px;
            background: #fff;
            min-height: 128px;
        }

        .section-title {
            margin: 0 0 7px 0;
            font-size: 11px;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .party-line {
            margin: 2px 0;
            font-size: 11px;
            color: var(--ink);
        }

        .party-line .label {
            color: var(--muted);
            font-weight: 700;
            margin-right: 4px;
        }

        .items-title {
            margin: 8px 0 4px 0;
            font-size: 12px;
            font-weight: 800;
            color: var(--ink);
            text-transform: uppercase;
            letter-spacing: 0.7px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
        }

        .items thead th {
            background: #f1f5f9;
            color: #475569;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 6px 6px;
            border-bottom: 1px solid var(--line);
            text-align: left;
        }

        .items tbody td {
            font-size: 10px;
            padding: 5px 6px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: top;
        }

        .items tbody tr:nth-child(even) td {
            background: #fbfdff;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .stack-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-top: 6px;
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 8px;
            background: #fff;
            vertical-align: top;
        }

        .payment-box {
            margin-bottom: 6px;
            border: 1px dashed #bfdbfe;
            border-radius: 10px;
            padding: 6px;
            background: #f8fbff;
        }

        .khqr-wrap {
            margin-top: 5px;
            text-align: center;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 6px;
            min-height: 110px;
            background: #fff;
        }

        .khqr-wrap img {
            max-width: 96px;
            max-height: 96px;
        }

        .fallback {
            color: var(--muted);
            font-size: 10px;
        }

        table.summary {
            width: 100%;
            border-collapse: collapse;
        }

        .summary td {
            padding: 3px 0;
            font-size: 11px;
            border-bottom: 1px dashed #e2e8f0;
        }

        .summary td:last-child {
            text-align: right;
            font-weight: 700;
        }

        .summary .grand td {
            padding-top: 7px;
            border-top: 1px solid var(--line);
            border-bottom: 0;
            font-size: 13px;
            color: var(--primary);
            font-weight: 800;
        }

        .note-box {
            margin-top: 6px;
            border: 1px solid #dbeafe;
            background: #f8fbff;
            border-radius: 10px;
            padding: 7px;
        }

        .note-title {
            margin: 0 0 4px 0;
            font-size: 10px;
            font-weight: 800;
            color: #1d4ed8;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .note-content {
            margin: 0;
            font-size: 11px;
            color: var(--ink);
        }

        .signature-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 16px 0;
            margin-top: 10px;
        }

        .signature-box {
            border-top: 1px solid #94a3b8;
            padding-top: 6px;
            text-align: center;
            font-size: 10px;
            color: var(--muted);
            width: 48%;
        }

        .footer {
            margin-top: 8px;
            border-top: 1px solid var(--line);
            padding-top: 8px;
            font-size: 9px;
            color: var(--muted);
        }

        .footer-right {
            float: right;
            font-weight: 700;
            color: var(--primary);
        }

        .item-col {
            text-align: left !important;
            padding-left: 4px !important;
        }
    </style>
</head>
<body>
    @php
        $orderBadgeClass = 'badge-' . strtolower(str_replace(' ', '_', $meta['order_status']));
        $paymentBadgeClass = 'badge-' . strtolower(str_replace(' ', '_', $meta['payment_status']));
    @endphp

    <div class="page">
        <div class="header-accent"></div>

        <table class="header">
            <tr>
                <td style="width: 65%;">
                    <table style="border-collapse: collapse;">
                        <tr>
                            <!-- <td style="width: 62px;">
                                <div class="company-logo">
                                    @if(!empty($company['logo']))
                                        <img src="{{ $company['logo'] }}" alt="Company Logo">
                                    @else
                                        {{ $company['initials'] }}
                                    @endif
                                </div>
                            </td> -->
                            <td>
                                <h1 class="title">{{ strtoupper($invoice['document_label']) }}</h1>
                                <p class="subtitle">{{ $invoice['title'] }}</p>
                                <p class="company-name">{{ $company['name'] }}</p>
                                <p class="website">{{ $company['website'] }}</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 35%;">
                    <div class="invoice-number-card">
                        <p class="invoice-label">Invoice Number</p>
                        <p class="invoice-no">#{{ $invoice['number'] }}</p>
                        <p class="invoice-generated">Generated: {{ $invoice['generated_at'] }}</p>
                    </div>
                </td>
            </tr>
        </table>

        <table class="meta-grid">
            <tr>
                <td class="meta-card">
                    <div class="meta-label">Order Status</div>
                    <div class="meta-value">
                        <span class="badge {{ $orderBadgeClass }}">{{ $meta['order_status'] }}</span>
                    </div>
                </td>
                <td class="meta-card">
                    <div class="meta-label">Payment Status</div>
                    <div class="meta-value">
                        <span class="badge {{ $paymentBadgeClass }}">{{ $meta['payment_status'] }}</span>
                    </div>
                </td>
                <td class="meta-card">
                    <div class="meta-label">Order Date</div>
                    <div class="meta-value">{{ $meta['order_date'] }}</div>
                </td>
                <td class="meta-card">
                    <div class="meta-label">Return Valid Until</div>
                    <div class="meta-value">{{ $meta['return_valid_until'] }}</div>
                </td>
            </tr>
        </table>

        <table class="party-grid">
            <tr>
                <td class="party-card" style="width:50%;">
                    <h3 class="section-title">From</h3>
                    <p class="party-line"><span class="label">Company:</span>{{ $company['name'] }}</p>
                    <p class="party-line"><span class="label">Contact:</span>{{ $company['contact_person'] }}</p>
                    <p class="party-line"><span class="label">Email:</span>{{ $company['email'] }}</p>
                    <p class="party-line"><span class="label">Phone:</span>{{ $company['phone'] }}</p>
                    <p class="party-line"><span class="label">VAT:</span>{{ $company['vat_number'] }}</p>
                    <p class="party-line"><span class="label">Address:</span>{{ $company['address'] }}</p>
                </td>
                <td class="party-card" style="width:50%;">
                    <h3 class="section-title">Bill To</h3>
                    <p class="party-line"><span class="label">Customer:</span>{{ $bill_to['name'] }}</p>
                    <p class="party-line"><span class="label">Customer Code:</span>{{ $bill_to['code'] }}</p>
                    <p class="party-line"><span class="label">Email:</span>{{ $bill_to['email'] }}</p>
                    <p class="party-line"><span class="label">Phone:</span>{{ $bill_to['phone'] }}</p>
                    <p class="party-line"><span class="label">Category:</span>{{ $bill_to['category'] }}</p>
                    <p class="party-line"><span class="label">Address:</span>{{ $bill_to['address'] }}</p>
                </td>
            </tr>
        </table>

        <h3 class="items-title">Order Items</h3>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 24%;">Product</th>
                    <th style="width: 9%;" class="num">Qty</th>
                    <th style="width: 12%;" class="num">Unit USD</th>
                    <th style="width: 10%;" class="num">Total USD</th>
                    <th style="width: 10%;" class="num">Total KHR</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>{{ $item['line_no'] }}</td>
                        <td class="item-col">{{ $item['product_name'] }}</td>
                        <td class="num">{{ $item['quantity'] }}</td>
                        <td class="num">{{ $item['unit_price_usd'] }}</td>
                        <td class="num">{{ $item['total_price_usd'] }}</td>
                        <td class="num">{{ $item['total_price_riel'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="stack-grid">
            <tr>
                <td class="panel" style="width: 50%;">
                    <h3 class="section-title">Payment Method</h3>
                    <div class="payment-box">
                        <p class="party-line"><span class="label">Bank:</span>{{ $payment['bank_name'] }}</p>
                        <p class="party-line"><span class="label">Account Holder:</span>{{ $payment['account_holder'] }}</p>
                        <p class="party-line"><span class="label">Account Number:</span>{{ $payment['account_number'] }}</p>
                        <p class="party-line"><span class="label">Payment Link:</span>{{ $payment['payment_link'] }}</p>
                    </div>
                    <h3 class="section-title" style="margin-top: 8px;">KHQR</h3>
                    <div class="khqr-wrap">
                        @if(!empty($payment['khqr_image']))
                            <img src="{{ $payment['khqr_image'] }}" alt="KHQR">
                        @else
                            <p class="fallback">KHQR image is not configured.</p>
                        @endif
                    </div>
                </td>

                <td class="panel" style="width: 50%;">
                    <h3 class="section-title">Summary</h3>
                    <table class="summary">
                        <tr><td>Subtotal</td><td>{{ $summary['sub_total_usd'] }}</td></tr>
                        <tr><td>Discount</td><td>{{ $summary['discount_amount_usd'] }}</td></tr>
                        <tr><td>Tax ({{ $summary['tax_percentage'] }})</td><td>{{ $summary['tax_amount_usd'] }}</td></tr>
                        <tr><td>Paid</td><td>{{ $summary['paid_amount_usd'] }}</td></tr>
                        <tr><td>Refunded</td><td>{{ $summary['refunded_amount_usd'] }}</td></tr>
                        <tr><td>Remaining</td><td>{{ $summary['remaining_balance_usd'] }}</td></tr>
                        <tr class="grand"><td>Total Amount</td><td>{{ $summary['total_amount_usd'] }}</td></tr>
                        <tr class="grand"><td></td><td>{{ $summary['total_amount_riel'] }}</td></tr>
                    </table>

                    <div class="note-box">
                        <h4 class="note-title">Note</h4>
                        <p class="note-content">{{ $note }}</p>
                    </div>
                </td>
            </tr>
        </table>

        <table class="signature-grid">
            <tr>
                <td class="signature-box">Prepared By</td>
                <td class="signature-box">Approved By</td>
            </tr>
        </table>

        <div class="footer">
            <span>{{ $footer['left'] }}</span>
            <span class="footer-right">{{ $footer['right'] }}</span>
        </div>
    </div>
</body>
</html>

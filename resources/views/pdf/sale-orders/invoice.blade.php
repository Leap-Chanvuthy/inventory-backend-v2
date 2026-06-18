<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ data_get($invoice ?? [], 'document_label', 'Invoice') }} - {{ data_get($invoice ?? [], 'number', '-') }}</title>

    <style>
        /*
        |--------------------------------------------------------------------------
        | mPDF-safe polished invoice template
        |--------------------------------------------------------------------------
        | This keeps the working table-only structure but improves the UI.
        | Do not use flex, grid, float, border-radius, box-sizing, or @font-face here.
        | Fonts are registered in SaleOrderInvoicePdfService.php.
        */

        @page {
            margin: 10mm 10mm 12mm 10mm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: siemreap;
            font-size: 10.5px;
            line-height: 1.35;
            color: #111827;
            background-color: #ffffff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td,
        th {
            vertical-align: top;
            font-size: 10.5px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .muted {
            color: #6b7280;
        }

        .strong {
            font-weight: bold;
        }

        .small {
            font-size: 9px;
        }

        .tiny {
            font-size: 8px;
        }

        .blue {
            color: #2563eb;
        }

        .green {
            color: #047857;
        }

        .orange {
            color: #b45309;
        }

        .red {
            color: #b91c1c;
        }

        .gray {
            color: #374151;
        }

        .header-company-name {
            font-size: 16px;
            font-weight: bold;
            color: #111827;
            line-height: 1.15;
        }

        .header-meta {
            font-size: 9.5px;
            color: #6b7280;
            line-height: 1.35;
        }

        .invoice-title {
            font-size: 30px;
            font-weight: bold;
            color: #111827;
            line-height: 1;
        }

        .invoice-subtitle {
            font-size: 10px;
            color: #6b7280;
            margin-top: 2px;
        }

        .invoice-number {
            font-size: 11px;
            font-weight: bold;
            color: #2563eb;
            margin-top: 6px;
        }

        .generated-at {
            font-size: 8.5px;
            color: #6b7280;
            margin-top: 2px;
        }

        .logo {
            max-width: 150px;
            max-height: 48px;
            margin-bottom: 5px;
        }

        .spacer-xs {
            height: 6px;
        }

        .spacer-sm {
            height: 10px;
        }

        .spacer-md {
            height: 14px;
        }

        .section-title {
            font-size: 10.5px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 4px;
        }

        .card {
            border: 1px solid #e5e7eb;
            background-color: #ffffff;
        }

        .card-soft {
            border: 1px solid #e5e7eb;
            background-color: #f9fafb;
        }

        .card-blue {
            border: 1px solid #dbeafe;
            background-color: #f8fbff;
        }

        .card-padding {
            padding: 9px;
        }

        .status-table {
            border: 1px solid #e5e7eb;
            background-color: #ffffff;
        }

        .status-table td {
            width: 25%;
            padding: 8px 9px;
            border-right: 1px solid #e5e7eb;
        }

        .status-label {
            font-size: 8px;
            color: #6b7280;
            line-height: 1.2;
        }

        .status-value {
            font-size: 10px;
            font-weight: bold;
            margin-top: 3px;
            line-height: 1.2;
        }

        .info-table td {
            padding: 1.5px 0;
            line-height: 1.3;
        }

        .info-label {
            color: #6b7280;
            width: 62px;
            white-space: nowrap;
        }

        .items-table {
            border: 1px solid #e5e7eb;
        }

        .items-table th {
            padding: 7px 6px;
            background-color: #f3f4f6;
            color: #374151;
            font-size: 9px;
            font-weight: bold;
            border-bottom: 1px solid #e5e7eb;
        }

        .items-table td {
            padding: 7px 6px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 9.5px;
            line-height: 1.3;
        }

        .product-name {
            font-size: 10px;
            font-weight: bold;
            color: #111827;
        }

        .product-meta {
            font-size: 8.5px;
            color: #6b7280;
            line-height: 1.25;
        }

        .summary-table td {
            padding: 5px 0;
            border-bottom: 1px dashed #e5e7eb;
            font-size: 10px;
        }

        .summary-label {
            color: #374151;
        }

        .summary-value {
            text-align: right;
            font-weight: bold;
            color: #111827;
            white-space: nowrap;
        }

        .summary-total-label {
            padding-top: 9px;
            border-top: 2px solid #111827;
            font-size: 13px;
            font-weight: bold;
            color: #111827;
        }

        .summary-total-value {
            padding-top: 9px;
            border-top: 2px solid #111827;
            text-align: right;
            font-size: 13px;
            font-weight: bold;
            color: #111827;
            white-space: nowrap;
        }

        .summary-khr {
            font-size: 10px;
            font-weight: bold;
            text-align: right;
            color: #374151;
            padding-top: 3px;
        }

        .qr-box {
            margin-top: 9px;
            padding: 8px;
            border: 1px dashed #d1d5db;
            text-align: center;
            min-height: 80px;
        }

        .qr {
            max-width: 88px;
            max-height: 88px;
        }

        .note-content {
            font-size: 10px;
            color: #111827;
            line-height: 1.35;
        }

        .signature-line {
            border-top: 1px solid #9ca3af;
            text-align: center;
            padding-top: 7px;
            font-size: 10px;
            color: #374151;
        }

        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
            color: #6b7280;
            font-size: 8.5px;
        }
    </style>
</head>

<body>
@php
    $safeText = static function ($value, string $fallback = '-') {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : $fallback;
    };

    $displayStatus = static function ($value) use ($safeText) {
        return str_replace('_', ' ', strtoupper($safeText($value)));
    };

    $statusColor = static function ($value): string {
        $status = strtoupper(str_replace([' ', '-'], '_', trim((string) $value)));

        if (in_array($status, ['COMPLETED', 'PAID', 'ACTIVE', 'APPROVED'], true)) {
            return 'green';
        }

        if (in_array($status, ['PENDING', 'PARTIAL', 'PROCESSING', 'INSTALLMENT', 'DRAFT', 'ON_HOLD'], true)) {
            return 'orange';
        }

        if (in_array($status, ['CANCELLED', 'FAILED', 'UNPAID', 'REFUNDED', 'DEBT'], true)) {
            return 'red';
        }

        return 'gray';
    };

    $companyName = $safeText(data_get($company ?? [], 'name'), config('app.name', 'Inventory System'));
    $companyLogo = data_get($company ?? [], 'logo_data_uri');
    $companyAddress = $safeText(data_get($company ?? [], 'address'));
    $companyPhone = $safeText(data_get($company ?? [], 'phone'));
    $companyEmail = $safeText(data_get($company ?? [], 'email'));
    $companyVat = $safeText(data_get($company ?? [], 'vat_number'));
    $companyWebsite = $safeText(data_get($company ?? [], 'website'));
    $companyContact = $safeText(data_get($company ?? [], 'contact_person'));

    $invoiceLabel = $safeText(data_get($invoice ?? [], 'document_label'), 'INVOICE');
    $invoiceTitle = $safeText(data_get($invoice ?? [], 'title'), 'Sales Invoice');
    $invoiceNumber = $safeText(data_get($invoice ?? [], 'number'));
    $generatedAt = $safeText(data_get($invoice ?? [], 'generated_at'));

    $orderStatus = $displayStatus(data_get($meta ?? [], 'order_status'));
    $paymentStatus = $displayStatus(data_get($meta ?? [], 'payment_status'));
    $orderDate = $safeText(data_get($meta ?? [], 'order_date'));
    $returnValidUntil = $safeText(data_get($meta ?? [], 'return_valid_until'));

    $billToName = $safeText(data_get($bill_to ?? [], 'name'), 'Walk-in Customer');
    $billToCode = $safeText(data_get($bill_to ?? [], 'code'));
    $billToEmail = $safeText(data_get($bill_to ?? [], 'email'));
    $billToPhone = $safeText(data_get($bill_to ?? [], 'phone'));
    $billToCategory = $safeText(data_get($bill_to ?? [], 'category'));
    $billToAddress = $safeText(data_get($bill_to ?? [], 'address'));

    $paymentBankName = $safeText(data_get($payment ?? [], 'bank_name'), 'Not configured');
    $paymentAccountHolder = $safeText(data_get($payment ?? [], 'account_holder'));
    $paymentAccountNumber = $safeText(data_get($payment ?? [], 'account_number'));
    $paymentLink = $safeText(data_get($payment ?? [], 'payment_link'));
    $khqrImage = data_get($payment ?? [], 'khqr_data_uri');

    $noteText = trim((string) ($note ?? ''));
@endphp

{{-- Header --}}
<table>
    <tr>
        <td style="width: 58%;">
            @if(! empty($companyLogo))
                <img class="logo" src="{{ $companyLogo }}" alt="Logo">
            @endif

            <div class="header-company-name">{{ $companyName }}</div>
            <div class="header-meta">{{ $companyAddress }}</div>

            <div class="header-meta">
                {{ $companyPhone }}
                @if($companyEmail !== '-')
                    | {{ $companyEmail }}
                @endif
            </div>

            @if($companyWebsite !== '-')
                <div class="header-meta">{{ $companyWebsite }}</div>
            @endif

            @if($companyVat !== '-')
                <div class="header-meta">VAT: {{ $companyVat }}</div>
            @endif
        </td>

        <td style="width: 42%;" class="text-right">
            <div class="invoice-title">{{ strtoupper($invoiceLabel) }}</div>
            <div class="invoice-subtitle">{{ $invoiceTitle }}</div>
            <div class="invoice-number">#{{ $invoiceNumber }}</div>
            <div class="generated-at">Generated: {{ $generatedAt }}</div>
        </td>
    </tr>
</table>

<div class="spacer-md"></div>

{{-- Status --}}
<table class="status-table">
    <tr>
        <td>
            <div class="status-label">Order Status</div>
            <div class="status-value {{ $statusColor($orderStatus) }}">{{ $orderStatus }}</div>
        </td>

        <td>
            <div class="status-label">Payment Status</div>
            <div class="status-value {{ $statusColor($paymentStatus) }}">{{ $paymentStatus }}</div>
        </td>

        <td>
            <div class="status-label">Order Date</div>
            <div class="status-value">{{ $orderDate }}</div>
        </td>

        <td style="border-right: 0;">
            <div class="status-label">Return Valid Until</div>
            <div class="status-value">{{ $returnValidUntil }}</div>
        </td>
    </tr>
</table>

<div class="spacer-md"></div>

{{-- From / Bill To --}}
<table>
    <tr>
        <td style="width: 50%; padding-right: 6px;">
            <table class="card">
                <tr>
                    <td class="card-padding">
                        <div class="section-title">From</div>

                        <table class="info-table">
                            <tr>
                                <td class="info-label">Company:</td>
                                <td class="strong">{{ $companyName }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Contact:</td>
                                <td>{{ $companyContact }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Phone:</td>
                                <td>{{ $companyPhone }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Email:</td>
                                <td>{{ $companyEmail }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">VAT:</td>
                                <td>{{ $companyVat }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Address:</td>
                                <td>{{ $companyAddress }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>

        <td style="width: 50%; padding-left: 6px;">
            <table class="card">
                <tr>
                    <td class="card-padding">
                        <div class="section-title">Bill To</div>

                        <table class="info-table">
                            <tr>
                                <td class="info-label">Customer:</td>
                                <td class="strong">{{ $billToName }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Code:</td>
                                <td>{{ $billToCode }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Phone:</td>
                                <td>{{ $billToPhone }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Email:</td>
                                <td>{{ $billToEmail }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Category:</td>
                                <td>{{ $billToCategory }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Address:</td>
                                <td>{{ $billToAddress }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="spacer-md"></div>

{{-- Items --}}
<div class="section-title">Order Items</div>

<table class="items-table">
    <tr>
        <th style="width: 5%;" class="text-center">#</th>
        <th style="width: 37%;">Product</th>
        <th style="width: 9%;" class="text-right">Qty</th>
        <th style="width: 14%;" class="text-right">Unit USD</th>
        <th style="width: 16%;" class="text-right">Total USD</th>
        <th style="width: 19%;" class="text-right">Total KHR</th>
    </tr>

    @forelse(($items ?? []) as $item)
        @php
            $itemSku = $safeText(data_get($item, 'sku'));
            $itemNote = $safeText(data_get($item, 'note'));
        @endphp

        <tr>
            <td class="text-center">
                {{ data_get($item, 'line_no', $loop->iteration) }}
            </td>

            <td>
                <div class="product-name">
                    {{ $safeText(data_get($item, 'product_name'), 'Product') }}
                </div>

                @if($itemSku !== '-')
                    <div class="product-meta">SKU: {{ $itemSku }}</div>
                @endif

                @if($itemNote !== '-')
                    <div class="product-meta">Note: {{ $itemNote }}</div>
                @endif
            </td>

            <td class="text-right">
                {{ $safeText(data_get($item, 'quantity'), '0.00') }}
            </td>

            <td class="text-right">
                {{ $safeText(data_get($item, 'unit_price_usd'), '$0.00') }}
            </td>

            <td class="text-right">
                {{ $safeText(data_get($item, 'total_price_usd'), '$0.00') }}
            </td>

            <td class="text-right">
                {{ $safeText(data_get($item, 'total_price_riel'), '៛ 0.00') }}
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="text-center muted">No order items found.</td>
        </tr>
    @endforelse
</table>

<div class="spacer-md"></div>

{{-- Payment / Summary --}}
<table>
    <tr>
        <td style="width: 50%; padding-right: 6px;">
            <table class="card">
                <tr>
                    <td class="card-padding">
                        <div class="section-title">Payment Method</div>

                        <table class="info-table">
                            <tr>
                                <td class="info-label">Bank:</td>
                                <td>{{ $paymentBankName }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Holder:</td>
                                <td>{{ $paymentAccountHolder }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Account:</td>
                                <td>{{ $paymentAccountNumber }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Link:</td>
                                <td>{{ $paymentLink }}</td>
                            </tr>
                        </table>

                        <div class="qr-box">
                            @if(! empty($khqrImage))
                                <img class="qr" src="{{ $khqrImage }}" alt="KHQR">
                                <div class="tiny muted">Scan to pay</div>
                            @else
                                <span class="muted small">KHQR image is not configured.</span>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>
        </td>

        <td style="width: 50%; padding-left: 6px;">
            <table class="card">
                <tr>
                    <td class="card-padding">
                        <div class="section-title">Summary</div>

                        <table class="summary-table">
                            <tr>
                                <td class="summary-label">Subtotal</td>
                                <td class="summary-value">{{ $safeText(data_get($summary ?? [], 'sub_total_usd'), '$0.00') }}</td>
                            </tr>

                            <tr>
                                <td class="summary-label">Discount</td>
                                <td class="summary-value">{{ $safeText(data_get($summary ?? [], 'discount_amount_usd'), '$0.00') }}</td>
                            </tr>

                            <tr>
                                <td class="summary-label">Tax ({{ $safeText(data_get($summary ?? [], 'tax_percentage'), '0.00%') }})</td>
                                <td class="summary-value">{{ $safeText(data_get($summary ?? [], 'tax_amount_usd'), '$0.00') }}</td>
                            </tr>

                            <tr>
                                <td class="summary-label">Paid</td>
                                <td class="summary-value">{{ $safeText(data_get($summary ?? [], 'paid_amount_usd'), '$0.00') }}</td>
                            </tr>

                            <tr>
                                <td class="summary-label">Refunded</td>
                                <td class="summary-value">{{ $safeText(data_get($summary ?? [], 'refunded_amount_usd'), '$0.00') }}</td>
                            </tr>

                            <tr>
                                <td class="summary-label">Remaining</td>
                                <td class="summary-value">{{ $safeText(data_get($summary ?? [], 'remaining_balance_usd'), '$0.00') }}</td>
                            </tr>

                            <tr>
                                <td class="summary-total-label">Total Amount</td>
                                <td class="summary-total-value">{{ $safeText(data_get($summary ?? [], 'total_amount_usd'), '$0.00') }}</td>
                            </tr>

                            <tr>
                                <td></td>
                                <td class="summary-khr">{{ $safeText(data_get($summary ?? [], 'total_amount_riel'), '៛ 0.00') }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if($noteText !== '')
    <div class="spacer-md"></div>

    <table class="card-blue">
        <tr>
            <td class="card-padding">
                <div class="section-title">Note</div>
                <div class="note-content">{{ $noteText }}</div>
            </td>
        </tr>
    </table>
@endif

</body>
</html>
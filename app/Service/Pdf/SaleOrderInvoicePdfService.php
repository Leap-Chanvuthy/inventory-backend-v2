<?php

namespace App\Service\Pdf;

use App\Enums\SaleOrderStatusEnum;
use App\Models\CompanyInformation;
use App\Models\SaleOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SaleOrderInvoicePdfService
{
    public function download(SaleOrder $saleOrder): Response
    {
        $pdf = Pdf::loadHTML($this->render($saleOrder))
            ->setPaper('a4')
            ->setOptions([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);

        $filename = 'sale-order-' . ((string) $saleOrder->order_no) . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function render(SaleOrder $saleOrder): string
    {
        return view('pdf.sale-orders.invoice', $this->prepareData($saleOrder))->render();
    }

    private function prepareData(SaleOrder $saleOrder): array
    {
        $companyInfo = CompanyInformation::query()
            ->with(['banking_infos' => fn ($q) => $q->orderByDesc('set_as_default')->orderBy('id')])
            ->first();

        $defaultPaymentMethod = $companyInfo?->banking_infos
            ?->first(fn ($bank) => (bool) $bank->set_as_default)
            ?? $companyInfo?->banking_infos?->first();

        $orderStatus = $this->normalizeStatus($saleOrder->order_status);
        $paymentStatus = strtoupper((string) (is_object($saleOrder->payment_status)
            ? $saleOrder->payment_status->value
            : $saleOrder->payment_status));
        $documentLabel = $orderStatus === SaleOrderStatusEnum::DRAFT->value ? 'QUOTATION INVOICE' : 'INVOICE';

        $customer = $saleOrder->customer;
        $orderItems = $saleOrder->orderItems ?? collect();

        $items = $orderItems->map(function ($item, $index) {
            $product = $item->product;

            return [
                'line_no' => $index + 1,
                'product_name' => (string) ($product?->product_name ?? "Product #{$item->product_id}"),
                'sku' => (string) ($product?->product_sku_code ?? '-'),
                'note' => (string) ($item->note ?? '-'),
                'quantity' => number_format((float) ($item->quantity ?? 0), 2),
                'unit_price_usd' => $this->formatUsd((float) ($item->unit_price_in_usd ?? 0)),
                'total_price_usd' => $this->formatUsd((float) ($item->total_price_in_usd ?? 0)),
                'total_price_riel' => $this->formatRiel((float) ($item->total_price_in_riel ?? 0)),
            ];
        })->values()->all();

        $companyAddress = trim((string) ($companyInfo?->full_address ?? ''));
        if ($companyAddress === '') {
            $companyAddress = implode(', ', array_filter([
                $companyInfo?->house_number,
                $companyInfo?->street,
                $companyInfo?->commune,
                $companyInfo?->district,
                $companyInfo?->city,
            ]));
        }

        return [
            'invoice' => [
                'title' => $documentLabel === 'QUOTE' ? 'Sales Quote' : 'Sales Invoice',
                'document_label' => $documentLabel,
                'number' => (string) $saleOrder->order_no,
                'generated_at' => $this->formatDate((string) $saleOrder->created_at, 'M d, Y h:i A'),
            ],
            'company' => [
                'name' => (string) ($companyInfo?->company_name ?? 'Warehouse Management System'),
                'initials' => $this->makeInitials((string) ($companyInfo?->company_name ?? 'WMS')),
                'website' => (string) ($companyInfo?->website_url ?? '-'),
                'logo' => $this->resolveAssetUrl($companyInfo?->company_logo),
                'contact_person' => (string) ($companyInfo?->contact_person ?? '-'),
                'email' => (string) ($companyInfo?->email ?? '-'),
                'phone' => (string) ($companyInfo?->phone_number ?? '-'),
                'vat_number' => (string) ($companyInfo?->vat_number ?? '-'),
                'address' => $companyAddress !== '' ? $companyAddress : '-',
            ],
            'meta' => [
                'order_status' => $orderStatus,
                'payment_status' => $paymentStatus !== '' ? $paymentStatus : '-',
                'order_date' => $this->formatDate((string) $saleOrder->order_date),
                'return_valid_until' => $this->formatDate($saleOrder->return_valid_until),
            ],
            'bill_to' => [
                'name' => (string) ($customer?->fullname ?? 'Walk-in Customer'),
                'code' => (string) ($customer?->customer_code ?? '-'),
                'email' => (string) ($customer?->email_address ?? '-'),
                'phone' => (string) ($customer?->phone_number ?? '-'),
                'category' => (string) ($customer?->customerCategory?->category_name ?? '-'),
                'address' => (string) ($customer?->customer_address ?? '-'),
            ],
            'items' => !empty($items) ? $items : [[
                'line_no' => 1,
                'product_name' => 'No items',
                'sku' => '-',
                'note' => '-',
                'quantity' => '0.00',
                'unit_price_usd' => $this->formatUsd(0),
                'total_price_usd' => $this->formatUsd(0),
                'total_price_riel' => $this->formatRiel(0),
            ]],
            'payment' => [
                'bank_name' => (string) ($defaultPaymentMethod?->bank_name ?? 'Not configured'),
                'account_holder' => (string) ($defaultPaymentMethod?->bank_account_holder_name ?? '-'),
                'account_number' => (string) ($defaultPaymentMethod?->bank_account_number ?? '-'),
                'payment_link' => (string) ($defaultPaymentMethod?->payment_link ?? '-'),
                'khqr_image' => $this->resolveAssetUrl($defaultPaymentMethod?->khqr_code),
            ],
            'summary' => [
                'sub_total_usd' => $this->formatUsd((float) ($saleOrder->sub_total_in_usd ?? 0)),
                'discount_amount_usd' => '-'.$this->formatUsd((float) ($saleOrder->discount_amount ?? 0)),
                'tax_percentage' => number_format((float) ($saleOrder->tax_percentage ?? 0), 2) . '%',
                'tax_amount_usd' => $this->formatUsd((float) ($saleOrder->tax_amount_in_usd ?? 0)),
                'paid_amount_usd' => $this->formatUsd((float) ($saleOrder->paid_amount_in_usd ?? 0)),
                'refunded_amount_usd' => '-'.$this->formatUsd((float) ($saleOrder->total_refunded_amount_in_usd ?? 0)),
                'remaining_balance_usd' => $this->formatUsd((float) ($saleOrder->remaining_balance_in_usd ?? 0)),
                'total_amount_usd' => $this->formatUsd((float) ($saleOrder->grand_total_amount_in_usd ?? 0)),
                'total_amount_riel' => number_format((float) ($saleOrder->grand_total_amount_in_riel ?? 0), 2) . ' KHR',
            ],
            'note' => (string) ($saleOrder->note ?: ($documentLabel === 'QUOTE'
                ? 'This is a quotation draft and may change before order processing.'
                : 'Thank you for your business.')),
            'footer' => [
                'left' => 'Confidential document. Internal use only.',
                'right' => 'WAREHOUSE MANAGEMENT SYSTEM',
            ],
        ];
    }

    private function formatUsd(float|int|string|null $amount): string
    {
        return '$' . number_format((float) ($amount ?? 0), 2);
    }

    private function formatRiel(float|int|string|null $amount): string
    {
        return '៛' . number_format((float) ($amount ?? 0), 2);
    }

    private function formatDate(string|\DateTimeInterface|null $date, string $format = 'M d, Y'): string
    {
        if ($date === null || (is_string($date) && trim($date) === '')) {
            return '-';
        }

        try {
            return Carbon::parse($date)->format($format);
        } catch (\Throwable) {
            return (string) $date;
        }
    }

    private function normalizeStatus(mixed $status): string
    {
        if (is_object($status) && property_exists($status, 'value')) {
            return strtoupper((string) $status->value);
        }

        return strtoupper((string) $status);
    }

    private function makeInitials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $initials = collect($words)
            ->filter(fn ($word) => $word !== '')
            ->take(2)
            ->map(fn ($word) => strtoupper(Str::substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'WMS';
    }

    private function resolveAssetUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', 'data:image'])) {
            return $path;
        }

        return url($path);
    }
}

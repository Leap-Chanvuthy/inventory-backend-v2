<?php

namespace App\Service\Pdf;

use App\Enums\SaleOrderStatusEnum;
use App\Models\CompanyInformation;
use App\Models\SaleOrder;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

class SaleOrderInvoicePdfService
{
    public function download(SaleOrder $saleOrder): Response
    {
        $html = $this->render($saleOrder);

        $tempDir = storage_path('app/mpdf');

        if (! File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0775, true);
        }

        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'] ?? [];

        if (! is_array($fontDirs)) {
            $fontDirs = [];
        }

        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'] ?? [];

        if (! is_array($fontData)) {
            $fontData = [];
        }

        $fontDir = public_path('fonts');
        $fontRegular = $fontDir . DIRECTORY_SEPARATOR . 'KhmerOS_siemreap.ttf';

        if (! file_exists($fontRegular)) {
            throw new \RuntimeException("Font not found: {$fontRegular}");
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'fontDir' => array_merge($fontDirs, [$fontDir]),
            'fontdata' => $fontData + [
                'siemreap' => [
                    'R' => 'KhmerOS_siemreap.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'default_font' => 'siemreap',
        ]);

        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;

        $mpdf->SetTitle('Sale Order Invoice - ' . ((string) $saleOrder->order_no));
        $mpdf->SetAuthor(config('app.name', 'Inventory System'));
        $mpdf->SetCreator(config('app.name', 'Inventory System'));

        $mpdf->WriteHTML($html);

        $filename = 'sale-order-' . ((string) $saleOrder->order_no) . '.pdf';

        return response($mpdf->Output('', 'S'), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function render(SaleOrder $saleOrder): string
    {
        return view('pdf.sale-orders.invoice', $this->prepareData($saleOrder))->render();
    }

    private function prepareData(SaleOrder $saleOrder): array
    {
        $saleOrder->loadMissing([
            'customer.customerCategory',
            'orderItems.product',
        ]);

        $companyInfo = CompanyInformation::query()
            ->with([
                'banking_infos' => fn ($q) => $q
                    ->orderByDesc('set_as_default')
                    ->orderBy('id'),
            ])
            ->first();

        $defaultPaymentMethod = $companyInfo?->banking_infos
            ?->first(fn ($bank) => (bool) $bank->set_as_default)
            ?? $companyInfo?->banking_infos?->first();

        $orderStatus = $this->normalizeStatus($saleOrder->order_status);

        $paymentStatus = strtoupper((string) (
            is_object($saleOrder->payment_status)
                ? $saleOrder->payment_status->value
                : $saleOrder->payment_status
        ));

        $documentLabel = $orderStatus === SaleOrderStatusEnum::DRAFT->value
            ? 'QUOTATION INVOICE'
            : 'INVOICE';

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

        $companyName = (string) ($companyInfo?->company_name ?? 'Warehouse Management System');

        return [
            'invoice' => [
                'title' => $documentLabel === 'QUOTATION INVOICE' ? 'Sales Quote' : 'Sales Invoice',
                'document_label' => $documentLabel,
                'number' => (string) $saleOrder->order_no,
                'generated_at' => now()->timezone(config('app.timezone', 'UTC'))->format('M d, Y h:i A'),
            ],
            'company' => [
                'name' => $companyName,
                'initials' => $this->makeInitials($companyName),
                'website' => (string) ($companyInfo?->website_url ?? '-'),
                'logo_data_uri' => $this->resolveImageDataUri($companyInfo?->company_logo),
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
            'items' => ! empty($items) ? $items : [[
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
                'khqr_data_uri' => $this->resolveImageDataUri($defaultPaymentMethod?->khqr_code),
            ],
            'summary' => [
                'sub_total_usd' => $this->formatUsd((float) ($saleOrder->sub_total_in_usd ?? 0)),
                'discount_amount_usd' => $this->formatNegativeUsd((float) ($saleOrder->discount_amount ?? 0)),
                'tax_percentage' => number_format((float) ($saleOrder->tax_percentage ?? 0), 2) . '%',
                'tax_amount_usd' => $this->formatUsd((float) ($saleOrder->tax_amount_in_usd ?? 0)),
                'paid_amount_usd' => $this->formatUsd((float) ($saleOrder->paid_amount_in_usd ?? 0)),
                'refunded_amount_usd' => $this->formatNegativeUsd((float) ($saleOrder->total_refunded_amount_in_usd ?? 0)),
                'remaining_balance_usd' => $this->formatUsd((float) ($saleOrder->remaining_balance_in_usd ?? 0)),
                'total_amount_usd' => $this->formatUsd((float) ($saleOrder->grand_total_amount_in_usd ?? 0)),
                'total_amount_riel' => $this->formatRiel((float) ($saleOrder->grand_total_amount_in_riel ?? 0)),
            ],
            'note' => (string) ($saleOrder->note ?? ''),
            'footer' => [
                'left' => 'Generated by Inventory System',
                'right' => $companyName,
            ],
        ];
    }

    private function resolveImageDataUri(?string $path): ?string
    {
        $path = trim((string) ($path ?? ''));

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, 'data:image')) {
            return $path;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $resolvedPath = $this->resolveLocalFilePath($path);

        if (! $resolvedPath || ! file_exists($resolvedPath)) {
            return null;
        }

        $contents = file_get_contents($resolvedPath);

        if ($contents === false) {
            return null;
        }

        $mime = File::mimeType($resolvedPath) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    private function resolveLocalFilePath(?string $path): ?string
    {
        $path = trim((string) ($path ?? ''));

        if ($path === '') {
            return null;
        }

        if (file_exists($path)) {
            return $path;
        }

        $normalizedPath = ltrim($path, '/');

        $storageRelativePath = Str::startsWith($normalizedPath, 'storage/')
            ? Str::after($normalizedPath, 'storage/')
            : $normalizedPath;

        $candidatePaths = array_unique([
            public_path($normalizedPath),
            public_path('storage/' . $storageRelativePath),
            storage_path('app/public/' . $storageRelativePath),
        ]);

        foreach ($candidatePaths as $candidatePath) {
            if (file_exists($candidatePath)) {
                return $candidatePath;
            }
        }

        return null;
    }

    private function formatUsd(float|int|string|null $amount): string
    {
        return '$' . number_format((float) ($amount ?? 0), 2);
    }

    private function formatRiel(float|int|string|null $amount): string
    {
        return '៛ ' . number_format((float) ($amount ?? 0), 2);
    }

    private function formatNegativeUsd(float|int|string|null $amount): string
    {
        $amount = (float) ($amount ?? 0);

        return $amount > 0 ? '-' . $this->formatUsd($amount) : $this->formatUsd(0);
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
}

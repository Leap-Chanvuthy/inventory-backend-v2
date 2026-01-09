<?php

namespace App\Service;

use App\Helpers\FileUploadHelper;
use App\Helpers\GetBankingLabel;
use App\Models\Supplier;
use App\Models\SupplierBank;
use Illuminate\Http\UploadedFile;

class SupplierBankService
{
    protected GetBankingLabel $bankLabelHelper;

    public function __construct(GetBankingLabel $bankLabelHelper)
    {
        $this->bankLabelHelper = $bankLabelHelper;
    }

    public function createMany(Supplier $supplier, array $banks): void
    {
        foreach ($banks as $bank) {
            if (isset($bank['qr_code_image']) && $bank['qr_code_image'] instanceof UploadedFile) {
                $bank['qr_code_image'] = FileUploadHelper::uploadSingle(
                    $bank['qr_code_image'],
                    'supplier-banks'
                );
            }

            $bank['bank_label'] = $this->bankLabelHelper
                ->getPaymentMethodLabel($bank['bank_name']);

            $bank['supplier_id'] = $supplier->id;

            SupplierBank::create($bank);
        }
    }

    /**
     * Update existing by bank_name, otherwise create new.
     * (Max-4 enforcement should be done in SupplierService before calling this.)
     */
    public function upsertByBankName(Supplier $supplier, array $banks): void
    {
        foreach ($banks as $bank) {
            $data = [
                'account_number' => $bank['account_number'],
                'account_holder_name' => $bank['account_holder_name'],
                'payment_link' => $bank['payment_link'] ?? null,
            ];

            if (isset($bank['qr_code_image']) && $bank['qr_code_image'] instanceof UploadedFile) {
                $data['qr_code_image'] = FileUploadHelper::uploadSingle(
                    $bank['qr_code_image'],
                    'supplier-banks'
                );
            }

            $data['bank_label'] = $this->bankLabelHelper
                ->getPaymentMethodLabel($bank['bank_name']);

            SupplierBank::updateOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'bank_name' => $bank['bank_name'],
                ],
                $data
            );
        }
    }
}
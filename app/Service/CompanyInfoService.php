<?php

namespace App\Service;

use App\Enums\PaymentMethodEnum;
use App\Enums\TelegramNotificationType;
use App\Helpers\FileUploadHelper;
use App\Models\CompanyInformation;
use App\Helpers\ResponseHelper;
use App\Models\CompanyBankingInfo;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use App\Service\AuditLoggerService;

class CompanyInfoService
{
    protected AuditLoggerService $auditLoggerService;

    public function __construct(AuditLoggerService $auditLoggerService)
    {
        $this->auditLoggerService = $auditLoggerService;
    }
    public function getCompanyInfo()
    {
        try {
            $company = CompanyInformation::with('banking_infos')->first();

            return ResponseHelper::success($company, "Company information retrieved successfully", 200);
        } catch (Exception $e) {
            return ResponseHelper::error("Failed to retrieve company information", 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    /**
     * GENERAL INFO
     */
    public function updateGeneralInfo($request)
    {
        try {
            $validated = $request->validate([
                'company_name'   => 'required|string',
                'email'          => 'required|email',
                'phone_number'   => 'required|string',
                'contact_person' => 'required|string',
                'industry_type'  => 'required|string',
                'website_url'    => 'nullable|string',
                'date_established' => 'nullable|date',
                'vat_number'     => 'nullable|string',
                'description'    => 'nullable|string',
            ]);

            DB::beginTransaction();

            $company = CompanyInformation::firstOrCreate([], []);

            $company = CompanyInformation::firstOrCreate([], []);

            // Snapshot before change
            $oldSnapshot = $this->auditLoggerService->snapshotModel($company);

            if ($request->hasFile('company_logo')) {
                $validated['company_logo'] = FileUploadHelper::uploadSingle(
                    $request->file('company_logo'),
                    'company_logo', // path folder
                    $company->company_logo
                );
            }

            $company->update($validated);
            $company->refresh();

            // Snapshot after and log diff
            $newSnapshot = $this->auditLoggerService->snapshotModel($company);
            $this->auditLoggerService->logDiff(
                'company.general.update',
                CompanyInformation::class,
                (int) $company->id,
                $oldSnapshot,
                $newSnapshot,
                null,
                ['context' => 'company_info_service']
            );

            DB::commit();

            return ResponseHelper::success(
                $validated,
                "General information updated successfully",
                200
            );
        }

        catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), "Validation Error");
        }

        catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error(
                "Failed to update general info",
                500,
                ['exception' => $e->getMessage()]
            );
        }
    }

    /**
     * ADDRESS INFO
     */
    public function updateAddressInfo($request)
    {
        try {
            $validated = $request->validate([
                'full_address' => 'required|string',
                'house_number' => 'nullable|string',
                'street'       => 'nullable|string',
                'commune'      => 'nullable|string',
                'district'     => 'nullable|string',
                'city'         => 'nullable|string',
            ]);

            DB::beginTransaction();

            $company = CompanyInformation::firstOrCreate([], []);

            $company = CompanyInformation::firstOrCreate([], []);

            // Snapshot before change
            $oldSnapshot = $this->auditLoggerService->snapshotModel($company);

            $company->update($validated);
            $company->refresh();

            // Snapshot after and log diff
            $newSnapshot = $this->auditLoggerService->snapshotModel($company);
            $this->auditLoggerService->logDiff(
                'company.address.update',
                CompanyInformation::class,
                (int) $company->id,
                $oldSnapshot,
                $newSnapshot,
                null,
                ['context' => 'company_info_service']
            );

            DB::commit();

            return ResponseHelper::success($validated, "Address information updated successfully", 200);
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors(), "Validation Error");
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error("Failed to update address info", 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    /**
     * TELEGRAM NOTIFICATION INFO
     */
    public function updateTelegramInfo($request)
    {
        try {
            // Validate type + chat_id
            $validated = $request->validate([
                'type'    => ['required', new Enum(TelegramNotificationType::class)],
                'chat_id' => 'required|string',
            ]);

            DB::beginTransaction();

            $type = TelegramNotificationType::from($validated['type']);

            $column = $type->columnName();

            $company = CompanyInformation::firstOrCreate([], []);

            // Snapshot before change
            $oldSnapshot = $this->auditLoggerService->snapshotModel($company);

            $company->update([
                $column => $validated['chat_id']
            ]);
            $company->refresh();

            // Snapshot after and log diff (include notification type in meta)
            $newSnapshot = $this->auditLoggerService->snapshotModel($company);
            $this->auditLoggerService->logDiff(
                'company.telegram.update',
                CompanyInformation::class,
                (int) $company->id,
                $oldSnapshot,
                $newSnapshot,
                null,
                ['context' => 'company_info_service', 'notification_type' => $validated['type']]
            );

            DB::commit();

            return ResponseHelper::success([
                $column => $company->$column
            ], ucfirst($validated['type']) . " chat ID updated", 200);
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors(), "Validation Error");
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error("Failed to update telegram info", 500, [
                'exception' => $e->getMessage()
            ]);
        }
    }

    /**
     * SETUP COMPANY PAYMENT
     */

    public function setupPayment($request)
    {
        try {
            $validated = $request->validate([
                'bank_name' => [
                    'required',
                    Rule::in([
                        PaymentMethodEnum::ABA->value,
                        PaymentMethodEnum::ACLEDA->value,
                        PaymentMethodEnum::WING->value,
                        PaymentMethodEnum::BAKONG->value,
                    ]),
                ],
                'payment_link' => 'required|string|max:255',
                'bank_account_holder_name' => 'required|string|max:255',
                'bank_account_number' => 'required|string|max:255',
                'khqr_code' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'set_as_default' => 'required|boolean',
            ]);

            DB::beginTransaction();

            $company = CompanyInformation::firstOrCreate([], []);

            // If set_as_default = true, remove default from the others
            if (!empty($validated['set_as_default']) && $validated['set_as_default']) {
                CompanyBankingInfo::where('company_information_id', $company->id)
                    ->update(['set_as_default' => false]);
            }

            $payment = CompanyBankingInfo::where('company_information_id', $company->id)
                ->where('bank_name', $validated['bank_name'])
                ->first();

            // Snapshot payment before create/update
            $oldPaymentSnapshot = $this->auditLoggerService->snapshotModel($payment);

            $oldQrUrl = $payment ? $payment->khqr_code : null;

            $validated['khqr_code'] = $request->hasFile('khqr_code')
                ? FileUploadHelper::uploadSingle(
                    $request->file('khqr_code'),
                    'khqr_images',
                    $oldQrUrl
                )
                : $oldQrUrl;

            $paymentData = [
                'company_information_id' => $company->id,
                'bank_name' => $validated['bank_name'],
                'payment_link' => $validated['payment_link'],
                'bank_account_holder_name' => $validated['bank_account_holder_name'],
                'bank_account_number' => $validated['bank_account_number'],
                'khqr_code' => $validated['khqr_code'],
                'set_as_default' => $validated['set_as_default'] ?? false,
                'payment_method_label' => $this->getPaymentMethodLabel($validated['bank_name']),
            ];

            // Create or update record
            if ($payment) {
                $payment->update($paymentData);
            } else {
                $payment = CompanyBankingInfo::create($paymentData);
            }

            // Snapshot after and log create/update accordingly
            $newPaymentSnapshot = $this->auditLoggerService->snapshotModel($payment->fresh());
            if (empty($oldPaymentSnapshot)) {
                $this->auditLoggerService->logChange(
                    'company.payment.create',
                    CompanyBankingInfo::class,
                    (int) $payment->id,
                    [],
                    $newPaymentSnapshot,
                    null,
                    ['context' => 'company_info_service']
                );
            } else {
                $this->auditLoggerService->logDiff(
                    'company.payment.update',
                    CompanyBankingInfo::class,
                    (int) $payment->id,
                    $oldPaymentSnapshot,
                    $newPaymentSnapshot,
                    null,
                    ['context' => 'company_info_service']
                );
            }

            DB::commit();

            return ResponseHelper::success(
                $paymentData,
                "Company payment setup successfully.",
                200
            );
        }

        catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        }

        catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error(
                "Failed to setup company payment",
                500,
                ['exception' => $e->getMessage()]
            );
        }
    }


    private function getPaymentMethodLabel(string $method): string
    {
        return match ($method) {
            PaymentMethodEnum::ABA->value   => "https://yt3.googleusercontent.com/ytc/AIdro_ljV-vXKHv8x9yHY_Z6RuI9jutIh6f8D0O1oYIY43fJiNo=s900-c-k-c0x00ffffff-no-rj",
            PaymentMethodEnum::ACLEDA->value => "https://www.acledabank.com.kh/kh/assets/layout/logo1.png",
            PaymentMethodEnum::WING->value  => "https://www.wingbank.com.kh/wp-content/uploads/2023/11/Wing-Bank-WIngmall-Logo-01-scaled.jpg",
            PaymentMethodEnum::BAKONG->value  => "https://api.nuget.org/v3-flatcontainer/kh.gov.nbc.bakongkhqr/1.0.0.15/icon",
            default => "",
        };
    }
}

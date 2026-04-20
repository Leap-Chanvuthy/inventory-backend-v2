<?php

namespace App\Service;

use App\Helpers\FileUploadHelper;
use App\Helpers\ResponseHelper;
use App\Models\CustomerFinancial;
use App\Models\Customer;
use App\QueryBuilders\CustomerQueryBuilder;
use App\Validations\CustomerAdvancedValidation;
use App\Validations\CustomerValidation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Service\AuditLoggerService;

class CustomerService {
    
    protected $customerBuilder;
    protected $customerValidation;
    protected CustomerAdvancedValidation $customerAdvancedValidation;
    protected AuditLoggerService $auditLoggerService;
    protected CustomerSearchService $customerSearchService;
    protected CustomerResolverService $customerResolverService;
    protected CustomerProfileService $customerProfileService;
    protected CustomerAddressService $customerAddressService;
    protected CustomerCreditService $customerCreditService;
    protected CustomerPaymentService $customerPaymentService;
    protected CustomerPricingService $customerPricingService;
    protected CustomerTagService $customerTagService;
    protected CustomerAnalyticsService $customerAnalyticsService;
    protected CustomerTimelineService $customerTimelineService;

    public function __construct(
        CustomerQueryBuilder $customerBuilder,
        CustomerValidation $customerValidation
        , AuditLoggerService $auditLoggerService,
        CustomerAdvancedValidation $customerAdvancedValidation,
        CustomerSearchService $customerSearchService,
        CustomerResolverService $customerResolverService,
        CustomerProfileService $customerProfileService,
        CustomerAddressService $customerAddressService,
        CustomerCreditService $customerCreditService,
        CustomerPaymentService $customerPaymentService,
        CustomerPricingService $customerPricingService,
        CustomerTagService $customerTagService,
        CustomerAnalyticsService $customerAnalyticsService,
        CustomerTimelineService $customerTimelineService
    )
    {
        $this->customerBuilder = $customerBuilder;
        $this->customerValidation = $customerValidation;
        $this->auditLoggerService = $auditLoggerService;
        $this->customerAdvancedValidation = $customerAdvancedValidation;
        $this->customerSearchService = $customerSearchService;
        $this->customerResolverService = $customerResolverService;
        $this->customerProfileService = $customerProfileService;
        $this->customerAddressService = $customerAddressService;
        $this->customerCreditService = $customerCreditService;
        $this->customerPaymentService = $customerPaymentService;
        $this->customerPricingService = $customerPricingService;
        $this->customerTagService = $customerTagService;
        $this->customerAnalyticsService = $customerAnalyticsService;
        $this->customerTimelineService = $customerTimelineService;
    }


    public function getCustomers(Request $request)
    {
        try {
            $customers = $this->customerBuilder->customerBuilder($request);
            return ResponseHelper::success($customers, "Customers retrieved successfully", 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Error fetching customers', 500, $e->getMessage());
        }
    }

    public function getSegmentedCustomers(Request $request)
    {
        try {
            $validated = $this->customerAdvancedValidation->validateCustomerSegmentation($request);

            $segmentationRequest = new Request(array_merge(
                $request->query(),
                $validated
            ));

            $customers = $this->customerBuilder->segmentedBuilder($segmentationRequest);

            return ResponseHelper::success($customers, 'Segmented customers retrieved successfully', 200);
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors(), 'Validation Errors', 422);
        } catch (Exception $e) {
            return ResponseHelper::error('Error fetching segmented customers', 500, $e->getMessage());
        }
    }


    public function getCustomerById($id){
        try {
            $customer = Customer::with([
                'customerCategory' => fn ($q) => $q->withTrashed(),
                'customerFinancial',
                'tags',
                'addresses',
                ])-> findOrFail($id);
            if (!$customer) {
                return ResponseHelper::error('Customer not found', 404);
            }
            return ResponseHelper::success($customer, "Customer retrieved successfully", 200);
        }catch (Exception $e) {
            return ResponseHelper::error('Error fetching customer', 500, $e->getMessage());
        }
    }


    public function createCustomer(Request $request){
        try {
            $validated = $this -> customerValidation -> CreateValidation($request);
            $paymentTerms = $validated['payment_terms'] ?? null;
            unset($validated['payment_terms']);

            if ($request->hasFile('image')) {
                $validated['image'] = FileUploadHelper::uploadSingle(
                    $request->file('image'),
                    'customers'
                );
            }

            $customer = Customer::create($validated);

            CustomerFinancial::query()->updateOrCreate(
                ['customer_id' => $customer->id],
                ['payment_terms' => $paymentTerms ?? 'NET_0']
            );

            // Audit: record creation
            $this->auditLoggerService->logChange(
                'customer.create',
                Customer::class,
                (int) $customer->id,
                [],
                $this->auditLoggerService->snapshotModel($customer->load(['customerCategory'])),
                null,
                ['context' => 'customer_service']
            );
            return ResponseHelper::success($customer, "Customer created successfully", 201);
        }
        catch (ValidationException $ve){
            return ResponseHelper::validation($ve->errors() , 'Validation Errors', 422);
        } 
        catch (Exception $e) {
            return ResponseHelper::error('Error creating customer', 500, $e->getMessage());
        }
    }


    public function updateCustomer(Request $request, $id){
        try {
            $customer = Customer::findOrFail($id);
            if (!$customer) {
                return ResponseHelper::error('Customer not found', 404);
            }

            $validated = $this -> customerValidation -> UpdateValidation($request, $id);
            $paymentTerms = $validated['payment_terms'] ?? null;
            unset($validated['payment_terms']);

            if ($request->hasFile('image')) {
                $validated['image'] = FileUploadHelper::uploadSingle(
                    $request->file('image'),
                    'customers'
                );
            }

            // Snapshot before update
            $oldSnapshot = $this->auditLoggerService->snapshotModel($customer->load(['customerCategory']));

            $customer->update($validated);

            if ($paymentTerms !== null) {
                CustomerFinancial::query()->updateOrCreate(
                    ['customer_id' => $customer->id],
                    ['payment_terms' => $paymentTerms]
                );
            }

            $customer->refresh();

            // Snapshot after and log diff
            $newSnapshot = $this->auditLoggerService->snapshotModel($customer->fresh(['customerCategory']));
            $this->auditLoggerService->logDiff(
                'customer.update',
                Customer::class,
                (int) $customer->id,
                $oldSnapshot,
                $newSnapshot,
                null,
                ['context' => 'customer_service']
            );

            return ResponseHelper::success($customer, "Customer updated successfully", 200);
        }
        catch (ValidationException $ve){
            return ResponseHelper::validation($ve->errors() , 'Validation Errors', 422);
        } 
        catch (Exception $e) {
            return ResponseHelper::error('Error updating customer', 500, $e->getMessage());
        }
    }


    public function deleteCustomer($id){
        try {
            $customer = Customer::findOrFail($id);
            if (!$customer) {
                return ResponseHelper::error('Customer not found', 404);
            }
            // Snapshot before delete
            $oldSnapshot = $this->auditLoggerService->snapshotModel($customer->load(['customerCategory']));

            $customer->delete();

            $this->auditLoggerService->logChange(
                'customer.delete',
                Customer::class,
                (int) $customer->id,
                $oldSnapshot,
                [],
                null,
                ['context' => 'customer_service']
            );

            return ResponseHelper::success(null, "Customer deleted successfully", 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Error deleting customer', 500, $e->getMessage());
        }
    }

    public function posSearch(Request $request)
    {
        try {
            $validated = $this->customerAdvancedValidation->validatePosSearch($request);

            $results = $this->customerSearchService
                ->search($validated['keyword'], (int) ($validated['limit'] ?? 15))
                ->map(fn ($dto) => $dto->toArray())
                ->values();

            return ResponseHelper::success($results, 'POS customer search completed', 200);
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors(), 'Validation Errors', 422);
        } catch (Exception $e) {
            return ResponseHelper::error('Error searching customers for POS', 500, $e->getMessage());
        }
    }

    public function getWalkInCustomer()
    {
        try {
            $customer = $this->customerResolverService->getOrCreateWalkIn();
            $dto = $this->customerResolverService->toPosDTO($customer);

            return ResponseHelper::success($dto->toArray(), 'Walk-in customer resolved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Error resolving walk-in customer', 500, $e->getMessage());
        }
    }

    public function getCustomerProfile(int $id)
    {
        try {
            $profile = $this->customerProfileService->getProfile($id);

            return ResponseHelper::success($profile->toArray(), 'Customer profile retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Error fetching customer profile', 500, $e->getMessage());
        }
    }

    public function setDefaultAddress(Request $request)
    {
        try {
            $validated = $this->customerAdvancedValidation->validateAddressDefaultRequest($request);

            $address = $this->customerAddressService->setDefaultAddress(
                (int) $validated['customer_id'],
                (int) $validated['address_id']
            );

            return ResponseHelper::success($address, 'Default address updated successfully', 200);
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors(), 'Validation Errors', 422);
        } catch (Exception $e) {
            return ResponseHelper::error('Error setting default address', 500, $e->getMessage());
        }
    }

    public function canPurchase(Request $request, int $id)
    {
        try {
            $amount = (float) $request->input('amount', 0);
            $customer = Customer::query()->with(['customerFinancial', 'customerCategory'])->findOrFail($id);
            $canPurchase = $this->customerCreditService->canPurchase($customer, $amount);
            $discountedAmount = $this->customerPricingService->calculateDiscountedPrice($customer, $amount);
            $paymentTerm = $this->customerPaymentService->getPaymentTerm($customer);
            $discountPercentage = (float) ($customer->customerCategory?->discount_percentage ?? 0);

            return ResponseHelper::success([
                'customer_id' => $customer->id,
                'amount' => $amount,
                'discount_percentage' => round($discountPercentage, 2),
                'discounted_amount' => $discountedAmount,
                'payment_terms' => $paymentTerm->value,
                'can_purchase' => $canPurchase,
            ], 'POS checkout pricing preview completed', 200);
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors(), 'Validation Errors', 422);
        } catch (Exception $e) {
            return ResponseHelper::error('Error generating POS checkout pricing preview', 500, $e->getMessage());
        }
    }

    public function applySale(Request $request, int $id)
    {
        try {
            $amount = (float) $request->input('amount', 0);
            $customer = Customer::query()->with(['customerFinancial', 'customerCategory'])->findOrFail($id);
            $this->customerCreditService->applySale($customer, $amount);

            $discountedAmount = $this->customerPricingService->calculateDiscountedPrice($customer, $amount);
            $paymentTerm = $this->customerPaymentService->getPaymentTerm($customer);

            return ResponseHelper::success([
                'customer_id' => $customer->id,
                'amount' => $amount,
                'discounted_amount' => $discountedAmount,
                'payment_terms' => $paymentTerm->value,
            ], 'Sale pricing resolved successfully', 200);
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors(), 'Validation Errors', 422);
        } catch (Exception $e) {
            return ResponseHelper::error('Error resolving sale pricing', 500, $e->getMessage());
        }
    }

    public function applyPayment(Request $request, int $id)
    {
        try {
            $amount = (float) $request->input('amount', 0);
            $customer = Customer::query()->with('customerFinancial')->findOrFail($id);
            $this->customerCreditService->applyPayment($customer, $amount);
            $paymentTerm = $this->customerPaymentService->getPaymentTerm($customer);

            return ResponseHelper::success([
                'customer_id' => $customer->id,
                'payment_terms' => $paymentTerm->value,
            ], 'Customer payment term confirmed successfully', 200);
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors(), 'Validation Errors', 422);
        } catch (Exception $e) {
            return ResponseHelper::error('Error resolving customer payment term', 500, $e->getMessage());
        }
    }

    public function customerStats(int $id)
    {
        try {
            $stats = $this->customerAnalyticsService->getStats($id);

            return ResponseHelper::success($stats->toArray(), 'Customer analytics retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Error fetching customer analytics', 500, $e->getMessage());
        }
    }

    public function customerTimeline(Request $request, int $id)
    {
        try {
            $limit = max(1, min((int) $request->query('limit', 50), 100));
            $items = $this->customerTimelineService->getTimeline($id, $limit)
                ->map(fn ($dto) => $dto->toArray())
                ->values();

            return ResponseHelper::success($items, 'Customer timeline retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Error fetching customer timeline', 500, $e->getMessage());
        }
    }

    public function attachTags(Request $request, int $id)
    {
        try {
            $tagIds = (array) $request->input('tag_ids', []);
            $this->customerTagService->attachTags($id, $tagIds);

            return ResponseHelper::success([], 'Tags attached successfully', 200);
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors(), 'Validation Errors', 422);
        } catch (Exception $e) {
            return ResponseHelper::error('Error attaching tags', 500, $e->getMessage());
        }
    }

    public function syncTags(Request $request, int $id)
    {
        try {
            $tagIds = (array) $request->input('tag_ids', []);
            $this->customerTagService->syncTags($id, $tagIds);

            return ResponseHelper::success([], 'Tags synced successfully', 200);
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors(), 'Validation Errors', 422);
        } catch (Exception $e) {
            return ResponseHelper::error('Error syncing tags', 500, $e->getMessage());
        }
    }

    public function detachTag(int $id, int $tagId)
    {
        try {
            $this->customerTagService->detachTag($id, $tagId);

            return ResponseHelper::success([], 'Tag detached successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Error detaching tag', 500, $e->getMessage());
        }
    }



}
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Service\CustomerService;
use Illuminate\Http\Request;

class CustomerAPIController extends Controller
{

    protected $customerService;

    public function __construct(
        CustomerService $customerService
    )
    {
       $this->customerService = $customerService;
    }


    public function index (Request $request)
    {
        return $this->customerService->getCustomers($request);
    }
    
    public function trashed(Request $request)
    {
        return $this->customerService->getTrashedCustomers($request);
    }

    public function show ($id){
        return $this -> customerService -> getCustomerById($id);
    }


    public function store (Request $request){
        return $this -> customerService -> createCustomer($request);
    }

    public function update (Request $request , $id){
        return $this -> customerService -> updateCustomer($request , $id);
    }


    public function destroy ($id){
        return $this -> customerService -> deleteCustomer($id);
    }

    public function restore ($id){
        return $this -> customerService -> restoreCustomer($id);
    }

    public function posSearch(Request $request)
    {
        return $this->customerService->posSearch($request);
    }

    public function walkIn()
    {
        return $this->customerService->getWalkInCustomer();
    }

    public function profile(int $id)
    {
        return $this->customerService->getCustomerProfile($id);
    }

    public function setDefaultAddress(Request $request)
    {
        return $this->customerService->setDefaultAddress($request);
    }

    public function canPurchase(Request $request, int $id)
    {
        return $this->customerService->canPurchase($request, $id);
    }

    public function applySale(Request $request, int $id)
    {
        return $this->customerService->applySale($request, $id);
    }

    public function applyPayment(Request $request, int $id)
    {
        return $this->customerService->applyPayment($request, $id);
    }

    public function segmented(Request $request)
    {
        return $this->customerService->getSegmentedCustomers($request);
    }

    public function stats(int $id)
    {
        return $this->customerService->customerStats($id);
    }

    public function timeline(Request $request, int $id)
    {
        return $this->customerService->customerTimeline($request, $id);
    }

    public function attachTags(Request $request, int $id)
    {
        return $this->customerService->attachTags($request, $id);
    }

    public function syncTags(Request $request, int $id)
    {
        return $this->customerService->syncTags($request, $id);
    }

    public function detachTag(int $id, int $tagId)
    {
        return $this->customerService->detachTag($id, $tagId);
    }




}

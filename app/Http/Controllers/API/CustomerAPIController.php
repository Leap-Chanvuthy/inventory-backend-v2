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




}

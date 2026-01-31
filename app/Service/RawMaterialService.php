<?php


namespace App\Service;

use App\Helpers\ResponseHelper;

class RawMaterialService
{
    


    public function __construct()
    {
        
    }



    public function createRawMaterial($request)
    {
        try {

        


        }catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }



}
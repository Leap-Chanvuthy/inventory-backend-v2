<?php

namespace App\Service;


use App\Helpers\ResponseHelper;
use App\Models\UOM;
use App\QueryBuilders\UOMQueryBuilder;
use App\Validations\UOMValidation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UOMService
{

    protected $UOMQueryBuilder;
    protected $UOMValidation;

    public function __construct(
        UOMValidation $UOMValidation,
        UOMQueryBuilder $UOMQueryBuilder
    )
    {
        $this->UOMValidation = $UOMValidation;
        $this->UOMQueryBuilder = $UOMQueryBuilder;  
    }



    public function getAllUOM(Request $request)
    {
        try {
            return $this->UOMQueryBuilder->UOMBuilder($request);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to fetch UOM: ', 500, $e->getMessage());
        }
    }

    public function getUOMById($id)
    {
        try {
            $uom = UOM::findOrFail($id);
            return $uom;
        } catch (Exception $e) {
            return ResponseHelper::error('UOM not found: ', 404, $e->getMessage());
        }
    }


    public function createUOM(Request $request)
    {
        try {
            $validated = $this -> UOMValidation -> CreateValidationFields($request);

            $uom = UOM::create($validated);
            return ResponseHelper::success( $uom, 'UOM created successfully', 201);
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors() , 422);
        }
        catch (Exception $e) {
            return ResponseHelper::error('Failed to create UOM: ', 500, $e->getMessage());
        }
    }

    public function updateUOM(Request $request , $id)
    {
        try {
            $validated = $this -> UOMValidation -> UpdateValidationFields($request, $id);
            $uom = UOM::findOrFail($id);
            $uom->update($validated);
            return ResponseHelper::success( $uom -> fresh(), 'UOM updated successfully', 200);
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors() , 422);
        }
        catch (Exception $e) {
            return ResponseHelper::error('Failed to create UOM: ', 500, $e->getMessage());
        }
    }

}

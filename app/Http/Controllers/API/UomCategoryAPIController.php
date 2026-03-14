<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\API\Interfaces\UomCategoryAPIControllerInterface;
use App\Service\UomCategoryService;
use Illuminate\Http\Request;

class UomCategoryAPIController extends Controller implements UomCategoryAPIControllerInterface
{
    protected UomCategoryService $uomCategoryService;

    public function __construct(UomCategoryService $uomCategoryService)
    {
        $this->uomCategoryService = $uomCategoryService;
    }

    public function index(Request $request)
    {
        return $this->uomCategoryService->getAllCategories($request);
    }

    public function show(int $id)
    {
        return $this->uomCategoryService->getCategoryById($id);
    }

    public function store(Request $request)
    {
        return $this->uomCategoryService->createCategory($request);
    }

    public function update(Request $request, int $id)
    {
        return $this->uomCategoryService->updateCategory($request, $id);
    }

    public function delete(int $id)
    {
        return $this->uomCategoryService->deleteCategory($id);
    }

    /**
     * Restore a soft-deleted category.
     * PATCH /uom-categories/{id}/restore
     */
    public function restore(int $id)
    {
        return $this->uomCategoryService->restoreCategory($id);
    }

    /**
     * List only soft-deleted (trashed) categories.
     * GET /uom-categories/trashed
     */
    public function trashed(Request $request)
    {
        return $this->uomCategoryService->getTrashedCategories($request);
    }
}

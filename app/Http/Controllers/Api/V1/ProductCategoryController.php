<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get list of product categories
     * Query params:
     *   - include_structure (optional): set to 'true' untuk include subcategory structure
     */
    public function index(Request $request)
    {
        try {
            $includeStructure = $request->input('include_structure', 'false') === 'true';
            
            $categories = ProductCategory::select('id', 'name', 'products', 'description')
                ->orderBy('name')
                ->get();

            $fullStructure = $includeStructure ? ProductCategory::getProductCategoryStructure() : null;

            return $this->successResponse([
                'categories' => $categories->map(function ($category) use ($includeStructure, $fullStructure) {
                    $response = [
                        'id' => $category->id,
                        'name' => $category->name,
                        'products' => $category->products,
                        'description' => $category->description
                    ];

                    if ($includeStructure) {
                        $response['structure'] = $fullStructure[$category->name] ?? [];
                    }

                    return $response;
                })
            ], 'Product categories retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving product categories: ' . $e->getMessage(),
                'CATEGORY_RETRIEVAL_ERROR',
                500
            );
        }
    }

    /**
     * Get products by category ID
     */
    public function getProducts(Request $request, int $categoryId)
    {
        try {
            $category = ProductCategory::find($categoryId);

            if (!$category) {
                return $this->notFoundResponse('Product category not found');
            }

            return $this->successResponse([
                'category_id' => $category->id,
                'category_name' => $category->name,
                'products' => $category->products
            ], 'Category products retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving category products: ' . $e->getMessage(),
                'CATEGORY_PRODUCTS_ERROR',
                500
            );
        }
    }

    /**
     * Search/autocomplete product names berdasarkan kategori dan query
     * Endpoint: GET /api/v1/product-categories/search-products
     * Query params:
     *   - category_id (optional): filter by category
     *   - q (optional): search query. Jika kosong, return ALL products
     * 
     * Use case:
     *   - q kosong + category_id ada -> return ALL products dari category tersebut
     *   - q ada + category_id ada -> return filtered products dari category tersebut
     *   - q ada + no category_id -> search across all categories
     */
    public function searchProducts(Request $request)
    {
        try {
            $query = $request->input('q', '');
            $categoryId = $request->input('category_id');

            // Jika ada category_id, filter by category
            if ($categoryId) {
                $category = ProductCategory::find($categoryId);
                
                if (!$category) {
                    return $this->notFoundResponse('Product category not found');
                }

                $allProducts = $category->products ?? [];
                
                // Jika query kosong, return ALL products dari category ini
                if (empty($query)) {
                    return $this->successResponse([
                        'category_id' => $category->id,
                        'category_name' => $category->name,
                        'query' => $query,
                        'products' => $allProducts,
                        'total' => count($allProducts)
                    ], 'All products retrieved successfully');
                }
                
                // Filter products yang mengandung query (case insensitive)
                $filteredProducts = array_values(array_filter($allProducts, function($product) use ($query) {
                    return stripos($product, $query) !== false;
                }));

                return $this->successResponse([
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'query' => $query,
                    'products' => $filteredProducts,
                    'total' => count($filteredProducts)
                ], 'Products search completed');
            }

            // Jika tidak ada category_id DAN query kosong, return error
            if (empty($query)) {
                return $this->errorResponse(
                    'Please provide either category_id or search query (q)',
                    'VALIDATION_ERROR',
                    400
                );
            }

            // Jika tidak ada category_id tapi ada query, search di semua kategori
            $categories = ProductCategory::all();
            $results = [];

            foreach ($categories as $category) {
                $allProducts = $category->products ?? [];
                $filteredProducts = array_values(array_filter($allProducts, function($product) use ($query) {
                    return stripos($product, $query) !== false;
                }));

                if (!empty($filteredProducts)) {
                    $results[] = [
                        'category_id' => $category->id,
                        'category_name' => $category->name,
                        'products' => $filteredProducts
                    ];
                }
            }

            return $this->successResponse([
                'query' => $query,
                'results' => $results,
                'total_categories' => count($results)
            ], 'Products search completed');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error searching products: ' . $e->getMessage(),
                'SEARCH_ERROR',
                500
            );
        }
    }

    /**
     * Get category dengan subcategory structure (hierarchical)
     * Endpoint: GET /api/v1/product-categories/structure
     */
    public function getStructure(Request $request)
    {
        try {
            $categoryId = $request->input('category_id');

            // Jika ada category_id, return structure untuk category tersebut
            if ($categoryId) {
                $category = ProductCategory::find($categoryId);
                
                if (!$category) {
                    return $this->notFoundResponse('Product category not found');
                }

                $structure = ProductCategory::getProductCategoryStructure();
                $categoryStructure = $structure[$category->name] ?? [];

                return $this->successResponse([
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'structure' => $categoryStructure,
                    'description' => $category->description
                ], 'Category structure retrieved successfully');
            }

            // Jika tidak ada category_id, return semua structure
            $categories = ProductCategory::all();
            $fullStructure = ProductCategory::getProductCategoryStructure();
            
            $response = [];
            foreach ($categories as $category) {
                $response[] = [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'structure' => $fullStructure[$category->name] ?? [],
                    'description' => $category->description
                ];
            }

            return $this->successResponse([
                'categories' => $response
            ], 'All category structures retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving category structure: ' . $e->getMessage(),
                'STRUCTURE_ERROR',
                500
            );
        }
    }
}
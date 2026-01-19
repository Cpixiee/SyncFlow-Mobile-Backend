<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use App\Enums\ToolType;
use App\Enums\ToolStatus;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;

class ToolController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get list of tools
     * Query params:
     *   - page (optional): page number, default 1
     *   - limit (optional): items per page, default 10
     *   - status (optional): filter by ACTIVE/INACTIVE
     *   - tool_model (optional): filter by tool model
     *   - tool_type (optional): filter by OPTICAL/MECHANICAL
     *   - search (optional): search by tool_name, tool_model, or imei
     */
    public function index(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 10);

            $query = Tool::query();

            // Filter by status
            if ($request->has('status')) {
                $status = strtoupper($request->input('status'));
                if (in_array($status, ['ACTIVE', 'INACTIVE'])) {
                    $query->where('status', $status);
                }
            }

            // Filter by tool_model
            if ($request->has('tool_model')) {
                $query->where('tool_model', $request->input('tool_model'));
            }

            // Filter by tool_type
            if ($request->has('tool_type')) {
                $toolType = strtoupper($request->input('tool_type'));
                if (in_array($toolType, ['OPTICAL', 'MECHANICAL'])) {
                    $query->where('tool_type', $toolType);
                }
            }

            // Search
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('tool_name', 'like', "%{$search}%")
                      ->orWhere('tool_model', 'like', "%{$search}%")
                      ->orWhere('imei', 'like', "%{$search}%")
                      ->orWhere('device_id', 'like', "%{$search}%");
                });
            }

            $tools = $query->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            $transformedTools = collect($tools->items())
                ->map(function ($tool) {
                    return [
                        'id' => $tool->id,
                        'tool_name' => $tool->tool_name,
                        'tool_model' => $tool->tool_model,
                        'tool_type' => $tool->tool_type->value,
                        'tool_type_description' => $tool->tool_type->getDescription(),
                        'last_calibration_at' => $tool->last_calibration_at?->format('Y-m-d'),
                        'next_calibration_at' => $tool->next_calibration_at?->format('Y-m-d'),
                        'imei' => $tool->imei,
                        'device_id' => $tool->device_id,
                        'status' => $tool->status->value,
                        'status_description' => $tool->status->getDescription(),
                        'created_at' => $tool->created_at->toISOString(),
                        'updated_at' => $tool->updated_at->toISOString(),
                    ];
                })->values()->all();

            return $this->paginationResponse(
                $transformedTools,
                [
                    'current_page' => $tools->currentPage(),
                    'total_page' => $tools->lastPage(),
                    'limit' => $tools->perPage(),
                    'total_docs' => $tools->total(),
                ],
                'Tools retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving tools: ' . $e->getMessage(),
                'TOOL_RETRIEVAL_ERROR',
                500
            );
        }
    }

    /**
     * Get single tool by ID
     */
    public function show(int $id)
    {
        try {
            $tool = Tool::find($id);

            if (!$tool) {
                return $this->notFoundResponse('Tool not found');
            }

            return $this->successResponse(
                [
                    'id' => $tool->id,
                    'tool_name' => $tool->tool_name,
                    'tool_model' => $tool->tool_model,
                    'tool_type' => $tool->tool_type->value,
                    'tool_type_description' => $tool->tool_type->getDescription(),
                    'last_calibration_at' => $tool->last_calibration_at?->format('Y-m-d'),
                    'next_calibration_at' => $tool->next_calibration_at?->format('Y-m-d'),
                    'imei' => $tool->imei,
                    'device_id' => $tool->device_id,
                    'status' => $tool->status->value,
                    'status_description' => $tool->status->getDescription(),
                    'created_at' => $tool->created_at->toISOString(),
                    'updated_at' => $tool->updated_at->toISOString(),
                ],
                'Tool retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving tool: ' . $e->getMessage(),
                'TOOL_RETRIEVAL_ERROR',
                500
            );
        }
    }

    /**
     * Create new tool
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tool_name' => 'required|string|max:255',
                'tool_model' => 'required|string|max:255',
                'tool_type' => ['required', new Enum(ToolType::class)],
                'last_calibration_at' => 'nullable|date',
                'next_calibration_at' => 'nullable|date',
                'imei' => 'required|string|max:255|unique:tools,imei',
                'device_id' => 'required|string|max:255',
                'status' => ['nullable', new Enum(ToolStatus::class)],
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            // Create tool
            $tool = Tool::create([
                'tool_name' => $request->input('tool_name'),
                'tool_model' => $request->input('tool_model'),
                'tool_type' => $request->input('tool_type'),
                'last_calibration_at' => $request->input('last_calibration_at'),
                'next_calibration_at' => $request->input('next_calibration_at'),
                'imei' => $request->input('imei'),
                'device_id' => $request->input('device_id'),
                'status' => $request->input('status', 'ACTIVE'),
            ]);

            return $this->successResponse(
                [
                    'id' => $tool->id,
                    'tool_name' => $tool->tool_name,
                    'tool_model' => $tool->tool_model,
                    'tool_type' => $tool->tool_type->value,
                    'tool_type_description' => $tool->tool_type->getDescription(),
                    'last_calibration_at' => $tool->last_calibration_at?->format('Y-m-d'),
                    'next_calibration_at' => $tool->next_calibration_at?->format('Y-m-d'),
                    'imei' => $tool->imei,
                    'device_id' => $tool->device_id,
                    'status' => $tool->status->value,
                    'status_description' => $tool->status->getDescription(),
                    'created_at' => $tool->created_at->toISOString(),
                    'updated_at' => $tool->updated_at->toISOString(),
                ],
                'Tool created successfully',
                201
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error creating tool: ' . $e->getMessage(),
                'TOOL_CREATE_ERROR',
                500
            );
        }
    }

    /**
     * Update existing tool
     */
    public function update(Request $request, int $id)
    {
        try {
            $tool = Tool::find($id);

            if (!$tool) {
                return $this->notFoundResponse('Tool not found');
            }

            $validator = Validator::make($request->all(), [
                'tool_name' => 'nullable|string|max:255',
                'tool_model' => 'nullable|string|max:255',
                'tool_type' => ['nullable', new Enum(ToolType::class)],
                'last_calibration_at' => 'nullable|date',
                'next_calibration_at' => 'nullable|date',
                'imei' => 'nullable|string|max:255|unique:tools,imei,' . $id,
                'device_id' => 'nullable|string|max:255',
                'status' => ['nullable', new Enum(ToolStatus::class)],
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            // Update tool - hanya update field yang dikirim
            if ($request->has('tool_name')) {
                $tool->tool_name = $request->input('tool_name');
            }
            if ($request->has('tool_model')) {
                $tool->tool_model = $request->input('tool_model');
            }
            if ($request->has('tool_type')) {
                $tool->tool_type = $request->input('tool_type');
            }
            if ($request->has('last_calibration_at')) {
                $tool->last_calibration_at = $request->input('last_calibration_at');
            }
            if ($request->has('next_calibration_at')) {
                $tool->next_calibration_at = $request->input('next_calibration_at');
            }
            if ($request->has('imei')) {
                $tool->imei = $request->input('imei');
            }
            if ($request->has('device_id')) {
                $tool->device_id = $request->input('device_id');
            }
            if ($request->has('status')) {
                $tool->status = $request->input('status');
            }

            $tool->save();

            return $this->successResponse(
                [
                    'id' => $tool->id,
                    'tool_name' => $tool->tool_name,
                    'tool_model' => $tool->tool_model,
                    'tool_type' => $tool->tool_type->value,
                    'tool_type_description' => $tool->tool_type->getDescription(),
                    'last_calibration_at' => $tool->last_calibration_at?->format('Y-m-d'),
                    'next_calibration_at' => $tool->next_calibration_at?->format('Y-m-d'),
                    'imei' => $tool->imei,
                    'device_id' => $tool->device_id,
                    'status' => $tool->status->value,
                    'status_description' => $tool->status->getDescription(),
                    'created_at' => $tool->created_at->toISOString(),
                    'updated_at' => $tool->updated_at->toISOString(),
                ],
                'Tool updated successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error updating tool: ' . $e->getMessage(),
                'TOOL_UPDATE_ERROR',
                500
            );
        }
    }

    /**
     * Delete tool
     */
    public function destroy(int $id)
    {
        try {
            $tool = Tool::find($id);

            if (!$tool) {
                return $this->notFoundResponse('Tool not found');
            }

            // Check if tool is being used in any products
            // Using JSON query to properly search in measurement_points
            $products = \App\Models\Product::all();
            $isUsedInProducts = false;
            
            foreach ($products as $product) {
                $measurementPoints = $product->measurement_points ?? [];
                foreach ($measurementPoints as $point) {
                    if (isset($point['setup']['source_tool_model']) && 
                        $point['setup']['source_tool_model'] === $tool->tool_model) {
                        $isUsedInProducts = true;
                        break 2;
                    }
                }
            }

            if ($isUsedInProducts) {
                return $this->errorResponse(
                    'Tool tidak dapat dihapus karena sedang digunakan di products',
                    'TOOL_IN_USE',
                    400
                );
            }

            $tool->delete();

            return $this->successResponse(
                ['deleted' => true],
                'Tool deleted successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error deleting tool: ' . $e->getMessage(),
                'TOOL_DELETE_ERROR',
                500
            );
        }
    }

    /**
     * Get unique tool models (untuk dropdown saat create product)
     * Hanya menampilkan tool yang ACTIVE
     */
    public function getModels()
    {
        try {
            $models = Tool::active()
                ->select('tool_model', 'tool_type')
                ->orderBy('tool_model')
                ->get()
                ->groupBy('tool_model')
                ->map(function ($items, $model) {
                    return [
                        'tool_model' => $model,
                        'tool_type' => $items->first()->tool_type->value,
                        'tool_type_description' => $items->first()->tool_type->getDescription(),
                        'imei_count' => $items->count(),
                    ];
                })->values();

            return $this->successResponse(
                $models->toArray(),
                'Tool models retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving tool models: ' . $e->getMessage(),
                'TOOL_MODELS_ERROR',
                500
            );
        }
    }

    /**
     * Get tools by model (untuk select IMEI saat measurement)
     * Hanya menampilkan tool yang ACTIVE dengan model tertentu
     */
    public function getByModel(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tool_model' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $toolModel = $request->input('tool_model');
            
            $tools = Tool::active()
                ->byModel($toolModel)
                ->get();

            if ($tools->isEmpty()) {
                return $this->notFoundResponse('No active tools found for this model');
            }

            return $this->successResponse(
                [
                    'tool_model' => $toolModel,
                    'tools' => $tools->map(function ($tool) {
                        return [
                            'id' => $tool->id,
                            'tool_name' => $tool->tool_name,
                            'imei' => $tool->imei,
                            'last_calibration_at' => $tool->last_calibration_at?->format('Y-m-d'),
                            'next_calibration_at' => $tool->next_calibration_at?->format('Y-m-d'),
                        ];
                    })->values()->all()
                ],
                'Tools retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving tools by model: ' . $e->getMessage(),
                'TOOL_BY_MODEL_ERROR',
                500
            );
        }
    }

    /**
     * Get tools with overdue calibration
     * Endpoint: GET /tools/overdue-calibration
     * Query params: date (required) - comparison date
     * 
     * Tool is overdue if next_calibration_at < date
     */
    public function getOverdueCalibration(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $comparisonDate = $request->input('date');
            
            // Get tools where next_calibration_at < comparison date
            // Only include ACTIVE tools with next_calibration_at set
            $tools = Tool::where('status', ToolStatus::ACTIVE)
                ->whereNotNull('next_calibration_at')
                ->where('next_calibration_at', '<', $comparisonDate)
                ->orderBy('next_calibration_at', 'asc') // Oldest overdue first
                ->get();

            $transformedTools = $tools->map(function ($tool) {
                return [
                    'id' => $tool->id,
                    'toolName' => $tool->tool_name,
                    'toolModel' => $tool->tool_model,
                    'toolType' => $tool->tool_type->value,
                    'toolTypeDescription' => $tool->tool_type->getDescription(),
                    'lastCalibration' => $tool->last_calibration_at?->toISOString(),
                    'nextCalibration' => $tool->next_calibration_at?->toISOString(),
                    'imei' => $tool->imei,
                    'deviceId' => $tool->device_id,
                    'status' => $tool->status->value,
                    'statusDescription' => $tool->status->getDescription(),
                    'createdAt' => $tool->created_at->toISOString(),
                    'updatedAt' => $tool->updated_at->toISOString(),
                ];
            })->values()->all();

            return $this->successResponse(
                $transformedTools,
                'Overdue calibration tools retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving overdue calibration tools: ' . $e->getMessage(),
                'OVERDUE_CALIBRATION_ERROR',
                500
            );
        }
    }
}


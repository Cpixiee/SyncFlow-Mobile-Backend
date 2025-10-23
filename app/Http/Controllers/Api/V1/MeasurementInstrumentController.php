<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MeasurementInstrument;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Carbon\Carbon;

class MeasurementInstrumentController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get list of measurement instruments
     */
    public function index(Request $request)
    {
        try {
            $query = MeasurementInstrument::query();

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            } else {
                // Default to active only
                $query->active();
            }

            // Search by name if provided
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('model', 'like', "%{$search}%")
                      ->orWhere('serial_number', 'like', "%{$search}%");
                });
            }

            $instruments = $query->select(
                'id',
                'name',
                'model',
                'serial_number',
                'manufacturer',
                'status',
                'description',
                'last_calibration',
                'next_calibration'
            )
            ->orderBy('name')
            ->get();

            return $this->successResponse([
                'instruments' => $instruments->map(function ($instrument) {
                    return [
                        'id' => $instrument->id,
                        'name' => $instrument->name,
                        'model' => $instrument->model,
                        'serial_number' => $instrument->serial_number,
                        'manufacturer' => $instrument->manufacturer,
                        'status' => $instrument->status,
                        'description' => $instrument->description,
                        'display_name' => $instrument->display_name,
                        'needs_calibration' => $instrument->needsCalibration(),
                        'last_calibration' => $instrument->last_calibration?->format('Y-m-d'),
                        'next_calibration' => $instrument->next_calibration?->format('Y-m-d')
                    ];
                })
            ], 'Measurement instruments retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving measurement instruments: ' . $e->getMessage(),
                'INSTRUMENT_RETRIEVAL_ERROR',
                500
            );
        }
    }

    /**
     * Get single instrument details
     */
    public function show(Request $request, int $instrumentId)
    {
        try {
            $instrument = MeasurementInstrument::find($instrumentId);

            if (!$instrument) {
                return $this->notFoundResponse('Measurement instrument not found');
            }

            return $this->successResponse([
                'instrument' => [
                    'id' => $instrument->id,
                    'name' => $instrument->name,
                    'model' => $instrument->model,
                    'serial_number' => $instrument->serial_number,
                    'manufacturer' => $instrument->manufacturer,
                    'status' => $instrument->status,
                    'description' => $instrument->description,
                    'specifications' => $instrument->specifications,
                    'display_name' => $instrument->display_name,
                    'needs_calibration' => $instrument->needsCalibration(),
                    'last_calibration' => $instrument->last_calibration ? 
                        (is_string($instrument->last_calibration) ? $instrument->last_calibration : $instrument->last_calibration->format('Y-m-d')) : null,
                    'next_calibration' => $instrument->next_calibration ? 
                        (is_string($instrument->next_calibration) ? $instrument->next_calibration : $instrument->next_calibration->format('Y-m-d')) : null
                ]
            ], 'Instrument details retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error retrieving instrument details: ' . $e->getMessage(),
                'INSTRUMENT_DETAIL_ERROR',
                500
            );
        }
    }
}
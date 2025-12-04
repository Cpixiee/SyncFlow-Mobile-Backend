<?php

namespace App\Models;

use App\Enums\ToolType;
use App\Enums\ToolStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tool extends Model
{
    use HasFactory;

    protected $fillable = [
        'tool_name',
        'tool_model',
        'tool_type',
        'last_calibration_at',
        'next_calibration_at',
        'imei',
        'status'
    ];

    protected $casts = [
        'tool_type' => ToolType::class,
        'status' => ToolStatus::class,
        'last_calibration_at' => 'datetime',
        'next_calibration_at' => 'datetime',
    ];

    /**
     * Boot method untuk auto-generate tool ID
     * Note: next_calibration_at tidak auto-fill, bisa nullable
     */
    protected static function boot()
    {
        parent::boot();

        // Tidak ada auto-fill untuk next_calibration_at
        // User harus set manual jika diperlukan
    }

    /**
     * Scope untuk filter tools yang active
     */
    public function scopeActive($query)
    {
        return $query->where('status', ToolStatus::ACTIVE);
    }

    /**
     * Scope untuk filter by tool model
     */
    public function scopeByModel($query, string $model)
    {
        return $query->where('tool_model', $model);
    }

    /**
     * Get all unique tool models yang active
     */
    public static function getActiveModels(): array
    {
        return self::active()
            ->distinct()
            ->pluck('tool_model')
            ->toArray();
    }

    /**
     * Get tools by model yang active
     */
    public static function getToolsByModel(string $model): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()
            ->byModel($model)
            ->orderBy('imei')
            ->get();
    }
}


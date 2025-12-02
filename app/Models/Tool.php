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
     * Boot method untuk auto-update next_calibration_at
     */
    protected static function boot()
    {
        parent::boot();

        // Auto update next_calibration_at saat updating
        static::updating(function ($tool) {
            // Jika last_calibration_at diubah, otomatis update next_calibration_at (1 tahun dari last_calibration_at)
            if ($tool->isDirty('last_calibration_at') && $tool->last_calibration_at) {
                $tool->next_calibration_at = $tool->last_calibration_at->copy()->addYear();
            }
        });

        // Set next_calibration_at saat creating
        static::creating(function ($tool) {
            if ($tool->last_calibration_at && !$tool->next_calibration_at) {
                $tool->next_calibration_at = $tool->last_calibration_at->copy()->addYear();
            }
        });
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


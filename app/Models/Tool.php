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
        'last_calibration',
        'next_calibration',
        'imei',
        'status'
    ];

    protected $casts = [
        'tool_type' => ToolType::class,
        'status' => ToolStatus::class,
        'last_calibration' => 'date',
        'next_calibration' => 'date',
    ];

    /**
     * Boot method untuk auto-update next_calibration
     */
    protected static function boot()
    {
        parent::boot();

        // Auto update next_calibration saat updating
        static::updating(function ($tool) {
            // Jika last_calibration diubah, otomatis update next_calibration (1 tahun dari last_calibration)
            if ($tool->isDirty('last_calibration') && $tool->last_calibration) {
                $tool->next_calibration = $tool->last_calibration->copy()->addYear();
            }
        });

        // Set next_calibration saat creating
        static::creating(function ($tool) {
            if ($tool->last_calibration && !$tool->next_calibration) {
                $tool->next_calibration = $tool->last_calibration->copy()->addYear();
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


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ScaleMeasurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'scale_measurement_id',
        'product_id',
        'measurement_date',
        'weight',
        'status',
        'measured_by',
        'notes'
    ];

    protected $casts = [
        'measurement_date' => 'date',
        'weight' => 'decimal:2',
    ];

    /**
     * Relationship dengan product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relationship dengan user yang melakukan pengukuran
     */
    public function measuredBy(): BelongsTo
    {
        return $this->belongsTo(LoginUser::class, 'measured_by');
    }

    /**
     * Boot method untuk auto-generate scale_measurement_id dan auto-update status
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($measurement) {
            if (empty($measurement->scale_measurement_id)) {
                $measurement->scale_measurement_id = self::generateScaleMeasurementId();
            }
            // Auto-set status based on weight
            $measurement->status = $measurement->weight !== null ? 'CHECKED' : 'NOT_CHECKED';
        });

        static::updating(function ($measurement) {
            // Auto-update status when weight changes
            if ($measurement->isDirty('weight')) {
                $measurement->status = $measurement->weight !== null ? 'CHECKED' : 'NOT_CHECKED';
            }
        });
    }

    /**
     * Generate unique scale measurement ID
     */
    public static function generateScaleMeasurementId(): string
    {
        do {
            $scaleMeasurementId = 'SCL-' . strtoupper(Str::random(8));
        } while (self::where('scale_measurement_id', $scaleMeasurementId)->exists());

        return $scaleMeasurementId;
    }

    /**
     * Check if measurement is checked (has weight)
     */
    public function isChecked(): bool
    {
        return $this->status === 'CHECKED' && $this->weight !== null;
    }

    /**
     * Update status berdasarkan weight
     */
    public function updateStatus(): void
    {
        if ($this->weight !== null) {
            $this->status = 'CHECKED';
        } else {
            $this->status = 'NOT_CHECKED';
        }
        $this->save();
    }
}


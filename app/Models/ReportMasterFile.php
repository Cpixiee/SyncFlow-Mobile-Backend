<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportMasterFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_measurement_id',
        'original_filename',
        'stored_filename',
        'file_path',
        'sheet_names',
    ];

    protected $casts = [
        'sheet_names' => 'array',
    ];

    /**
     * Relationship dengan user yang upload
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(LoginUser::class, 'user_id');
    }

    /**
     * Relationship dengan product measurement
     */
    public function productMeasurement(): BelongsTo
    {
        return $this->belongsTo(ProductMeasurement::class);
    }
}

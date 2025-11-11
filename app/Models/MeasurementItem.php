<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'measurement_id',
        'tool_id',
        'thickness_type',
        'value',
        'sequence'
    ];

    protected $casts = [
        'value' => 'decimal:2'
    ];

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(Measurement::class);
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }
}






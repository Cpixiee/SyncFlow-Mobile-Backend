<?php

namespace App\Enums;

enum MeasurementType: string
{
    case FULL_MEASUREMENT = 'FULL_MEASUREMENT';
    case SCALE_MEASUREMENT = 'SCALE_MEASUREMENT';

    public function getDescription(): string
    {
        return match($this) {
            self::FULL_MEASUREMENT => 'Per Quarter (3 bulan)',
            self::SCALE_MEASUREMENT => 'Per Bulan (1 bulan)',
        };
    }

    public function getDuration(): string
    {
        return match($this) {
            self::FULL_MEASUREMENT => 'quarter',
            self::SCALE_MEASUREMENT => 'month',
        };
    }
}

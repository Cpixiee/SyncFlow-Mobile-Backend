<?php

namespace App\Enums;

enum ToolType: string
{
    case OPTICAL = 'OPTICAL';
    case MECHANICAL = 'MECHANICAL';

    public function getDescription(): string
    {
        return match($this) {
            self::OPTICAL => 'Optical',
            self::MECHANICAL => 'Mechanical',
        };
    }
}


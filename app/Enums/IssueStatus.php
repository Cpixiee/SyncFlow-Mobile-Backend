<?php

namespace App\Enums;

enum IssueStatus: string
{
    case PENDING = 'PENDING';
    case ON_GOING = 'ON_GOING';
    case SOLVED = 'SOLVED';

    public function getDescription(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::ON_GOING => 'On Going',
            self::SOLVED => 'Solved',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::PENDING => 'orange',
            self::ON_GOING => 'blue',
            self::SOLVED => 'green',
        };
    }
}


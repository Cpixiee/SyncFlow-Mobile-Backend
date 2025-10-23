<?php

namespace App\Enums;

enum SampleStatus: string
{
    case OK = 'OK';
    case NG = 'NG';
    case NOT_COMPLETE = 'NOT_COMPLETE';

    public function getDescription(): string
    {
        return match($this) {
            self::OK => 'Semua sample OK',
            self::NG => 'Ada sample NG',
            self::NOT_COMPLETE => 'Belum selesai diukur',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::OK => 'green',
            self::NG => 'red',
            self::NOT_COMPLETE => 'yellow',
        };
    }
}

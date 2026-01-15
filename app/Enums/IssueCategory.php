<?php

namespace App\Enums;

enum IssueCategory: string
{
    case CUSTOMER_CLAIM = 'CUSTOMER_CLAIM';
    case INTERNAL_DEFECT = 'INTERNAL_DEFECT';
    case NON_CONFORMITY = 'NON_CONFORMITY';
    case QUALITY_INFORMATION = 'QUALITY_INFORMATION';
    case OTHER = 'OTHER';
    
    public function getLabel(): string
    {
        return match($this) {
            self::CUSTOMER_CLAIM => 'Customer Claim',
            self::INTERNAL_DEFECT => 'Internal Defect',
            self::NON_CONFORMITY => 'Non Conformity',
            self::QUALITY_INFORMATION => 'Quality Information',
            self::OTHER => 'Other',
        };
    }
}

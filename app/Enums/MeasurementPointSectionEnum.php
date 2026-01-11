<?php

namespace App\Enums;

enum MeasurementPointSectionEnum: string
{
    case SETUP = 'setup';
    case VARIABLE = 'variable';
    case PRE_PROCESSING_FORMULA = 'pre_processing_formula';
    case EVALUATION = 'evaluation';
    case RULE_EVALUATION = 'rule_evaluation';
    case GROUP = 'group';
    case JOINT_FORMULA = 'joint_formula';
    case QUALITATIVE_SETTING = 'qualitative_setting';
}

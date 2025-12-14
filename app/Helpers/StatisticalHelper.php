<?php

namespace App\Helpers;

class StatisticalHelper
{
    /**
     * Calculate standard deviation (sigma)
     * 
     * @param array $values Array of numeric values
     * @return float Standard deviation
     */
    public static function calculateStandardDeviation(array $values): float
    {
        $count = count($values);
        
        if ($count < 2) {
            return 0.0;
        }
        
        $mean = array_sum($values) / $count;
        
        $variance = 0.0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        $variance = $variance / ($count - 1); // Sample standard deviation (Bessel's correction)
        
        return sqrt($variance);
    }

    /**
     * Calculate CP (Process Capability) - only for BETWEEN rule
     * CP = (max - min) / (6 * sigma)
     * 
     * @param float $maxValue Maximum value
     * @param float $minValue Minimum value
     * @param float $sigma Standard deviation
     * @return float|null CP value, or null if sigma is 0
     */
    public static function calculateCP(float $maxValue, float $minValue, float $sigma): ?float
    {
        if ($sigma == 0) {
            return null;
        }
        
        $range = $maxValue - $minValue;
        return $range / (6 * $sigma);
    }

    /**
     * Calculate CPK (Process Capability Index)
     * CPK can be calculated for MIN, MAX, or BETWEEN rules
     * 
     * For MIN: CPK = (mean - min) / (3 * sigma)
     * For MAX: CPK = (max - mean) / (3 * sigma)
     * For BETWEEN: CPK = min((mean - min) / (3 * sigma), (max - mean) / (3 * sigma))
     * 
     * @param string $rule Evaluation rule: 'MIN', 'MAX', or 'BETWEEN'
     * @param float $mean Mean/average value
     * @param float $sigma Standard deviation
     * @param float|null $minValue Minimum value (required for MIN and BETWEEN)
     * @param float|null $maxValue Maximum value (required for MAX and BETWEEN)
     * @param float|null $ruleValue Rule value (for MIN/MAX rules)
     * @param float|null $toleranceMinus Tolerance minus (for BETWEEN rule)
     * @param float|null $tolerancePlus Tolerance plus (for BETWEEN rule)
     * @return float|null CPK value, or null if cannot be calculated
     */
    public static function calculateCPK(
        string $rule,
        float $mean,
        float $sigma,
        ?float $minValue = null,
        ?float $maxValue = null,
        ?float $ruleValue = null,
        ?float $toleranceMinus = null,
        ?float $tolerancePlus = null
    ): ?float {
        if ($sigma == 0) {
            return null;
        }

        switch ($rule) {
            case 'MIN':
                if ($ruleValue === null) {
                    return null;
                }
                // CPK = (mean - min) / (3 * sigma)
                // where min = ruleValue
                $cpk = ($mean - $ruleValue) / (3 * $sigma);
                return $cpk;

            case 'MAX':
                if ($ruleValue === null) {
                    return null;
                }
                // CPK = (max - mean) / (3 * sigma)
                // where max = ruleValue
                $cpk = ($ruleValue - $mean) / (3 * $sigma);
                return $cpk;

            case 'BETWEEN':
                if ($ruleValue === null || $toleranceMinus === null || $tolerancePlus === null) {
                    return null;
                }
                // Calculate min and max from rule value and tolerances
                $min = $ruleValue - $toleranceMinus;
                $max = $ruleValue + $tolerancePlus;
                
                // CPK = min((mean - min) / (3 * sigma), (max - mean) / (3 * sigma))
                $cpkLower = ($mean - $min) / (3 * $sigma);
                $cpkUpper = ($max - $mean) / (3 * $sigma);
                
                return min($cpkLower, $cpkUpper);

            default:
                return null;
        }
    }

    /**
     * Calculate n-sigma (sigma * n)
     * 
     * @param float $sigma Standard deviation
     * @param int $n Multiplier (e.g., 3 for 3-sigma, 6 for 6-sigma)
     * @return float n-sigma value
     */
    public static function calculateNSigma(float $sigma, int $n): float
    {
        return $sigma * $n;
    }
}


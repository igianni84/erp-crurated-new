<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Type-safe wrapper for PHP's BC Math functions.
 *
 * Eloquent's `decimal:N` cast returns `string`, but BC Math functions
 * expect `numeric-string` at PHPStan level 7. This class bridges
 * that gap with properly annotated static methods.
 */
final class DecimalMath
{
    /**
     * @return numeric-string
     */
    public static function add(string $num1, string $num2, int $scale = 2): string
    {
        /** @var numeric-string $num1 */
        /** @var numeric-string $num2 */
        return bcadd($num1, $num2, $scale);
    }

    /**
     * @return numeric-string
     */
    public static function sub(string $num1, string $num2, int $scale = 2): string
    {
        /** @var numeric-string $num1 */
        /** @var numeric-string $num2 */
        return bcsub($num1, $num2, $scale);
    }

    /**
     * @return numeric-string
     */
    public static function mul(string $num1, string $num2, int $scale = 2): string
    {
        /** @var numeric-string $num1 */
        /** @var numeric-string $num2 */
        return bcmul($num1, $num2, $scale);
    }

    /**
     * @return numeric-string
     */
    public static function div(string $num1, string $num2, int $scale = 2): string
    {
        /** @var numeric-string $num1 */
        /** @var numeric-string $num2 */
        return bcdiv($num1, $num2, $scale);
    }

    /**
     * Compare two numbers. Returns -1, 0, or 1.
     */
    public static function comp(string $num1, string $num2, int $scale = 2): int
    {
        /** @var numeric-string $num1 */
        /** @var numeric-string $num2 */
        return bccomp($num1, $num2, $scale);
    }
}

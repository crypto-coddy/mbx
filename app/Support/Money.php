<?php

namespace App\Support;

class Money
{
    public static function formatInr(string|float|int $amount, bool $withSymbol = true): string
    {
        $value = number_format((float) $amount, 2, '.', '');

        return $withSymbol ? '₹'.$value : $value;
    }
}

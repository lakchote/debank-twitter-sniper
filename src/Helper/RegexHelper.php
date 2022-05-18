<?php

declare(strict_types=1);

namespace App\Helper;

class RegexHelper
{
    public static function sanitizeTxInput(string $text): string
    {
        $regex = '/\D+/';

        preg_match($regex, $text, $matches);

        $matches = str_replace('#', '', implode('', $matches));

        return trim($matches);
    }
}

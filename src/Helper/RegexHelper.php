<?php

declare(strict_types=1);

namespace App\Helper;

class RegexHelper
{
    public static function getContractName(string $text): string
    {
        $regex = '/\w[^\d,.\s]+\w/';

        preg_match($regex, $text, $matches);

        return $matches[0];
    }
}

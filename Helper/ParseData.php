<?php

namespace Getnet\Payments\Helper;

abstract class ParseData
{
    public static function unmaskString(string $string): string
    {
        return str_rot13($string);
    }
}

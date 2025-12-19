<?php

namespace Banchile\Payments\Countries;

interface CountryConfigInterface
{
    public static function resolve(string $countryCode): bool;
    public static function getEndpoints(): array;
}

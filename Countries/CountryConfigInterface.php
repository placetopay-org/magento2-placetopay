<?php

namespace PlacetoPay\Payments\Countries;

interface CountryConfigInterface
{
    public static function resolve(string $countryCode): bool;
    public static function getEndpoints(string $client): array;
    public static function getClient(): array;

}

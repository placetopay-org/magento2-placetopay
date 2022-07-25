<?php

namespace PlacetoPay\Payments\Countries;

use Magento\Tests\NamingConvention\true\string;

interface CountryConfigInterface
{
    public function resolve(string $countryCode): bool;
    public function getEndpoints(): array;
}

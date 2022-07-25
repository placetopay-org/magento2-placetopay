<?php

namespace PlacetoPay\Payments\Countries;

use PlacetoPay\Payments\Model\Adminhtml\Source\Country;
use PlacetoPay\Payments\Model\Adminhtml\Source\Mode;

class BelizeCountryConfig extends CountryConfig
{
    public function resolve(string $countryCode): bool
    {
        return Country::BELIZE === $countryCode;
    }

    public function getEndpoints(): array
    {
        return array_merge(parent::getEndpoints(), [
            Mode::PRODUCTION => 'https://abgateway.atlabank.com'
        ]);
    }
}

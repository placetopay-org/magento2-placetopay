<?php

namespace PlacetoPay\Payments\Countries;

use PlacetoPay\Payments\Model\Adminhtml\Source\Country;
use PlacetoPay\Payments\Model\Adminhtml\Source\Mode;

class ChileCountryConfig extends CountryConfig
{
    public function resolve(string $countryCode): bool
    {
        return Country::CHILE === $countryCode;
    }

    public function getEndpoints(): array
    {
        return array_merge(parent::getEndpoints(), [
            Mode::TEST => 'https://checkout.test.getnet.cl',
            Mode::PRODUCTION => 'https://checkout.getnet.cl',
        ]);
    }
}

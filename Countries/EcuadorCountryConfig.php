<?php

namespace PlacetoPay\Payments\Countries;

use PlacetoPay\Payments\Model\Adminhtml\Source\Country;
use PlacetoPay\Payments\Model\Adminhtml\Source\Mode;

class EcuadorCountryConfig implements CountryConfigInterface
{
    public function resolve(string $countryCode): bool
    {
        return Country::ECUADOR === $countryCode;
    }

    public function getEndpoints(): array
    {
        return [
            Mode::DEVELOPMENT => 'https://dev.placetopay.ec/redirection',
            Mode::TEST => 'https://checkout-test.placetopay.ec',
            Mode::PRODUCTION => 'https://checkout.placetopay.ec',
        ];
    }
}

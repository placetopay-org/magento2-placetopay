<?php

namespace PlacetoPay\Payments\Countries;

use PlacetoPay\Payments\Model\Adminhtml\Source\Country;
use PlacetoPay\Payments\Model\Adminhtml\Source\Mode;

abstract class UruguayCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode): bool
    {
        return Country::URUGUAY === $countryCode;
    }

    public static function getEndpoints(): array
    {
        return array_merge(parent::getEndpoints(), [
            Mode::TEST => 'https://uy-uat-checkout.placetopay.com',
            Mode::PRODUCTION => 'https://checkout.placetopay.uy',
        ]);
    }
}

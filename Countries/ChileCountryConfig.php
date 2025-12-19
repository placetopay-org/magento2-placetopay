<?php

namespace Banchile\Payments\Countries;

use Banchile\Payments\Constants\Country;
use Banchile\Payments\Model\Adminhtml\Source\Mode;

abstract class ChileCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode): bool
    {
        return true;
    }

    public static function getEndpoints(): array
    {
        return array_merge(parent::getEndpoints(), [
            Mode::PRODUCTION => 'https://checkout.banchilepagos.cl',
            Mode::TEST => 'https://checkout.test.banchilepagos.cl',
        ]);
    }
}

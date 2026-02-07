<?php

namespace PlacetoPay\Payments;

use PlacetoPay\Payments\Model\Adminhtml\Source\Mode;

abstract class CountryConfig
{
    public const CLIENT_ID = 'avalpay_colombia';
    public const CLIENT = 'Avalpay';
    public const IMAGE = 'https://static.placetopay.com/avalpay-logo.svg';
    public const COUNTRY_CODE = 'CO';
    public const COUNTRY_NAME = 'Colombia';


    public static function getEndpoints(): array
    {
        return [
            Mode::TEST => 'https://checkout.test.avalpaycenter.com',
            Mode::PRODUCTION => 'https://checkout.avalpaycenter.com',
        ];
    }

    public static function getClient(): array
    {
        return [
            [
                'value' => self::CLIENT,
                'label' => __(self::CLIENT),
            ],
        ];
    }
}

<?php

namespace PlacetoPay\Payments;

use PlacetoPay\Payments\Model\Adminhtml\Source\Mode;

abstract class CountryConfig
{
    public const CLIENT_ID = 'placetopay_ecuador';
    public const CLIENT = 'Placetopay';
    public const IMAGE = 'https://static.placetopay.com/placetopay-logo.svg';
    public const COUNTRY_CODE = 'EC';
    public const COUNTRY_NAME = 'Ecuador';


    public static function getEndpoints(): array
    {
        return [
            Mode::DEVELOPMENT => 'https://checkout-ec.placetopay.dev',
            Mode::TEST => 'https://checkout-test.placetopay.ec',
            Mode::PRODUCTION => 'https://checkout.placetopay.ec',
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

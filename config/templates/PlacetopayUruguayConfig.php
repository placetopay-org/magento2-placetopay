<?php

namespace PlacetoPay\Payments;

use PlacetoPay\Payments\Model\Adminhtml\Source\Mode;

abstract class CountryConfig
{
    public const CLIENT_ID = 'placetopay_uruguay';
    public const CLIENT = 'Placetopay';
    public const IMAGE = 'https://static.placetopay.com/placetopay-logo.svg';
    public const COUNTRY_CODE = 'UY';
    public const COUNTRY_NAME = 'Uruguay';


    public static function getEndpoints(): array
    {
        return [
            Mode::UAT => 'https://uy-uat-checkout.placetopay.com',
            Mode::PRODUCTION => 'https://checkout.placetopay.uy',
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

<?php

namespace PlacetoPay\Payments;

use PlacetoPay\Payments\Model\Adminhtml\Source\Mode;

abstract class CountryConfig 
{
    public const CLIENT_ID = 'placetopay_honduras';
    public const CLIENT = 'Placetopay';
    public const IMAGE = 'https://static.placetopay.com/placetopay-logo.svg';
    public const COUNTRY_CODE = 'HN';
    public const COUNTRY_NAME = 'Honduras';


    public static function getEndpoints(): array
    {
        $baseEndpoints = [
            Mode::DEVELOPMENT => 'https://checkout-co.placetopay.dev',
            Mode::TEST => 'https://checkout-test.placetopay.com',
            Mode::PRODUCTION => 'https://checkout.placetopay.com',
        ];
        
        return array_merge($baseEndpoints, [
            Mode::PRODUCTION => 'https://pagoenlinea.bancatlan.hn'
        ]);
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


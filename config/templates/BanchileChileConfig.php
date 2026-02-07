<?php

namespace PlacetoPay\Payments;

use PlacetoPay\Payments\Model\Adminhtml\Source\Mode;

abstract class CountryConfig
{
    public const CLIENT_ID = 'banchile_chile';
    public const CLIENT = 'Banchile pagos';
    public const IMAGE = 'https://placetopay-static-prod-bucket.s3.us-east-2.amazonaws.com/banchile/logos/Logotipo_superior.png';
    public const COUNTRY_CODE = 'CL';
    public const COUNTRY_NAME = 'Chile';

    public static function getEndpoints(): array
    {
        return [
            Mode::DEVELOPMENT => 'https://checkout-co.placetopay.dev',
            Mode::TEST => 'https://checkout.test.banchilepagos.cl',
            Mode::PRODUCTION => 'https://checkout.banchilepagos.cl',
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


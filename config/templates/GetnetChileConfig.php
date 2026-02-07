<?php

namespace PlacetoPay\Payments;

use PlacetoPay\Payments\Model\Adminhtml\Source\Mode;

abstract class CountryConfig
{
    public const CLIENT_ID = 'getnet_chile';
    public const CLIENT = 'Getnet';
    public const IMAGE = 'https://banco.santander.cl/uploads/000/029/870/0620f532-9fc9-4248-b99e-78bae9f13e1d/original/Logo_WebCheckout_Getnet.svg';
    public const COUNTRY_CODE = 'CL';
    public const COUNTRY_NAME = 'Chile';

    public static function getEndpoints(): array
    {
        return [
            Mode::TEST => 'https://checkout.test.getnet.cl',
            Mode::PRODUCTION => 'https://checkout.getnet.cl',
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

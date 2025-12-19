<?php

namespace Getnet\Payments\Countries;

use Getnet\Payments\Constants\Client;
use Getnet\Payments\Helper\ParseData;
use Getnet\Payments\Model\Adminhtml\Source\Mode;

abstract class CountryConfig implements CountryConfigInterface
{
    public static function resolve(string $countryCode): bool
    {
        return true;
    }

    public static function getEndpoints(string $client): array
    {
        return [
            Mode::DEVELOPMENT => 'https://checkout-co.placetopay.dev',
            Mode::TEST => 'https://checkout.test.getnet.cl',
            Mode::PRODUCTION => 'https://checkout.getnet.cl',
        ];
    }

    public static function getClient(): array
    {
        return [
            [
                'value' => ParseData::unmaskString(Client::GNT),
                'label' => __(ParseData::unmaskString(Client::GNT)),
            ],
        ];
    }
}

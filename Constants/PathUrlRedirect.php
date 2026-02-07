<?php

namespace PlacetoPay\Payments\Constants;

use PlacetoPay\Payments\CountryConfig;

abstract class PathUrlRedirect
{
    public static function getFailure(): string
    {
        return CountryConfig::CLIENT_ID . '/onepage/failure';
    }

    public static function getSuccessful(): string
    {
        return CountryConfig::CLIENT_ID . '/onepage/success';
    }

    public static function getPending(): string
    {
        return CountryConfig::CLIENT_ID . '/onepage/pending';
    }
}

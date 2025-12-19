<?php

namespace Getnet\Payments\Constants;

use Getnet\Payments\Countries\BelizeCountryConfig;
use Getnet\Payments\Countries\ChileCountryConfig;
use Getnet\Payments\Countries\ColombiaCountryConfig;
use Getnet\Payments\Countries\CountryConfig;
use Getnet\Payments\Countries\EcuadorCountryConfig;
use Getnet\Payments\Countries\HondurasCountryConfig;
use Getnet\Payments\Countries\UruguayCountryConfig;

class Country
{
    public const COLOMBIA = 'CO';
    public const COSTA_RICA = 'CR';
    public const ECUADOR = 'EC';
    public const CHILE = 'CL';
    public const PUERTO_RICO = 'PR';
    public const HONDURAS = 'HN';
    public const BELIZE = 'BZ';

    public const PANAMA = 'PA';

    public const URUGUAY = 'UY';

    public const COUNTRIES_CONFIG = [
        EcuadorCountryConfig::class,
        ChileCountryConfig::class,
        HondurasCountryConfig::class,
        BelizeCountryConfig::class,
        UruguayCountryConfig::class,
        CountryConfig::class
    ];

    public const COUNTRIES_CLIENT = [
        ColombiaCountryConfig::class,
        ChileCountryConfig::class,
        CountryConfig::class
    ];
}

<?php

namespace PlacetoPay\Payments\Model\Adminhtml\Source;

use PlacetoPay\Payments\CountryConfig;

class Mode
{
    public const DEVELOPMENT = 'development';
    public const TEST = 'test';
    public const PRODUCTION = 'production';
    public const CUSTOM = 'custom';

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        $environments = [
            [
                'value' => self::DEVELOPMENT,
                'label' => __('Development'),
            ],
            [
                'value' => self::TEST,
                'label' => __('Test'),
            ],
            [
                'value' => self::PRODUCTION,
                'label' => __('Production'),
            ],
        ];

        if (CountryConfig::COUNTRY_CODE != 'CL' ) {
            $environments[] =
                [
                    'value' => self::CUSTOM,
                    'label' => __('Custom'),
                ];
        }

        return $environments;
    }
}

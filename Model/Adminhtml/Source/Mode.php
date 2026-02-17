<?php

namespace PlacetoPay\Payments\Model\Adminhtml\Source;

use PlacetoPay\Payments\CountryConfig;
use PlacetoPay\Payments\Helper\Data;

class Mode
{
    public const DEVELOPMENT = 'development';
    public const TEST = 'test';
    public const PRODUCTION = 'production';
    public const CUSTOM = 'custom';
    protected $dataHelper;

    public function __construct(Data $dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

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

        $endpoints = $this->dataHelper->getEndpointsTo();

        foreach ($environments as $key => $environment) {
            if (!array_key_exists($environment['value'], $endpoints)) {
                unset($environments[$key]);
            }
        }

        if (CountryConfig::COUNTRY_CODE != 'CL' ) {
            $environments[] = [
                'value' => self::CUSTOM,
                'label' => __('Custom'),
            ];
        }

        return $environments;
    }
}

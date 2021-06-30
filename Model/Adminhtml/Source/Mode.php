<?php

namespace PlacetoPay\Payments\Model\Adminhtml\Source;

class Mode
{
    const DEVELOPMENT = 'development';
    const TEST = 'test';
    const PRODUCTION = 'production';
    const CUSTOM = 'custom';

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
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
            [
                'value' => self::CUSTOM,
                'label' => __('Custom'),
            ]
        ];
    }
}

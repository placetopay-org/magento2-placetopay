<?php

namespace Getnet\Payments\Model\Adminhtml\Source;

class Mode
{
    public const DEVELOPMENT = 'development';
    public const TEST = 'test';
    public const PRODUCTION = 'production';

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
            ]
        ];
    }
}

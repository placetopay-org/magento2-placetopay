<?php

namespace Getnet\Payments\Model\Adminhtml\Source;

class Mode
{
    public const TEST = 'test';
    public const PRODUCTION = 'production';

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
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

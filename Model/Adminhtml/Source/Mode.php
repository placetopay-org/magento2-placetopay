<?php

namespace PlacetoPay\Payments\Model\Adminhtml\Source;

/**
 * Class Mode.
 */
class Mode
{
    /**
     * Mode Development.
     */
    const DEVELOPMENT = 'development';

    /**
     * Mode Test.
     */
    const TEST = 'test';

    /**
     * Mode Production.
     */
    const PRODUCTION = 'production';

    /**
     * @return array
     */
    public function toOptionArray()
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
        ];
    }
}

<?php

namespace PlacetoPay\Payments\Model\Adminhtml\Source;

/**
 * Class Country.
 */
class Country
{
    /**
     * Country Colombia.
     */
    const COLOMBIA = 'CO';

    /**
     * Country Ecuador.
     */
    const ECUADOR = 'EC';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::COLOMBIA,
                'label' => __('Colombia'),
            ],
            [
                'value' => self::ECUADOR,
                'label' => __('Ecuador'),
            ],
        ];
    }
}

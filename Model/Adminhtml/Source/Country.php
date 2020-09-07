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
     * Country Costa Rica
     */
    const COSTA_RICA = 'CR';

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
                'value' => self::COSTA_RICA,
                'label' => __('Costa Rica'),
            ],
            [
                'value' => self::ECUADOR,
                'label' => __('Ecuador'),
            ],
        ];
    }
}

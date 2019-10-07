<?php

namespace PlacetoPay\Payments\Model\Adminhtml\Source;

/**
 * Class Country.
 */
class Country
{
    /**
     * Country Colombia
     */
    const COLOMBIA = 'CO';

    /**
     * Country Ecuador
     */
    const ECUADOR = 'EC';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => Country::COLOMBIA,
                'label' => __('Colombia'),
            ],
            [
                'value' => Country::ECUADOR,
                'label' => __('Ecuador'),
            ],
        ];
    }
}
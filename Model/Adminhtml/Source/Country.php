<?php

namespace PlacetoPay\Payments\Model\Adminhtml\Source;

class Country
{
    const CHILE = 'CL';
    const COLOMBIA = 'CO';
    const COSTA_RICA = 'CR';
    const ECUADOR = 'EC';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::CHILE,
                'label' => __('Chile'),
            ],
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

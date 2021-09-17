<?php

namespace PlacetoPay\Payments\Model\Adminhtml\Source;

class Country
{
    const COLOMBIA = 'CO';
    const COSTA_RICA = 'CR';
    const ECUADOR = 'EC';
    const CHILE = 'CL';
    const PUERTO_RICO = 'PR';

    public function toOptionArray(): array
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
            [
                'value' => self::CHILE,
                'label' => __('Chile'),
            ],
            [
                'value' => self::PUERTO_RICO,
                'label' => __('Puerto Rico'),
            ],
        ];
    }
}

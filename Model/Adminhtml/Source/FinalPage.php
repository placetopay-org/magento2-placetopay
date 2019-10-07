<?php

namespace PlacetoPay\Payments\Model\Adminhtml\Source;

/**
 * Class FinalPage.
 */
class FinalPage
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'magento_default',
                'label' => __('Default'),
            ],
            [
                'value' => 'order_info',
                'label' => __('Order Information'),
            ],
        ];
    }
}
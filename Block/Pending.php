<?php
/**
 * Created by PhpStorm.
 * User: smp
 * Date: 21/07/19
 * Time: 04:05 PM
 */

namespace PlacetoPay\Payments\Block;

class Pending extends \Magento\Framework\View\Element\Template
{
    public function getMessage()
    {
        return __('The status of the order is pending, waiting to process the payment by placetoPay');
    }

    public function getUrlHome()
    {
        return $this->_storeManager->getStore()->getBaseUrl();
    }
}
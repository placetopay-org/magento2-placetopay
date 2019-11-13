<?php

namespace PlacetoPay\Payments\Block;

use Magento\Framework\View\Element\Template;

class Cancel extends Template
{
    public function getMessage()
    {
        return __('We regret that you have decided to cancel the payment');
    }

    public function getUrlHome()
    {
        return $this->_storeManager->getStore()->getBaseUrl();
    }
}

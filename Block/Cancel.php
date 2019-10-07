<?php


namespace PlacetoPay\Payments\Block;


class Cancel extends \Magento\Framework\View\Element\Template
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
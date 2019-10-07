<?php


namespace PlacetoPay\Payments\Block;


class Response extends \Magento\Framework\View\Element\Template
{
    public function getMessage()
    {
        return __('An error has occurred while checking the payment status');
    }

    public function getUrlHome()
    {
        return $this->_storeManager->getStore()->getBaseUrl();
    }
}
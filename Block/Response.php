<?php

namespace PlacetoPay\Payments\Block;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;

/**
 * Class Response.
 */
class Response extends Template
{
    /**
     * @return string
     */
    public function getMessage()
    {
        return __('An error has occurred while checking the payment status');
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getUrlHome()
    {
        return $this->_storeManager->getStore()->getBaseUrl();
    }
}

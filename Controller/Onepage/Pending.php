<?php

namespace PlacetoPay\Payments\Controller\Onepage;

/**
 * Class Pending.
 */
class Pending extends \Magento\Checkout\Controller\Onepage
{
    /**
     * @return \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $lastQuoteId = $this->getOnepage()->getCheckout()->getLastQuoteId();
        $lastOrderId = $this->getOnepage()->getCheckout()->getLastOrderId();

        if (!$lastQuoteId || !$lastOrderId) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        return $this->resultPageFactory->create();
    }
}
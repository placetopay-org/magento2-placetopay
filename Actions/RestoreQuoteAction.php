<?php

namespace PlacetoPay\Payments\Actions;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Sales\Model\Order;

abstract class RestoreQuoteAction
{
    public static function execute(Session $session, CustomerSession $customerSession, Order $order): bool
    {
        if (!self::belongsToSession($session, $order) && !self::belongsToCustomer($customerSession, $order)) {
            return false;
        }

        SetOrderInfoSession::withQouteId($session, $order);

        $restored = $session->restoreQuote();

        $session->setLastRealOrderId($order->getIncrementId());

        return $restored;
    }

    private static function belongsToSession(Session $session, Order $order): bool
    {
        return (int) $session->getLastOrderId() === (int) $order->getId()
            && (string) $session->getLastRealOrderId() === (string) $order->getIncrementId();
    }

    private static function belongsToCustomer(CustomerSession $customerSession, Order $order): bool
    {
        return $order->getCustomerId()
            && $customerSession->isLoggedIn()
            && (int) $customerSession->getCustomerId() === (int) $order->getCustomerId();
    }
}

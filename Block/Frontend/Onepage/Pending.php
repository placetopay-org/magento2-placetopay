<?php

namespace PlacetoPay\Payments\Block\Frontend\Onepage;

use Magento\Checkout\Model\Session;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Class Pending.
 */
class Pending extends Template
{
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var TimezoneInterface
     */
    protected $timezoneInterface;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        TimezoneInterface $timezoneInterface,
        PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;

        parent::__construct($context, $data);

        $this->_isScopePrivate = true;
        $this->orderRepository = $orderRepository;
        $this->timezoneInterface = $timezoneInterface;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * @return mixed
     */
    public function getRealOrderId()
    {
        return $this->checkoutSession->getLastRealOrderId();
    }

    /**
     *  Payment custom error message.
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->checkoutSession->getErrorMessage();
    }

    /**
     * Continue shopping URL.
     *
     * @return string
     */
    public function getContinueShoppingUrl()
    {
        return $this->getUrl('checkout/cart');
    }

    /**
     * @return OrderInterface
     */
    public function getOrder()
    {
        return $this->orderRepository->get($this->checkoutSession->getLastRealOrderId());
    }

    /**
     * @param $date
     * @param string $format
     * @return string
     */
    public function dateFormat($date, $format = 'd F Y')
    {
        return $this->timezoneInterface->date($date)->format($format);
    }

    /**
     * @param $amount
     * @return string
     */
    public function getFormattedPrice($amount)
    {
        return $this->priceCurrency->convertAndFormat($amount);
    }
}

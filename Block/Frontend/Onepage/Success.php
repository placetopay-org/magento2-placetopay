<?php

namespace PlacetoPay\Payments\Block\Frontend\Onepage;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Config;

/**
 * Class Success.
 */
class Success extends \Magento\Checkout\Block\Onepage\Success
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

    /**
     * Success constructor.
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param Config $orderConfig
     * @param HttpContext $httpContext
     * @param OrderRepositoryInterface $orderRepository
     * @param TimezoneInterface $timezoneInterface
     * @param PriceCurrencyInterface $priceCurrency
     * @param array $data
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        Config $orderConfig,
        HttpContext $httpContext,
        OrderRepositoryInterface $orderRepository,
        TimezoneInterface $timezoneInterface,
        PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;

        parent::__construct($context, $checkoutSession, $orderConfig, $httpContext, $data);

        $this->orderRepository = $orderRepository;
        $this->timezoneInterface = $timezoneInterface;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * @return \Magento\Sales\Api\Data\OrderInterface
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
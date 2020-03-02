<?php

namespace PlacetoPay\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Class SendMailOnOrderSuccess.
 */
class SendMailOnOrderSuccess implements ObserverInterface
{
    /**
     * @var \PlacetoPay\Payments\Helper\Data
     */
    protected $config;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $order;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * SendMailOnOrderSuccess constructor.
     *
     * @param \PlacetoPay\Payments\Helper\Data $config
     * @param \Magento\Sales\Model\OrderFactory $order
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Magento\Checkout\Model\Session $session
     */
    public function __construct(
        \PlacetoPay\Payments\Helper\Data $config,
        \Magento\Sales\Model\OrderFactory $order,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Checkout\Model\Session $session
    ) {
        $this->config = $config;
        $this->order = $order;
        $this->orderSender = $orderSender;
        $this->session = $session;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $orderIds = $observer->getEvent()->getOrderIds();
        $emailSuccess = $this->config->getEmailSuccessOption();

        if (count($orderIds) && $emailSuccess) {
            $this->session->getLastRealOrder()->setCanSendNewEmailFlag(false);

            $order = $this->order->create()->load($orderIds[0]);

            $this->orderSender->send($order, true);
            $this->session->getLastRealOrder()->setCanSendNewEmailFlag(true);
        }
    }
}

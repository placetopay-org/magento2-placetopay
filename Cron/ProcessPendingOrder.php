<?php

namespace PlacetoPay\Payments\Cron;

use Dnetix\Redirection\Exceptions\PlacetoPayException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use PlacetoPay\Payments\Model\PaymentMethod;

/**
 * Class ProcessPendingOrder.
 */
class ProcessPendingOrder
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    protected $placetopay;

    /**
     * ProcessPendingOrder constructor.
     *
     * @param CollectionFactory $collectionFactory
     * @param PaymentMethod     $placetopay
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        PaymentMethod $placetopay
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->placetopay = $placetopay;
    }

    /**
     * @throws PlacetoPayException
     * @throws LocalizedException
     */
    public function execute()
    {
        /** @var Order $orders */
        $orders = $this->collectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('state', ['in' => [
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_NEW,
            ]])
            ->addAttributeToFilter('status', ['in' => [
                'pending_payment',
            ]]);

        if ($orders) {
            foreach ($orders as $order) {
                $requestId = $order->getPayment()->getAdditionalInformation()['request_id'];

                if (!$requestId) {
                    continue;
                }

                if (!$this->placetopay->isPendingStatusOrder($order->getRealOrderId())) {
                    continue;
                }

                $this->placetopay->processPendingOrder($order, $requestId);
            }
        }
    }
}

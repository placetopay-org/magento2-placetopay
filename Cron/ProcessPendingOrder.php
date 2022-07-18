<?php

namespace PlacetoPay\Payments\Cron;

use Dnetix\Redirection\Exceptions\PlacetoPayException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use PlacetoPay\Payments\Model\PaymentMethod;
use Monolog\Handler\StreamHandler;
use PlacetoPay\Payments\Logger\Logger as LoggerInterface;

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

    private $logger ;

    /**
     * ProcessPendingOrder constructor.
     *
     * @param CollectionFactory $collectionFactory
     * @param PaymentMethod     $placetopay
     */
    public function __construct(
        LoggerInterface $logger,
        CollectionFactory $collectionFactory,
        PaymentMethod $placetopay
    ) {
        $this->logger = $logger;
        $this->collectionFactory = $collectionFactory;
        $this->placetopay = $placetopay;
    }

    /**
     * @throws PlacetoPayException
     * @throws LocalizedException
     */
    public function execute(): void
    {
        /** @var Order $orders */
        $orders = $this->collectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('state', ['in' => [
              Order::STATE_PENDING_PAYMENT,
              Order::STATE_NEW,
            ]])
            ->addAttributeToFilter('status', ['in' => [
                'pending_payment', 'pending',
            ]])->addAttributeToSort('entity_id');

        if ($orders) {
            foreach ($orders as $order) {
                $requestId = $order->getPayment()->getAdditionalInformation()['request_id'];
                if (!$requestId) {
                    continue;
                }
                $this->logger->debug('estoy antes del stauts ');
                $statusPayment = $order->getPayment()->getAdditionalInformation()['status'];
                $this->logger->debug('status '.$statusPayment);
                if (!in_array($statusPayment, ['APPROVED','REJECTED'])) {
                    $this->logger->debug('ProcessPendingOrder', ['Request:' => $requestId]);
                    $this->placetopay->processPendingOrder($order, $requestId);
                }
            }
        }
    }
}

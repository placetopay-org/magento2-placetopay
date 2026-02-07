<?php

namespace PlacetoPay\Payments\Cron;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use PlacetoPay\Payments\Constants\PaymentStatus;
use PlacetoPay\Payments\CountryConfig;
use PlacetoPay\Payments\Logger\Logger as LoggerInterface;
use PlacetoPay\Payments\Model\PaymentMethod;
use Magento\Store\Model\StoreManagerInterface;
use PlacetoPay\Payments\Helper\Data as Config;

/**
 * Class ProcessPendingOrder.
 */
class ProcessPendingOrder
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var PaymentMethod
     */
    protected $placetopay;

    /**
     * @var LoggerInterface
     */
    private $logger;
    private $storeManager;
    private $config;

    /**
     * ProcessPendingOrder constructor.
     *
     * @param CollectionFactory $collectionFactory
     * @param PaymentMethod $placetopay
     */
    public function __construct(
        LoggerInterface $logger,
        CollectionFactory $collectionFactory,
        PaymentMethod $placetopay,
        StoreManagerInterface $storeManager,
        Config $config
    ) {
        $this->logger = $logger;
        $this->collectionFactory = $collectionFactory;
        $this->placetopay = $placetopay;
        $this->storeManager = $storeManager;
        $this->config = $config;
    }

    public function execute(): void
    {
        $this->logger->info('ProcessPendingOrder cron job started.');

        $stores = $this->storeManager->getStores();
        foreach ($stores as $store) {
            $this->logger->info('Resolve payments for store with id:  ' . $store->getId());
            if(empty($this->config->getLogin($store->getId())) || empty($this->config->getTranKey($store->getId()))) {
                $this->logger->info('Login or TranKey were not defined for store: ' . $store->getId());
                continue;
            }
            $this->processOrdersForStore($store->getId());
        }

        $this->logger->info('ProcessPendingOrder cron job finished.');
    }

    private function processOrdersForStore(int $storeId): void
    {
        $gatewayConfig = [
            'login' => $this->config->getLogin($storeId),
            'tranKey' => $this->config->getTranKey($storeId),
            'baseUrl' => $this->config->getUri($storeId),
            'headers' => $this->config->getHeaders(),
        ];

        $this->placetopay->setGateway($gatewayConfig);

        $orders = $this->collectionFactory->create()
            ->addFieldToFilter('store_id', $storeId)
            ->join(
                ['payment' => 'sales_order_payment'],
                'main_table.entity_id = payment.parent_id',
                ['method']
            )
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('payment.method', ['eq' => PaymentMethod::CODE])
            ->addAttributeToFilter('state', ['in' => [Order::STATE_PENDING_PAYMENT, Order::STATE_NEW]])
            ->addAttributeToFilter('status', ['in' => [PaymentStatus::PENDING_PAYMENT, PaymentStatus::PENDING]])
            ->addAttributeToSort('entity_id');

        if ($orders->getSize() > 0) {
            foreach ($orders as $order) {
                $this->logger->debug('Processing order pending id: ' . $order->getRealOrderId());

                $payment = $order->getPayment();
                if ($payment === null) {
                    $this->logger->warning('Order ' . $order->getRealOrderId() . ' does not have a payment associated.');
                    $this->placetopay->processPendingOrderFail($order);
                    continue;
                }

                $information = $payment->getAdditionalInformation();
                if (!empty($information['request_id'])) {
                    $this->logger->debug('Processing order with session request: ' . $information['request_id']);
                    $requestId = $information['request_id'];
                    $statusPayment = $information['status'] ?? null;
                    $this->logger->debug('Status ' . $statusPayment);
                    if (!in_array($statusPayment, [PaymentStatus::APPROVED, PaymentStatus::REJECTED])) {
                        $this->logger->info('ProcessPendingOrder', ['Request:' => $requestId]);
                        $this->placetopay->processPendingOrder($order, $requestId);
                    }
                } else {
                    $this->logger->warning('The payment for the order ' . $order->getRealOrderId() . ' does not have a request ID.');
                    $this->placetopay->processPendingOrderFail($order);
                }
            }
        } else {
            $this->logger->info('No pending orders found for store ID ' . $storeId);
        }
    }
}

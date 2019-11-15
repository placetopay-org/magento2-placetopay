<?php

namespace PlacetoPay\Payments\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use PlacetoPay\Payments\Helper\Data;

/**
 * Class CustomConfigProvider.
 */
class CustomConfigProvider implements ConfigProviderInterface
{
    const CODE = PaymentMethod::CODE;

    /**
     * @var Data
     */
    protected $_scopeConfig;

    /**
     * @var Repository $_assetRepo
     */
    protected $_assetRepo;

    /**
     * @var CustomerSession $customerSession
     */
    protected $customerSession;

    /**
     * @var CollectionFactory $collectionFactory
     */
    protected $collectionFactory;

    /**
     * CustomConfigProvider constructor.
     *
     * @param Data $scopeConfig
     * @param Repository $assetRepo
     * @param CustomerSession $customerSession
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Data $scopeConfig,
        Repository $assetRepo,
        CustomerSession $customerSession,
        CollectionFactory $collectionFactory
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_assetRepo = $assetRepo;
        $this->customerSession = $customerSession;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'logoUrl' => $this->_assetRepo->getUrl('PlacetoPay_Payments::images/logo.png'),
                    'order' => $this->getLastOrder(),
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    public function getLastOrder()
    {
        $data = ['hasPendingOrder' => false];
        $customerId = $this->customerSession->getCustomer()->getId();

        $collection = $this->collectionFactory->create()
            ->addAttributeToSelect('*')->addFieldToFilter('customer_id', $customerId)
            ->addAttributeToFilter('state', ['in' => [
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_NEW,
            ]])
            ->addAttributeToFilter('status', ['in' => [
                'pending',
                'pending_payment',
                'pending_placetopay',
                AbstractMethod::STATUS_UNKNOWN,
            ]])
            ->addAttributeToSort('created_at', 'DESC')
            ->load()
            ->getItems();

        $pendingOrders = sizeof($collection);

        if ($pendingOrders > 0) {
            $lastOrder = reset($collection);
            $information = $lastOrder->getPayment()->getAdditionalInformation();

            $data = [
                'hasPendingOrder' => true,
                'id' => $lastOrder->getRealOrderId(),
                'phone' => $this->_scopeConfig->getPhone(),
                'email' => $this->_scopeConfig->getEmail(),
                'authorization' => isset($information['authorization']) ? $information['authorization'] : null,
            ];
        }

        return $data;
    }
}

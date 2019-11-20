<?php

namespace PlacetoPay\Payments\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
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
     * @var StoreManagerInterface $storeManager
     */
    protected $storeManager;

    /**
     * CustomConfigProvider constructor.
     *
     * @param Data                  $scopeConfig
     * @param Repository            $assetRepo
     * @param StoreManagerInterface $storeManager
     * @param CustomerSession       $customerSession
     * @param CollectionFactory     $collectionFactory
     */
    public function __construct(
        Data $scopeConfig,
        Repository $assetRepo,
        StoreManagerInterface $storeManager,
        CustomerSession $customerSession,
        CollectionFactory $collectionFactory
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_assetRepo = $assetRepo;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'logoUrl' => $this->_assetRepo->getUrl('PlacetoPay_Payments::images/logo.png'),
                    'legalName' => $this->_scopeConfig->getLegalName(),
                    'order' => $this->getLastOrder(),
                    'hasCifinMessage' => $this->_scopeConfig->getHasCifin(),
                    'allowPendingPayments' => $this->_scopeConfig->getAllowPendingPayment(),
                    'minimum' => $this->_scopeConfig->getMinimumAmount(),
                    'maximum' => $this->_scopeConfig->getMaximumAmount(),
                    'url' => $this->getUrl(),
                    'paymentMethods' => $this->getPaymentMethods(),
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

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
    }

    /**
     * @return array
     */
    public function getPaymentMethods()
    {
        $paymentMethods = [];

        if ($pm = $this->_scopeConfig->getPaymentMethods()) {
            $parsingsCountry = [
                'CO' => [],
                'EC' => [
                    'CR_VS' => 'ID_VS',
                    'RM_MC' => 'ID_MC',
                    'CR_DN' => 'ID_DN',
                    'CR_DS' => 'ID_DS',
                    'CR_AM' => 'ID_AM',
                    'CR_CR' => 'ID_CR',
                    'CR_VE' => 'ID_VE',
                ],
            ];

            foreach (explode(',', $pm) as $paymentMethod) {
                if (isset($parsingsCountry[$this->_scopeConfig->getCountryCode()][$paymentMethod])) {
                    $paymentMethods[] = $parsingsCountry[$this->_scopeConfig->getCountryCode()][$paymentMethod];
                } else {
                    $paymentMethods[] = $paymentMethod;
                }
            }

            $data['paymentMethod'] = implode(',', $paymentMethods);
        }

        return $paymentMethods;
    }
}

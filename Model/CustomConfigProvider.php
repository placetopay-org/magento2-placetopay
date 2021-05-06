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

class CustomConfigProvider implements ConfigProviderInterface
{
    const CODE = PaymentMethod::CODE;

    /**
     * @var Data
     */
    protected Data $_scopeConfig;

    /**
     * @var Repository
     */
    protected Repository $_assetRepo;

    /**
     * @var CustomerSession
     */
    protected CustomerSession $customerSession;

    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $collectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * CustomConfigProvider constructor.
     * @param Data $scopeConfig
     * @param Repository $assetRepo
     * @param StoreManagerInterface $storeManager
     * @param CustomerSession $customerSession
     * @param CollectionFactory $collectionFactory
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
     * @return array[][]
     * @throws NoSuchEntityException
     */
    public function getConfig(): array
    {
        return [
            'payment' => [
                self::CODE => [
                    'logoUrl' => $this->_assetRepo->getUrl('PlacetoPay_Payments::images/logo.png'),
                    'logo' => $this->getImage(),
                    'legalName' => $this->_scopeConfig->getLegalName(),
                    'order' => $this->getLastOrder(),
                    'hasCifinMessage' => $this->_scopeConfig->getHasCifin(),
                    'allowPendingPayments' => $this->_scopeConfig->getAllowPendingPayment(),
                    'minimum' => $this->_scopeConfig->getMinimumAmount(),
                    'maximum' => $this->_scopeConfig->getMaximumAmount(),
                    'url' => $this->getUrl(),
                    'paymentMethods' => $this->getPaymentMethods(),
                ],
            ],
        ];
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getImage(): string
    {
        $url = $this->_scopeConfig->getImageUrl();

        if (is_null($url)) {
            $image = 'https://static.placetopay.com/placetopay-logo.svg';
        } elseif ($this->checkValidUrl($url)) {
            $image = $url;
        } elseif ($this->checkDirectory($url)) {
            $image = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA).$url;
        } else {
            $image = 'https://static.placetopay.com/'.$url.'.svg';
        }

        return $image;
    }

    protected function checkDirectory(string $path): bool
    {
        return substr($path, 0, 1) === '/';
    }

    protected function checkValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL);
    }

    /**
     * @return array|false[]
     */
    public function getLastOrder(): array
    {
        $data = ['hasPendingOrder' => false];
        $customerId = $this->customerSession->getCustomer()->getId();

        $collection = $this->collectionFactory->create()
            ->addAttributeToSelect('*')
            ->addFieldToFilter('customer_id', $customerId)
            ->addAttributeToFilter('state', ['in' => [
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_NEW,
            ]])
            ->addAttributeToFilter('status', ['in' => [
                'pending_payment',
            ]])
            ->addAttributeToSort('created_at', 'DESC')
            ->load()
            ->getItems();

        $pendingOrders = count($collection);

        if ($pendingOrders > 0) {
            $lastOrder = reset($collection);
            $information = $lastOrder->getPayment()->getAdditionalInformation();

            $data = [
                'hasPendingOrder' => true,
                'id' => $lastOrder->getRealOrderId(),
                'phone' => $this->_scopeConfig->getPhone(),
                'email' => $this->_scopeConfig->getEmail(),
                'authorization' => $information['authorization'] ?? null,
            ];
        }

        return $data;
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getUrl(): string
    {
        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
    }

    /**
     * @return array
     */
    public function getPaymentMethods(): array
    {
        $paymentMethods = [];

        if ($pm = $this->_scopeConfig->getPaymentMethods()) {
            $parsingsCountry = [
                'CO' => [],
                'CR' => [],
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
        }

        return $paymentMethods;
    }
}

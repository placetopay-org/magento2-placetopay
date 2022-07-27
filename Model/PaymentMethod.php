<?php

namespace PlacetoPay\Payments\Model;

use Dnetix\Redirection\Message\RedirectInformation;
use Dnetix\Redirection\PlacetoPay;
use Exception;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\HTTP\Header;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Tax\Item;
use PlacetoPay\Payments\Concerns\IsSetStatusOrderTrait;
use PlacetoPay\Payments\Helper\Data as Config;
use PlacetoPay\Payments\Logger\Logger as LoggerInterface;
use PlacetoPay\Payments\Model\Info as InfoFactory;
use PlacetoPay\Payments\PlacetoPayService\PlacetoPayPayment;

/**
 * Class PlaceToPay.
 */
class PaymentMethod extends AbstractMethod
{
    use IsSetStatusOrderTrait;

    const CODE = 'placetopay';

    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canOrder = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = true;
    protected $_canFetchTransactionInfo = true;
    protected $_canReviewPayment = true;
    protected string $version;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Order
     */
    protected $_order;

    /**
     * @var Resolver
     */
    protected $_store;

    /**
     * @var UrlInterface
     */
    protected $_url;

    /**
     * @var RemoteAddress
     */
    protected $remoteAddress;

    /**
     * @var Header
     */
    protected $httpHeader;

    /**
     * @var Item
     */
    protected $taxItem;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Info
     */
    protected $infoFactory;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    protected PlacetoPayPayment $placetoPayPayment;

    /**
     * PaymentMethod constructor.
     *
     * @param LoggerInterface $_logger
     * @param InfoFactory $infoFactory
     * @param Config $config
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param Resolver $store
     * @param UrlInterface $urlInterface
     * @param Item $taxItem
     * @param Header $httpHeader
     * @param RemoteAddress $remoteAddress
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param array $data
     */
    public function __construct(
        LoggerInterface $_logger,
        InfoFactory $infoFactory,
        Config $config,
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        OrderRepositoryInterface $orderRepository,
        Resolver $store,
        UrlInterface $urlInterface,
        Item $taxItem,
        Header $httpHeader,
        RemoteAddress $remoteAddress,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        Resolver $resolver,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );

        $this->config = $config;
        $this->_store = $store;
        $this->_url = $urlInterface;
        $this->remoteAddress = $remoteAddress;
        $this->httpHeader = $httpHeader;
        $this->taxItem = $taxItem;
        $this->logger = $_logger;
        $this->orderRepository = $orderRepository;
        $this->infoFactory = $infoFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->version = '1.8.11';
        $this->placetoPayPayment = new PlacetoPayPayment($config, $_logger, $resolver, $urlInterface, $remoteAddress, $httpHeader, $taxItem);
    }

    /**
     * @param string $paymentAction
     * @param object $stateObject
     * @return PaymentMethod
     */
    public function initialize($paymentAction, $stateObject): PaymentMethod
    {
        $stateObject->setState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setState(AbstractMethod::STATUS_UNKNOWN);
        $stateObject->setIsNotified(false);

        return $this;
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function isActive($storeId = null): bool
    {
        return $this->config->getActive();
    }

    /**
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null): bool
    {
        return !(!$this->config->getTranKey()
            || !$this->config->getLogin()
            || !$this->config->getEndpointsTo($this->config->getCountryCode()));
    }

    /**
     * @param $value
     *
     * @return Phrase
     */
    public static function trans($value): Phrase
    {
        return __($value);
    }

    public function processPendingOrderFail(Order $order): void
    {
        $this->logger->debug('processPendingOrderFail to cancel');
        $this->changeStatusOrderFail($order);
    }

    public function processPendingOrder(Order $order, string $requestId): void
    {
        $this->logger->debug('processPendingOrder with request id: ' . $requestId);
        $transactionInfo = $this->placetoPayPayment->consultTransactionInfo($requestId);
        $this->logger->debug('processPendingOrder with placetopay status: ' . $transactionInfo->status()->status());

        $this->setStatus($transactionInfo, $order);
        $this->logger->debug('Cron job processed order with ID = ' . $order->getRealOrderId());
    }

    public function gateway(): PlacetoPay
    {
        return $this->placetoPayPayment->gateway();
    }

    /**
     * @return string|null
     * @throws Exception
     */
    public function getCheckoutRedirect(Order $order)
    {
        return $this->placetoPayPayment->getCheckoutRedirect($order);
    }

    public function resolve(Order $order, Order\Payment $payment = null): RedirectInformation
    {
        return $this->placetoPayPayment->resolve($order, $payment);
    }
}

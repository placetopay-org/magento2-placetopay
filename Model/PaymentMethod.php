<?php

namespace Getnet\Payments\Model;

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
use Getnet\Payments\Concerns\IsSetStatusOrderTrait;
use Getnet\Payments\Constants\PaymentStatus;
use Getnet\Payments\Helper\Data as Config;
use Getnet\Payments\Logger\Logger as LoggerInterface;
use Getnet\Payments\Model\Adminhtml\Source\Mode;
use Getnet\Payments\Model\Info as InfoFactory;
use Getnet\Payments\GetnetService\GetnetPayment;
use Magento\Tax\Model\Config as TaxConfig;

/**
 * Class Getnet.
 */
class PaymentMethod extends AbstractMethod
{
    use IsSetStatusOrderTrait;
    public const CODE = 'getnet';

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

    /**
     * @var string
     */
    protected $version;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var order
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

    /**
     * @var GetnetPayment
     */
    protected $getnetPayment;

    /**
     * @var TaxConfig
     */
    protected $tax;

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
        Resolver $resolver,
        RemoteAddress $remoteAddress,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        TaxConfig $tax,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
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
        $this->version = '1.12.2';

        $this->getnetPayment = new GetnetPayment(
            $config,
            $_logger,
            $resolver,
            $urlInterface,
            $remoteAddress,
            $httpHeader,
            $taxItem,
            $tax
        );
    }

    /**
     * @param null $storeId
     * @return bool
     * @see vendor/magento/module-payment/Model/MethodInterface.php
     */
    public function isActive($storeId = null): bool
    {
        return $this->config->getActive();
    }

    /**
     * @param CartInterface|null $quote
     * @return bool
     * @see vendor/magento/module-payment/Model/MethodInterface.php
     */
    public function isAvailable(CartInterface $quote = null): bool
    {
        return !(!$this->config->getTranKey()
            || !$this->config->getLogin()
            || !$this->config->getEndpointsTo());
    }

    /**
     * @param string $paymentAction
     * @param \Magento\Framework\DataObject $stateObject
     * @return PaymentMethod
     * @see vendor/magento/module-payment/Model/MethodInterface.php
     */
    public function initialize($paymentAction, $stateObject): PaymentMethod
    {
        $stateObject->setStatus('new');
        $stateObject->setState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);

        return $this;
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
        $this->logger->info('processPendingOrderFail to', ['status: ' => PaymentStatus::CANCELED]);
        $this->changeStatusOrderFail($order);
        $this->logger->info('Cron job processed order with ID = ' . $order->getRealOrderId());
    }

    public function processPendingOrder(Order $order, string $requestId): void
    {
        $transactionInfo = $this->getnetPayment->consultTransactionInfo($requestId);
        $this->logger->info('processPendingOrder with getnet status: ' . $transactionInfo->status()->status());

        $this->setStatus($transactionInfo, $order);
        $this->logger->info('Cron job processed order with ID = ' . $order->getRealOrderId());
    }

    public function gateway(): PlacetoPay
    {
        return $this->getnetPayment->gateway();
    }

    /**
     * @return string|null
     * @throws Exception
     */
    public function getCheckoutRedirect(Order $order)
    {
        return $this->getnetPayment->getCheckoutRedirect($order);
    }

    public function resolve(Order $order, Order\Payment $payment = null): RedirectInformation
    {
        return $this->getnetPayment->resolve($order, $payment);
    }

    public function getNameOfStore(): string
    {
        return $this->config->getTitle();
    }

    public function inDebugMode(): bool
    {
        return false;
    }

    public function setGateway($gatewayConfig): void
    {
        $this->getnetPayment->setGateway($gatewayConfig);
    }
}

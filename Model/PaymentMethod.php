<?php

namespace PlacetoPay\Payments\Model;

use Dnetix\Redirection\Entities\Status;
use Dnetix\Redirection\Exceptions\PlacetoPayException;
use Dnetix\Redirection\Message\RedirectInformation;
use Dnetix\Redirection\Message\RedirectResponse;
use Dnetix\Redirection\PlacetoPay;
use Dnetix\Redirection\Validators\Currency;
use Exception;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
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
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Tax\Item;
use PlacetoPay\Payments\Helper\Data as Config;
use PlacetoPay\Payments\Logger\Logger as LoggerInterface;
use PlacetoPay\Payments\Model\Info as InfoFactory;

/**
 * Class PlaceToPay.
 */
class PaymentMethod extends AbstractMethod
{
    const CODE = 'placetopay';
    const EXPIRATION_TIME_MINUTES_DEFAULT = 120;
    const EXPIRATION_TIME_MINUTES_MIN = 10;

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
     * @var Config
     */
    protected $_config;

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

        $this->_config = $config;
        $this->_store = $store;
        $this->_url = $urlInterface;
        $this->remoteAddress = $remoteAddress;
        $this->httpHeader = $httpHeader;
        $this->taxItem = $taxItem;
        $this->logger = $_logger;
        $this->orderRepository = $orderRepository;
        $this->infoFactory = $infoFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode): bool
    {
        return Currency::isValidCurrency($currencyCode);
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
     * @return InfoFactory
     */
    public function getInfoModel(): InfoFactory
    {
        return $this->infoFactory;
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function isActive($storeId = null): bool
    {
        return $this->_config->getActive();
    }

    /**
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null): bool
    {
        return !(!$this->_config->getTranKey()
            || !$this->_config->getLogin()
            || !$this->_config->getEndpointsTo($this->_config->getCountryCode()));
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

    /**
     * @param Order $order
     * @return Status
     */
    public function parseOrderState(Order $order): Status
    {
        switch ($order->getStatus()) {
            case Order::STATE_PROCESSING:
                $status = Status::ST_APPROVED;
                break;
            case Order::STATE_CANCELED:
                $status = Status::ST_REJECTED;
                break;
            case Order::STATE_NEW:
                $status = Status::ST_PENDING;
                break;
            default:
                $status = Status::ST_PENDING;
        }

        return new Status([
            'status' => $status,
        ]);
    }

    /**
     * @param int $orderId
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isPendingStatusOrder(int $orderId): bool
    {
        $this->logger->debug('isPendingStatusOrder: start search order id: '.$orderId);
        $status = $this->getOrderByIncrementId($orderId)->getPayment()->getAdditionalInformation()['status'];
        $this->logger->debug('isPendingStatusOrder: finish with status: '. $status);

        return Status::ST_PENDING === $status;
    }

    /**
     * @param $incrementId
     * @return false|OrderInterface
     * @throws NoSuchEntityException
     */
    public function getOrderByIncrementId($incrementId)
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter(
            OrderInterface::INCREMENT_ID,
            $incrementId
        )->create();

        $result = $this->orderRepository->getList($searchCriteria);

        if (empty($result->getItems())) {
            throw new NoSuchEntityException(__('No such order.'));
        }

        $orders = $result->getItems();

        return reset($orders);
    }

    /**
     * @param Order $order
     * @param string $requestId
     * @throws LocalizedException
     * @throws PlacetoPayException
     */
    public function processPendingOrder(Order $order, string $requestId): void
    {
        $this->logger->debug('processPendingOrder with request id: '.$requestId);
        $transactionInfo = $this->gateway()->query($requestId);
        $this->logger->debug('processPendingOrder with placetopay status: '.$transactionInfo->status()->status());
        $this->settleOrderStatus($transactionInfo, $order);
        $this->logger->debug('Cron job processed order with ID = ' . $order->getRealOrderId());
    }

    /**
     * @return PlacetoPay
     * @throws PlacetoPayException
     */
    public function gateway(): PlacetoPay
    {
        return new PlacetoPay([
            'login' => $this->_config->getLogin(),
            'tranKey' => $this->_config->getTranKey(),
            'url' => $this->_config->getUri(),
        ]);
    }

    /**
     * @param Order $order
     *
     * @return RedirectResponse
     * @throws PlacetoPayException
     */
    public function getPaymentRedirect($order)
    {
        $data = $this->getRedirectRequestDataFromOrder($order);

        return $this->gateway()->request($data);
    }

    /**
     * @param Order $order
     *
     * @return string|null
     * @throws Exception
     */
    public function getCheckoutRedirect($order)
    {
        $this->_order = $order;

        try {
            $response = $this->getPaymentRedirect($order);

            if ($response->isSuccessful()) {
                $payment = $order->getPayment();
                $info = $this->getInfoModel();

                $info->loadInformationFromRedirectResponse($payment, $response, $this->_config->getMode(), $order);
            } else {
                $this->_logger->debug(
                    'Payment error [' .
                    $order->getRealOrderId() . '] ' .
                    $response->status()->message() . ' - ' .
                    $response->status()->reason() . ' ' .
                    $response->status()->status()
                );

                throw new LocalizedException(__($response->status()->message()));
            }

            return $response->processUrl();
        } catch (Exception $ex) {
            $this->_logger->debug(
                'Payment error [' .
                $order->getRealOrderId() . '] ' .
                $ex->getMessage() . ' on ' . $ex->getFile() . ' line ' .
                $ex->getLine() . ' - ' . get_class($ex)
            );

            throw new Exception($ex->getMessage());
        }
    }

    /**
     * @param Order $order
     *
     * @return array
     */
    public function getRedirectRequestDataFromOrder($order)
    {
        $reference = $order->getRealOrderId();
        $total = ! is_null($order->getGrandTotal()) ? $order->getGrandTotal() : $order->getTotalDue();
        $subtotal = $order->getSubtotal();
        $discount = $order->getDiscountAmount() != 0 ? ($order->getDiscountAmount() * -1) : 0;
        $shipping = $order->getShippingAmount();
        $visibleItems = $order->getAllVisibleItems();
        $expiration = date('c', strtotime($this->getExpirationTimeMinutes() . ' minutes'));
        $items = [];

        /** @var Order\Item $item */
        foreach ($visibleItems as $item) {
            $items[] = [
                'sku' => $item->getSku(),
                'name' => $this->cleanText($item->getName()),
                'category' => $item->getProductType(),
                'qty' => $item->getQtyOrdered(),
                'price' => $item->getPrice(),
                'tax' => $item->getTaxAmount(),
            ];
        }

        $data = [
            'locale' => $this->_store->getLocale(),
            'buyer' => $this->parseAddressPerson($order->getBillingAddress()),
            'payment' => [
                'reference' => $reference,
                'description' => __('Payment in PlacetoPay No: %1', $order->getId())->render(),
                'amount' => [
                    'details' => [
                        [
                            'kind' => 'subtotal',
                            'amount' => $subtotal,
                        ],
                        [
                            'kind' => 'discount',
                            'amount' => $discount,
                        ],
                        [
                            'kind' => 'shipping',
                            'amount' => $shipping,
                        ],
                    ],
                    'currency' => $order->getOrderCurrencyCode(),
                    'total' => $total,
                ],
                'items' => $items,
                'shipping' => $this->parseAddressPerson($order->getShippingAddress()),
                'allowPartial' => $this->_config->getAllowPartialPayment(),
            ],
            'returnUrl' => $this->_url->getUrl('placetopay/payment/response', ['reference' => $reference]),
            'expiration' => $expiration,
            'ipAddress' => $this->remoteAddress->getRemoteAddress(),
            'userAgent' => $this->httpHeader->getHttpUserAgent(),
            'skipResult' => $this->_config->getSkipResult(),
            'noBuyerFill' => $this->_config->getFillBuyerInformation(),
        ];

        if ($this->_config->getFillTaxInformation()) {
            try {
                $map = [];

                if ($mapping = $this->_config->getTaxRateParsing()) {
                    foreach (explode('|', $mapping) as $item) {
                        $t = explode(':', $item);

                        if (is_array($t) && count($t) == 2) {
                            $map[$t[0]] = $t[1];
                        }
                    }
                }

                $taxInformation = $this->taxItem->getTaxItemsByOrderId($order->getId());

                if (is_array($taxInformation) && count($taxInformation) > 0) {
                    $taxes = [];

                    foreach ($taxInformation as $item) {
                        $taxes[] = [
                            'kind' => isset($map[$item['code']]) ? $map[$item['code']] : 'valueAddedTax',
                            'amount' => $item['real_amount'],
                        ];
                    }

                    $mergedTaxes = [];

                    foreach ($taxes as $elem) {
                        $mergedTaxes[$elem['kind']]['kind'] = $elem['kind'];
                        $mergedTaxes[$elem['kind']]['amount'] =
                            isset($mergedTaxes[$elem['kind']]['amount']) ?
                                number_format((float) $mergedTaxes[$elem['kind']]['amount'] + (float) $elem['amount'], 4, '.', '') :
                                number_format((float) $elem['amount'], 4, '.', '');
                    }

                    $mergedTaxes = array_values($mergedTaxes);

                    $data['payment']['amount']['taxes'] = $mergedTaxes;
                }
            } catch (Exception $ex) {
                $this->_logger->debug(
                    'Error calculating taxes: [' .
                    $order->getRealOrderId() .
                    '] ' . serialize($this->taxItem->getTaxItemsByOrderId($order->getId()))
                );
            }
        }

        if ($pm = $this->_config->getPaymentMethods()) {
            $paymentMethods = [];

            foreach (explode(',', $pm) as $paymentMethod) {
                $paymentMethods[] = $paymentMethod;
            }

            $data['paymentMethod'] = implode(',', $paymentMethods);
        }

        return $data;
    }

    /**
     * @param OrderAddressInterface $address
     *
     * @return array
     */
    public function parseAddressPerson($address)
    {
        if ($address) {
            return [
                'name' => $address->getFirstname(),
                'surname' => $address->getLastname(),
                'email' => $address->getEmail(),
                'mobile' => $address->getTelephone(),
                'address' => [
                    'country' => $address->getCountryId(),
                    'state' => $address->getRegion(),
                    'city' => $address->getCity(),
                    'street' => implode(' ', $address->getStreet()),
                    //'phone' => $address->getTelephone(),
                    'postalCode' => $address->getPostcode(),
                ],
            ];
        }

        return [];
    }

    /**
     * @return int
     */
    public function getExpirationTimeMinutes()
    {
        $minutes = $this->_config->getExpirationTime();

        return ! is_numeric($minutes) || $minutes < self::EXPIRATION_TIME_MINUTES_MIN
            ? self::EXPIRATION_TIME_MINUTES_DEFAULT
            : $minutes;
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    public function isPendingOrder($order)
    {
        return $order->getStatus() == 'pending' || $order->getStatus() == 'pending_payment';
    }

    /**
     * @param Order $order
     *
     * @throws LocalizedException
     */
    public function _createInvoice($order)
    {
        if (! $order->canInvoice()) {
            return;
        }

        $invoice = $order->prepareInvoice();
        $invoice->register()->capture();
        $order->addRelatedObject($invoice);
    }

    /**
     * @param Order         $order
     * @param Order\Payment $payment
     *
     * @return RedirectInformation
     * @throws LocalizedException
     * @throws PlacetoPayException
     */
    public function resolve($order, $payment = null)
    {
        if (! $payment) {
            $payment = $order->getPayment();
        }

        $info = $payment->getAdditionalInformation();

        if (! $info || ! isset($info['request_id'])) {
            $this->_logger->debug(
                'No additional information for order: ' .
                $order->getRealOrderId()
            );

            throw new LocalizedException(__('No additional information for order: %1', $order->getRealOrderId()));
        }

        $response = $this->gateway()->query($info['request_id']);

        if ($response->isSuccessful()) {
            $this->settleOrderStatus($response, $order, $payment);
        } else {
            $this->_logger->debug(
                'Non successful: ' .
                $response->status()->message() . ' ' .
                $response->status()->reason()
            );
        }

        return $response;
    }

    /**
     * @param RedirectInformation $information
     * @param Order $order
     * @param Order\Payment|null $payment
     *
     * @throws LocalizedException
     * @throws \PlacetoPay\Payments\Exception\PlacetoPayException
     */
    public function settleOrderStatus(RedirectInformation $information, Order $order, Order\Payment $payment = null)
    {
        $status = $information->status();

        switch ($status->status()) {
            case Status::ST_APPROVED:
                $state = Order::STATE_PROCESSING;
                $orderStatus = Order::STATE_PROCESSING;

                break;
            case Status::ST_REJECTED:
                $state = Order::STATE_CANCELED;
                $orderStatus = Order::STATE_CANCELED;

                break;
            case Status::ST_PENDING:
                $state = Order::STATE_NEW;
                $orderStatus = Order::STATE_PENDING_PAYMENT;

                break;
            default:
                $state = $orderStatus = null;
        }

        if ($state !== null) {
            if (! $payment) {
                $payment = $order->getPayment();
            }

            $info = $this->getInfoModel();
            $transactions = $information->payment();
            $info->updateStatus($payment, $status, $transactions);
            $this->logger->debug('settleOrderStatus with status: '. $status->status());

            if ($status->isApproved()) {
                $payment->setIsTransactionPending(false);
                $payment->setIsTransactionApproved(true);
                $payment->setSkipOrderProcessing(true);
                $this->_createInvoice($order);
                $order->setEmailSent(true);
                $order->setState($state)->setStatus($orderStatus)->save();
            } elseif ($status->isRejected()) {
                $payment->setIsTransactionDenied(true);
                $payment->setSkipOrderProcessing(true);
                $order->cancel()->save();
            } else {
                $order->setState($state)->setStatus($orderStatus)->save();
            }
        }
    }

    /**
     * @param $text
     *
     * @return string|string[]|null
     */
    public function cleanText($text)
    {
        return preg_replace('/[(),.#!\-]/', '', $text);
    }
}

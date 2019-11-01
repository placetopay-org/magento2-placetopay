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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
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
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Tax\Item;
use Magento\Store\Model\ScopeInterface;
use PlacetoPay\Payments\Helper\Data as Config;
use PlacetoPay\Payments\Model\Info as InfoFactory;
use Psr\Log\LoggerInterface;

/**
 * Class PlaceToPay.
 */
class PaymentMethod extends AbstractMethod
{
    const CODE = 'placetopay';
    const EXPIRATION_TIME_MINUTES_DEFAULT = 120;
    const EXPIRATION_TIME_MINUTES_MIN = 10;

    protected $gateway;
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
    protected $_config;
    protected $_order;
    protected $_store;
    protected $_url;
    protected $remoteAddress;
    protected $httpHeader;
    protected $taxItem;
    protected $logger;
    protected $infoFactory;

    /**
     * PaymentMethod constructor.
     *
     * @param LoggerInterface            $_logger
     * @param InfoFactory                $infoFactory
     * @param Config                     $config
     * @param Context                    $context
     * @param Registry                   $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory      $customAttributeFactory
     * @param Data                       $paymentData
     * @param ScopeConfigInterface       $scopeConfig
     * @param Logger                     $logger
     * @param Resolver                   $store
     * @param UrlInterface               $urlInterface
     * @param Item                       $taxItem
     * @param Header                     $httpHeader
     * @param RemoteAddress              $remoteAddress
     * @param AbstractResource           $resource
     * @param AbstractDb                 $resourceCollection
     * @param array                      $data
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
        Resolver $store,
        UrlInterface $urlInterface,
        Item $taxItem,
        Header $httpHeader,
        RemoteAddress $remoteAddress,
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
        $this->infoFactory = $infoFactory;
    }

    /**
     * @param string $currencyCode
     *
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        return Currency::isValidCurrency($currencyCode);
    }

    /**
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return $this|AbstractMethod
     */
    public function initialize($paymentAction, $stateObject)
    {
        $stateObject->setState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setState(AbstractMethod::STATUS_UNKNOWN);
        $stateObject->setIsNotified(false);

        return $this;
    }

    /**
     * @return InfoFactory
     */
    public function getInfoModel()
    {
        return $this->infoFactory;
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        if ($this->_config->getActive()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param CartInterface|null $quote
     *
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        if (! $this->_config->getTranKey() ||
            ! $this->_config->getLogin() ||
            ! $this->_config->getEndpointsTo($this->_config->getCountryCode())) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param $value
     *
     * @return Phrase
     */
    public static function trans($value)
    {
        return __($value);
    }

    /**
     * @param Order $order
     *
     * @return Status
     */
    public function parseOrderState($order)
    {
        $status = null;

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
     * @return PlacetoPay
     * @throws PlacetoPayException
     */
    public function gateway()
    {
        if (! $this->gateway) {
            $env = $this->_config->getMode();
            $url = $this->_config->getEndpointsTo($this->_config->getCountryCode());

            $this->gateway = new PlacetoPay([
                'login' => $this->_config->getLogin(),
                'tranKey' => $this->_config->getTranKey(),
                'url' => $url[$env]
            ]);
        }

        return $this->gateway;
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

        $this->_logger->debug(
            'P2P_LOG: CheckoutRedirect/Failure [' .
            $order->getRealOrderId() . '] ' . $this->serialize($data)
        );

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
                    'P2P_LOG: CheckoutRedirect/Failure [' .
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
                'P2P_LOG: CheckoutRedirect/Exception [' .
                $order->getRealOrderId() . '] ' .
                $ex->getMessage() . ' ON ' . $ex->getFile() . ' LINE ' .
                $ex->getLine() . ' -- ' . get_class($ex)
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
                'description' => __('Order # %1', $order->getId()),
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
            'cancelUrl' => $this->_url->getUrl('placetopay/payment/cancel'),
            'expiration' => $expiration,
            'ipAddress' => $this->remoteAddress->getRemoteAddress(),
            'userAgent' => $this->httpHeader->getHttpUserAgent(),
            'skipResult' => $this->_config->getSkipResult(),
            'noBuyerFill' => $this->_config->getFillBuyerInformation(),
        ];

        if ($this->_config->getFillTaxInformation()) {
            try {
                $map = [];
                $taxInformation = $this->taxItem->getTaxItemsByOrderId($order->getId());

                if (is_array($taxInformation) && sizeof($taxInformation) > 0) {
                    $taxes = [];

                    while ($compound = array_pop($taxInformation)) {
                        $taxAmount = $compound['amount'];
                        $taxPercent = $compound['percent'];

                        foreach ($compound['rates'] as $rate) {
                            $taxes[] = [
                                'kind' => isset($map[$rate['code']]) ? $map[$rate['code']] : 'valueAddedTax',
                                'amount' => $taxAmount * ($rate['percent'] / $taxPercent),
                            ];
                        }
                    }
                    $data['payment']['amount']['taxes'] = $taxes;
                }
            } catch (Exception $ex) {
                $this->_logger->debug(
                    'P2P_LOG: Error calculating taxes: [' .
                    $order->getRealOrderId() .
                    '] ' . serialize($this->taxItem->getTaxItemsByOrderId($order->getId()))
                );
            }
        }

        if ($pm = $this->_config->getPaymentMethods()) {
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

            $paymentMethods = [];

            foreach (explode(',', $pm) as $paymentMethod) {
                if (isset($parsingsCountry[$this->_config->getCountryCode()][$paymentMethod])) {
                    $paymentMethods[] = $parsingsCountry[$this->_config->getCountryCode()][$paymentMethod];
                } else {
                    $paymentMethods[] = $paymentMethod;
                }
            }

            $data['paymentMethod'] = implode(',', $paymentMethods);
        }

        return $data;
    }

    /**
     * @param OrderAddressInterface $address
     *
     * @return array|null
     */
    public function parseAddressPerson($address)
    {
        if ($address) {
            $data = [
                'name' => $address->getFirstname(),
                'surname' => $address->getLastname(),
                'email' => $address->getEmail(),
                'mobile' => $address->getTelephone(),
                'address' => [
                    'country' => $address->getCountryId(),
                    'state' => $address->getRegion(),
                    'city' => $address->getCity(),
                    'street' => implode(' ', $address->getStreet()),
                    'phone' => $address->getTelephone(),
                    'postalCode' => $address->getPostcode(),
                ],
            ];

            return $data;
        }

        return null;
    }

    /**
     * @return int
     */
    public function getExpirationTimeMinutes()
    {
        $minutes = $this->_config->getExpirationTime();

        return !is_numeric($minutes) || $minutes < self::EXPIRATION_TIME_MINUTES_MIN
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
                'P2P_LOG: Abstract/Resolve No additional information for order: ' .
                $order->getRealOrderId()
            );

            throw new LocalizedException(__('No additional information for order: %1', $order->getRealOrderId()));
        }

        $response = $this->gateway()->query($info['request_id']);

        if ($response->isSuccessful()) {
            $this->settleOrderStatus($response, $order, $payment);
        } else {
            $this->_logger->debug(
                'P2P_LOG: Abstract/Resolve Non successful: ' .
                $response->status()->message() . ' ' .
                $response->status()->reason()
            );
        }

        return $response;
    }

    /**
     * @param Order         $order
     * @param Order\Payment $payment
     *
     * @return RedirectInformation
     * @throws LocalizedException
     * @throws PlacetoPayException
     */
    public function query($order, $payment = null)
    {
        if (! $payment) {
            $payment = $order->getPayment();
        }

        $info = $payment->getAdditionalInformation();

        if (! $info || ! isset($info['request_id'])) {
            $this->_logger->debug('P2P_LOG: Abstract/Resolve No additional information for order: ' . $order->getRealOrderId());

            throw new LocalizedException(__('No additional information for order: ' . $order->getRealOrderId()));
        }
        return $this->gateway()->query($info['request_id']);
    }

    /**
     * @param RedirectInformation $information
     * @param Order               $order
     * @param Order\Payment       $payment
     *
     * @throws LocalizedException
     * @throws Exception
     */
    public function settleOrderStatus(RedirectInformation $information, &$order, $payment = null)
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
                $state = $orderStatus = $comment = null;
        }

        if ($state !== null) {
            if (! $payment) {
                $payment = $order->getPayment();
            }

            $info = $this->getInfoModel();
            $transactions = $information->payment();
            $info->updateStatus($payment, $status, $transactions);

            if ($status->isApproved()) {
                $this->_createInvoice($order);
                $order->setEmailSent(true);
                $order->setState($state)->setStatus($orderStatus)->save();
            } elseif ($status->isRejected()) {
                $order->cancel()->save();
            } else {
                $order->setState($state)->setStatus($orderStatus)->save();
            }
        }
    }

    public function getAmount($order)
    {
        $amount = $order->getGrandTotal();
        return $amount;
    }

    public function getOrderStates()
    {
        return [
            'pending' => $this->_scopeConfig->getValue(
                'payment/placetopay/states/pending',
                ScopeInterface::SCOPE_STORE
            ),
            'approved' => $this->_scopeConfig->getValue(
                'payment/placetopay/states/approved',
                ScopeInterface::SCOPE_STORE
            ),
            'rejected' => $this->_scopeConfig->getValue(
                'payment/placetopay/states/rejected',
                ScopeInterface::SCOPE_STORE
            )
        ];
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

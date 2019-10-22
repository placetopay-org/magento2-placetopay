<?php

namespace PlacetoPay\Payments\Model;

use Dnetix\Redirection\PlacetoPay;
use Dnetix\Redirection\Validators\Currency;
use Exception;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\HTTP\Header;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
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
use PlacetoPay\Payments\Helper\Data as HelperData;

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
    protected $_helperData;
    protected $_order;
    protected $_store;
    protected $_url;
    protected $remoteAddress;
    protected $httpHeader;
    protected $taxItem;

    /**
     * PaymentMethod constructor.
     *
     * @param HelperData $helperData
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param Resolver $store
     * @param UrlInterface $urlInterface
     * @param Item $taxItem
     * @param Header $httpHeader
     * @param RemoteAddress $remoteAddress
     * @param AbstractResource $resource
     * @param AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        HelperData $helperData,
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

        $this->_helperData = $helperData;
        $this->_store = $store;
        $this->_url = $urlInterface;
        $this->remoteAddress = $remoteAddress;
        $this->httpHeader = $httpHeader;
        $this->taxItem = $taxItem;
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
     * @param null $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        if ($this->_helperData->getActive()) {
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
        if (!$this->_helperData->getTranKey() ||
            !$this->_helperData->getLogin() ||
            !$this->_helperData->getEndpointsTo($this->_helperData->getCountryCode())) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @return PlacetoPay
     * @throws Exception
     */
    public function placeToPay()
    {
        $env = $this->_helperData->getMode();
        $url = $this->_helperData->getEndpointsTo($this->_helperData->getCountryCode());

        try {
            $placeToPay = new PlacetoPay([
                'login' => $this->_helperData->getLogin(),
                'tranKey' => $this->_helperData->getTranKey(),
                'url' => $url[$env]
            ]);
            return $placeToPay;
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
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
                'allowPartial' => $this->_helperData->getAllowPartialPayment(),
            ],
            'returnUrl' => $this->_url->getUrl('placetopay/processing/response') . '?reference=' . $reference,
            'cancelUrl' => $this->_url->getUrl('placetopay/payment/cancel'),
            'expiration' => $expiration,
            'ipAddress' => $this->remoteAddress->getRemoteAddress(),
            'userAgent' => $this->httpHeader->getHttpUserAgent(),
            'skipResult' => $this->_helperData->getSkipResult(),
            'noBuyerFill' => $this->_helperData->getFillBuyerInformation(),
        ];

        if ($this->_helperData->getFillTaxInformation()) {
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
                $this->_helperData->log(
                    'P2P_LOG: Error calculating taxes: [' .
                    $order->getRealOrderId() .
                    '] ' . serialize($this->taxItem->getTaxItemsByOrderId($order->getId()))
                );
            }
        }

        if ($pm = $this->_helperData->getPaymentMethods()) {
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
                if (isset($parsingsCountry[$this->_helperData->getCountryCode()][$paymentMethod])) {
                    $paymentMethods[] = $parsingsCountry[$this->_helperData->getCountryCode()][$paymentMethod];
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
        $minutes = $this->_helperData->getExpirationTime();

        return !is_numeric($minutes) || $minutes < self::EXPIRATION_TIME_MINUTES_MIN
            ? self::EXPIRATION_TIME_MINUTES_DEFAULT
            : $minutes;
    }

    public function getAmount($order)
    {
        $amount = $order->getGrandTotal();
        return $amount;
    }

    public function getOrderStates()
    {
        return [
            'pending' => $this->_scopeConfig->getValue('payment/placetopay/states/pending', ScopeInterface::SCOPE_STORE),
            'approved' => $this->_scopeConfig->getValue('payment/placetopay/states/approved', ScopeInterface::SCOPE_STORE),
            'rejected' => $this->_scopeConfig->getValue('payment/placetopay/states/rejected', ScopeInterface::SCOPE_STORE)
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

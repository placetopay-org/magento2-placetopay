<?php

namespace PlacetoPay\Payments\Model;

use Dnetix\Redirection\PlacetoPay as PlacetoPayRedirect;
use Exception;
use Magento\Payment\Model\Method\AbstractMethod;

/**
 * Class PlaceToPay.
 */
class PlaceToPay extends AbstractMethod
{
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

    protected $_supportedCurrencyCodes = ['COP', 'USD'];

    protected $_helperData;

    /**
     * PlaceToPay constructor.
     * @param \PlacetoPay\Payments\Helper\Data $helperData
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \PlacetoPay\Payments\Helper\Data $helperData,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
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
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
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
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }

        return true;
    }

    /**
     * @return PlacetoPayRedirect
     * @throws Exception
     */
    public function placeToPay()
    {
        $env = $this->_helperData->getMode();
        $url = $this->_helperData->getEndpointsTo($this->_helperData->getCountryCode());

        try {
            $placeToPay = new PlacetoPayRedirect([
                'login' => $this->_helperData->getLogin(),
                'tranKey' => $this->_helperData->getTranKey(),
                'url' => $url[$env]
            ]);
            return $placeToPay;
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
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
            'pending' => $this->_scopeConfig->getValue('payment/placetopay/states/pending', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'approved' => $this->_scopeConfig->getValue('payment/placetopay/states/approved', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'rejected' => $this->_scopeConfig->getValue('payment/placetopay/states/rejected', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
        ];
    }
}

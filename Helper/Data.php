<?php


namespace PlacetoPay\Payments\Helper;

use Magento\Framework\View\LayoutFactory;
use PlacetoPay\Payments\Model\Adminhtml\Source\Mode;

class Data extends \Magento\Payment\Helper\Data
{
    protected $_placeToPayLogger;

    protected $_enviroment;

    protected $logger;

    public function __construct(
        \PlacetoPay\Payments\Logger\Logger $placeToPayLogger,
        \Magento\Framework\App\Helper\Context $context,
        LayoutFactory $layoutFactory,
        \Magento\Payment\Model\Method\Factory $paymentMethodFactory,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\Payment\Model\Config $paymentConfig,
        \Magento\Framework\App\Config\Initial $initialConfig,
        \Psr\Log\LoggerInterface $logger
    )
    {
        parent::__construct(
            $context,
            $layoutFactory,
            $paymentMethodFactory,
            $appEmulation,
            $paymentConfig,
            $initialConfig
        );

        $this->_placeToPayLogger = $placeToPayLogger;
        $this->_enviroment = $this->scopeConfig->getValue('payment/placetopay/placetopay_mode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->logger = $logger;
    }

    public function log($message, $array = null)
    {
        if (!is_null($array))
            $message .= " - " . json_encode($array);

        $this->_placeToPayLogger->debug($message);
    }

    public function getActive()
    {
        return (bool)(int)$this->scopeConfig->getValue('payment/placetopay/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getEnviroment()
    {
        return $this->_enviroment;
    }

    /**
     * @return string|null
     */
    public function getTranKey()
    {
        $tranKey = null;

        switch ($this->_enviroment) {
            case Mode::DEVELOPMENT:
                $tranKey = $this->scopeConfig->getValue('payment/placetopay/placetopay_development_tk', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
            case Mode::TEST:
                $tranKey = $this->scopeConfig->getValue('payment/placetopay/placetopay_test_tk', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
            default:
                $tranKey = $this->scopeConfig->getValue('payment/placetopay/placetopay_production_tk', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
        }

        return $tranKey;
    }

    /**
     * @return string|null
     */
    public function getLogin()
    {
        $login = null;

        switch ($this->_enviroment) {
            case Mode::DEVELOPMENT:
                $login = $this->scopeConfig->getValue('payment/placetopay/placetopay_development_lg', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
            case Mode::TEST:
                $login = $this->scopeConfig->getValue('payment/placetopay/placetopay_test_lg', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
            default:
                $login = $this->scopeConfig->getValue('payment/placetopay/placetopay_production_lg', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
        }

        $this->logger->debug($login);

        return $login;
    }

    public function getUrlEndPoint()
    {
        if ($this->_enviroment) {
            return $this->scopeConfig->getValue('payment/placetopay/enviroment_g/development/url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        } else {
            return $this->scopeConfig->getValue('payment/placetopay/enviroment_g/production/url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }
    }

    public function getMinOrderTotal()
    {
        return $this->scopeConfig->getValue('payment/placetopay/min_order_total', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getMaxOrderTotal()
    {
        return $this->scopeConfig->getValue('payment/placetopay/max_order_total', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getOrderStates()
    {
        return [
            'pending' => $this->scopeConfig->getValue('payment/placetopay/states/pending', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'approved' => $this->scopeConfig->getValue('payment/placetopay/states/approved', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'rejected' => $this->scopeConfig->getValue('payment/placetopay/states/rejected', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
        ];
    }
}
<?php


namespace PlacetoPay\Payments\Helper;

use Magento\Framework\View\LayoutFactory;

class Data extends \Magento\Payment\Helper\Data
{
    protected $_placeToPayLogger;

    protected $_enviroment;

    public function __construct(
        \PlacetoPay\Payments\Logger\Logger $placeToPayLogger,
        \Magento\Framework\App\Helper\Context $context,
        LayoutFactory $layoutFactory,
        \Magento\Payment\Model\Method\Factory $paymentMethodFactory,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\Payment\Model\Config $paymentConfig,
        \Magento\Framework\App\Config\Initial $initialConfig
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
        $this->_enviroment = (bool)(int)$this->scopeConfig->getValue('payment/placetopay/environment',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
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

    public function getTrankey()
    {
        if ($this->_enviroment){
            return $this->scopeConfig->getValue('payment/placetopay/enviroment_g/development/trankey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }else{
            return $this->scopeConfig->getValue('payment/placetopay/enviroment_g/production/trankey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }
    }

    public function getLogin()
    {
        if ($this->_enviroment){
            return $this->scopeConfig->getValue('payment/placetopay/enviroment_g/development/login', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }else{
            return $this->scopeConfig->getValue('payment/placetopay/enviroment_g/production/login', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }
    }

    public function getUrlEndPoint()
    {
        if ($this->_enviroment){
            return $this->scopeConfig->getValue('payment/placetopay/enviroment_g/development/url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }else{
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
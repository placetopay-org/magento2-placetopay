<?php

namespace PlacetoPay\Payments\Helper;

use Magento\Framework\App\Config\Initial;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\View\LayoutFactory;
use Magento\Payment\Helper\Data as BaseData;
use Magento\Payment\Model\Config;
use Magento\Payment\Model\Method\Factory;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\ScopeInterface;
use PlacetoPay\Payments\Logger\Logger;
use PlacetoPay\Payments\Model\Adminhtml\Source\Country;
use PlacetoPay\Payments\Model\Adminhtml\Source\Mode;

class Data extends BaseData
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $mode;

    /**
     * Data constructor.
     * @param Logger $logger
     * @param Context $context
     * @param LayoutFactory $layoutFactory
     * @param Factory $paymentMethodFactory
     * @param Emulation $appEmulation
     * @param Config $paymentConfig
     * @param Initial $initialConfig
     */
    public function __construct(
        Logger $logger,
        Context $context,
        LayoutFactory $layoutFactory,
        Factory $paymentMethodFactory,
        Emulation $appEmulation,
        Config $paymentConfig,
        Initial $initialConfig
    ) {
        parent::__construct(
            $context,
            $layoutFactory,
            $paymentMethodFactory,
            $appEmulation,
            $paymentConfig,
            $initialConfig
        );

        $this->logger = $logger;

        $this->mode = $this->scopeConfig->getValue(
            'payment/placetopay/placetopay_mode',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getMerchantId(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/merchant_id',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getLegalName(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/legal_name',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/email',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getPhone(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/phone',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getExpirationTime(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/expiration',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getFinalPage(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/final_page',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function getAllowPendingPayment(): bool
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/allow_pending_payment',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function getAllowPartialPayment(): bool
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/allow_partial_payment',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function getHasCifin(): bool
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/has_cifin',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function getFillTaxInformation(): bool
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/fill_tax_information',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function getFillBuyerInformation(): bool
    {
        return ! $this->scopeConfig->getValue(
            'payment/placetopay/fill_buyer_information',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function getSkipResult(): bool
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/skip_result',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getPaymentMethods(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/payment_methods',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getMinimumAmount(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/minimum_amount',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getMaximumAmount(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/maximum_amount',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getTaxRateParsing(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/tax_rate_parsing',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function getEmailSuccessOption(): bool
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/email_success',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function getActive(): bool
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/active',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getTile(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/title',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/description',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getMode(): ?string
    {
        return $this->mode;
    }

    /**
     * @return string|null
     */
    public function getCustomConnectionUrl(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/placetopay_custom_url',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function isCustomEnvironment(): bool
    {
        return $this->getMode() === Mode::CUSTOM;
    }

    /**
     * @return string|null
     */
    public function getUri(): ?string
    {
        $uri = null;
        $endpoints = $this->getEndpointsTo($this->getCountryCode());

        if ($this->isCustomEnvironment()) {
            $uri = $this->getCustomConnectionUrl();
        } elseif (!empty($endpoints[$this->getMode()])) {
            $uri = $endpoints[$this->getMode()];
        }

        return $uri;
    }

    /**
     * @return string|null
     */
    public function getTranKey(): ?string
    {
        switch ($this->mode) {
            case Mode::DEVELOPMENT:
                $tranKey = $this->scopeConfig->getValue(
                    'payment/placetopay/placetopay_development_tk',
                    ScopeInterface::SCOPE_STORE
                );
                break;
            case Mode::TEST:
                $tranKey = $this->scopeConfig->getValue(
                    'payment/placetopay/placetopay_test_tk',
                    ScopeInterface::SCOPE_STORE
                );
                break;
            case Mode::CUSTOM:
                $tranKey = $this->scopeConfig->getValue(
                    'payment/placetopay/placetopay_custom_tk',
                    ScopeInterface::SCOPE_STORE
                );
                break;
            default:
                $tranKey = $this->scopeConfig->getValue(
                    'payment/placetopay/placetopay_production_tk',
                    ScopeInterface::SCOPE_STORE
                );
                break;
        }

        return $tranKey;
    }

    /**
     * @return string|null
     */
    public function getLogin(): ?string
    {
        switch ($this->mode) {
            case Mode::DEVELOPMENT:
                $login = $this->scopeConfig->getValue(
                    'payment/placetopay/placetopay_development_lg',
                    ScopeInterface::SCOPE_STORE
                );
                break;
            case Mode::TEST:
                $login = $this->scopeConfig->getValue(
                    'payment/placetopay/placetopay_test_lg',
                    ScopeInterface::SCOPE_STORE
                );
                break;
            case Mode::CUSTOM:
                $login = $this->scopeConfig->getValue(
                    'payment/placetopay/placetopay_custom_lg',
                    ScopeInterface::SCOPE_STORE
                );
                break;
            default:
                $login = $this->scopeConfig->getValue(
                    'payment/placetopay/placetopay_production_lg',
                    ScopeInterface::SCOPE_STORE
                );
                break;
        }

        return $login;
    }

    /**
     * @return string|null
     */
    public function getCountryCode(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/country',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getImageUrl(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/placetopay/payment_button_image',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string[]
     */
    public static function getDefaultEndpoints(): array
    {
        return [
            Mode::DEVELOPMENT => 'https://dev.placetopay.com/redirection',
            Mode::TEST => 'https://test.placetopay.com/redirection',
            Mode::PRODUCTION => 'https://checkout.placetopay.com',
        ];
    }

    /**
     * @param $countryCode
     * @return string[]
     */
    public function getEndpointsTo($countryCode): array
    {
        switch ($countryCode) {
            case Country::ECUADOR:
                $endpoints = [
                    Mode::DEVELOPMENT => 'https://dev.placetopay.ec/redirection',
                    Mode::TEST => 'https://checkout-test.placetopay.ec',
                    Mode::PRODUCTION => 'https://checkout.placetopay.ec',
                ];
                break;
            case Country::CHILE:
                $endpoints = [
                    Mode::DEVELOPMENT => 'https://dev.placetopay.com/redirection',
                    Mode::TEST => 'https://checkout.uat.getnet.cl',
                    Mode::PRODUCTION => 'https://checkout.getnet.cl',
                ];
                break;
            case Country::PUERTO_RICO:
                $endpoints = [
                    Mode::DEVELOPMENT => 'https://dev.placetopay.com/redirection',
                    Mode::TEST => 'https://pr-uat-checkout.placetopay.com/',
                    Mode::PRODUCTION => 'https://checkout.placetopay.com/',
                ];
                break;
            case Country::COLOMBIA:
            case Country::COSTA_RICA:
            default:
                $endpoints = self::getDefaultEndpoints();
                break;
        }

        return $endpoints;
    }
}

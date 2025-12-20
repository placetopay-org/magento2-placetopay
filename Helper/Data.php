<?php

namespace Banchile\Payments\Helper;

use Banchile\Payments\Countries\ChileCountryConfig;
use Magento\Framework\App\Config\Initial;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\LayoutFactory;
use Magento\Payment\Helper\Data as BaseData;
use Magento\Payment\Model\Config;
use Magento\Payment\Model\Method\Factory;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\ScopeInterface;
use Banchile\Payments\Constants\Country;
use Banchile\Payments\Logger\Logger;
use Banchile\Payments\Model\Adminhtml\Source\Mode;
use Banchile\Payments\Model\Info as InfoFactory;

class Data extends BaseData
{
    public const CODE = 'banchile';
    public const EXPIRATION_TIME_MINUTES_DEFAULT = 120;
    public const EXPIRATION_TIME_MINUTES_MIN = 10;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var string|mixed
     */
    protected $mode;

    /**
     * @param InfoFactory
     */
    protected $infoFactory;

    public function __construct(
        Logger        $logger,
        Context       $context,
        LayoutFactory $layoutFactory,
        Factory       $paymentMethodFactory,
        Emulation     $appEmulation,
        Config        $paymentConfig,
        Initial       $initialConfig,
        InfoFactory   $infoFactory
    ) {
        parent::__construct(
            $context,
            $layoutFactory,
            $paymentMethodFactory,
            $appEmulation,
            $paymentConfig,
            $initialConfig
        );
        $this->infoFactory = $infoFactory;
        $this->logger = $logger;
        $this->version = '1.12.2';

        $this->mode = $this->getMode();
    }

    /**
     * @return string|null
     */
    public function getMerchantId(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/merchant_id',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getLegalName(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/legal_name',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/email',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getPhone(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/phone',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getExpirationTime(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/expiration',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getFinalPage(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/final_page',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getAllowPendingPayment(): bool
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/allow_pending_payment',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getAllowPartialPayment(): bool
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/allow_partial_payment',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getHasCifin(): bool
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/has_cifin',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getFillTaxInformation(): bool
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/fill_tax_information',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getFillBuyerInformation(): bool
    {
        return !$this->scopeConfig->getValue(
            'payment/banchile/fill_buyer_information',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getSkipResult(): bool
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/skip_result',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getMinimumAmount(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/minimum_amount',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getMaximumAmount(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/maximum_amount',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getTaxRateParsing(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/tax_rate_parsing',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function getEmailSuccessOption(): bool
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/email_success',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getDiscount(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/discount',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getInvoice(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/invoice',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getActive(): bool
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/active',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/title',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getMode($storeId = null)
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/banchile_mode',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return string|null
     */
    public function getUri($storeId = null): ?string
    {
        $uri = null;
        $endpoints = $this->getEndpointsTo();

        if (!empty($endpoints[$this->getMode($storeId)])) {
            return $endpoints[$this->getMode($storeId)];
        }

        return $uri;
    }

    /**
     * @return string|null
     */
    public function getTranKey($storeId = null): ?string
    {
        $mode = $this->getMode($storeId);

        switch ($mode) {
            case Mode::DEVELOPMENT:
                $tranKey = $this->scopeConfig->getValue(
                    'payment/banchile/banchile_development_tk',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
                break;
            case Mode::TEST:
                $tranKey = $this->scopeConfig->getValue(
                    'payment/banchile/banchile_test_tk',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
                break;
            default:
                $tranKey = $this->scopeConfig->getValue(
                    'payment/banchile/banchile_production_tk',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
                break;
        }

        return $tranKey;
    }

    public function getLogin($storeId = null): ?string
    {
        $mode = $this->getMode($storeId);

        switch ($mode) {
            case Mode::DEVELOPMENT:
                $login = $this->scopeConfig->getValue(
                    'payment/banchile/banchile_development_lg',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
                break;
            case Mode::TEST:
                $login = $this->scopeConfig->getValue(
                    'payment/banchile/banchile_test_lg',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
                break;
            default:
                $login = $this->scopeConfig->getValue(
                    'payment/banchile/banchile_production_lg',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
                break;
        }

        return $login;
    }

    public function getCountryCode(): ?string
    {
        return $this->scopeConfig->getValue(
            'general/country/default',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string|null
     */
    public function getImageUrl(): ?string
    {
        return $this->scopeConfig->getValue(
            'payment/banchile/payment_button_image',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string[]
     */
    public function getEndpointsTo(): array
    {
        return ChileCountryConfig::getEndpoints();
    }

    public function cleanText($text)
    {
        return preg_replace('/[(),.#!\-]/', '', $text);
    }

    public function getExpirationTimeMinutes()
    {
        $minutes = $this->getExpirationTime();

        return !is_numeric($minutes) || $minutes < self::EXPIRATION_TIME_MINUTES_MIN
            ? self::EXPIRATION_TIME_MINUTES_DEFAULT
            : $minutes;
    }

    /**
     * @return InfoFactory
     */
    public function getInfoModel(): InfoFactory
    {
        return $this->infoFactory;
    }

    public function getHeaders(): array
    {
        $objectManager = ObjectManager::getInstance();
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        $version = $productMetadata->getVersion();

        $domain = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

        return [
            'User-Agent' => "magento2-module-payments/{$this->version} (origin:$domain; vr:" . $version . ')',
            'X-Source-Platform' => 'magento',
        ];
    }
}

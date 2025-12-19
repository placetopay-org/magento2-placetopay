<?php

namespace Getnet\Payments\Model\Adminhtml\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Expiration
{
    private const EXPIRATION_TIME_MINUTES_DEFAULT = 8640;
    private const EXPIRATION_TIME_MINUTES_CL = 30;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        $format = '%d %s';
        $minutes = 10;

        $expirationTimeLimit = $this->getExpirationTimeLimit();

        while ($minutes <= $expirationTimeLimit) {
            if ($minutes < 60) {
                $options[$minutes] = sprintf($format, $minutes, __('Minutes'));
                $minutes += 10;
            } elseif ($minutes >= 60 && $minutes < 1440) {
                $options[$minutes] = sprintf($format, $minutes / 60, __('Hour(s)'));
                $minutes += 60;
            } elseif ($minutes >= 1440 && $minutes < 10080) {
                $options[$minutes] = sprintf($format, $minutes / 1440, __('Day(s)'));
                $minutes += 1440;
            }
        }

        return $options;
    }

    /**
     * @return int
     */
    private function getExpirationTimeLimit(): int
    {
        $countryCode = $this->scopeConfig->getValue(
            'general/country/default',
            ScopeInterface::SCOPE_STORE
        );

        return $countryCode === 'CL' ? self::EXPIRATION_TIME_MINUTES_CL : self::EXPIRATION_TIME_MINUTES_DEFAULT;
    }
}

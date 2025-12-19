<?php

namespace Getnet\Payments\Model\Adminhtml\Source;

use Getnet\Payments\Countries\CountryConfigInterface;
use Getnet\Payments\Constants\Country;
use Getnet\Payments\Helper\Data;

class Client
{
    /**
     * @var Data
     */
    protected $dataHelper;

    public function __construct(Data $dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

    public function toOptionArray(): array
    {
        $countryCode = $this->dataHelper->getCountryCode();

        /** @var CountryConfigInterface $config */
        foreach (Country::COUNTRIES_CLIENT as $config) {
            if (!$config::resolve($countryCode)) {
                continue;
            }
            return $config::getClient();
        }
        return [];
    }
}

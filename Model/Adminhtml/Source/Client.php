<?php

namespace PlacetoPay\Payments\Model\Adminhtml\Source;

use PlacetoPay\Payments\CountryConfig;
use PlacetoPay\Payments\Helper\Data;

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
        return CountryConfig::getClient();
    }
}

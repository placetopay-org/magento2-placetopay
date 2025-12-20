<?php

namespace Getnet\Payments\Model\Adminhtml\Source;

use Getnet\Payments\Helper\Data;
use Getnet\Payments\Helper\ParseData;
use Getnet\Payments\Constants\Client as Clients;

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
        return [
            [
                'value' => ParseData::unmaskString(Clients::GNT),
                'label' => __(ParseData::unmaskString(Clients::GNT)),
            ],
        ];
    }
}

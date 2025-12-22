<?php

namespace Banchile\Payments\Model\Adminhtml\Source;

use Banchile\Payments\Helper\Data;

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
                'value' => 'Banchile pagos',
                'label' => 'Banchile pagos',
            ],
        ];
    }
}

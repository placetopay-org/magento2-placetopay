<?php

namespace PlacetoPay\Payments\Model\Adminhtml\Source;

class Discount
{
    public const UY_IVA_REFUND = '17934';

    public const UY_IMESI_REFUND = '18083';

    public const UY_FINANCIAL_INCLUSION = '19210';

    public const UY_AFAM_REFUND = '18910';

    public const UY_TAX_REFUND = '18999';

    public const UY_NONE = '0';

    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::UY_IVA_REFUND,
                'label' => __('17934'),
            ],
            [
                'value' => self::UY_IMESI_REFUND,
                'label' => __('18083'),
            ],
            [
                'value' => self::UY_FINANCIAL_INCLUSION,
                'label' => __('19210'),
            ],
            [
                'value' => self::UY_AFAM_REFUND,
                'label' => __('18910'),
            ],
            [
                'value' => self::UY_TAX_REFUND,
                'label' => __('18999'),
            ],
            [
                'value' => self::UY_NONE,
                'label' => __('None'),
            ],
        ];
    }
}

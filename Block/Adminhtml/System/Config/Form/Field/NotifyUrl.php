<?php

namespace PlacetoPay\Payments\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Config\Block\System\Config\Form\Field as BaseField;

/**
 * Class NotifyUrl.
 */
class NotifyUrl extends BaseField
{
    /**
     * @param AbstractElement $element
     *
     * @return string|string[]|null
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $url = $this->getUrl('placetopay/process/notify', array('_forced_secure' => true));
        $url = preg_replace('/\/key[\w\/]+$/', '', $url);

        return $url;
    }
}
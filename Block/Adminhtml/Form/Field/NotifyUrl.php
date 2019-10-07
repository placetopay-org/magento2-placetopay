<?php


namespace PlacetoPay\Payments\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field as BaseField;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\UrlInterface;

class NotifyUrl extends BaseField
{
    protected function _renderValue(AbstractElement $element)
    {
        $stores = $this->_storeManager->getStores();
        $valueReturn = '';
        $urlArray = [];

        foreach ($stores as $store) {
            $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
            if ($baseUrl) {
                $value      = $baseUrl . 'placetopay/payment/notify';
                $urlArray[] = "<div>".$this->escapeHtml($value)."</div>";
            }
        }

        $urlArray = array_unique($urlArray);
        foreach ($urlArray as $uniqueUrl) {
            $valueReturn .= "<div>".$uniqueUrl."</div>";
        }

        return '<td class="value">' . $valueReturn . '</td>';
    }

    protected function _renderInheritCheckbox(AbstractElement $element)
    {
        return '<td class="use-default"></td>';
    }
}
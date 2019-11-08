<?php

namespace PlacetoPay\Payments\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field as BaseField;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\UrlInterface;

/**
 * Class NotifyUrl.
 */
class NotifyUrl extends BaseField
{
    /**
     * @param AbstractElement $element
     *
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $stores = $this->_storeManager->getStores();
        $valueReturn = '';
        $urlArray = [];

        foreach ($stores as $store) {
            $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);

            if ($baseUrl) {
                $value      = $baseUrl . 'rest/V1/placetopay/payment/notify';
                $urlArray[] = "<div>" . $this->escapeHtml($value) . "</div>";
            }
        }

        $urlArray = array_unique($urlArray);

        foreach ($urlArray as $uniqueUrl) {
            $valueReturn .= "<div>" . $uniqueUrl . "</div>";
        }

        return $valueReturn;
    }
}

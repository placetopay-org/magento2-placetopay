<?php

namespace PlacetoPay\Payments\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\View\Asset\Repository;

/**
 * Class CustomConfigProvider.
 */
class CustomConfigProvider implements ConfigProviderInterface
{
    /**
     * @var Repository $_assetRepo
     */
    protected $_assetRepo;

    /**
     * @var string $methodCode
     */
    protected $methodCode = PaymentMethod::CODE;

    /**
     * CustomConfigProvider constructor.
     *
     * @param Repository $assetRepo
     */
    public function __construct(Repository $assetRepo)
    {
        $this->_assetRepo = $assetRepo;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                $this->methodCode => [
                    'logoUrl' => $this->_assetRepo->getUrl("PlacetoPay_Payments::images/logo.png")
                ]
            ]
        ];
    }
}

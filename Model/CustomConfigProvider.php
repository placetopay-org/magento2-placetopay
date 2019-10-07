<?php


namespace PlacetoPay\Payments\Model;


class CustomConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    /**
     * @var \Magento\Framework\View\Asset\Repository
     */
    protected $_assetRepo;

    /**
     * @var string
     */
    protected $methodCode = \PlacetoPay\Payments\Model\PlaceToPay::CODE;

    public function __construct(
        \Magento\Framework\View\Asset\Repository $assetRepo
    )
    {
        $this->_assetRepo = $assetRepo;
    }

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
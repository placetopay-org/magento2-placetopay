<?php

namespace PlacetoPay\Payments\Plugin\Sales\Order\Email\Container;

/**
 * Class OrderIdentityPlugin.
 */
class OrderIdentityPlugin
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @var \PlacetoPay\Payments\Helper\Data
     */
    protected $config;

    /**
     * OrderIdentityPlugin constructor.
     *
     * @param \Magento\Checkout\Model\Session $session
     * @param \PlacetoPay\Payments\Helper\Data $config
     */
    public function __construct(
        \Magento\Checkout\Model\Session $session,
        \PlacetoPay\Payments\Helper\Data $config
    ) {
        $this->session = $session;
        $this->config = $config;
    }

    /**
     * @param \Magento\Sales\Model\Order\Email\Container\OrderIdentity $subject
     * @param callable $proceed
     * @return bool
     */
    public function aroundIsEnabled(\Magento\Sales\Model\Order\Email\Container\OrderIdentity $subject, callable $proceed)
    {
        $canProceed = $proceed();
        $emailSuccess = $this->config->getEmailSuccessOption();
        $canSendEmail = $this->session->getLastRealOrder()->getCanSendNewEmailFlag();

        if ($emailSuccess) {
            if (isset($canSendEmail) && $canSendEmail) {
                if ($canProceed) {
                    $canProceed = false;
                } else {
                    $canProceed = true;
                }
            }
        }

        return $canProceed;
    }
}

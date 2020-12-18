<?php

namespace PlacetoPay\Payments\Api;

use Dnetix\Redirection\Exceptions\PlacetoPayException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\OrderFactory;
use PlacetoPay\Payments\Helper\PlacetoPayLogger;
use PlacetoPay\Payments\Model\PaymentMethod;

/**
 * Class Service.
 */
class Service implements ServiceInterface
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var PlacetoPayLogger
     */
    protected $_logger;

    /**
     * @var EventManager
     */
    protected $manager;

    /**
     * @var Json
     */
    protected $_json;

    /**
     * Service constructor.
     *
     * @param RequestInterface $request
     * @param OrderFactory $orderFactory
     * @param PlacetoPayLogger $logger
     * @param EventManager $manager
     * @param Json $json
     */
    public function __construct(
        RequestInterface $request,
        OrderFactory $orderFactory,
        PlacetoPayLogger $logger,
        EventManager $manager,
        Json $json
    ) {
        $this->request = $request;
        $this->orderFactory = $orderFactory;
        $this->_logger = $logger;
        $this->manager = $manager;
        $this->_json = $json;
    }

    /**
     * Endpoint for the notification of PlacetoPay.
     *
     * @return mixed
     * @throws LocalizedException
     * @throws PlacetoPayException
     */
    public function notify()
    {
        $data = $this->_json->unserialize($this->request->getContent());

        if ($data && ! empty($data['reference']) && ! empty($data['signature']) && ! empty($data['requestId'])) {
            $order = $this->orderFactory->create()->loadByIncrementId($data['reference']);

            if (! $order->getId()) {
                $this->_logger->log($this, 'error', __FUNCTION__ . ' message', [
                    'Non existent order for reference #' . $data['reference'],
                ]);

                return ['success' => false];
            }

            /** @var PaymentMethod $placetopay */
            $placetopay = $order->getPayment()->getMethodInstance();
            $notification = $placetopay->gateway()->readNotification($data);

            if ($notification->isValidNotification()) {
                $information = $placetopay->gateway()->query($notification->requestId());
                $placetopay->settleOrderStatus($information, $order);

                if ($information->isApproved()) {
                    $this->manager->dispatch('placetopay_api_success', [
                        'order_ids' => [$order->getRealOrderId()],
                    ]);
                }

                return ['success' => true];
            } else {
                $this->_logger->log($this, 'error', __FUNCTION__ . ' message', [
                    'Invalid notification for order #' . $order->getId(),
                ]);

                return $notification->makeSignature();
            }
        } else {
            $this->_logger->log($this, 'error', __FUNCTION__ . ' message', [
                'Wrong or empty notification data for reference #' . $data['reference']
            ]);

            return ['success' => false];
        }
    }
}

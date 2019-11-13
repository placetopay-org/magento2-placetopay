<?php

namespace PlacetoPay\Payments\Api;

use Dnetix\Redirection\Exceptions\PlacetoPayException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use PlacetoPay\Payments\Api\ServiceInterface as ApiInterface;
use PlacetoPay\Payments\Model\PaymentMethod;
use PlacetoPay\Payments\Logger\Logger as LoggerInterface;

/**
 * Class Service.
 */
class Service implements ApiInterface
{
    /**
     * @var RequestInterface $request
     */
    protected $request;

    /**
     * @var OrderFactory $orderFactory
     */
    protected $orderFactory;

    /**
     * @var LoggerInterface $logger
     */
    protected $logger;

    /**
     * Service constructor.
     *
     * @param RequestInterface $request
     * @param OrderFactory     $orderFactory
     * @param LoggerInterface  $logger
     */
    public function __construct(
        RequestInterface $request,
        OrderFactory $orderFactory,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
    }

    /**
     * Endpoint for the notification of PlacetoPay.
     *
     * @return array|mixed|string
     * @throws LocalizedException
     * @throws PlacetoPayException
     */
    public function notify()
    {
        $data = json_decode($this->request->getContent(), true);

        if ($data && ! empty($data['reference']) && ! empty($data['signature']) && ! empty($data['requestId'])) {
            /** @var Order $order */
            $order = $this->orderFactory->create()->loadByIncrementId($data['reference']);

            if (! $order->getId()) {
                $this->logger->debug('Non existent order for reference #' . $data['reference']);

                throw new LocalizedException(__('Order not found.'));
            }

            /** @var PaymentMethod $placetopay */
            $placetopay = $order->getPayment()->getMethodInstance();
            $notification = $placetopay->gateway()->readNotification($data);

            if ($notification->isValidNotification()) {
                $information = $placetopay->gateway()->query($notification->requestId());
                $placetopay->settleOrderStatus($information, $order);

                return ['success' => true];
            } else {
                $this->logger->debug('Invalid notification for order #' . $order->getId());

                return $notification->makeSignature();
            }
        } else {
            $this->logger->debug('Wrong or empty notification data for reference #' . $data['reference']);

            throw new LocalizedException(__('Wrong or empty notification data.'));
        }
    }
}

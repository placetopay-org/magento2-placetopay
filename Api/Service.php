<?php

namespace PlacetoPay\Payments\Api;

use Dnetix\Redirection\Exceptions\PlacetoPayException;
use Exception;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use PlacetoPay\Payments\Helper\PlacetoPayLogger;
use PlacetoPay\Payments\Model\PaymentMethod;

/**
 * Class Service.
 */
class Service implements ServiceInterface
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var PlacetoPayLogger
     */
    protected $logger;

    /**
     * @var EventManager
     */
    protected $manager;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * Service constructor.
     *
     * @param Request $request
     * @param PlacetoPayLogger $logger
     * @param EventManager $manager
     * @param Json $json
     * @param OrderRepository $orderRepository
     */
    public function __construct(
        Request $request,
        PlacetoPayLogger $logger,
        EventManager $manager,
        Json $json,
        OrderRepository $orderRepository
    ) {
        $this->logger = $logger;
        $this->manager = $manager;
        $this->json = $json;
        $this->orderRepository = $orderRepository;
        $this->request = $request;
    }

    /**
     * Endpoint for the notification of PlacetoPay.
     *
     * @return array
     */
    public function notify(): array
    {
        $data = $this->getRequestData($this->request->getContent());

        try {
            if (!$this->isValidRequest($data)) {
                throw new PlacetoPayException('Wrong or empty notification data.');
            }

            /** @var Order $order */
            $order = $this->getOrderById($data['reference']);

            /** @var Order\Payment $payment */
            $payment = $order->getPayment();

            /** @var PaymentMethod $placetopay */
            $placetopay = $payment->getMethodInstance();

            $notification = $placetopay->gateway()->readNotification($data);

            if ($notification->isValidNotification()) {
                $information = $placetopay->gateway()->query($notification->requestId());
                $placetopay->settleOrderStatus($information, $order);

                if ($information->isApproved()) {
                    $this->manager->dispatch('placetopay_api_success', [
                        'order_ids' => [$order->getRealOrderId()],
                    ]);
                }

                $response = [
                    'message' => sprintf('Transaction with status: %s', $information->status()->status()),
                ];
            } else {
                $response = [
                    'signature' => $notification->makeSignature(),
                    'message' => 'Replace this signature with the one on the request body for testing.'
                ];
            }

            return [$response];
        } catch (Exception $ex) {
            $this->logger->log($this, 'error', __FUNCTION__ . ' message', [$ex->getMessage()]);

            return [$ex->getMessage()];
        }
    }

    /**
     * @param int $id
     * @return OrderInterface
     * @throws InputException
     * @throws NoSuchEntityException
     */
    private function getOrderById(int $id): OrderInterface
    {
        return $this->orderRepository->get($id);
    }

    /**
     * @param array $data
     * @return bool
     */
    private function isValidRequest(array $data): bool
    {
        return $data && !empty($data['reference']) && !empty($data['signature']) && !empty($data['requestId']);
    }

    /**
     * @param string $data
     * @return array
     */
    private function getRequestData(string $data): array
    {
        return $this->json->unserialize($data);
    }
}

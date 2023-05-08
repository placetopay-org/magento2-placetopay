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
use Magento\Sales\Model\ResourceModel\Order\Payment\CollectionFactory;
use PlacetoPay\Payments\Helper\PlacetoPayLogger;
use PlacetoPay\Payments\Model\PaymentMethod;

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
     * @var CollectionFactory
     */
    protected $paymentCollectionFactory;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    public function __construct(
        Request $request,
        PlacetoPayLogger $logger,
        EventManager $manager,
        Json $json,
        OrderRepository $orderRepository,
        CollectionFactory $paymentCollectionFactory
    ) {
        $this->logger = $logger;
        $this->manager = $manager;
        $this->json = $json;
        $this->orderRepository = $orderRepository;
        $this->request = $request;
        $this->paymentCollectionFactory = $paymentCollectionFactory;
    }

    public function notify(): array
    {
        $data = $this->getRequestData($this->request->getContent());

        try {
            if (!$this->isValidRequest($data)) {
                throw new PlacetoPayException('Wrong or empty notification data.');
            }

            $orderId = $this->paymentCollectionFactory->create()
                        ->addFieldToFilter('last_trans_id', $data['requestId'])
                        ->getFirstItem()
                        ->getParentId();

            if (!$orderId) {
                return ['There is no order associated to the request id: ' . $data['requestId']];
            }

            /** @var Order $order */
            $order = $this->getOrderById($orderId);

            /** @var Order\Payment $payment */
            $payment = $order->getPayment();

            /** @var PaymentMethod $placetopay */
            $placetopay = $payment->getMethodInstance();

            $notification = $placetopay->gateway()->readNotification($data);

            if ($notification->isValidNotification()) {
                $information = $placetopay->gateway()->query($notification->requestId());
                $placetopay->setStatus($information, $order);

                if ($information->isApproved()) {
                    $this->manager->dispatch('placetopay_api_success', [
                        'order_ids' => [$order->getRealOrderId()],
                    ]);
                    $response = [
                        'message' => sprintf('Transaction with status: %s', $information->status()->status()),
                    ];
                } else {
                    if ($information->lastApprovedTransaction() && $information->lastApprovedTransaction()->refunded()) {
                        $response = [
                            'message' => 'The payment refunded',
                        ];
                    } else {
                        $response = [
                            'message' => sprintf('Transaction with status: %s', $information->status()->status()),
                        ];
                    }
                }
            } else {
                $response = [
                    'signature' => $notification->makeSignature(),
                    'message' => 'Replace this signature with the one on the request body for testing.'
                ];
            }

            return $response;
        } catch (Exception $ex) {
            $this->logger->log($this, 'error', __FUNCTION__ . ' message', [$ex->getMessage()]);

            return [$ex->getMessage()];
        }
    }

    /**
     * @throws InputException
     * @throws NoSuchEntityException
     */
    private function getOrderById(int $id): OrderInterface
    {
        return $this->orderRepository->get($id);
    }

    private function isValidRequest(array $data): bool
    {
        return $data && !empty($data['reference']) && !empty($data['signature']) && !empty($data['requestId']);
    }

    private function getRequestData(string $data): array
    {
        return $this->json->unserialize($data);
    }
}

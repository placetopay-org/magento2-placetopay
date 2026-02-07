<?php

namespace PlacetoPay\Payments\Api;

use Dnetix\Redirection\Exceptions\PlacetoPayException;
use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use PlacetoPay\Payments\CountryConfig;
use PlacetoPay\Payments\Helper\Data as ConfigHelper;
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
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    public function __construct(
        Request $request,
        PlacetoPayLogger $logger,
        EventManager $manager,
        Json $json,
        OrderRepository $orderRepository,
        ResourceConnection $resource,
        ConfigHelper $configHelper
    ) {
        $this->logger = $logger;
        $this->manager = $manager;
        $this->json = $json;
        $this->orderRepository = $orderRepository;
        $this->request = $request;
        $this->resource = $resource;
        $this->configHelper = $configHelper;
    }

    public function notify(): array
    {
        $data = $this->getRequestData($this->request->getContent());

        try {
            if (!$this->isValidRequest($data)) {
                throw new PlacetoPayException('Wrong or empty notification data.');
            }

            $tableName = $this->resource->getTableName('sales_order_payment');

            $orderId = $this->resource->getConnection()->query(
                "SELECT parent_id FROM $tableName WHERE method = ? AND last_trans_id = ?",
                [CountryConfig::CLIENT_ID, (string)$data['requestId']]
            )->fetchColumn();

            if (!$orderId) {
                return [
                    'message' => 'There is no order associated to the request id: ' . $data['requestId'],
                ];
            }

            /** @var Order $order */
            $order = $this->getOrderById($orderId);

            /** @var Order\Payment $payment */
            $payment = $order->getPayment();

            /** @var PaymentMethod $placetopay */
            $placetopay = $payment->getMethodInstance();

            $tranKey = $this->configHelper->getTranKey($order->getStoreId());

            $expectedSignature = sprintf(
                '%s%s%s%s',
                $data['requestId'],
                $data['status']['status'],
                $data['status']['date'],
                $tranKey
            );

            $signature = $data['signature'];

            if (strpos($signature, ':') === false) {
                $signature = 'sha1:' . $signature;
            }

            [$algo, $receivedSignature] = explode(':', $signature, 2);

            $this->logger->log($this, 'debug', 'signature algorithm: ' . $algo, []);

            if (hash($algo, $expectedSignature) !== $receivedSignature) {
                if ($placetopay->inDebugMode()) {
                    return [
                        'message' => 'Replace this signature with the one on the request body for testing.',
                        'signature' => hash($algo, $expectedSignature),
                    ];
                }

                return [
                    'message' => 'Invalid notification for order #' . $order->getId(),
                ];
            }

            $information = $placetopay->gateway()->query($data['requestId']);

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
        } catch (Exception $ex) {
            $this->logger->log($this, 'error', __FUNCTION__ . ' message', [$ex->getMessage()]);

            $response = [
                'message' => $ex->getMessage(),
            ];
        }

        return $response;
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
        return $data
            && !empty($data['signature'])
            && !empty($data['requestId'])
            && !empty($data['status']['status'])
            && !empty($data['status']['date']);
    }

    private function getRequestData(string $data): array
    {
        $request = [];

        try {
            $request = $this->json->unserialize($data);
        } catch (Exception $exception) {
            $this->logger->log($this, 'error', __FUNCTION__ . ' message', [$exception->getMessage()]);
        }

        return $request;
    }
}

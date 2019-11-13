<?php

namespace PlacetoPay\Payments\Controller\Payment;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PlacetoPay\Payments\Logger\Logger as LoggerInterface;
use PlacetoPay\Payments\Model\PaymentMethod;

/**
 * Class Data.
 */
class Data extends Action
{
    /**
     * @var Session $checkoutSession
     */
    protected $checkoutSession;

    /**
     * @var LoggerInterface $logger
     */
    protected $logger;

    /**
     * @var ManagerInterface $messageManager
     */
    protected $messageManager;

    /**
     * @var JsonFactory $jsonFactory
     */
    protected $jsonFactory;

    /**
     * @var ResultFactory $resultRedirect
     */
    protected $resultRedirect;

    /**
     * @var OrderRepositoryInterface $orderRepository
     */
    protected $orderRepository;

    /**
     * Data constructor.
     *
     * @param Context                  $context
     * @param Session                  $checkoutSession
     * @param LoggerInterface          $logger
     * @param ManagerInterface         $messageManager
     * @param JsonFactory              $jsonFactory
     * @param ResultFactory            $result
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        LoggerInterface $logger,
        ManagerInterface $messageManager,
        JsonFactory $jsonFactory,
        ResultFactory $result,
        OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);

        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->messageManager = $messageManager;
        $this->jsonFactory = $jsonFactory;
        $this->resultRedirect = $result;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Get singleton of Checkout Session Model
     *
     * @return Session
     */
    protected function _getCheckout()
    {
        return $this->checkoutSession;
    }

    /**
     * When the user clicks on the proceed to payment button.
     *
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $session = $this->_getCheckout();

        try {
            /**
             * @var Order $order
             */
            $order = $session->getLastRealOrder();

            if (! $order->getId()) {
                $this->logger->error('Non existent order for reference #' . $order->getId());

                throw new LocalizedException(__('No order for processing was found.'));
            }

            /**
             * @var PaymentMethod $placetopay
             */
            $placetopay = $order->getPayment()->getMethodInstance();
            $url = $placetopay->getCheckoutRedirect($order);

            $order->setStatus('pending');
            $order->setState(Order::STATE_PENDING_PAYMENT);
            $this->orderRepository->save($order);

            $result = $this->jsonFactory->create();

            return $result->setData([
                'url' => $url,
            ]);
        } catch (Exception $exception) {
            $session->restoreQuote();

            $this->logger->debug(
                'RedirectAction ' .
                $exception->getMessage() . ' on ' .
                $exception->getFile() . ' line ' .
                $exception->getLine()
            );

            $this->messageManager->addErrorMessage($exception->getMessage());

            $resultRedirect = $this->resultRedirect->create(ResultFactory::TYPE_REDIRECT);

            $resultRedirect->setUrl($this->_redirect->getRefererUrl());

            return $resultRedirect;
        }
    }
}

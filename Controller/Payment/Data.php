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
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use PlacetoPay\Payments\Model\PaymentMethod;
use Psr\Log\LoggerInterface;

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
     * @var OrderFactory $orderFactory
     */
    protected $orderFactory;

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
     * Data constructor.
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderFactory $orderFactory
     * @param LoggerInterface $logger
     * @param ManagerInterface $messageManager
     * @param JsonFactory $jsonFactory
     * @param ResultFactory $result
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        LoggerInterface $logger,
        ManagerInterface $messageManager,
        JsonFactory $jsonFactory,
        ResultFactory $result
    ) {
        parent::__construct($context);

        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
        $this->messageManager = $messageManager;
        $this->jsonFactory = $jsonFactory;
        $this->resultRedirect = $result;
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
     * When the user clicks on the proceed to payment button
     *
     * @return ResponseInterface|Json|ResultInterface
     * @throws Exception
     */
    public function execute()
    {
        $session = $this->_getCheckout();

        try {
            /**
             * @var Order $order
             */
            $order = $this->orderFactory->create();

            $order->loadByIncrementId($session->getLastRealOrderId());

            if (! $order->getId()) {
                throw new LocalizedException(__('No order for processing was found.'));
            }

            /**
             * @var PaymentMethod $placetopay
             */
            $placetopay = $order->getPayment()->getMethodInstance();
            $url = $placetopay->getCheckoutRedirect($order);

            $session->setPlacetoPayQuoteId($session->getQuoteId());
            $session->setPlacetoPayRealOrderId($session->getLastRealOrderId());
            $session->getQuote()->setIsActive(false)->save();
            $session->clearQuote();
            $session->clearHelperData();
            $session->clearStorage();
            $order->setStatus('pending');
            $order->save();

            $result = $this->jsonFactory->create();

            return $result->setData([
                'url' => $url,
            ]);
        } catch (Exception $exception) {
            $this->logger->debug(
                'P2P_LOG: RedirectAction ' .
                $exception->getMessage() . ' ON ' .
                $exception->getFile() . ' LINE ' .
                $exception->getLine()
            );

            $this->messageManager->addErrorMessage($exception->getMessage());

            $resultRedirect = $this->resultRedirect->create(ResultFactory::TYPE_REDIRECT);

            $resultRedirect->setUrl($this->_redirect->getRefererUrl());

            return $resultRedirect;
        }
    }
}

<?php

namespace PlacetoPay\Payments\Controller\Payment;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\OrderFactory;
use PlacetoPay\Payments\Helper\Data as HelperData;
use PlacetoPay\Payments\Logger\Logger;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;

/**
 * Class Data.
 */
class Data extends Action
{
    protected $_helperData;

    protected $_placeToPayLogger;

    protected $_checkoutSession;

    protected $_orderFactory;

    protected $_resultJsonFactory;

    protected $_url;

    protected $_transactionBuilder;

    protected $_paymentHelper;

    /**
     * Data constructor.
     *
     * @param Context          $context
     * @param Session          $checkoutSession
     * @param OrderFactory     $orderFactory
     * @param HelperData       $helperData
     * @param Logger           $placeToPayLogger
     * @param JsonFactory      $resultJsonFactory
     * @param BuilderInterface $transactionBuilder
     * @param PaymentHelper    $paymentHelper
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        HelperData $helperData,
        Logger $placeToPayLogger,
        JsonFactory $resultJsonFactory,
        BuilderInterface $transactionBuilder,
        PaymentHelper $paymentHelper
    ) {
        parent::__construct($context);

        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->_helperData = $helperData;
        $this->_placeToPayLogger = $placeToPayLogger;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_url = $context->getUrl();
        $this->_transactionBuilder = $transactionBuilder;
        $this->_paymentHelper = $paymentHelper;
    }

    protected function _getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     * @throws Exception
     */
    public function execute()
    {
        try {
            $order = $this->_getCheckoutSession()->getLastRealOrder();
            $method = $order->getPayment()->getMethod();
            $methodInstance = $this->_paymentHelper->getMethodInstance($method);

            $placetopay =  $methodInstance->gateway();
            $request = $methodInstance->getRedirectRequestDataFromOrder($order);

            $response = $placetopay->request($request);
            if ($response->isSuccessful()) {
                $payment = $order->getPayment();
                $payment->setTransactionId($response->requestId)
                    ->setIsTransactionClosed(0);

                $payment->setParentTransactionId($order->getId());
                $payment->setIsTransactionPending(true);
                $transaction = $this->_transactionBuilder->setPayment($payment)
                    ->setOrder($order)
                    ->setTransactionId($payment->getTransactionId())
                    ->build(Transaction::TYPE_ORDER);

                $payment->addTransactionCommentsToOrder($transaction, __('pending'));

                $statuses = $methodInstance->getOrderStates();
                $status = $statuses["pending"];
                $this->_helperData->log($status);
                $state = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
                $order->setState($state)->setStatus($status);
                $payment->setSkipOrderProcessing(true);
                $order->save();

                $result = $this->_resultJsonFactory->create();
                return $result->setData([
                    'url' => $response->processUrl()
                ]);
            } else {
                throw new Exception($response->status()->message());
            }
        } catch (Exception $exception) {
            $this->_helperData->log($exception->getMessage());
            throw new Exception($exception->getMessage());
        }
    }
}

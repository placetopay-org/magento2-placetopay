<?php

namespace PlacetoPay\Payments\Controller\Payment;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use PlacetoPay\Payments\Logger\Logger;

/**
 * Class Notify.
 */
class Notify extends Action
{
    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var Session
     */
    protected $_checkoutSession;

    /**
     * @var \Saulmoralespa\PlaceToPay\Logger\Logger
     */
    protected $_placeToPayLogger;

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var TransactionRepositoryInterface
     */
    protected $_transactionRepository;

    /**
     * @var BuilderInterface
     */
    protected $_transactionBuilder;

    /**
     * @var \Saulmoralespa\PlaceToPay\Helper\Data
     */
    protected $_helperData;

    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        Session $checkoutSession,
        \PlacetoPay\Payments\Helper\Data $helperData,
        Logger $placeToPayLogger,
        PaymentHelper $paymentHelper,
        TransactionRepositoryInterface $transactionRepository,
        BuilderInterface $transactionBuilder
    ) {
        parent::__construct($context);

        $this->_scopeConfig = $scopeConfig;
        $this->_checkoutSession = $checkoutSession;
        $this->_paymentHelper = $paymentHelper;
        $this->_transactionRepository = $transactionRepository;
        $this->_transactionBuilder = $transactionBuilder;
        $this->_placeToPayLogger = $placeToPayLogger;
        $this->_helperData = $helperData;
    }

    public function execute()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $request = $this->getRequest()->getContent();
        //$params = $request->getParams();

        if (empty($params)) {
            exit;
        }

        /*$request = $this->getRequest();
        $params = $request->getParams();

        if (empty($params)) {
            exit;
        }

        if (!$request->getParam('reference') ||
            !$request->getParam('requestId') ||
            !$request->getParam('signature')) {
            exit;
        }

        $reference = $request->getParam('reference');
        $reference = explode('_', $reference);
        $order_id = $reference[0];

        $objectManager = ObjectManager::getInstance();
        $order_model = $objectManager->get('Magento\Sales\Model\Order');
        $order = $order_model->load($order_id);
        $method = $order->getPayment()->getMethod();
        $methodInstance = $this->_paymentHelper->getMethodInstance($method);

        $payment = $order->getPayment();

        $statuses = $methodInstance->getOrderStates();

        $transaction = $this->_transactionRepository->getByTransactionType(
            Transaction::TYPE_ORDER,
            $payment->getId()
        );

        try {
            $placeToPay = $methodInstance->placeToPay();
            $notification = $placeToPay->readNotification();

            if ($notification->isValidNotification()) {
                // In order to use the functions please refer to the Notification class
                if ($notification->isApproved()) {
                    $payment->setIsTransactionPending(false);
                    $payment->setIsTransactionApproved(true);
                    $status = $statuses["approved"];

                    $order->setState(Order::STATE_PROCESSING)->setStatus($status);
                    $payment->setSkipOrderProcessing(true);

                    $invoice = $objectManager->create('Magento\Sales\Model\Service\InvoiceService')->prepareInvoice($order);
                    $invoice = $invoice->setTransactionId($payment->getTransactionId())
                        ->addComment("Invoice created.")
                        ->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                    $invoice->register()
                        ->pay();
                    $invoice->save();

                    // Save the invoice to the order
                    $transactionInvoice = $this->_objectManager->create('Magento\Framework\DB\Transaction')
                        ->addObject($invoice)
                        ->addObject($invoice->getOrder());

                    $transactionInvoice->save();

                    $order->addStatusHistoryComment(
                        __('Invoice #%1.', $invoice->getId())
                    )
                        ->setIsCustomerNotified(true);

                    $message = __('Payment approved');

                    $payment->addTransactionCommentsToOrder($transaction, $message);

                    $transaction->save();

                    $order->save();
                } else {
                    $payment->setIsTransactionDenied(true);
                    $status = $statuses["rejected"];

                    $order->cancel();

                    $message = __('Payment declined');
                }
                $order->setState(Order::STATE_CANCELED)->setStatus($status);
                $payment->setSkipOrderProcessing(true);

                $transaction = $this->_transactionBuilder->setPayment($payment)
                    ->setOrder($order)
                    ->setTransactionId($payment->getTransactionId())
                    ->build(Transaction::TYPE_ORDER);

                $payment->addTransactionCommentsToOrder($transaction, $message);

                $transaction->save();

                $order->save();
            } else {
                $this->_helperData->log(__('invalid notification'));
            }
        } catch (Exception $e) {
            $this->_helperData->log($e->getMessage());
        }*/
    }
}

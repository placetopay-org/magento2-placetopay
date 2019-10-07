<?php


namespace PlacetoPay\Payments\Controller\Payment;


use Exception;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order\Payment\Transaction;

class Notify extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Checkout\Model\Session
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
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $_transactionRepository;

    /**
     * @var \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface
     */
    protected $_transactionBuilder;

    /**
     * @var \Saulmoralespa\PlaceToPay\Helper\Data
     */
    protected $_helperData;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        \PlacetoPay\Payments\Helper\Data $helperData,
        \PlacetoPay\Payments\Logger\Logger $placeToPayLogger,
        PaymentHelper $paymentHelper,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
    )
    {
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
        $request = $this->getRequest();
        $params = $request->getParams();

        if (empty($params))
            exit;

        if (!$request->getParam('reference') ||
            !$request->getParam('requestId') ||
            !$request->getParam('signature'))
            exit;

        $reference = $request->getParam('reference');
        $reference = explode('_', $reference);
        $order_id = $reference[0];

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order_model = $objectManager->get('Magento\Sales\Model\Order');
        $order = $order_model->load($order_id);
        $method = $order->getPayment()->getMethod();
        $methodInstance = $this->_paymentHelper->getMethodInstance($method);

        $payment = $order->getPayment();

        $statuses = $methodInstance->getOrderStates();

        $transaction = $this->_transactionRepository->getByTransactionType(
            Transaction::TYPE_ORDER,
            $payment->getId(),
            $payment->getOrder()->getId()
        );

        try{
            $placeToPay = $methodInstance->placeToPay();
            $notification = $placeToPay->readNotification();

            if ($notification->isValidNotification()) {
                // In order to use the functions please refer to the Notification class
                if ($notification->isApproved()) {
                    $payment->setIsTransactionPending(false);
                    $payment->setIsTransactionApproved(true);
                    $status = $statuses["approved"];

                    $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)->setStatus($status);
                    $payment->setSkipOrderProcessing(true);

                    $invoice = $objectManager->create('Magento\Sales\Model\Service\InvoiceService')->prepareInvoice($order);
                    $invoice = $invoice->setTransactionId($payment->getTransactionId())
                        ->addComment("Invoice created.")
                        ->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
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
                $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED)->setStatus($status);
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

        }catch (Exception $e) {
            $this->_helperData->log($e->getMessage());
        }
    }
}
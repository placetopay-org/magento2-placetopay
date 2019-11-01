<?php

namespace PlacetoPay\Payments\Controller\Payment;

use Exception;
use Magento\Framework\Controller\ResultFactory;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order\Payment\Transaction;

/**
 * Class Response.
 */
class Response extends \Magento\Framework\App\Action\Action
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

    protected $_pageFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        \PlacetoPay\Payments\Helper\Data $helperData,
        \PlacetoPay\Payments\Logger\Logger $placeToPayLogger,
        PaymentHelper $paymentHelper,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Framework\View\Result\PageFactory $pageFactory
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
        $this->_pageFactory = $pageFactory;
    }

    public function execute()
    {
        $request = $this->getRequest();
        $params = $request->getParams();

        if (empty($params))
            exit;

        if (!$request->getParam('reference'))
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
        $requestId = $payment->getLastTransId();

        $pendingOrder = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
        $failedOrder = \Magento\Sales\Model\Order::STATE_CANCELED;
        $aprovvedOrder =  \Magento\Sales\Model\Order::STATE_PROCESSING;
        $statuses = $methodInstance->getOrderStates();

        $pathRedirect = 'checkout/onepage/success';

        $transaction = $this->_transactionRepository->getByTransactionType(
            Transaction::TYPE_ORDER,
            $payment->getId(),
            $payment->getOrder()->getId()
        );

        try{
            $placeToPay = $methodInstance->gateway();
            $response = $placeToPay->query($requestId);

            if ($response->isSuccessful()) {
                $status = $response->status();

                if ($status->status() === 'PENDING'){
                    $pathRedirect = "placetopay/payment/pending";
                }elseif ($order->getState() == $failedOrder && ($status->status() === 'FAILED' || $status->status() === 'REJECTED')){
                    $pathRedirect = "checkout/onepage/failure";
                }elseif ($order->getState() == $pendingOrder && ($status->status() === 'FAILED' || $status->status() === 'REJECTED')){

                    $payment->setIsTransactionDenied(true);
                    $status = $statuses["rejected"];
                    $state = $failedOrder;

                    $order->setState($state)->setStatus($status);
                    $payment->setSkipOrderProcessing(true);

                    $message = __('Payment declined');

                    $payment->addTransactionCommentsToOrder($transaction, $message);

                    $transaction->save();
                    $order->cancel()->save();

                    $pathRedirect = "checkout/onepage/failure";
                }else if ($order->getState() == $pendingOrder && $status->status() === 'APPROVED') {

                    $payment->setIsTransactionPending(false);
                    $payment->setIsTransactionApproved(true);
                    $status = $statuses["approved"];
                    $state = $aprovvedOrder;

                    $order->setState($state)->setStatus($status);
                    $payment->setSkipOrderProcessing(true);

                    $invoice = $objectManager->create('Magento\Sales\Model\Service\InvoiceService')->prepareInvoice($order);
                    $invoice = $invoice->setTransactionId($payment->getTransactionId())
                        ->addComment("Invoice created")
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
                        __('Invoice #%1', $invoice->getId())
                    )
                        ->setIsCustomerNotified(true);

                    $message = __('Payment approved');

                    $payment->addTransactionCommentsToOrder($transaction, $message);

                    $transaction->save();

                    $order->save();

                }

                $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                $resultRedirect->setPath($pathRedirect);
                return $resultRedirect;

            } else {
                //echo $response->status()->message();
                return $this->_pageFactory->create();
            }
        }catch (Exception $exception){
            //echo $exception->getMessage();
            return $this->_pageFactory->create();
        }

    }
}

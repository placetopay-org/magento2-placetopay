<?php

namespace PlacetoPay\Payments\Controller\Payment;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\OrderFactory;
use PlacetoPay\Payments\Model\PaymentMethod;
use Psr\Log\LoggerInterface;

/**
 * Class Response.
 */
class Response extends Action
{
    /**
     * @var Session $checkoutSession
     */
    protected $checkoutSession;

    /**
     * @var OrderFactory
     */
    protected $salesOrderFactory;

    /**
     * @var ManagerInterface $messageManager
     */
    protected $messageManager;

    /**
     * @var LoggerInterface $logger
     */
    protected $logger;

    /**
     * @var Http $request
     */
    protected $request;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var QuoteFactory
     */
    protected $quoteQuoteFactory;

    /**
     * @var CustomerSession $customerSession
     */
    protected $customerSession;

    /**
     * @var EventManager $eventManager
     */
    protected $eventManager;

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
        Session $checkoutSession,
        OrderFactory $salesOrderFactory,
        ManagerInterface $messageManager,
        LoggerInterface $logger,
        Http $request,
        ScopeConfigInterface $scopeConfig,
        QuoteFactory $quoteQuoteFactory,
        CustomerSession $customerSession,
        EventManager $eventManager,
        \PlacetoPay\Payments\Helper\Data $helperData,
        \PlacetoPay\Payments\Logger\Logger $placeToPayLogger,
        PaymentHelper $paymentHelper,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Framework\View\Result\PageFactory $pageFactory
    ) {
        parent::__construct($context);

        $this->checkoutSession = $checkoutSession;
        $this->salesOrderFactory = $salesOrderFactory;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->quoteQuoteFactory = $quoteQuoteFactory;
        $this->customerSession = $customerSession;
        $this->eventManager = $eventManager;
        $this->_paymentHelper = $paymentHelper;
        $this->_transactionRepository = $transactionRepository;
        $this->_transactionBuilder = $transactionBuilder;
        $this->_placeToPayLogger = $placeToPayLogger;
        $this->_helperData = $helperData;
        $this->_pageFactory = $pageFactory;
    }

    protected function _getCheckout()
    {
        return $this->checkoutSession;
    }

    public function execute()
    {
        $session = $this->_getCheckout();
        $order = $session->getLastRealOrder();
        $pathRedirect = 'checkout/onepage/success';

        try {
            if ($order && $this->request->getParam('reference') == $order->getRealOrderId()) {
                $payment = $order->getPayment();

                /**
                 * @var PaymentMethod $placetopay
                 */
                $placetopay = $payment->getMethodInstance();

                if (0 !== strpos($placetopay->getCode(), 'placetopay')) {
                    throw new LocalizedException(__('Unknown payment method.'));
                }

                if ($placetopay->isPendingOrder($order)) {
                    $response = $placetopay->resolve($order, $payment);
                    $status = $response->status();
                } else {
                    $status = $placetopay->parseOrderState($order);
                }

                /** @var Transaction $transaction */
                $transaction = $this->_transactionRepository->getByTransactionType(
                    Transaction::TYPE_ORDER,
                    $payment->getId()
                );

                if ($this->scopeConfig->getValue('payment/' . $placetopay->getCode() . '/final_page') == 'magento_default') {
                    if ($status->isApproved()) {
                        $payment->setIsTransactionPending(false);
                        $payment->setIsTransactionApproved(true);
                        $payment->setSkipOrderProcessing(true);

                        $message = __('Payment approved');

                        $payment->addTransactionCommentsToOrder($transaction, $message);

                        $transaction->save();

                        $this->_getCheckout()->setLastSuccessQuoteId($order->getQuoteId());
                        $this->_getCheckout()->setLastQuoteId($order->getQuoteId());
                        $this->_getCheckout()->setLastOrderId($order->getEntityId());
                    } elseif ($status->isRejected()) {
                        $payment->setIsTransactionDenied(true);

                        $quote = $this->quoteQuoteFactory->create()->load($order->getQuoteId());

                        if ($quote->getId()) {
                            $quote->setIsActive(true)->save();
                            $session->setQuoteId($order->getQuoteId());
                        }

                        $payment->setSkipOrderProcessing(true);

                        $message = __('Payment declined');

                        $payment->addTransactionCommentsToOrder($transaction, $message);

                        $transaction->save();

                        $this->messageManager->addErrorMessage(__('transaction_declined_message'));

                        $pathRedirect = 'checkout/onepage/failure';
                    } else {
                        $this->messageManager->addSuccessMessage(__('transaction_pending_message'));

                        $pathRedirect = 'checkout/cart';
                    }
                } else {
                    $this->messageManager->addSuccessMessage(__('transaction_pending_message'));

                    if ($this->customerSession->isLoggedIn()) {
                        $this->eventManager->dispatch(
                            'checkout_onepage_controller_success_action',
                            ['order_ids' => [$order->getRealOrderId()]]
                        );

                        $pathRedirect = 'sales/order/view/order_id/' . $order->getRealOrderId();
                    } else {
                        $pathRedirect = 'sales/guest/form/';
                    }
                }
            } else {
                $reference = $this->getRequest()->getParam('reference');

                $this->logger->debug(
                    'P2P_LOG: ResponseAction0 [' .
                    $order->getRealOrderId() . '] with Reference: ' . $reference
                );

                /**
                 * @var Order $order
                 */
                $order = $this->salesOrderFactory->create()->loadByIncrementId($reference);

                if ($order->getId()) {
                    $payment = $order->getPayment();

                    if ($payment) {
                        /**
                         * @var PaymentMethod $placetopay
                         */
                        $placetopay = $payment->getMethodInstance();

                        if ($placetopay instanceof PaymentMethod) {
                            $additional = $payment->getAdditionalInformation();

                            if ($placetopay->isPendingOrder($order) && $additional && isset($additional['request_id'])) {
                                if ($placetopay->isPendingOrder($order) && $additional && isset($additional['request_id'])) {
                                    $information = $placetopay->gateway()->query($additional['request_id']);

                                    $placetopay->settleOrderStatus($information, $order);
                                }
                            }
                        }
                    }
                }
            }

            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

            $resultRedirect->setPath($pathRedirect);

            return $resultRedirect;
        } catch (LocalizedException $exception) {
            $this->logger->debug(
                'P2P_LOG: ResponseAction1 [' . $order->getRealOrderId() . ']' .
                $exception->getMessage() . ' ON ' .
                $exception->getFile() . ' LINE ' .
                $exception->getLine()
            );

            $this->messageManager->addErrorMessage($exception->getMessage());

            return $this->_pageFactory->create();
        } catch (Exception $exception) {
            $this->logger->debug('P2P_LOG: ResponseAction2 [' .
                $order->getRealOrderId() . ']' .
                $exception->getMessage() . ' ON ' .
                $exception->getFile() . ' LINE ' .
                $exception->getLine());

            return $this->_pageFactory->create();
        }
    }
}

<?php

namespace PlacetoPay\Payments\Controller\Payment;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\OrderFactory;
use PlacetoPay\Payments\Logger\Logger as LoggerInterface;
use PlacetoPay\Payments\Model\PaymentMethod;

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
     * @var OrderFactory $salesOrderFactory
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
     * @var ScopeConfigInterface $scopeConfig
     */
    protected $scopeConfig;

    /**
     * @var QuoteFactory $quoteQuoteFactory
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
     * @var Session $_checkoutSession
     */
    protected $_checkoutSession;

    /**
     * @var PaymentHelper $_paymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var TransactionRepositoryInterface $_transactionRepository
     */
    protected $_transactionRepository;

    /**
     * @var PageFactory $_pageFactory
     */
    protected $_pageFactory;

    /**
     * Response constructor.
     *
     * @param Context                        $context
     * @param Session                        $checkoutSession
     * @param OrderFactory                   $salesOrderFactory
     * @param ManagerInterface               $messageManager
     * @param LoggerInterface                $logger
     * @param Http                           $request
     * @param ScopeConfigInterface           $scopeConfig
     * @param QuoteFactory                   $quoteQuoteFactory
     * @param CustomerSession                $customerSession
     * @param EventManager                   $eventManager
     * @param TransactionRepositoryInterface $transactionRepository
     * @param PageFactory                    $pageFactory
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $salesOrderFactory,
        ManagerInterface $messageManager,
        LoggerInterface $logger,
        Http $request,
        ScopeConfigInterface $scopeConfig,
        QuoteFactory $quoteQuoteFactory,
        CustomerSession $customerSession,
        EventManager $eventManager,
        TransactionRepositoryInterface $transactionRepository,
        PageFactory $pageFactory
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
        $this->_transactionRepository = $transactionRepository;
        $this->_pageFactory = $pageFactory;
    }

    /**
     * @return Session
     */
    protected function _getCheckout()
    {
        return $this->checkoutSession;
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
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
                        $this->setPaymentApproved($payment, $transaction);
                        $this->_getCheckout()->setLastSuccessQuoteId($order->getQuoteId());
                        $this->_getCheckout()->setLastQuoteId($order->getQuoteId());
                        $this->_getCheckout()->setLastOrderId($order->getEntityId());
                    } elseif ($status->isRejected()) {
                        $this->setPaymentDenied($payment, $transaction);

                        $quote = $this->quoteQuoteFactory->create()->load($order->getQuoteId());

                        if ($quote->getId()) {
                            $quote->setIsActive(true)->save();
                            $session->setQuoteId($order->getQuoteId());
                        }

                        $this->messageManager->addErrorMessage(__('We regret that you have decided to cancel the payment.'));

                        $pathRedirect = 'checkout/onepage/failure';
                    } else {
                        $this->messageManager->addWarningMessage(__('Transaction pending, please wait a moment while it automatically resolves.'));

                        //$pathRedirect = 'checkout/cart';
                        $pathRedirect = 'placetopay/onepage/pending';
                    }
                } else {
                    if ($status->isApproved()) {
                        $this->messageManager->addSuccessMessage(__('Thanks, transaction approved by placetopay.'));
                        $this->setPaymentApproved($payment, $transaction);
                    } elseif ($status->isRejected()) {
                        $this->messageManager->addErrorMessage(__('We regret that you have decided to cancel the payment.'));
                        $this->setPaymentDenied($payment, $transaction);
                    } else {
                        $this->messageManager->addWarningMessage(__('Transaction pending, please wait a moment while it automatically resolves.'));
                    }

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
                    'Response [' .
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
                'Response [' . $order->getRealOrderId() . ']' .
                $exception->getMessage() . ' ON ' .
                $exception->getFile() . ' LINE ' .
                $exception->getLine()
            );

            $this->messageManager->addErrorMessage($exception->getMessage());

            return $this->_pageFactory->create();
        } catch (Exception $exception) {
            $this->logger->debug('Response [' .
                $order->getRealOrderId() . ']' .
                $exception->getMessage() . ' ON ' .
                $exception->getFile() . ' LINE ' .
                $exception->getLine());

            return $this->_pageFactory->create();
        }
    }

    /**
     * @param Order\Payment $payment
     * @param Transaction   $transaction
     *
     * @throws Exception
     */
    public function setPaymentApproved($payment, $transaction)
    {
        $message = __('Payment approved');

        $payment->addTransactionCommentsToOrder($transaction, $message);

        $transaction->save();
    }

    /**
     * @param Order\Payment $payment
     * @param Transaction   $transaction
     *
     * @throws Exception
     */
    public function setPaymentDenied($payment, $transaction)
    {
        $message = __('Payment declined');

        $payment->addTransactionCommentsToOrder($transaction, $message);

        $transaction->save();
    }
}

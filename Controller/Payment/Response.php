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
use PlacetoPay\Payments\Helper\PlacetoPayLogger;
use PlacetoPay\Payments\Model\PaymentMethod;

/**
 * Class Response.
 */
class Response extends Action
{
    /**
     * @var PlacetoPayLogger
     */
    protected $_logger;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var OrderFactory
     */
    protected $salesOrderFactory;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var Http
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
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var Session
     */
    protected $_checkoutSession;

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var TransactionRepositoryInterface
     */
    protected $_transactionRepository;

    /**
     * @var PageFactory
     */
    protected $_pageFactory;

    /**
     * Response constructor.
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderFactory $salesOrderFactory
     * @param ManagerInterface $messageManager
     * @param PlacetoPayLogger $logger
     * @param Http $request
     * @param ScopeConfigInterface $scopeConfig
     * @param QuoteFactory $quoteQuoteFactory
     * @param CustomerSession $customerSession
     * @param EventManager $eventManager
     * @param TransactionRepositoryInterface $transactionRepository
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $salesOrderFactory,
        ManagerInterface $messageManager,
        PlacetoPayLogger $logger,
        Http $request,
        ScopeConfigInterface $scopeConfig,
        QuoteFactory $quoteQuoteFactory,
        CustomerSession $customerSession,
        EventManager $eventManager,
        TransactionRepositoryInterface $transactionRepository,
        PageFactory $pageFactory
    ) {
        $this->_logger = $logger;
        $this->checkoutSession = $checkoutSession;
        $this->salesOrderFactory = $salesOrderFactory;
        $this->messageManager = $messageManager;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->quoteQuoteFactory = $quoteQuoteFactory;
        $this->customerSession = $customerSession;
        $this->eventManager = $eventManager;
        $this->_transactionRepository = $transactionRepository;
        $this->_pageFactory = $pageFactory;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        $reference = $this->request->getParam('reference');
        $order = $this->salesOrderFactory->create()->loadByIncrementId($reference);
        $session = $this->checkoutSession;
        $pathRedirect = 'placetopay/onepage/success';

        try {
            if ($order && $this->request->getParam('reference') == $order->getRealOrderId()) {
                $payment = $order->getPayment();

                /**
                 * @var PaymentMethod
                 */
                $placetopay = $payment->getMethodInstance();

                if (strcmp($placetopay->getCode(), 'placetopay') !== 0) {
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
                    $payment->getId(),
                    $payment->getOrder()->getId()
                );

                if ($this->scopeConfig->getValue('payment/' . $placetopay->getCode() . '/final_page') == 'magento_default') {
                    if ($status->isApproved()) {
                        $this->setPaymentApproved($payment, $transaction);

                        $session->setLastQuoteId($order->getQuoteId())
                            ->setLastSuccessQuoteId($order->getQuoteId())
                            ->setLastOrderId($order->getId())
                            ->setLastRealOrderId($order->getIncrementId())
                            ->setLastOrderStatus($order->getStatus());

                        $this->messageManager->addSuccessMessage(__('Thanks, transaction approved by Placetopay.'));
                    } elseif ($status->isRejected()) {
                        $this->setPaymentDenied($payment, $transaction);

                        $quote = $this->quoteQuoteFactory->create()->load($order->getQuoteId());

                        if ($quote->getId()) {
                            $quote->setIsActive(true)->save();

                            $session->setLastQuoteId($order->getQuoteId())
                                ->setLastOrderId($order->getId())
                                ->setLastRealOrderId($order->getIncrementId())
                                ->setLastOrderStatus($order->getStatus());
                        }

                        $this->messageManager->addErrorMessage(__('The payment process has been declined.'));

                        $pathRedirect = 'placetopay/onepage/failure';
                    } else {
                        $session->setLastOrderId($order->getId())
                            ->setLastRealOrderId($order->getIncrementId())
                            ->setLastOrderStatus($order->getStatus());

                        $this->messageManager->addWarningMessage(__('Transaction pending, please wait a moment while it automatically resolves.'));

                        $pathRedirect = 'placetopay/onepage/pending';
                    }
                } else {
                    if ($status->isApproved()) {
                        $this->messageManager->addSuccessMessage(__('Thanks, transaction approved by Placetopay.'));
                        $this->setPaymentApproved($payment, $transaction);
                    } elseif ($status->isRejected()) {
                        $this->messageManager->addErrorMessage(__('The payment process has been declined.'));
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
                /**
                 * @var Order
                 */
                $order = $this->salesOrderFactory->create()->loadByIncrementId($reference);

                if ($order->getId()) {
                    $payment = $order->getPayment();

                    if ($payment) {
                        /**
                         * @var PaymentMethod
                         */
                        $placetopay = $payment->getMethodInstance();

                        if ($placetopay instanceof PaymentMethod) {
                            $additional = $payment->getAdditionalInformation();

                            if ($placetopay->isPendingOrder($order) && $additional && isset($additional['request_id'])) {
                                $information = $placetopay->gateway()->query($additional['request_id']);

                                $placetopay->settleOrderStatus($information, $order);
                            }
                        }
                    }

                    $session->setLastQuoteId($order->getQuoteId())
                        ->setLastSuccessQuoteId($order->getQuoteId())
                        ->setLastOrderId($order->getId())
                        ->setLastRealOrderId($order->getIncrementId())
                        ->setLastOrderStatus($order->getStatus());
                }
            }

            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

            $resultRedirect->setPath($pathRedirect);

            return $resultRedirect;
        } catch (LocalizedException $exception) {
            $this->_logger->logger($this, 'error', __FUNCTION__ . ' error', [
                'response' => $exception->getMessage(),
                'code_exception' => $exception->getCode(),
                'file_exception' => $exception->getFile(),
                'line_exception' => $exception->getLine(),
            ]);

            $this->messageManager->addErrorMessage($exception->getMessage());

            return $this->_pageFactory->create();
        } catch (Exception $exception) {
            $this->_logger->logger($this, 'error', __FUNCTION__ . ' error', [
                'response' => $exception->getMessage(),
                'code_exception' => $exception->getCode(),
                'file_exception' => $exception->getFile(),
                'line_exception' => $exception->getLine(),
            ]);

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

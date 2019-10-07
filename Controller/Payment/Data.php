<?php


namespace PlacetoPay\Payments\Controller\Payment;


use Exception;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Payment\Helper\Data as PaymentHelper;

class Data extends \Magento\Framework\App\Action\Action
{
    protected $_helperData;

    protected $_placeToPayLogger;

    protected $_checkoutSession;

    protected $_orderFactory;

    protected $_resultJsonFactory;

    protected $_url;

    protected $_transactionBuilder;

    protected $_paymentHelper;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \PlacetoPay\Payments\Helper\Data $helperData,
        \PlacetoPay\Payments\Logger\Logger $placeToPayLogger,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        PaymentHelper $paymentHelper
    )
    {
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
        try{
            $order = $this->_getCheckoutSession()->getLastRealOrder();
            $method = $order->getPayment()->getMethod();
            $methodInstance = $this->_paymentHelper->getMethodInstance($method);

            $placetopay =  $methodInstance->placeToPay();

            $orderId = $order->getId();
            $reference = $orderId . "_" . time();

            $request = $this->getDataParamsPayment($order, $reference);

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

        }catch (Exception $exception){
            $this->_helperData->log($exception->getMessage());
            throw new Exception($exception->getMessage());
        }
    }

    public function getIP()
    {
        return ($_SERVER['REMOTE_ADDR'] == '::1' || $_SERVER['REMOTE_ADDR'] == '::' ||
            !preg_match('/^((?:25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9]).){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9])$/m',
                $_SERVER['REMOTE_ADDR'])) ? '127.0.0.1' : $_SERVER['REMOTE_ADDR'];
    }

    public function getDataParamsPayment($order, $reference)
    {
        $address = $this->getAddress($order);

        $request = [
            'buyer' => [
                'name' => $address->getFirstname(),
                'surname' => $address->getLastname(),
                'email' => $order->getCustomerEmail()
            ],
            'payment' => [
                'reference' => $reference,
                'description' => __('Order # %1', $order->getId()),
                'amount' => [
                    'currency' => $order->getOrderCurrencyCode(),
                    'total' => $order->getGrandTotal(),
                ],
                'shipping' => [
                    'name' => $address->getFirstname(),
                    'surname' => $address->getLastname(),
                    'address' => [
                        'street' => $address->getData("street"),
                        'city' => $address->getCity(),
                        'phone' => $address->getTelephone(),
                        'country' => $order->getOrderCurrencyCode()
                    ]
                ]
            ],
            'expiration' => date('c', strtotime($this->getDays())),
            'returnUrl' => $this->_url->getUrl('placetopay/payment/response', ['reference' => $reference]),
            "cancelUrl" => $this->_url->getUrl('placetopay/payment/cancel'),
            'ipAddress' => $this->getIP(),
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
        ];

        return $request;
    }

    public function getAddress($order)
    {
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        if ($billingAddress)
            return $billingAddress;

        return $shippingAddress;

    }

    public function getDays()
    {
        $today = date('Y-m-d');
        $weekDay = date('w', strtotime($today));

        $days = 0;
        if ($weekDay == 0)
            $days += 1;
        if ($weekDay == 5)
            $days += 3;
        if ($weekDay == 6)
            $days += 2;

        return "+$days days";
    }
}
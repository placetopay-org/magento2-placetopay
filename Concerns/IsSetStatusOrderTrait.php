<?php

namespace PlacetoPay\Payments\Concerns;

use Dnetix\Redirection\Message\RedirectInformation;
use Magento\Sales\Model\Order;
use PlacetoPay\Payments\Actions\CreateInvoiceAction;
use PlacetoPay\Payments\Helper\OrderHelper;

trait IsSetStatusOrderTrait
{
    public function setStatus(RedirectInformation $information, Order $order, Order\Payment $payment = null): void
    {
        $this->logger->debug('Consulting status of the redirect information');
        $status = $information->status();
        $this->logger->debug('settear status', [$status->status()]);

        $data = OrderHelper::getState($status->status());

        if ($data['state'] !== null) {
            if (!$payment) {
                $this->logger->debug('Obtaining the payment of the order');
                $payment = $order->getPayment();
            }

            $info = $this->_config->getInfoModel();
            $transactions = $information->payment();
            $info->updateStatus($payment, $status, $transactions);
            $this->logger->debug('settleOrderStatus with status: ' . $status->status());

            if ($status->isApproved()) {
                $this->logger->debug('The status is approved, create invoice and set status: APPROVED, in the order');
                $payment->setIsTransactionPending(false);
                $payment->setIsTransactionApproved(true);
                $payment->setSkipOrderProcessing(true);
                CreateInvoiceAction::execute($order);
                $order->setEmailSent(true);
                $order->setState($data['state'])->setStatus($data['orderStatus'])->save();
            } elseif ($status->isRejected()) {
                $payment->setIsTransactionDenied(true);
                $payment->setSkipOrderProcessing(true);
                $this->logger->debug('The status is rejected, create invoice and set status: REJECTED, in the order');
                $order->cancel()->save();
            } else {
                $this->logger->debug('The ', ['state: ' => $data['state']]);
                $this->logger->debug('Setting in ', ['Order status: ' => $data['orderStatus']]);
                $order->setState($data['state'])->setStatus($data['orderStatus'])->save();
            }
        }
    }

    public function changeStatusOrderFail(Order $order, Order\Payment $payment = null): void
    {
        if (!$payment) {
            $payment = $order->getPayment();
        }
        $this->logger->debug('Process payment to denied');
        $payment->setIsTransactionDenied(true);
        $payment->setSkipOrderProcessing(true);
        $order->cancel()->save();
    }
}

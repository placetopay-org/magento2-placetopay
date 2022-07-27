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
        $status = $information->status();
        $this->logger->debug('settear status', [$status->status()]);

        $data = OrderHelper::getState($status->status());

        $this->logger->debug('state');
        if ($data['state'] !== null) {
            if (!$payment) {
                $payment = $order->getPayment();
            }

            $info = $this->config->getInfoModel();
            $transactions = $information->payment();
            $info->updateStatus($payment, $status, $transactions);
            $this->logger->debug('settleOrderStatus with status: ' . $status->status());

            if ($status->isApproved()) {
                $payment->setIsTransactionPending(false);
                $payment->setIsTransactionApproved(true);
                $payment->setSkipOrderProcessing(true);
                CreateInvoiceAction::execute($order);
                $order->setEmailSent(true);
                $order->setState($data['state'])->setStatus($data['orderStatus'])->save();
            } elseif ($status->isRejected()) {
                $payment->setIsTransactionDenied(true);
                $payment->setSkipOrderProcessing(true);
                $order->cancel()->save();
            } else {
                $order->setState($data['state'])->setStatus($data['orderStatus'])->save();
            }
        }
    }

    public function changeStatusOrderFail(Order $order, Order\Payment $payment = null): void
    {
        if (!$payment) {
            $payment = $order->getPayment();
        }
        $payment->setIsTransactionDenied(true);
        $payment->setSkipOrderProcessing(true);
        $order->cancel()->save();
    }
}

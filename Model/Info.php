<?php

namespace PlacetoPay\Payments\Model;

use Dnetix\Redirection\Entities\Status;
use Dnetix\Redirection\Entities\Transaction;
use Dnetix\Redirection\Message\RedirectResponse;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment;

/**
 * Class Info.
 */
class Info
{
    /**
     * @param Payment          $payment
     * @param RedirectResponse $response
     * @param string           $env
     *
     * @throws LocalizedException
     * @throws Exception
     */
    public function loadInformationFromRedirectResponse(&$payment, $response, $env)
    {
        $payment->setLastTransId($response->requestId());

        $payment->setAdditionalInformation([
            'request_id' => $response->requestId(),
            'process_url' => $response->processUrl(),
            'status' => $response->status()->status(),
            'status_reason' => $response->status()->reason(),
            'status_message' => $response->status()->message(),
            'status_date' => $response->status()->date(),
            'environment' => $env,
            'transactions' => [],
        ]);

        try {
            $payment->save();
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }
    }

    /**
     * @param Payment     $payment
     * @param Status      $status
     * @param Transaction $transactions
     *
     * @throws LocalizedException
     */
    public function updateStatus(&$payment, $status, $transactions = null)
    {
        $information = $payment->getAdditionalInformation();
        $parsedTransactions = $information['transactions'];
        $lastTransaction = null;

        if ($transactions && is_array($transactions) && sizeof($transactions) > 0) {
            $lastTransaction = $transactions[0];

            foreach ($transactions as $transaction) {
                $parsedTransactions[$transaction->internalReference()] = [
                    'authorization' => $transaction->authorization(),
                    'status' => $transaction->status()->status(),
                    'status_date' => $transaction->status()->date(),
                    'status_message' => $transaction->status()->message(),
                    'status_reason' => $transaction->status()->reason(),
                    'franchise' => $transaction->franchise(),
                    'payment_method_name' => $transaction->paymentMethodName(),
                    'payment_method' => $transaction->paymentMethod(),
                    'amount' => $transaction->amount()->from()->total(),
                ];
            }
        }

        $this->importToPayment($payment, [
            'status' => $status->status(),
            'status_reason' => $status->reason(),
            'status_message' => $status->message(),
            'status_date' => $status->date(),
            'authorization' => $lastTransaction ? $lastTransaction->authorization() : null,
            'transactions' => $parsedTransactions,
        ]);
    }

    /**
     * @param Payment $payment
     * @param array $data
     *
     * @throws LocalizedException
     * @throws Exception
     */
    public function importToPayment(&$payment, $data)
    {
        $actual = $payment->getAdditionalInformation() ? $payment->getAdditionalInformation() : [];

        $payment->setAdditionalInformation(array_merge($actual, $data));

        try {
            $payment->save();
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }
    }
}

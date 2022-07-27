<?php

namespace PlacetoPay\Payments\PlacetoPayService;

use Dnetix\Redirection\Message\RedirectInformation;
use Dnetix\Redirection\PlacetoPay;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Header;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Tax\Item;
use PlacetoPay\Payments\Concerns\IsSetStatusOrderTrait;
use PlacetoPay\Payments\Helper\Data as Config;
use PlacetoPay\Payments\Helper\OrderHelper;
use PlacetoPay\Payments\Logger\Logger as LoggerInterface;

class PlacetoPayPayment
{
    use IsSetStatusOrderTrait;

    private LoggerInterface $logger;
    private Resolver $resolver;
    private Config $config;
    private UrlInterface $url;
    private RemoteAddress $remoteAddress;
    private Header $header;
    private Item $item;
    protected PlacetoPay $gateway;
    protected Order $_order;

    public function __construct(Config $config, LoggerInterface $logger, Resolver $resolver, UrlInterface $url, RemoteAddress $remoteAddress, Header $header, Item $item)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->resolver = $resolver;
        $this->url = $url;
        $this->remoteAddress = $remoteAddress;
        $this->header = $header;
        $this->item = $item;

        $settings = [
            'login' => $config->getLogin(),
            'tranKey' => $config->getTranKey(),
            'baseUrl' => $config->getUri(),
        ];

        $this->gateway = new PlacetoPay($settings);
    }

    private function getRedirectRequestData(Order $order): array
    {
        $reference = $order->getRealOrderId();
        $total = ! is_null($order->getGrandTotal()) ? $order->getGrandTotal() : $order->getTotalDue();
        $subtotal = $order->getSubtotal();
        $discount = (string)$order->getDiscountAmount() != 0 ? ($order->getDiscountAmount() * -1) : 0;
        $shipping = $order->getShippingAmount();
        $visibleItems = $order->getAllVisibleItems();
        $expiration = date('c', strtotime($this->config->getExpirationTimeMinutes() . ' minutes'));
        $items = [];

        /** @var Order\Item $item */
        foreach ($visibleItems as $item) {
            $items[] = [
                'sku' => $item->getSku(),
                'name' => $this->config->cleanText($item->getName()),
                'category' => $item->getProductType(),
                'qty' => $item->getQtyOrdered(),
                'price' => $item->getPrice(),
                'tax' => $item->getTaxAmount(),
            ];
        }

        $data = [
            'locale' => $this->resolver->getLocale(),
            'buyer' => OrderHelper::parseAddressPerson($order->getBillingAddress()),
            'payment' => [
                'reference' => $reference,
                'description' => 'Pedido ' . $order->getId(),
                'amount' => [
                    'details' => [
                        [
                            'kind' => 'subtotal',
                            'amount' => $subtotal,
                        ],
                        [
                            'kind' => 'discount',
                            'amount' => (string)$discount,
                        ],
                        [
                            'kind' => 'shipping',
                            'amount' => $shipping,
                        ],
                    ],
                    'currency' => $order->getOrderCurrencyCode(),
                    'total' => $total,
                ],
                'items' => $items,
                'shipping' => OrderHelper::parseAddressPerson($order->getShippingAddress()),
                'allowPartial' => $this->config->getAllowPartialPayment(),
            ],
            'returnUrl' => $this->url->getUrl('placetopay/payment/response', ['reference' => $reference]),
            'expiration' => $expiration,
            'ipAddress' => $this->remoteAddress->getRemoteAddress(),
            'userAgent' => $this->header->getHttpUserAgent(),
            'skipResult' => $this->config->getSkipResult(),
            'noBuyerFill' => $this->config->getFillBuyerInformation(),
        ];

        if ($this->config->getFillTaxInformation()) {
            try {
                $map = [];

                if ($mapping = $this->config->getTaxRateParsing()) {
                    foreach (explode('|', $mapping) as $item) {
                        $t = explode(':', $item);

                        if (is_array($t) && count($t) == 2) {
                            $map[$t[0]] = $t[1];
                        }
                    }
                }

                $taxInformation = $this->item->getTaxItemsByOrderId($order->getId());

                if (is_array($taxInformation) && count($taxInformation) > 0) {
                    $taxes = [];

                    foreach ($taxInformation as $item) {
                        $taxes[] = [
                            'kind' => isset($map[$item['code']]) ? $map[$item['code']] : 'valueAddedTax',
                            'amount' => $item['real_amount'],
                            'base' => $order->getItemById($item['item_id'])->getBasePrice(),
                        ];
                    }

                    $mergedTaxes = [];

                    foreach ($taxes as $elem) {
                        $mergedTaxes[$elem['kind']]['kind'] = $elem['kind'];
                        $mergedTaxes[$elem['kind']]['amount'] =
                            isset($mergedTaxes[$elem['kind']]['amount']) ?
                                number_format((float) $mergedTaxes[$elem['kind']]['amount'] + (float) $elem['amount'], 4, '.', '') :
                                number_format((float) $elem['amount'], 4, '.', '');
                        $mergedTaxes[$elem['kind']]['base'] = isset($mergedTaxes[$elem['kind']]['base']) ?
                            number_format((float) $mergedTaxes[$elem['kind']]['base'] + (float) $elem['base'], 4, '.', '') :
                            number_format((float) $elem['base'], 4, '.', '');
                    }

                    $mergedTaxes = array_values($mergedTaxes);

                    $data['payment']['amount']['taxes'] = $mergedTaxes;
                }
            } catch (Exception $ex) {
                $this->logger->debug(
                    'Error calculating taxes: [' .
                    $order->getRealOrderId() .
                    '] ' . serialize($this->item->getTaxItemsByOrderId($order->getId()))
                );
            }
        }

        if ($pm = $this->config->getPaymentMethods()) {
            $paymentMethods = [];

            foreach (explode(',', $pm) as $paymentMethod) {
                $paymentMethods[] = $paymentMethod;
            }

            $data['paymentMethod'] = implode(',', $paymentMethods);
        }

        return $data;
    }

    /**
     * @return string|null
     * @throws Exception
     */

    public function getCheckoutRedirect(Order $order)
    {
        $this->_order = $order;

        try {
            $response = $this->gateway->request($this->getRedirectRequestData($order));

            if ($response->isSuccessful()) {
                $payment = $order->getPayment();
                $info = $this->config->getInfoModel();

                $info->loadInformationFromRedirectResponse($payment, $response, $this->config->getMode(), $order);
            } else {
                $this->logger->debug(
                    'Payment error [' .
                    $order->getRealOrderId() . '] ' .
                    $response->status()->message() . ' - ' .
                    $response->status()->reason() . ' ' .
                    $response->status()->status()
                );

                throw new LocalizedException(__($response->status()->message()));
            }

            return $response->processUrl();
        } catch (Exception $ex) {
            $this->logger->debug(
                'Payment error [' .
                $order->getRealOrderId() . '] ' .
                $ex->getMessage() . ' on ' . $ex->getFile() . ' line ' .
                $ex->getLine() . ' - ' . get_class($ex)
            );

            throw new Exception($ex->getMessage());
        }
    }

    public function resolve(Order $order, Order\Payment $payment = null): RedirectInformation
    {
        if (! $payment) {
            $payment = $order->getPayment();
        }

        $info = $payment->getAdditionalInformation();

        if (! $info || ! isset($info['request_id'])) {
            $this->logger->debug(
                'No additional information for order: ' .
                $order->getRealOrderId()
            );

            throw new LocalizedException(__('No additional information for order: %1', $order->getRealOrderId()));
        }

        $response = $this->gateway->query($info['request_id']);

        if ($response->isSuccessful()) {
            $this->setStatus($response, $order, $payment);
        } else {
            $this->logger->debug(
                'Non successful: ' .
                $response->status()->message() . ' ' .
                $response->status()->reason()
            );
        }

        return $response;
    }

    public function consultTransactionInfo(string $requestId): RedirectInformation
    {
        return $this->gateway->query($requestId);
    }

    public function gateway(): PlacetoPay
    {
        return $this->gateway;
    }
}

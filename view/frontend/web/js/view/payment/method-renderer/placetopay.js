define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/translate',
        'mage/url'
    ],
    function (
        $,
        Component,
        placeOrderAction,
        selectPaymentMethodAction,
        customer,
        checkoutData,
        additionalValidators,
        errorProcessor,
        fullScreenLoader,
        $t,
        url
    ) {
        'use strict';

        return Component.extend({
            redirectAfterPlaceOrder: false,
            defaults: {
                template: 'PlacetoPay_Payments/payment/placetopay'
            },

            placeOrder: function (data, event) {
                if (event)
                    event.preventDefault();
                var self = this,
                    placeOrder,
                    emailValidationResult = customer.isLoggedIn(),
                    loginFormSelector = 'form[data-role=email-with-possible-login]';
                if (!customer.isLoggedIn()) {
                    $(loginFormSelector).validation();
                    emailValidationResult = Boolean($(loginFormSelector + ' input[name=username]').valid());
                }
                if (emailValidationResult && this.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                    placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);

                    $.when(placeOrder).done(function () {
                        self.isPlaceOrderActionAllowed(true);
                    }).done(this.afterPlaceOrder.bind(this));
                    return true;
                }
                return false;
            },

            selectPaymentMethod: function() {
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);
                return true;
            },

            afterPlaceOrder: function() {
                $.post(url.build('placetopay/payment/data'), 'json')
                    .done( data => {
                        window.location = data.url;
                    }).fail( response => {
                     console.log(this.messageContainer);
                    errorProcessor.process('error', this.messageContainer);
                }).always( () => {
                    fullScreenLoader.stopLoader();
                });
            },

            getLogoUrl: function() {
                return window.checkoutConfig.payment.placetopay.logoUrl;
            },

            hasPendingPayment: function () {
                return window.checkoutConfig.payment.placetopay.order.hasPendingOrder;
            },

            pendingMessage: function () {
                let orderId = window.checkoutConfig.payment.placetopay.order.id;
                let phone = window.checkoutConfig.payment.placetopay.order.phone;

                let email = '<a href="mailto:' +
                    window.checkoutConfig.payment.placetopay.order.email + '">' +
                    window.checkoutConfig.payment.placetopay.order.email + '</a>';

                let authorization = window.checkoutConfig.payment.placetopay.order.authorization;
                let values = [orderId, phone, email, authorization];

                return $.mage.__("At this time your order #%1 display a checkout transaction which is pending receipt of confirmation from your financial institution, please wait a few minutes and check back later to see if your payment was successfully confirmed. For more information about the current state of your operation you may contact our customer service line at %2 or send your concerns to the email %3 and ask for the status of the transaction: '%4'.")
                    .replace(/%(\d+)/g, (_, n) => values[+n-1]);
            }
        });
    }
);

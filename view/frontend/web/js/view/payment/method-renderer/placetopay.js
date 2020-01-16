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
        'mage/url',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/model/totals'
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
        url,
        globalMessageList,
        totals
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

            selectPaymentMethod: function () {
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);
                return true;
            },

            afterPlaceOrder: function () {
                $.post(url.build('placetopay/payment/data'), 'json')
                    .done(data => {
                        window.location = data.url;
                    }).fail(response => {
                    console.log(this.messageContainer);
                    errorProcessor.process('error', this.messageContainer);
                }).always(() => {
                    fullScreenLoader.stopLoader();
                });
            },

            getLogo: function () {
                return '<img src="https://static.placetopay.com/placetopay-logo.svg" height="48" border="0" alt="PlacetoPay"/>';
            },

            hasPendingPayment: function () {
                return window.checkoutConfig.payment.placetopay.order.hasPendingOrder;
            },

            hasCifin: function () {
                return window.checkoutConfig.payment.placetopay.hasCifinMessage;
            },

            pendingMessage: function () {
                let orderId = window.checkoutConfig.payment.placetopay.order.id;
                let phone = window.checkoutConfig.payment.placetopay.order.phone;

                let email = '<a href="mailto:' +
                    window.checkoutConfig.payment.placetopay.order.email + '">' +
                    window.checkoutConfig.payment.placetopay.order.email + '</a>';

                let authorization = window.checkoutConfig.payment.placetopay.order.authorization;
                let data = [orderId, phone, email, authorization];

                return $t("At this time your order #%1 display a checkout transaction which is pending receipt of confirmation from your financial institution, please wait a few minutes and check back later to see if your payment was successfully confirmed. For more information about the current state of your operation you may contact our customer service line at %2 or send your concerns to the email %3 and ask for the status of the transaction: '%4'.")
                    .replace(/%(\d+)/g, (_, n) => data[+n - 1]);
            },

            securityMessage: function () {
                let url = window.checkoutConfig.payment.placetopay.url;
                let name = window.checkoutConfig.payment.placetopay.legalName;
                let merchant = '<b>EGM Ingeniería Sin Fronteras S.A.S</b>';
                let brand = '<b>PlacetoPay</b>';
                let data = [url, name, merchant, brand];

                return $t('Any person who realizes a purchase in the site %1, acting freely and voluntarily, authorizes to %2, through the service provider %3 y/o %4 to consult and request information from credit, financial, commercial performance and services to third parties, even in countries of the same nature in the central risk, generating a footprint consultation.')
                    .replace(/%(\d+)/g, (_, n) => data[+n - 1]);
            },

            getPaymentIcons: function () {
                let paymentMethods = window.checkoutConfig.payment.placetopay.paymentMethods;
                let icons = [];

                paymentMethods.forEach(function (icon) {
                    icons.push('<img src="https://www.placetopay.com/images/providers/' + icon + '.png" alt="" class="acceptance_logo" style="max-width: 80px; max-height: 50px; display: inline-block; padding: 0 5px;" />');
                });

                return icons.join(' ');
            },

            getMinimum: function () {
                return window.checkoutConfig.payment.placetopay.minimum;
            },

            getMaximum: function () {
                return window.checkoutConfig.payment.placetopay.maximum;
            },

            getTotal: function () {
                return totals.totals().grand_total;
            },

            showErrorMessage: function (message) {
                document.getElementById('payment-method-placetopay').scrollIntoView(true);
                this.messageContainer.addErrorMessage({message: message});
            },

            validate: function () {
                let isValid = true;

                if (! this.isMinimumValid()) {
                    this.showErrorMessage(
                        $t('Does not meet the minimum amount to process the order, the minimum amount must be greater or equal to value to use this payment gateway.')
                            .replace('value', this.getMinimum())
                    );

                    isValid = false;
                } else if (! this.isMaximumValid()) {
                    this.showErrorMessage(
                        $t('Exceeds the maximum amount allowed to process the order, it must be less or equal to value to use this payment gateway.')
                            .replace('value', this.getMaximum())
                    );

                    isValid = false;
                } else if (! this.allowPendingPayment()) {
                    if (this.hasPendingPayment()) {
                        this.showErrorMessage(
                            $t('The payment could not be continued because a pending order has been found.')
                        );

                        isValid = false;
                    }
                }

                return isValid;
            },

            isMinimumValid: function () {
                return ! (this.getMinimum() != null && this.getTotal() < this.getMinimum());
            },

            isMaximumValid: function () {
                return ! (this.getMaximum() != null && this.getTotal() > this.getMaximum());
            },

            allowPendingPayment: function () {
                return window.checkoutConfig.payment.placetopay.allowPendingPayments;
            }
        });
    }
);

define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'banchile',
                component: 'Banchile_Payments/js/view/payment/method-renderer/banchile'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);

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
                type: 'uapay',
                component: 'UaPayPayment_UaPay/js/view/payment/method-renderer/checkout-uapay'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
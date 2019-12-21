define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/url-builder',
        'mage/url'
    ],
    function (
        $,
        Component,
        placeOrderAction,
        urlBuilder,
        url
    ){
        'use strict';

        return Component.extend({
            defaults: {
                template: 'UaPayPayment_UaPay/payment/checkout-uapay'
            },
            placeOrder: function (data, event) {
                if (event) {
                    event.preventDefault();
                }

                this.afterPlaceOrder.bind(this);
                var self = this, placeOrder;

                placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);
                $.when(placeOrder).fail(function () {
                    self.isPlaceOrderActionAllowed(true);
                }).done(this.afterPlaceOrder.bind(this));
                return true;
            },
            afterPlaceOrder: function () {
                console.log('Redirect UaPay')
                window.location.replace(url.build('uapay/checkout/redirect'));
            }
        });
    }
);
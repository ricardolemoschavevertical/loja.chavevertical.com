/**
 * Chave Vertical — faturação primeiro, entrega opcional igual à faturação.
 *
 * WooCommerce Checkout Blocks 11.1.2.
 * Uses Woo's actual checkout/cart stores instead of editing React-owned DOM.
 * Billing is ALWAYS the invoice address; only delivery/shipping can be copied.
 * No billing tax fields, gateways, order endpoints, or shipping rates are
 * changed by this script. Fail-open: both addresses stay available if Woo's
 * checkout store APIs change.
 */
(function () {
    'use strict';

    var CHECKOUT_STORE = 'wc/store/checkout';
    var CART_STORE = 'wc/store/cart';
    var CHECKBOX_ID = 'order-cvl-fiscal-use-billing-for-shipping';
    var CLASS_READY = 'cvl-billing-first-active';
    var CLASS_SAME = 'cvl-use-billing-for-shipping';
    var FIELDS = [
        'first_name', 'last_name', 'company', 'address_1', 'address_2',
        'country', 'state', 'city', 'postcode', 'phone'
    ];

    var started = false;
    var timer = null;
    var observers = [];
    var initAttempts = 0;
    var lastChoice = null;

    function stores() {
        try {
            if (!window.wp || !window.wp.data) {
                return null;
            }
            var data = window.wp.data;
            var checkout = data.select(CHECKOUT_STORE);
            var checkoutActions = data.dispatch(CHECKOUT_STORE);
            var cart = data.select(CART_STORE);
            var cartActions = data.dispatch(CART_STORE);
            if (!checkout || !checkoutActions || !cart || !cartActions) {
                return null;
            }
            if (
                typeof checkout.getUseShippingAsBilling !== 'function' ||
                typeof checkoutActions.__internalSetUseShippingAsBilling !== 'function' ||
                typeof cart.getCustomerData !== 'function' ||
                typeof cartActions.setShippingAddress !== 'function'
            ) {
                return null;
            }
            return { checkout: checkout, checkoutActions: checkoutActions, cart: cart, cartActions: cartActions };
        } catch (_error) {
            return null;
        }
    }

    function shippingNeeded(context) {
        if (typeof context.cart.getNeedsShipping === 'function' && context.cart.getNeedsShipping() === false) {
            return false;
        }
        if (typeof context.checkout.prefersCollection === 'function' && context.checkout.prefersCollection()) {
            return false;
        }
        return true;
    }

    function activeCheckout() {
        return !!document.querySelector('.wc-block-checkout');
    }

    function toggleClass(name, enabled) {
        if (document.body && document.body.classList) {
            document.body.classList.toggle(name, !!enabled);
        }
    }

    function checkbox() {
        return document.getElementById(CHECKBOX_ID);
    }

    function copyBillingToShipping(context) {
        var customer = context.cart.getCustomerData();
        if (!customer || !customer.billingAddress || !customer.shippingAddress) {
            return;
        }

        var billing = customer.billingAddress;
        var shipping = customer.shippingAddress;
        var updated = Object.assign({}, shipping);
        var different = false;

        FIELDS.forEach(function (field) {
            var value = typeof billing[field] === 'string' ? billing[field] : '';
            if (String(shipping[field] || '') !== value) {
                updated[field] = value;
                different = true;
            }
        });

        if (different) {
            // Native action, also triggers Woo's shipping rate recalculations.
            context.cartActions.setShippingAddress(updated);
        }
    }

    function synchronize() {
        timer = null;

        var context = stores();
        if (!context || !activeCheckout()) {
            toggleClass(CLASS_READY, false);
            toggleClass(CLASS_SAME, false);
            return;
        }

        // Woo's built-in direction is SHIPPING -> billing. Turn it off before
        // collecting the invoice address, then copy only billing -> SHIPPING.
        if (context.checkout.getUseShippingAsBilling()) {
            context.checkoutActions.__internalSetUseShippingAsBilling(false);
        }
        var billingReady = !context.checkout.getUseShippingAsBilling();
        toggleClass(CLASS_READY, billingReady);

        var useSame = checkbox();
        var needsShipping = shippingNeeded(context);
        var checked = !!(useSame && useSame.checked);
        var allowCopy = billingReady && needsShipping && checked;
        toggleClass(CLASS_SAME, allowCopy);

        if (useSame && !needsShipping) {
            // Avoid offering a delivery option for virtual items or collection.
            toggleClass('cvl-delivery-unavailable', true);
        } else {
            toggleClass('cvl-delivery-unavailable', false);
        }

        if (lastChoice !== allowCopy) {
            lastChoice = allowCopy;
        }
        if (allowCopy) {
            copyBillingToShipping(context);
        }
    }

    function schedule() {
        if (timer !== null) {
            return;
        }
        timer = window.setTimeout(synchronize, 90);
    }

    function onChange(event) {
        if (!event.target || event.target.id !== CHECKBOX_ID) {
            return;
        }
        // Woo's own React control owns the value; read it after onChange.
        schedule();
    }

    function boot() {
        if (started) {
            return;
        }
        var context = stores();
        if (!context || !activeCheckout()) {
            if (initAttempts++ < 48) {
                window.setTimeout(boot, 250);
            }
            return;
        }

        started = true;
        if (typeof window.wp.data.subscribe === 'function') {
            observers.push(window.wp.data.subscribe(schedule));
        }
        document.addEventListener('change', onChange, true);
        document.addEventListener('input', onChange, true);
        synchronize();

        // The fields may be mounted asynchronously by React. Observe only for
        // the appearance of the native checkbox; never mutate React children.
        if (!checkbox() && document.body && typeof MutationObserver === 'function') {
            var observer = new MutationObserver(function () {
                if (checkbox()) {
                    observer.disconnect();
                    schedule();
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
            window.setTimeout(function () { observer.disconnect(); }, 15000);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
